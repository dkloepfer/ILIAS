<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once('./Modules/Session/classes/class.ilSessionAppointment.php');
include_once './Services/Membership/classes/class.ilMembershipRegistrationSettings.php';

/**
* @defgroup ModulesSession Modules/Session
*
* @author Stefan Meyer <smeyer.ilias@gmx.de>
* @version $Id$
*
* @ingroup ModulesSession
*/
class ilObjSession extends ilObject
{
	const CAL_REG_START = 1;

	// cat-tms-patch start
	const TUTOR_CFG_MANUALLY = 0;
	const TUTOR_CFG_FROMCOURSE = 1;
	// cat-tms-patch end

	protected $db;

	protected $location;
	protected $name;
	protected $phone;
	protected $email;
	protected $details;
	protected $registration;
	protected $event_id;

	protected $reg_type = ilMembershipRegistrationSettings::TYPE_NONE;
	protected $reg_limited = 0;
	protected $reg_min_users = 0;
	protected $reg_limited_users = 0;
	protected $reg_waiting_list = 0;
	protected $reg_waiting_list_autofill; // [bool]

	protected $appointments;
	protected $files = array();

	// cat-tms-patch start
	/**
	 * @var int TUTOR_CFG_MANUALLY|TUTOR_CFG_FROMCOURSE
	 */
	protected $tutor_source;

	/**
	 * @var array<int,ilObjUser>
	 */
	protected $assigned_tutors=array();
	// cat-tms-patch end

	/**
	* Constructor
	* @access	public
	* @param	integer	reference_id or object_id
	* @param	boolean	treat the id as reference_id (true) or object_id (false)
	*/
	public function __construct($a_id = 0,$a_call_by_reference = true)
	{
		global $ilDB;

		$this->db = $ilDB;
		$this->type = "sess";
		parent::__construct($a_id,$a_call_by_reference);
	}

	/**
	 * lookup registration enabled
	 *
	 * @access public
	 * @param
	 * @return
	 * @static
	 */
	public static function _lookupRegistrationEnabled($a_obj_id)
	{
		global $ilDB;

		$query = "SELECT reg_type FROM event ".
			"WHERE obj_id = ".$ilDB->quote($a_obj_id ,'integer')." ";
		$res = $ilDB->query($query);
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
		{
			return (bool) $row->reg_type != ilMembershipRegistrationSettings::TYPE_NONE;
		}
		return false;
	}

	/**
	 * Get session data
	 * @param object $a_obj_id
	 * @return
	 */
	public static function lookupSession($a_obj_id)
	{
		global $ilDB;

		$query = "SELECT * FROM event ".
			"WHERE obj_id = ".$ilDB->quote($a_obj_id);
		$res = $ilDB->query($query);
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
		{
			$data['location'] 	= $row->location ? $row->location : '';
			$data['details']	= $row->details ? $row->details : '';
			$data['name']		= $row->tutor_name ? $row->tutor_name : '';
			$data['email']		= $row->tutor_email ? $row->tutor_email : '';
			$data['phone']		= $row->tutor_phone ? $row->tutor_phone : '';
			// cat-tms-patch start
			$data['tutor_source'] = $row->tutor_source;
			if($row->tutor_source === self::TUTOR_CFG_FROMCOURSE) {
				$data['tutor_ids'] = self::lookupTutorReferences($a_obj_id);
			}
			// cat-tms-patch end
		}
		return (array) $data;
	}

	/**
	 * get title
	 * (overwritten from base class)
	 *
	 * @access public
	 * @return
	 */
	public function getPresentationTitle()
	{
		$date = new ilDate($this->getFirstAppointment()->getStart()->getUnixTime(),IL_CAL_UNIX);
		if($this->getTitle())
		{
			return ilDatePresentation::formatDate($this->getFirstAppointment()->getStart()).': '.$this->getTitle();
		}
		else
		{
			return ilDatePresentation::formatDate($date);
		}

	}



	/**
	 * sget event id
	 *
	 * @access public
	 * @return
	 */
	public function getEventId()
	{
		return $this->event_id;
	}

