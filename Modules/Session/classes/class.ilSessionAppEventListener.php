<?php
// cat-tms-patch start
require_once('./Modules/Session/classes/class.ilObjSession.php');
require_once("Services/Membership/classes/class.ilParticipants.php");

use ILIAS\TMS\Timezone;

/**
 * Class ilSessionAppEventListener
 *
 * @author Daniel Weise <daniel.weise@concepts-and-training.de>
 */
class ilSessionAppEventListener {

	protected static $ref_ids = array();


	/**
	 * Handle an event in a listener.
	 *
	 * @param    string $a_component component, e.g. "Modules/Forum" or "Services/User"
	 * @param    string $a_event     event e.g. "createUser", "updateUser", "deleteUser", ...
	 * @param    array $a_parameter  parameter array (assoc), array("name" => ..., "phone_office" => ...)
	 */
	static function handleEvent($a_component, $a_event, $a_parameter) {
		switch ($a_component) {
			case 'Modules/Course':
				switch ($a_event) {
					case 'update':
						self::updateSessionAppointments($a_parameter['object']);
						break;
				}
				break;
			case 'Services/User':
				switch($a_event) {
					case 'deleteUser':
						self::deleteTutorFromAllLecture((int)$a_parameter["usr_id"]);
						break;
				}
				break;
			case "Services/AccessControl":
				if($a_parameter["type"] == "crs") {
					switch($a_event) {
						case "deassignUser":
							if($a_parameter["role_id"] == self::getDefaultTutorRoleFor((int)$a_parameter["obj_id"])) {
								self::deleteTutorFromLecture((int)$a_parameter["usr_id"], (int)$a_parameter["obj_id"]);
							}
							break;
						case "assignUser":
							if($a_parameter["role_id"] == self::getDefaultTutorRoleFor((int)$a_parameter["obj_id"])) {
								self::setTutorAsLecture((int)$a_parameter["usr_id"], (int)$a_parameter["obj_id"]);
							}
							break;
					}
				}
		}
	}

	/**
	 * Get the id of the default tutor role for coruse
	 *
	 * @param int 	$crs_id
	 *
	 * @return int | null
	 */
	protected static function getDefaultTutorRoleFor($crs_id) {
		$ref_id = array_shift(\ilObject::_getAllReferences($crs_id));
		$crs = \ilObjectFactory::getInstanceByRefId((int)$ref_id);

		return $crs->getDefaultTutorRole();
	}

	/**
	 * Update sessions relative to course
	 *
	 * @param 	ilObjCourse 		$crs
	 * @return 	void
	 */
	protected static function updateSessionAppointments(ilObjCourse $crs)
	{
		$crs_start = $crs->getCourseStart();
		$sessions = self::getSessionsOfCourse($crs->getRefId());

		foreach ($sessions as $session)
		{
			$appointment 	= $session->getFirstAppointment();
			$start_time 	= $appointment->getStart()->get(IL_CAL_FKT_DATE, "H:i:s");
			$end_time 		= $appointment->getEnd()->get(IL_CAL_FKT_DATE, "H:i:s");
			$offset 		= $appointment->getDaysOffset();

			$start_date 	= self::createDateTime(date("Y-m-d"), $start_time);
			$end_date 		= self::createDateTime(date("Y-m-d"), $end_time);

			if($crs_start)
			{
				$clac_crs_start = clone $crs_start;
				$clac_crs_start->increment(ilDateTime::DAY, --$offset);

				$date 		= $clac_crs_start->get(IL_CAL_FKT_DATE, "Y-m-d");

				$start_date = self::createDateTime($date, $start_time);
				$end_date 	= self::createDateTime($date, $end_time);
			}

			$appointment->setStart($start_date);
			$appointment->setEnd($end_date);
			$appointment->update();
		}
	}

	/**
	 * Get instance of timezone checker
	 *
	 * @return TimezoneCheckerImpl
	 */
	protected static function getTimezoneChecker() {
		return new Timezone\TimezoneCheckerImpl(new Timezone\TimezoneDBImpl());
	}

