<?php

namespace CaT\IliasUserOrguImport\User;

use CaT\UserOrguImport\User as User;
use CaT\IliasUserOrguImport as Base;

/**
 * Ilias side updates of user data.
 */
class UserUpdater
{

	protected $ul;

	public function __construct(
		UserLocator $ul,
		UserRoleUpdater $uru,
		RoleConfiguration $rc,
		UdfWrapper $udf,
		Base\Orgu\OrguConfig $oc,
		Base\ErrorReporting\ErrorCollection $ec,
		Base\Log\Log $log
	) {

		$this->ul = $ul;
		$this->uru = $uru;
		$this->rc = $rc;
		$this->udf = $udf;
		$this->ec = $ec;
		$this->log = $log;

		$this->exit_ref_id = $oc->getExitRefId();
	}

	/**
	 * Apply UsersDiff to Ilias
	 *
	 * @param User\UsersDifference	$diff
	 * @return void
	 */
	public function applyDiff(User\UsersDifference $diff)
	{
		if ($this->checkConfig()) {
			$this->add($diff->toCreate());
			$this->update($diff->toChange());
		} else {
			$this->ec->addError('User import not configured.');
		}
	}

	protected function checkConfig()
	{
		return in_array(UdfWrapper::PROP_PNR, $this->udf->availiableUdfKeywords());
	}

	/**
	 * Update orgus by provided information.
	 *
	 * @param	User\Users	$users
	 * @return	void
	 */
	public function update(User\Users $users)
	{
		foreach ($users as $user) {
			$this->updateUser($user);
		}
	}

	/**
	 * Update user by provided information.
	 *
	 * @param	IliasUser	$user
	 * @return	void
	 */
	public function updateUser(IliasUser $user)
	{
		$props = $user->properties();
		$il_id = $user->iliasId();
		if (\ilObjUser::userExists([$il_id])) {
			if ($props[UdfWrapper::PROP_EXIT_DATE] > date('Y-m-d') || $props[UdfWrapper::PROP_EXIT_DATE] === '') {
				$this->uru->updateRolesOfChangedUser($this->ul->userByUserId($il_id), $user, $this->rc);
			}
			$il_usr = $this->updateUserData($props, new \ilObjUser($il_id));
			$il_usr->writePrefs();
			$il_usr->update();
			$this->udf->updateUserData($props, (int)$il_usr->getId());
			$this->log->createEntry('updating user by properties '.Base\Log\DatabaseLog::arrayToString($props), ['pnr' => $props[UdfWrapper::PROP_PNR]]);
		} else {
			$this->ec->addError('user with properties '.Base\Log\DatabaseLog::arrayToString($props).'does not exist and may not be updated');
		}
	}

	protected function updateUserData(array $data, \ilObjUser $il_usr)
	{
		$il_usr->setLogin($data[UdfWrapper::PROP_PNR]);
		$il_usr->setLastname($data[UdfWrapper::PROP_LASTNAME]);
		$il_usr->setFirstname($data[UdfWrapper::PROP_FIRSTNAME]);
		$il_usr->setBirthday($data[UdfWrapper::PROP_BIRTHDAY]);
		$il_usr->setGender($data[UdfWrapper::PROP_GENDER]);
		$il_usr->setEmail($data[UdfWrapper::PROP_EMAIL]);

		$inactive_end = trim((string)$data[UdfWrapper::PROP_INACTIVE_END]);
		$inactive_begin = trim((string)$data[UdfWrapper::PROP_INACTIVE_BEGIN]);
		$exit_date =  trim((string)$data[UdfWrapper::PROP_EXIT_DATE]);

		$current_date = date('Y-m-d');

		$before_inactive = $inactive_begin === '' || $current_date < $inactive_begin;
		$after_inactive = $inactive_end === '' || $current_date > $inactive_end;
		$before_exit = $current_date < $exit_date || $exit_date === '';
		$active = ($before_inactive || $after_inactive) && $before_exit;
		$il_usr->setTimeLimitUnlimited(true);
		$il_usr->setActive($active);
		return $il_usr;
	}

	/**
	 * Add users with provided information.
	 *
	 * @param	User\Users	$users
	 * @return	void
	 */
	public function add(User\Users $users)
	{
		foreach ($users as $user) {
			$this->addUser($user);
		}
	}

	/**
	 * Add user with provided information.
	 *
	 * @param	User\User	$user
	 * @return	void
	 */
	public function addUser(User\User $user)
	{
		$props = $user->properties();
		if (!\ilObjUser::_lookupId($props[UdfWrapper::PROP_LOGIN])) {
			$il_usr = new \ilObjUser();
			$il_usr->setIsSelfRegistered(true);
			try {
				$usr_id = $il_usr->create();
				$il_usr = $this->updateUserData($props, $il_usr);
				$il_usr->saveAsNew(false);
				$il_usr->writePrefs();
				$il_usr->update();
				$this->udf->updateUserData($props, (int)$il_usr->getId());
				if ($props[UdfWrapper::PROP_EXIT_DATE] > date('Y-m-d') || $props[UdfWrapper::PROP_EXIT_DATE] === '') {
					$this->uru->assignRolesToUserAccodingToConfig($this->ul->userByUserId($usr_id), $this->rc);
				}
			} catch (\Exception $e) {
				$this->ec->addError('user with properties '.Base\Log\DatabaseLog::arrayToString($props).' could not be created:'.$e->getMessage());
			}
			$this->log->createEntry('creating user with properties '.Base\Log\DatabaseLog::arrayToString($props), ['pnr' => $props[UdfWrapper::PROP_PNR]]);
		} else {
			$this->ec->addError('user with properties '.Base\Log\DatabaseLog::arrayToString($props).' allready exists and may not created');
		}
	}

	/**
	 * Remove users with provided information.
	 *
	 * @param	User\Users $users
	 * @return	void
	 */
	public function remove(User\Users $users)
	{
		foreach ($users as $user) {
			$this->removeUser($user);
		}
	}

	/**
	 * Remove user with provided information.
	 *
	 * @param	User\User $users
	 * @return	void
	 */
	public function removeUser(IliasUser $user)
	{
		$il_id = $user->iliasId();
		$props = $user->properties();
		$curr_date = date('Y-m-d');
		$exit_date = $props[UdfWrapper::PROP_EXIT_DATE];

		if ($curr_date < $exit_date || $exit_date === '') {
			$props[UdfWrapper::PROP_EXIT_DATE] = $curr_date;
			if (\ilObjUser::userExists([$il_id])) {
				$il_usr = $this->updateUserData($props, new \ilObjUser($il_id));
				$il_usr->writePrefs();
				$il_usr->update();
				$this->udf->updateUserData($props, $il_id);
				$this->log->createEntry('moving undelivered user to exit with properties '.Base\Log\DatabaseLog::arrayToString($props), ['pnr' => $props[UdfWrapper::PROP_PNR]]);
			} else {
				$this->ec->addError('user with properties '.Base\Log\DatabaseLog::arrayToString($props).' does not exists and may not be deleted');
			}
		}
	}
}