	/**
	 * set location
	 *
	 * @access public
	 * @param string location
	 */
	public function setLocation($a_location)
	{
		$this->location = $a_location;
	}

	/**
	 * get location
	 *
	 * @access public
	 * @return string location
	 */
	public function getLocation()
	{
		return $this->location;
	}

	/**
	 * set name
	 *
	 * @access public
	 * @param string name
	 */
	public function setName($a_name)
	{
		$this->name = $a_name;
	}

	/**
	 * get name
	 *
	 * @access public
	 * @return string name
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * set phone
	 *
	 * @access public
	 * @param string phone
	 */
	public function setPhone($a_phone)
	{
		$this->phone = $a_phone;
	}

	/**
	 * get phone
	 *
	 * @access public
	 * @return string phone
	 */
	public function getPhone()
	{
		return $this->phone;
	}

	/**
	 * set email
	 *
	 * @access public
	 * @param string email
	 * @return
	 */
	public function setEmail($a_email)
	{
		$this->email = $a_email;
	}

	/**
	 * get email
	 *
	 * @access public
	 * @return string email
	 */
	public function getEmail()
	{
		return $this->email;
	}

	/**
	 * check if there any tutor settings
	 *
	 * @access public
	 */
	public function hasTutorSettings()
	{
		return strlen($this->getName()) or
			strlen($this->getEmail()) or
			strlen($this->getPhone())
		// cat-tms-patch start
			or $this->getTutorSource() === self::TUTOR_CFG_FROMCOURSE;
		// cat-tms-patch end
	}

	/**
	 * set details
	 *
	 * @access public
	 * @param string details
	 */
	public function setDetails($a_details)
	{
		$this->details = $a_details;
	}

	/**
	 * get details
	 *
	 * @access public
	 * @return string details
	 */
	public function getDetails()
	{
		return $this->details;
	}

	public function setRegistrationType($a_type)
	{
		$this->reg_type = $a_type;
	}

	public function getRegistrationType()
	{
		return $this->reg_type;
	}

	public function isRegistrationUserLimitEnabled()
	{
		return $this->reg_limited;
	}

	public function enableRegistrationUserLimit($a_limit)
	{
		$this->reg_limited = $a_limit;
	}

	public function getRegistrationMinUsers()
	{
		return $this->reg_min_users;
	}

	public function setRegistrationMinUsers($a_users)
	{
		$this->reg_min_users = $a_users;
	}

	public function getRegistrationMaxUsers()
	{
		return $this->reg_limited_users;
	}

	public function setRegistrationMaxUsers($a_users)
	{
		$this->reg_limited_users = $a_users;
	}

	public function isRegistrationWaitingListEnabled()
	{
		return $this->reg_waiting_list;
	}

	public function enableRegistrationWaitingList($a_stat)
	{
		$this->reg_waiting_list = $a_stat;
	}

	public function setWaitingListAutoFill($a_value)
	{
		$this->reg_waiting_list_autofill = (bool)$a_value;
	}

	public function hasWaitingListAutoFill()
	{
		return (bool)$this->reg_waiting_list_autofill;
	}

	/**
	 * is registration enabled
	 *
	 * @access public
	 * @return
	 */
	public function enabledRegistration()
	{
		return $this->reg_type != ilMembershipRegistrationSettings::TYPE_NONE;
	}

	/**
	 * get appointments
	 *
	 * @access public
	 * @return array
	 */
	public function getAppointments()
	{
		return $this->appointments ? $this->appointments : array();
	}

	/**
	 * add appointment
	 *
	 * @access public
	 * @param object ilSessionAppointment
	 * @return
	 */
	public function addAppointment($appointment)
	{
		$this->appointments[] = $appointment;
	}

	/**
	 * set appointments
	 *
	 * @access public
	 * @param array ilSessionAppointments
	 * @return
	 */
	public function setAppointments($appointments)
	{
		$this->appointments = $appointments;
	}

	/**
	 * get first appointment
	 *
	 * @access public
	 * @return object ilSessionAppointment
	 */
	public function getFirstAppointment()
	{
		return is_object($this->appointments[0]) ? $this->appointments[0] : ($this->appointments[0] = new ilSessionAppointment());
	}