	/**
	 * Deletes a tutor from list as lecture
	 *
	 * @param int 	$usr_id
	 * @param int 	$crs_ref_id
	 *
	 * @return void
	 */
	protected static function deleteTutorFromLecture($usr_id, $crs_obj_id) {
		assert('is_int($usr_id)');
		assert('is_int($crs_obj_id)');
		$crs_ref_id = self::getReferenceId($crs_obj_id);
		foreach(self::getSessionsOfCourse($crs_ref_id) as $session) {
			$assigned_tutors = $session->getAssignedTutorsIds();
			$assigned_tutors = array_filter($assigned_tutors, function($id) use ($usr_id) { return $id != $usr_id;});
			$session->setAssignedTutors($assigned_tutors);

			$session->update();

			if(count($assigned_tutors) == 0) {
				$session->setTutorSource(ilObjSession::TUTOR_CFG_MANUALLY);
			}

			$session->update();
		}
	}

	/**
	 * Deletes a tutor from all lists as lecture
	 *
	 * @param int 	$usr_id
	 * @param int 	$crs_ref_id
	 *
	 * @return void
	 */
	protected static function deleteTutorFromAllLecture($usr_id) {
		assert('is_int($usr_id)');

		foreach (self::getAllSessions() as $session) {
			$assigned_tutors = $session->getAssignedTutorsIds();
			$assigned_tutors = array_filter($assigned_tutors, function($id) use ($usr_id) { return $id != $usr_id;});
			$session->setAssignedTutors($assigned_tutors);

			$session->update();

			if(count($assigned_tutors) == 0) {
				$session->setTutorSource(ilObjSession::TUTOR_CFG_MANUALLY);
			}

			$session->update();
		}
	}

	/**
	 * Adds tutor as lecture
	 * Activates from course option
	 *
	 * @param int 	$usr_id
	 * @param int 	$crs_obj_id
	 *
	 * @return void
	 */
	protected static function setTutorAsLecture($usr_id, $crs_obj_id) {
		assert('is_int($usr_id)');
		assert('is_int($crs_obj_id)');
		$crs_ref_id = self::getReferenceId($crs_obj_id);
		foreach(self::getSessionsOfCourse($crs_ref_id) as $session) {
			$assigned_tutors = $session->getAssignedTutorsIds();
			array_push($assigned_tutors, $usr_id);
			$session->setAssignedTutors($assigned_tutors);
			$session->setTutorSource(ilObjSession::TUTOR_CFG_FROMCOURSE);
			$session->update();
		}
	}

	/**
	 * Find sessions underneath course 
	 *
	 * @param 	int 			$crs_ref_id
	 * @return 	ilObjSession[]
	 */
	protected static function getSessionsOfCourse($crs_ref_id)
	{
		global $DIC;

		$g_tree 	= $DIC->repositoryTree();
		$ret 		= array();
		$sessions 	= $g_tree->getChildsByType($crs_ref_id, "sess");

		foreach($sessions as $session)
		{
			$ret[] = ilObjectFactory::getInstanceByRefId($session['ref_id']);
		}

		return $ret;
	}

	/**
	 * Get all sessions
	 *
	 * @return ilSession[]
	 */
	protected static function getAllSessions() {
		global $DIC;
		$g_tree = $DIC->repositoryTree();

		$ret = array();
		foreach(\ilObject::_getObjectsByType("sess") as $sess) {
			$ret[] = \ilObjectFactory::getInstanceByObjId($sess["obj_id"]);
		}

		return $ret;
	}

	/**
	 * Get reference id of object
	 *
	 * @param int 	$obj_id
	 *
	 * @return int
	 */
	protected static function getReferenceId($obj_id) {
		return array_shift(ilObject::_getAllReferences($obj_id));
	}

	/**
	 * Creates a DateTime object in UTC timezone
	 *
	 * @param 	string 		$date
	 * @param 	string 		$time
	 * @return 	ilDateTime
	 */
	protected static function createDateTime($date, $time)
	{
		return new ilDateTime($date." ".$time, IL_CAL_DATETIME);
	}
}
// cat-tms-patch end
