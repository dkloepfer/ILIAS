<?php

/**
 * cat-tms-patch start
 */
require_once("Services/Component/classes/class.ilPluginAdmin.php");
require_once("Services/TMS/TrainingSearch/classes/class.ilTrainingSearchGUI.php");
require_once("Services/TMS/TrainingSearch/classes/TrainingSearchDB.php");
require_once("Services/TMS/TrainingSearch/classes/class.Helper.php");

class ilTrainingSearchDB implements TrainingSearchDB {
	/**
	 * @var ilObjBookingModalitiesPlugin
	 */
	protected $xbkm;

	public function __construct(ilBookableFilter $filter, Helper $helper) {
		global $DIC;

		$this->g_db = $DIC->database();
		$this->g_tree = $DIC->repositoryTree();

		$this->filter = $filter;
		$this->helper = $helper;
	}

	/**
	 * @inheritdoc
	 */
	public function getBookableTrainingsFor($user_id, array $filter) {
		$crs_infos = array();

		if(ilPluginAdmin::isPluginActive('xbkm') && ilPluginAdmin::isPluginActive('xccl')) {
			$this->xbkm = ilPluginAdmin::getPluginObjectById('xbkm');

			$crs_infos = $this->getBookingModalitiesWithPermissionFor($user_id);
			$crs_infos = $this->addCourseIfUserIsNotBooked($crs_infos, $user_id);
			$crs_infos = $this->transformBkmToCourse($crs_infos);
			$crs_infos = $this->addCourseClassification($crs_infos);
			$crs_infos = $this->createBookableCourseByFilter($crs_infos);
		}

		return $crs_infos;
	}

	/**
	 * @inheritdoc
	 */
	public function getBookableCourse($crs_title,
				$type,
				ilDateTime $start_date,
				$bookings_available,
				array $target_group,
				$goals,
				array $topics,
				ilDateTime $end_date,
				$city,
				$address,
				$costs = "KOSTEN"
	) {
		require_once("Services/TMS/TrainingSearch/classes/BookableCourse.php");
		return new BookableCourse(
				$crs_title,
				$type,
				$start_date,
				(string)$bookings_available,
				$target_group,
				$goals,
				$topics,
				$end_date,
				$city,
				$address,
				$costs
			);
	}

	/**
	 * Add first course classification of course
	 *
	 * @param array<int, ilObjCourse | ilObjBookingModalities[]>
	 *
	 * @return array<int, ilObjCourse | ilObjBookingModalities[] | ilObjCourseClassification>
	 */
	protected function addCourseClassification($crs_infos) {
		foreach ($crs_infos as $key => &$crs_info) {
			foreach($this->g_tree->getChildsByType($crs_info["crs"]->getRefId(), "xccl") as $cc_info) {
				$crs_info["xccl"] = ilObjectFactory::getInstanceByRefId($cc_info["ref_id"]);
			}
		}

		return $crs_infos;
	}

	/**
	 * Get all booking modalities user as permission to book with
	 *
	 * @param int 	$user_id
	 *
	 * @return array<int, ilObjBookingModalities>
	 */
	protected function getBookingModalitiesWithPermissionFor($user_id) {
		$op_id = ilRbacReview::_getOperationIdByName("book_by_this");
		$query = "SELECT xbkm_booking.obj_id, rbac_pa.ops_id, object_reference.ref_id FROM xbkm_booking".PHP_EOL
				." JOIN object_reference".PHP_EOL
				."     ON object_reference.obj_id = xbkm_booking.obj_id".PHP_EOL
				." JOIN rbac_ua".PHP_EOL
				."     ON rbac_ua.usr_id = ".$this->g_db->quote($user_id, "integer").PHP_EOL
				." JOIN rbac_pa".PHP_EOL
				."     ON rbac_pa.ref_id = object_reference.ref_id".PHP_EOL
				."         AND rbac_pa.rol_id = rbac_ua.rol_id".PHP_EOL
				." WHERE xbkm_booking.modus = ".$this->g_db->quote("self_booking", "text");

		$ret = array();
		$res = $this->g_db->query($query);
		while($row = $this->g_db->fetchAssoc($res)) {
			$ops = unserialize(stripslashes($row["ops_id"]));
			if(in_array($op_id, $ops)) {
				$bm = ilObjectFactory::getInstanceByRefId($row["ref_id"]);
				$ret[] = array("xbkm" => $bm);
			}
		}

		return $ret;
	}