	/**
	 * get files
	 *
	 * @access public
	 * @param
	 * @return
	 */
	public function getFiles()
	{
		return $this->files ? $this->files : array();
	}

	/**
	 * validate
	 *
	 * @access public
	 * @param
	 * @return bool
	 */
	public function validate()
	{
		global $ilErr;

		// #17114
		if($this->isRegistrationUserLimitEnabled() &&
			!$this->getRegistrationMaxUsers())
		{
			$ilErr->appendMessage($this->lng->txt("sess_max_members_needed"));
			return false;
		}

		return true;
	}

	/**
	 * Clone course (no member data)
	 *
	 * @access public
	 * @param int target ref_id
	 * @param int copy id
	 *
	 */
	public function cloneObject($a_target_id,$a_copy_id = 0, $a_omit_tree = false)
	{
		global $ilDB,$ilUser,$ilAppEventHandler;

	 	$new_obj = parent::cloneObject($a_target_id,$a_copy_id, $a_omit_tree);

	 	$this->read();

		// Copy settings
		$this->cloneSettings($new_obj);

		// Clone appointment
		$new_app = $this->getFirstAppointment()->cloneObject($new_obj->getId());
		$new_obj->setAppointments(array($new_app));
		$new_obj->update();

		// Clone session files
		foreach($this->files as $file)
		{
			$file->cloneFiles($new_obj->getEventId());
		}

		// Raise update forn new appointments



		// Copy learning progress settings
		include_once('Services/Tracking/classes/class.ilLPObjSettings.php');
		$obj_settings = new ilLPObjSettings($this->getId());
		$obj_settings->cloneSettings($new_obj->getId());
		unset($obj_settings);

		return $new_obj;
	}

	/**
	 * clone settings
	 *
	 * @access public
	 * @param ilObjSession
	 * @return
	 */
	public function cloneSettings(ilObjSession $new_obj)
	{
		// @var
		$new_obj->setLocation($this->getLocation());
		$new_obj->setName($this->getName());
		$new_obj->setPhone($this->getPhone());
		$new_obj->setEmail($this->getEmail());
		$new_obj->setDetails($this->getDetails());

		$new_obj->setRegistrationType($this->getRegistrationType());
		$new_obj->enableRegistrationUserLimit($this->isRegistrationUserLimitEnabled());
		$new_obj->enableRegistrationWaitingList($this->isRegistrationWaitingListEnabled());
		$new_obj->setWaitingListAutoFill($this->hasWaitingListAutoFill());
		$new_obj->setRegistrationMinUsers($this->getRegistrationMinUsers());
		$new_obj->setRegistrationMaxUsers($this->getRegistrationMaxUsers());

		$new_obj->update();

		return true;
	}

	/**
	 * Clone dependencies
	 *
	 * @param int target id ref_id of new session
	 * @param int copy_id
	 * @return
	 */
	public function cloneDependencies($a_target_id,$a_copy_id)
	{
		global $ilObjDataCache;

		parent::cloneDependencies($a_target_id,$a_copy_id);

		$target_obj_id = $ilObjDataCache->lookupObjId($a_target_id);

		include_once('./Modules/Session/classes/class.ilEventItems.php');
		$session_materials = new ilEventItems($target_obj_id);
		$session_materials->cloneItems($this->getId(),$a_copy_id);

		return true;
	}



