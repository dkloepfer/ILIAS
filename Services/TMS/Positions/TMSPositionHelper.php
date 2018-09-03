<?php

declare(strict_types=1);

/**
 * Helper to get user ids via positions and/or authorities
 *
 * @author Stefan Hecken 	<stefan.hecken@concepts-and-training.de>
 */
class TMSPositionHelper
{
	const ORGU_ROOT = 56;

	/**
	 * @var ilOrgUnitUserAssignmentQueries
	 */
	protected $orgua_queries;

	public function __construct(ilOrgUnitUserAssignmentQueries $orgua_queries)
	{
		$this->orgua_queries = $orgua_queries;
	}

	/**
	 * Get all user ids where user has authorities.
	 *
	 * @return int[]
	 */
	public function getUserIdWhereUserHasAuhtority(int $user_id): array
	{
		$positons = $this->getPositionsOf($user_id);

		$user_ids = array();
		foreach ($positons as $positon) {
			$result = array_map(
				function($u) { return (int)$u; },
				$this->getUserIdsByPositionAndUser($positon, $user_id)
			);

			$user_ids = array_merge($user_ids, $result);
		}

		return array_unique($user_ids);
	}

	/**
	 * Get all user id where position has authority for specified org units.
	 *
	 * @param ilOrgUnitPosition[] $positions
	 * @param int[] $orgus
	 *
	 * @return int[]
	 */
	public function getUserIdsForPositionsAndOrgunits(array $positions, array $orgus): array
	{
		$user_ids = array();
		foreach ($positions as $position) {
			foreach ($position->getAuthorities() as $authority) {
				$result = array_map(
					function($u) { return (int)$u; },
					$this->orgua_queries->getUserIdsOfOrgUnitsInPosition($orgus, $authority->getOver())
				);

				$user_ids = array_merge(
					$user_ids,
					$result
				);
			}
		}

		return array_unique($user_ids);
	}

	/**
	 * Get all orgu ids where use has any authority.
	 *
	 * @return int[]
	 */
	public function getOrgUnitIdsWhereUserHasAuthority(int $user_id): array
	{
		$positions = $this->getPositionsOfUserWithAuthority($user_id);
		return $this->getOrgUnitByPositions($positions, $user_id);
	}

	/**
	 * Get all org units where user has position.
	 *
	 * @param ilOrgUnitPosition[] $positions
	 * @param int $user_id
	 *
	 * @return int[]
	 */
	public function getOrgUnitByPositions(array $positions, int $user_id): array
	{
		$orgus = array();
		foreach ($positions as $position) {
			$orgus = array_merge(
				$orgus,
				$orgus = $this->orgua_queries->getOrgUnitIdsOfUsersPosition($position->getId(), $user_id)
			);
		}

		return array_unique($orgus);
	}

	/**
	 * Get positions where user has any authority.
	 *
	 * @return ilOrgUnitPosition[]
	 */
	public function getPositionsOfUserWithAuthority(int $user_id): array
	{
		$positions = $this->getPositionsOf($user_id);
		$positions = array_filter($positions, function($p) {
			if (count($p->getAuthorities()) > 0) {
				return $p;
			}
		});
		return $positions;
	}

	/**
	 * Get all orgu assignments of user.
	 *
	 * @return ilOrgUnitUserAssignment[]
	 */
	protected function getAssignmentsOf(int $user_id): array
	{
		return $this->orgua_queries->getAssignmentsOfUserId($user_id);
	}

	/**
	 * Get positions of user on his aussignments.
	 *
	 * @return ilOrgUnitPosition[]
	 */
	protected function getPositionsOf(int $user_id): array
	{
		require_once("Modules/OrgUnit/classes/Positions/class.ilOrgUnitPosition.php");
		$assignments = $this->getAssignmentsOf($user_id);
		return array_map(function($a) {
			return new ilOrgUnitPosition($a->getPositionId());
		}, $assignments);
	}