	/**
	 * Adds course object if user is not booked or drops bkm
	 *
	 * @param array<int, ilObjBookingModalities>
	 * @param int 	$user_id
	 *
	 * @return array<int, ilObjCourse | ilObjBookingModalities>
	 */
	protected function addCourseIfUserIsNotBooked(array $bms, $user_id) {
		foreach ($bms as $key => &$value) {
			$bm = ilObjectFactory::getInstanceByRefId($value["xbkm"]->getRefId());

			if($parent_crs = $bm->getParentCourse()) {
				require_once("Modules/Course/classes/class.ilCourseParticipants.php");
				if(!ilCourseParticipants::_isParticipant($parent_crs->getRefId(), $user_id)) {
					$value["crs"] = $parent_crs;
					continue;
				}
			}

			unset($bms[$key]);
		}

		sort($bms);
		return $bms;
	}

	/**
	 * transform array to get bkm with same crs in a single array
	 *
	 * @param array<int, ilObjCourse | ilObjBookingModalities>
	 * 
	 * @return array<int, ilObjCourse | ilObjBookingModalities[]>
	 */
	protected function transformBkmToCourse($bms) {
		$ret = array();

		uasort($bms, function($a, $b) {
			return strcmp((string)$a["crs"]->getRefId(), (string)$b["crs"]->getRefId());
		});

		$crs_ref_id = null;
		foreach ($bms as $key => $value) {
			if($crs_ref_id != $value["crs"]->getRefId()) {
				$crs_ref_id = $value["crs"]->getRefId();
				$ret[$crs_ref_id]["crs"] = $value["crs"];
			}

			$ret[$crs_ref_id]["xbkm"][] = $value["xbkm"];
		}

		return $ret;
	}

		/**
	 * Perform filter on all course informations
	 *
	 * @param array<int, ilObjCourse | ilObjBookingModalities[] | ilObjCourseClassification>
	 * @param array<int, string | int>
	 *
	 * @return BookableCourse[]
	 */
	protected function createBookableCourseByFilter(array $crs_infos, array $filter) {
		$ret = array();

		foreach ($crs_infos as $key => $value) {
			$crs = $value["crs"];

			$start_date = $crs->getCourseStart();
			$end_date = $crs->getCourseEnd();
			$title = $crs->getTitle();

			if($start_date === null) {
				unset($crs_infos[$key]);
				continue;
			}

			list($max_member, $booking_start_date, $booking_end_date, $waiting_list, $min_member, $bookings_available) = $this->helper->getBestBkmValues($value["xbkm"], $start_date);
			list($venue_id, $city, $address) = $this->helper->getVenueInfos($crs->getId());
			list($type_id,$type,$target_group_ids,$target_group,$goals,$topic_ids,$topics) = $this->helper->getCourseClassificationValues($value["xccl"]);
			list($provider_id) = $this->helper->getProviderInfos($crs->getId());

			if(!$this->filter->isInBookingPeriod($booking_start_date, $booking_end_date)) {
				unset($crs_infos[$key]);
				continue;
			}

			if(array_key_exists(self::F_DURATION, $filter)
				&& !$this->filter->courseInFilterPeriod($start_date, $filter[self::F_DURATION]["start"], $filter[self::F_DURATION]["end"])
			) {
				unset($crs_infos[$key]);
				continue;
			}

			if(array_key_exists(self::F_TARGET_GROUP, $filter)
				&& !$this->filter->courseHasTargetGroups($target_group_ids, $filter[self::F_TARGET_GROUP])
			) {
				unset($crs_infos[$key]);
				continue;
			}

			if(array_key_exists(self::F_TOPIC, $filter)
				&& !$this->filter->courseHasTopics($topic_ids, $filter[self::F_TOPIC])
			) {
				unset($crs_infos[$key]);
				continue;
			}

			if(array_key_exists(self::F_TYPE, $filter)
				&& !$this->filter->courseHasType($type_id, $filter[self::F_TYPE])
			) {
				unset($crs_infos[$key]);
				continue;
			}

			if(array_key_exists(self::F_TITLE, $filter)
				&& !$this->filter->crsTitleStartsWith($title, $filter[self::F_TITLE])
			) {
				unset($crs_infos[$key]);
				continue;
			}

			if(array_key_exists(self::F_NOT_MIN_MEMBER, $filter)
				&& $this->filter->minMemberReached($crs->getRefId(), $min_member)
			) {
				unset($crs_infos[$key]);
				continue;
			}

			if(array_key_exists(self::F_CITY, $filter)
				&& $venue_id != -1
				&& !$this->filter->courseHasVenue($venue_id, $filter[self::F_CITY])
			) {
				unset($crs_infos[$key]);
				continue;
			}

			if(array_key_exists(self::F_PROVIDER, $filter)
				&& !$this->filter->courseHasProvider($provider_id, (int)$filter[self::F_PROVIDER])
			) {
				unset($crs_infos[$key]);
				continue;
			}

			$ret[] = $this->getBookableCourse($title,
				$type,
				$start_date,
				$bookings_available,
				$target_group,
				$goals,
				$topics,
				$end_date,
				$city,
				$address
			);
		}

		return $ret;
	}
}

/**
 * cat-tms-patch end
 */