	/**
	 * create new session
	 *
	 * @access public
	 */
	public function create()
	{
		global $ilDB;
		global $ilAppEventHandler;

		parent::create();

		$next_id = $ilDB->nextId('event');
		$query = "INSERT INTO event (event_id,obj_id,location,tutor_name,tutor_phone,tutor_email,details,registration, ".
			'reg_type, reg_limit_users, reg_limited, reg_waiting_list, reg_min_users, reg_auto_wait) '.
			"VALUES( ".
			$ilDB->quote($next_id,'integer').", ".
			$this->db->quote($this->getId() ,'integer').", ".
			$this->db->quote($this->getLocation() ,'text').",".
			$this->db->quote($this->getName() ,'text').", ".
			$this->db->quote($this->getPhone() ,'text').", ".
			$this->db->quote($this->getEmail() ,'text').", ".
			$this->db->quote($this->getDetails() ,'text').",".
			$this->db->quote($this->enabledRegistration() ,'integer').", ".
			$this->db->quote($this->getRegistrationType(),'integer').', '.
			$this->db->quote($this->getRegistrationMaxUsers(),'integer').', '.
			$this->db->quote($this->isRegistrationUserLimitEnabled(),'integer').', '.
			$this->db->quote($this->isRegistrationWaitingListEnabled(),'integer').', '.
			$this->db->quote($this->getRegistrationMinUsers(),'integer').', '.
			$this->db->quote($this->hasWaitingListAutoFill(),'integer').' '.
			")";
		$res = $ilDB->manipulate($query);
		$this->event_id = $next_id;

		$ilAppEventHandler->raise('Modules/Session',
			'create',
			array('object' => $this,
				'obj_id' => $this->getId(),
				'appointments' => $this->prepareCalendarAppointments('create')));

		return $this->getId();
	}

	/**
	 * update object
	 *
	 * @access public
	 * @param
	 * @return bool success
	 */
	public function update()
	{
		global $ilDB;
		global $ilAppEventHandler;

		if(!parent::update())
		{
			return false;
		}
		$query = "UPDATE event SET ".
			"location = ".$this->db->quote($this->getLocation() ,'text').",".
			"tutor_name = ".$this->db->quote($this->getName() ,'text').", ".
			"tutor_phone = ".$this->db->quote($this->getPhone() ,'text').", ".
			"tutor_email = ".$this->db->quote($this->getEmail() ,'text').", ".
			"details = ".$this->db->quote($this->getDetails() ,'text').", ".
			"registration = ".$this->db->quote($this->enabledRegistration() ,'integer').", ".
			"reg_type = ".$this->db->quote($this->getRegistrationType() ,'integer').", ".
			"reg_limited = ".$this->db->quote($this->isRegistrationUserLimitEnabled() ,'integer').", ".
			"reg_limit_users = ".$this->db->quote($this->getRegistrationMaxUsers() ,'integer').", ".
			"reg_min_users = ".$this->db->quote($this->getRegistrationMinUsers() ,'integer').", ".
			"reg_waiting_list = ".$this->db->quote($this->isRegistrationWaitingListEnabled(),'integer').", ".
			"reg_auto_wait = ".$this->db->quote($this->hasWaitingListAutoFill(),'integer').", ".
			// cat-tms-patch start
			"tutor_source = ".$this->db->quote($this->getTutorSource(),'integer')." ".
			// cat-tms-patch end
			"WHERE obj_id = ".$this->db->quote($this->getId() ,'integer')." ";
		$res = $ilDB->manipulate($query);

		// cat-tms-patch start
		if($this->getTutorSource() === self::TUTOR_CFG_FROMCOURSE) {
			$this->storeTutorReferences();
		}
		// cat-tms-patch end



		$ilAppEventHandler->raise('Modules/Session',
			'update',
			array('object' => $this,
				'obj_id' => $this->getId(),
				'appointments' => $this->prepareCalendarAppointments('update')));
		return true;
	}

	/**
	 * delete session and all related data
	 *
	 * @access public
	 * @return bool
	 */
	public function delete()
	{
		global $ilDB;
		global $ilAppEventHandler;

		if(!parent::delete())
		{
			return false;
		}
		$query = "DELETE FROM event ".
			"WHERE obj_id = ".$this->db->quote($this->getId() ,'integer')." ";
		$res = $ilDB->manipulate($query);

		include_once('./Modules/Session/classes/class.ilSessionAppointment.php');
		ilSessionAppointment::_deleteBySession($this->getId());

		include_once('./Modules/Session/classes/class.ilEventItems.php');
		ilEventItems::_delete($this->getId());

		include_once('./Modules/Session/classes/class.ilEventParticipants.php');
		ilEventParticipants::_deleteByEvent($this->getId());

		foreach($this->getFiles() as $file)
		{
			$file->delete();
		}

		$ilAppEventHandler->raise('Modules/Session',
			'delete',
			array('object' => $this,
				'obj_id' => $this->getId(),
				'appointments' => $this->prepareCalendarAppointments('delete')));


		return true;
	}