	/**
	 * Get all user id via positions.
	 *
	 * @return int[]
	 */
	protected function getUserIdsByPositionAndUser(ilOrgUnitPosition $position, int $user_id): array
	{
		require_once("Modules/OrgUnit/classes/Positions/Authorities/class.ilOrgUnitAuthority.php");
		$ids = array();
		foreach ($position->getAuthorities() as $authority) {
			switch ($authority->getOver()) {
				case ilOrgUnitAuthority::OVER_EVERYONE:
					switch ($authority->getScope()) {
						case ilOrgUnitAuthority::SCOPE_SAME_ORGU:
							$ids = array_merge(
								$ids,
								$this->orgua_queries->getUserIdsOfOrgUnitsOfUsersPosition($position->getId(), $user_id)
							);
							break;
						case ilOrgUnitAuthority::SCOPE_SUBSEQUENT_ORGUS:
							$ids = array_merge(
								$ids,
								$this->orgua_queries->getUserIdsOfOrgUnitsOfUsersPosition($position->getId(), $user_id, true)
							);
							break;
					}
					break;
				default:
					switch ($authority->getScope()) {
						case ilOrgUnitAuthority::SCOPE_SAME_ORGU:
							$ids = array_merge(
								$ids,
								$this->orgua_queries->getUserIdsOfUsersOrgUnitsInPosition($user_id, $position->getId(), $authority->getOver())
							);
							break;
						case ilOrgUnitAuthority::SCOPE_SUBSEQUENT_ORGUS:
							$ids = array_merge(
								$ids,
								$this->orgua_queries->getUserIdsOfUsersOrgUnitsInPosition($user_id, $position->getId(), $authority->getOver(), true)
							);
							break;
					}
			}
		}

		return $ids;
	}

	/**
	 * Get all user ids with at last one position.
	 */
	public function getUserIdsWithAtLeastOnePositionWithAuthority(): array
	{
		$assignemnts = $this->orgua_queries->getUserIdsWithAtLeastOnePosition();
		return array_keys($assignemnts);
	}

	/**
	 * Get user ids the given user has authority over
	 *
	 * @return int[]
	 */
	public function getAllVisibleUserIdsForUser(int $usr_id, \ilObjOrgUnitTree $orgu_tree): array
	{
		$visible_users = [];
		$positions = $this->getPositionsOf($usr_id);
		foreach ($positions as $pkey => $position) {
			$orgus = $this->getOrgUnitByPositions([$position], $usr_id);
			$authorities = $position->getAuthorities();

			foreach ($authorities as $akey => $authority) {

				if ((int)$authority->getScope() === \ilOrgUnitAuthority::SCOPE_SAME_ORGU) {
					if ((int)$authority->getOver() === -1) {
						$v = $this->orgua_queries->getUserIdsOfOrgUnits($orgus);
					} else {
						$v = $this->orgua_queries->getUserIdsOfOrgUnitsInPosition($orgus, $authority->getOver());
					}
					$visible_users = array_merge($visible_users, $v);

				}
				if ((int)$authority->getScope() === \ilOrgUnitAuthority::SCOPE_SUBSEQUENT_ORGUS) {
					$subsequent_orgus = [];
					foreach ($orgus as $orgu_ref_id) {
						$lower_orgus = array_filter($orgu_tree->getAllChildren($orgu_ref_id),
							function($o) use ($orgu_ref_id){
								return $o !== $orgu_ref_id;
							}
						);
						$subsequent_orgus = array_merge($subsequent_orgus, $lower_orgus);
					}
					if (count($subsequent_orgus) === 0) {
						continue;
					}
					if ((int)$authority->getOver() === -1) {
						$v = $this->orgua_queries->getUserIdsOfOrgUnits($subsequent_orgus);
					} else {
						$v = $this->orgua_queries->getUserIdsOfOrgUnitsInPosition($subsequent_orgus, $authority->getOver());
					}
					$visible_users = array_merge($visible_users, $v);
				}
			}


		}

		$visible_users  = array_map(
			function ($intlike_val) {
				return (int)$intlike_val;
			},
			array_unique($visible_users)
		);

		$visible_users = array_filter($visible_users,
			function($entry) use ($usr_id) {
				return $entry !== $usr_id;
			}
		);
		return $visible_users;
	}