	/**
	 * read session data
	 *
	 * @access public
	 * @param
	 * @return
	 */
	public function read()
	{
		parent::read();

		$query = "SELECT * FROM event WHERE ".
			"obj_id = ".$this->db->quote($this->getId() ,'integer')." ";
		$res = $this->db->query($query);

		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
		{
			$this->setLocation($row->location);
			$this->setName($row->tutor_name);
			$this->setPhone($row->tutor_phone);
			$this->setEmail($row->tutor_email);
			$this->setDetails($row->details);
			$this->setRegistrationType($row->reg_type);
			$this->enableRegistrationUserLimit($row->reg_limited);
			$this->enableRegistrationWaitingList($row->reg_waiting_list);
			$this->setWaitingListAutoFill($row->reg_auto_wait);
			$this->setRegistrationMaxUsers($row->reg_limit_users);
			$this->setRegistrationMinUsers($row->reg_min_users);
			$this->event_id = $row->event_id;
			// cat-tms-patch start
			$this->setTutorSource((int)$row->tutor_source);
			// cat-tms-patch end
		}
		// cat-tms-patch start
		$tids = $this->readTutorReferences();
		$this->setAssignedTutors($tids);
		// cat-tms-patch end

		$this->initAppointments();
		$this->initFiles();
	}


	/**
	 * init appointments
	 *
	 * @access protected
	 * @param
	 * @return
	 */
	protected function initAppointments()
	{
		// get assigned appointments
		include_once('./Modules/Session/classes/class.ilSessionAppointment.php');
		$this->appointments = ilSessionAppointment::_readAppointmentsBySession($this->getId());
	}

	/**
	 * init files
	 *
	 * @access protected
	 * @param
	 * @return
	 */
	public function initFiles()
	{
		include_once('./Modules/Session/classes/class.ilSessionFile.php');
		$this->files = ilSessionFile::_readFilesByEvent($this->getEventId());
	}


	/**
	 * Prepare calendar appointments
	 *
	 * @access public
	 * @param string mode UPDATE|CREATE|DELETE
	 * @return
	 */
	public function prepareCalendarAppointments($a_mode = 'create')
	{
		include_once('./Services/Calendar/classes/class.ilCalendarAppointmentTemplate.php');

		switch($a_mode)
		{
			case 'create':
			case 'update':

				$app = new ilCalendarAppointmentTemplate(self::CAL_REG_START);
				$app->setTranslationType(IL_CAL_TRANSLATION_NONE);
				$app->setTitle($this->getTitle() ? $this->getTitle() : $this->lng->txt('obj_sess'));
				$app->setDescription($this->getLongDescription());

				$sess_app = $this->getFirstAppointment();
				$app->setFullday($sess_app->isFullday());
				$app->setStart($sess_app->getStart());
				$app->setEnd($sess_app->getEnd());
				$apps[] = $app;

				return $apps;

			case 'delete':
				// Nothing to do: The category and all assigned appointments will be deleted.
				return array();
		}
	}

	public function handleAutoFill()
	{
		if($this->isRegistrationWaitingListEnabled() &&
			$this->hasWaitingListAutoFill())
		{
			// :TODO: what about ilSessionParticipants?

			include_once './Modules/Session/classes/class.ilEventParticipants.php';
			$part_obj = new ilEventParticipants($this->getId());
			$all_reg = $part_obj->getRegistered();
			$now = sizeof($all_reg);
			$max = $this->getRegistrationMaxUsers();
			if($max > $now)
			{
				include_once('./Modules/Session/classes/class.ilSessionWaitingList.php');
				$waiting_list = new ilSessionWaitingList($this->getId());

				foreach($waiting_list->getUserIds() as $user_id)
				{
					if(!$tmp_obj = ilObjectFactory::getInstanceByObjId($user_id,false))
					{
						continue;
					}
					if(in_array($user_id, $all_reg))
					{
						continue;
					}

					ilEventParticipants::_register($user_id, $this->getId());
					$waiting_list->removeFromList($user_id);

					$now++;
					if($now >= $max)
					{
						break;
					}
				}
			}
		}
	}

	// cat-tms-patch start
	/**
	 * How should the tutors be configured?
	 *
	 * @param int $tutor_source
	 */
	public function setTutorSource($tutor_source) {
		if($tutor_source !== self::TUTOR_CFG_MANUALLY
			&& $tutor_source !== self::TUTOR_CFG_FROMCOURSE) {
			throw new \InvalidArgumentException('ilObjSession::setTutorSource - invalid source: ' .$tutor_source);
		}
		$this->tutor_source = $tutor_source;
	}

	/**
	 * How are the tutors configured?
	 *
	 * @return int
	 */
	public function getTutorSource() {
		return $this->tutor_source;
	}

	/**
	 * Get a list of users that are assigned as tutors at the course
	 * this session lives in.
	 *
	 * @global type $tree
	 * @return ilObjUser[]
	 */
	public function getParentCourseTutors() {
		global $tree;
		if(!$this->getRefId()) {
			return array();
		}
		$parent = $tree->getParentNodeData($this->getRefId());
		$crs = ilObjectFactory::getInstanceByObjId($parent['obj_id'],false);
		$members = $crs->getMembersObject();
		$tutors = array();
		foreach($members->getTutors() as $user_id) {
			$tutors[] = ilObjectFactory::getInstanceByObjId($user_id,false);
		}
		return $tutors;
	}

	/**
	 * Add a tutor.
	 *
	 * @param int $usr_id
	 */
	public function addAssignedTutor($usr_id) {
		assert('is_integer($usr_id)');
		$this->assigned_tutors[$usr_id] = \ilObjectFactory::getInstanceByObjId($usr_id, false);
	}

	/**
	 * Re-set tutors.
	 *
	 * @param int[] $usr_ids
	 */
	public function setAssignedTutors(array $usr_ids) {
		$this->assigned_tutors = array();
		foreach ($usr_ids as $usr_id) {
			$this->addAssignedTutor((int)$usr_id);
		}
	}

	/**
	 * Get all assigned tutors.
	 *
	 * @return ilObjUser[]
	 */
	public function getAssignedTutors() {
		return array_values($this->assigned_tutors);
	}
	/**
	 * Get ids of all assigned tutors.
	 *
	 * @return int[]
	 */
	public function getAssignedTutorsIds() {
		return array_keys($this->assigned_tutors);
	}

	/**
	 * Get assigned tutors from DB.
	 *
	 * @return int[]
	 */
	private function readTutorReferences() {
		return self::lookupTutorReferences($this->getId());
	}

	/**
	 * Get assigned tutors from DB.
	 *
	 * @param int $a_obj_id
	 * @return int[]
	 */
	private static function lookupTutorReferences($a_obj_id) {
		global $ilDB;
		$tids = array();
		$query = "SELECT usr_id FROM event_tutors WHERE ".
			"obj_id = ".$ilDB->quote($a_obj_id,'integer')." ";
		$res = $ilDB->query($query);
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
			$tids[] = (int)$row->usr_id;
		}
	return $tids;
	}

	/**
	 * Store assigned tutors in DB
	 */
	private function storeTutorReferences() {
		global $ilDB;
		$query = "DELETE FROM event_tutors WHERE obj_id = ".$this->db->quote($this->getId() ,'integer');
		$this->db->manipulate($query);

		foreach ($this->getAssignedTutors() as $usr) {
			$query = "INSERT INTO  event_tutors (id, obj_id, usr_id) VALUES ("
				.$ilDB->nextId('event_tutors')
				.', '.$this->db->quote($this->getId() ,'integer')
				.', '.$this->db->quote($usr->getId() ,'integer')
				.")";
			$this->db->manipulate($query);
		}
	}
	// cat-tms-patch end
}

?>