	/**
	 * Get all orgus the user is assigned to.
	 * Get all users with $position from these orgus.
	 * If none is found, aquire next higher OrgU and try again.
	 *
	 * Note, that there are no checks on authorities.
	 *
	 * @return int[]
	 */
	public function getNextHigherUsersWithPositionForUser(int $position, int $usr_id) : array
	{
		$users = [];
		$orgus = $this->getAssignmentsOf($usr_id); //ilOrgUnitUserAssignment[]

		foreach ($orgus as $orgu_assignment) {
			$orgu_id = (int)$orgu_assignment->getOrguId();
			$users = array_merge($users, $this->acquireUsersWithPositionFromOrgu($orgu_id, $position));
		}

		return $users;
	}

	/**
	 * Recursively (walk _up_ the tree!) check for position in orgu.
	 *
	 * @return int[]
	 */
	protected function acquireUsersWithPositionFromOrgu(int $orgu_id, int $position): array
	{
		if($orgu_id === self::ORGU_ROOT || $orgu_id === 0) {
			return [];
		}

		$result = $this->orgua_queries->getUserIdsOfOrgUnitsInPosition([$orgu_id], $position);
		if (count($result) === 0) {
			$tree = \ilObjOrgUnitTree::_getInstance();
			$orgu_id = (int)$tree->getParent($orgu_id);
			return $this->acquireUsersWithPositionFromOrgu($orgu_id, $position);
		}
		$result = array_map(function($entry){ return (int)$entry; }, $result);
		return $result;
	}

	/**
	 * Get all visible users for a user id within $orgu_ids, possibly recursive.
	 */
	public function getUserIdUnderAuthorityOfUserByPositionsAndOrgus(
		int $usr_id,
		array $orgu_ids,
		bool $recursive = false)  : array {
		$requested_orgus = $orgu_ids;
		if(count($requested_orgus) === 0){
			return [];
		}
		if($recursive) {
			foreach ($requested_orgus as $r_orgu_id) {
				$requested_orgus = array_merge($requested_orgus,$this->getSubsequentOrgus($r_orgu_id));
			}
		}
		$rel_users = [];
		foreach($this->orgua_queries->getAssignmentsOfUserId($usr_id) as $assignment) {
			$position = new \ilOrgUnitPosition($assignment->getPositionId());
			$orgu_id = (int)$assignment->getOrguId();
			foreach ($position->getAuthorities() as $authority) {

				$scope = $authority->getScope();
				$over = (int)$authority->getOver();
				$rel_orgus = [];
				switch($scope) {
					case \ilOrgUnitAuthority::SCOPE_ALL_ORGUS:
						$rel_orgus = array_merge([$orgu_id],$this->getSubsequentOrgus($orgu_id));
						break;
					case \ilOrgUnitAuthority::SCOPE_SUBSEQUENT_ORGUS:
						$rel_orgus = $this->getSubsequentOrgus($orgu_id);
						break;
					case \ilOrgUnitAuthority::SCOPE_SAME_ORGU:
						$rel_orgus = [$orgu_id];
						break;
				}
				$rel_orgus = array_intersect($requested_orgus, $rel_orgus);
				if(count($rel_orgus) === 0) {
					continue;
				}
				if($over === \ilOrgUnitAuthority::OVER_EVERYONE) {
					$add_users = $this->orgua_queries->getUserIdsOfOrgUnits($rel_orgus);
				} else {
					$add_users = $this->orgua_queries->getUserIdsOfOrgUnitsInPosition($rel_orgus,$over);
				}
				$rel_users = array_merge($rel_users,$add_users);
			}
		}
		return array_unique($rel_users);
	}

	/**
	 * Get all orgus strictly below $orgu_id recursively
	 *
	 * @param	int	$orgu_id
	 * @return	int[]
	 */
	public function getSubsequentOrgus(int $orgu_id) : array
	{
		assert('is_int($orgu_id)');
		$children = \ilObjOrgUnitTree::_getInstance()->getAllChildren($orgu_id);
		if (($key = array_search($orgu_id, $children)) !== false) {
			unset($children[$key]);
		}
		return $children;
	}

	public function getPositionTitleById(int $id): string
	{
		require_once("Modules/OrgUnit/classes/Positions/class.ilOrgUnitPosition.php");
		return ilOrgUnitPosition::where([ 'id' => $id ])->first()->getTitle();
	}
}