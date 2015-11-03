<?php

/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */#

/**
* Desktop for the Generali
*
* @author	Richard Klees <richard.klees@concepts-and-training.de>
* @author   Martin Studer <ms@studer-raimann.ch>
* @version	$Id$
*/

require_once("Services/MainMenu/classes/class.ilMainMenuGUI.php");
require_once("Services/UIComponent/GroupedList/classes/class.ilGroupedListGUI.php");
require_once("Services/UIComponent/Overlay/classes/class.ilOverlayGUI.php");
require_once("Modules/OrgUnit/classes/class.ilObjOrgUnitAccess.php");

require_once("Services/GEV/Utils/classes/class.gevUserUtils.php");


class gevMainMenuGUI extends ilMainMenuGUI {
	const IL_STANDARD_ADMIN = "gev_ilias_admin_menu";
	/**
	 * @var  gevUserUtils
	 */
	protected $userUtils = Null;
	/**
	 * @var ilCtrl
	 */
	protected $ctrl = Null;

	public function __construct() {
		parent::__construct($a_target, $a_use_start_template);
		
		global $lng, $ilCtrl, $ilAccess, $ilUser;
		
		$this->gLng = &$lng;
		$this->gCtrl = &$ilCtrl;
		$this->gAccess = &$ilAccess;
		$this->gUser = &$ilUser;
		
		if($this->gUser->getId() !== 0) {
			$this->gUserUtils = gevUserUtils::getInstance($this->gUser->getId());
		}

		$this->gLng->loadLanguageModule("gev");
	}

	public function renderMainMenuListEntries($a_tpl, $a_call_get = true) {
		// No Menu during registration or on makler page
		$basename = basename($_SERVER["PHP_SELF"]);
		if (   $basename == "gev_registration.php"
			|| $basename == "gev_logindata.php"
			|| $basename == "makler.php" ) {
			return;
		}
		
		// switch to patch template
		$a_tpl = new ilTemplate("tpl.gev_main_menu_entries.html", true, true, "Services/GEV/Desktop");
		
		// known ref_ids
		$repository = 1;
		$user_mgmt = 7;
		$org_mgmt = 56;
		$mail_mgmt = 12;
		$competence_mgmt = 41;
		$general_settings = 9;
		
		//permissions
		$manage_courses = $this->gAccess->checkAccess("write", "", $repository);
		$search_courses = $manage_courses || ($this->gUserUtils && $this->gUserUtils->hasRoleIn(array("Admin-Ansicht")));
		$manage_users = $this->gAccess->checkAccess("visible", "", $user_mgmt);
		$manage_org_units = $this->gAccess->checkAccess("visible", "", $org_mgmt);
		$manage_mails = $this->gAccess->checkAccess("visible", "", $mail_mgmt);
		$manage_competences = $this->gAccess->checkAccess("visible", "", $competence_mgmt);
		$has_managment_menu = ($manage_courses || $search_courses || $manage_users || $manage_org_units || $manage_mails || $manage_competences)
							&& ($this->gUserUtils && !$this->gUserUtils->hasRoleIn(array("OD/BD", "FD", "UA", "ID FK", "DBV UVG", "HA 84")))
							;
		
		$has_super_admin_menu = $this->gAccess->checkAccess("write", "", $general_settings);
		
		require_once("Services/TEP/classes/class.ilTEPPermissions.php");

		$employee_booking = ($this->gUserUtils && $this->gUserUtils->canViewEmployeeBookings());
		$my_org_unit = false;
		$tep = ($this->gUserUtils && ($this->gUserUtils->isAdmin() || ilTEPPermissions::getInstance($this->gUser->getId())->isTutor()));
		$pot_participants = false;
		$apprentices = false;
		require_once("Services/GEV/Utils/classes/class.gevHAUtils.php");
		$can_create_ha_unit = ($this->gUserUtils && ($this->gUserUtils->hasRoleIn(array("HA 84")) && !gevHAUtils::getInstance()->hasHAUnit($this->gUserUtils->getId())));
		$local_user_admin = ($this->gUserUtils && $this->gUserUtils->isSuperior()); //Local User Administration Permission

		$has_others_menu = $employee_booking || $my_org_unit || $tep || $pot_participants || $apprentices || $local_user_admin || $can_create_ha_unit;

		require_once("Services/GEV/Reports/classes/class.gevReportingPermissions.php");
		
		$report_permission_billing = ($this->gUserUtils && gevReportingPermissions::getInstance($this->gUser->getId())->viewBillingReport());
		$report_permission_attendancebyuser =  ($this->gUserUtils && ($this->gUserUtils->isAdmin() || $this->gUserUtils->isSuperior()));
		$report_permission_bookingsbyvenue =  ($this->gUserUtils && ($this->gUserUtils->isAdmin() || $this->gUserUtils->hasRoleIn(array("Veranstalter"))));
		$report_permission_employee_edu_bio = ($this->gUserUtils && ($this->gUserUtils->isAdmin() || $this->gUserUtils->hasRoleIn(array("OD-Betreuer")) || $this->gUserUtils->isSuperior()));
		$report_permission_attendancebyorgunit = ($this->gUserUtils && ($this->gUserUtils->isAdmin() ||  $this->gUserUtils->hasRoleIn(array("Admin-Ansicht"))));
		$report_permission_attendancebycoursetemplate = ($this->gUserUtils && $this->gUserUtils->isAdmin());
		$report_permission_wbd = ($this->gUserUtils && $this->gUserUtils->isAdmin());
		$report_permission_traineroperationbytepcategory = ($this->gUserUtils && $this->gUserUtils->isAdmin());
		$report_permission_dbv = ($this->gUserUtils && $this->gUserUtils->hasRoleIn(array("DBV-Fin-UVG")));
		$report_permission_dbv_superior = ($this->gUserUtils && ($this->gUserUtils->isSuperior() || $this->gUserUtils->isAdmin()));

		$has_reporting_menu =  $report_permission_billing 
							|| $report_permission_attendancebyuser 
							|| $report_permission_bookingsbyvenue 
							|| $report_permission_employee_edu_bio
							|| $report_permission_wbd
							|| $report_permission_attendancebyorgunit; //$report_permission_attendancebyuser; // || ....

		$is_trainer = $tep; // $tep_permissions->isTutor();

		$manage_course_block_units = true;

		//get all OrgUnits of superior
		$arr_org_units_of_superior = $this->gUserUtils ? $this->gUserUtils->getOrgUnitsWhereUserIsDirectSuperior() : array();
		$arr_local_user_admin_links = array();
		if($arr_org_units_of_superior) {
			foreach($arr_org_units_of_superior as $arr_org_unit_of_superior) {
				if (ilObjOrgUnitAccess::_checkAccessAdministrateUsers($arr_org_unit_of_superior['ref_id'])) {
					$this->gCtrl->setParameterByClass("ilLocalUserGUI", "ref_id", $arr_org_unit_of_superior['ref_id']);
					$arr_local_user_admin_links[$arr_org_unit_of_superior['ref_id']]['title'] = ilObject::_lookupTitle($arr_org_unit_of_superior['obj_id']);
					$arr_local_user_admin_links[$arr_org_unit_of_superior['ref_id']]['url'] = $this->gCtrl->getLinkTargetByClass(array("ilAdministrationGUI","ilObjOrgUnitGUI","ilLocalUserGUI"), "index");
				}
			}
		}
		
		$menu = array( 
			//single entry?
			//render entry?
			//content
			//link title
			  "gev_search_menu" => array(true, true, "ilias.php?baseClass=gevDesktopGUI&cmd=toCourseSearch",$this->gLng->txt("gev_search_menu"), $this->gLng->txt("gev_search_menu"))
			, "gev_me_menu" => array(false, true, array(
											  //render entry?
  											  //url
				                              //link title
				  "gev_my_courses" => array(true, "ilias.php?baseClass=gevDesktopGUI&cmd=toMyCourses",$this->gLng->txt("gev_my_courses"))
				, "gev_edu_bio" => array(false, "NYI!",$this->gLng->txt("gev_edu_bio"))
				, "gev_my_profile" => array(true, "ilias.php?baseClass=gevDesktopGUI&cmd=toMyProfile",$this->gLng->txt("gev_my_profile"))
				, "gev_my_settings" => array(true, "ilias.php?baseClass=ilPersonalDesktopGUI&cmd=jumpToSettings",$this->gLng->txt("gev_my_settings"))
				, "gev_my_groups" => array(false, "NYI!",$this->gLng->txt("gev_my_groups"))
				, "gev_my_roadmap" => array(false, "NYI!",$this->gLng->txt("gev_my_roadmap"))
				, "gev_my_trainer_ap" => array($is_trainer, "ilias.php?baseClass=gevDesktopGUI&cmd=toMyTrainingsAp",$this->gLng->txt("gev_my_trainer_ap"))

				), $this->gLng->txt("gev_me_menu"))
			, "gev_others_menu" => array(false, $has_others_menu, array(
				  "gev_employee_booking" => array($employee_booking, "ilias.php?baseClass=gevDesktopGUI&cmd=toEmployeeBookings",$this->gLng->txt("gev_employee_booking"))
				, "gev_my_org_unit" => array($my_org_unit, "NYI!",$this->gLng->txt("gev_my_org_unit"))
				, "gev_tep" => array($tep, "ilias.php?baseClass=ilTEPGUI",$this->gLng->txt("gev_tep"))
				, "gev_pot_participants" => array($pot_participants, "NYI!",$this->gLng->txt("gev_pot_participants"))
				, "gev_my_apprentices" => array($apprentices, "NYI!",$this->gLng->txt("gev_my_apprentices"))
				, "gev_create_org_unit" => array($can_create_ha_unit, "ilias.php?baseClass=gevDesktopGUI&cmd=createHAUnit", $this->gLng->txt("gev_create_ha_org_unit"))
				), $this->gLng->txt("gev_others_menu"))
			, "gev_process_menu" => array(false, false, array(
				  "gev_apprentice_grant" => array(true, "NYI!",$this->gLng->txt("gev_apprentice_grant"))
				, "gev_pot_applicants" => array(true, "NYI!",$this->gLng->txt("gev_pot_applicants"))
				, "gev_spec_course_create" => array(true, "NYI!",$this->gLng->txt("gev_spec_course_create"))
				, "gev_spec_course_approval" => array(true, "NYI!",$this->gLng->txt("gev_spec_course_approval"))
				, "gev_spec_course_check" => array(true, "NYI!",$this->gLng->txt("gev_spec_course_check"))
				), $this->gLng->txt("gev_others_menu"))
			, "gev_reporting_menu" => array(false, $has_reporting_menu, array(
				  "gev_report_attendance_by_employee" => array($report_permission_attendancebyuser, "ilias.php?baseClass=gevDesktopGUI&cmd=toReportAttendanceByEmployee",$this->gLng->txt("gev_report_attendance_by_employee"))
				, "gev_report_employee_edu_bio" => array($report_permission_employee_edu_bio, "ilias.php?baseClass=gevDesktopGUI&cmd=toReportEmployeeEduBios",$this->gLng->txt("gev_report_employee_edu_bios"))
				, "gev_report_billing" => array($report_permission_billing, "ilias.php?baseClass=gevDesktopGUI&cmd=toBillingReport",$this->gLng->txt("gev_report_billing"))
				, "gev_report_bookingbyvenue" => array($report_permission_bookingsbyvenue, "ilias.php?baseClass=gevDesktopGUI&cmd=toReportBookingsByVenue",$this->gLng->txt("gev_report_bookingbyvenue"))
				, "gev_report_trainer_operation_by_tep_category" => array($report_permission_traineroperationbytepcategory, "ilias.php?baseClass=gevDesktopGUI&cmd=toReportTrainerOperationByTEPCategory",$this->gLng->txt("gev_report_trainer_operation_by_tep_category"))
				, "gev_report_attendance_by_orgunit" => array($report_permission_attendancebyorgunit, "ilias.php?baseClass=gevDesktopGUI&cmd=toReportAttendanceByOrgUnit",$this->gLng->txt("gev_report_attendancebyorgunit"))
				, "gev_report_attendance_by_coursetemplate" => array($report_permission_attendancebycoursetemplate, "ilias.php?baseClass=gevDesktopGUI&cmd=toReportAttendanceByCourseTemplate",$this->gLng->txt("gev_report_attendancebycoursetemplate"))
				, "gev_report_wbd_edupoints" => array($report_permission_wbd, "ilias.php?baseClass=gevDesktopGUI&cmd=toReportWBDEdupoints",$this->gLng->txt("gev_report_wbd_edupoints"))
				, "gev_report_wbd_errors" => array($report_permission_wbd, "ilias.php?baseClass=gevDesktopGUI&cmd=toWBDErrors",$this->gLng->txt("gev_report_wbd_errors"))
				, "gev_report_dbv_report" => array($report_permission_dbv, "ilias.php?baseClass=gevDesktopGUI&cmd=toDBVReport",$this->gLng->txt("gev_report_dbv_report"))
				, "gev_report_dbv_report_superior" => array($report_permission_dbv_superior, "ilias.php?baseClass=gevDesktopGUI&cmd=toDBVReportSuperior",$this->gLng->txt("gev_report_dbv_report_superior"))
				, "gev_report_trainer_workload" => array(($this->gUserUtils && $this->gUserUtils->isAdmin()), "ilias.php?baseClass=gevDesktopGUI&cmd=toTrainerWorkload",$this->gLng->txt("gev_report_trainer_workload"))
				, "gev_report_trainer_operation_by_orgu_trainer" => array(($this->gUserUtils && $this->gUserUtils->isAdmin()), "ilias.php?baseClass=gevDesktopGUI&cmd=toTrainerOperationByOrgUnitAndTrainer",$this->gLng->txt("gev_report_trainer_operation_by_orgu_trainer"))
				), $this->gLng->txt("gev_reporting_menu"))

			, "gev_admin_menu" => array(false, $has_managment_menu, array(
				  "gev_course_mgmt" => array($manage_courses, "goto.php?target=root_1",$this->gLng->txt("gev_course_mgmt"))
				, "gev_course_mgmt_search" => array($search_courses, "ilias.php?baseClass=gevDesktopGUI&cmd=toAdmCourseSearch",$this->gLng->txt("gev_course_search_adm"))
				, "gev_user_mgmt" => array($manage_users, "ilias.php?baseClass=ilAdministrationGUI&ref_id=7&cmd=jump",$this->gLng->txt("gev_user_mgmt"))
				, "gev_org_mgmt" => array($manage_org_units, "ilias.php?baseClass=ilAdministrationGUI&ref_id=56&cmd=jump",$this->gLng->txt("gev_org_mgmt"))
				, "gev_mail_mgmt" => array($manage_mails, "ilias.php?baseClass=ilAdministrationGUI&ref_id=12&cmd=jump",$this->gLng->txt("gev_mail_mgmt"))
				//, "gev_competence_mgmt" => array($manage_competences, "ilias.php?baseClass=ilAdministrationGUI&ref_id=41&cmd=jump",$this->gLng->txt("gev_competence_mgmt"))
				, "gev_course_bock_unit_mgmt" => array($manage_course_block_units, "ilias.php?baseClass=gevDesktopGUI&cmd=toDctBuildingBlockAdm",$this->gLng->txt("gev_dec_building_block_mgmt"))
				), $this->gLng->txt("gev_admin_menu"))
			, self::IL_STANDARD_ADMIN => array(false, $has_super_admin_menu, null)
			);

		//Enhance Menu with Local Useradmin Roles
		if(count($arr_local_user_admin_links) > 0)  {
			foreach($arr_local_user_admin_links as $key => $arr_local_user_admin_link) {
				$menu["gev_others_menu"][2]["gev_my_local_user_admin_".$key] = array(
					$local_user_admin,
					$arr_local_user_admin_link['url'],
					sprintf($this->gLng->txt("gev_my_local_user_admin"), $arr_local_user_admin_link['title'])
					);
			}
		}

		foreach ($menu as $id => $entry) {
			if (! $entry[1]) {
				continue;
			}
			
			if ($entry[0]) {
				$this->_renderSingleEntry($a_tpl, $id, $entry);
			}
			else{
				$this->_renderDropDownEntry($a_tpl, $id, $entry);
			}
		}
		
		// Some ILIAS idiosyncracy copied from ilMainMenuGUI.
		if ($a_call_get) {
			return $a_tpl->get();
		}
		
		return "";
	}
	
	protected function _renderSingleEntry($a_tpl, $a_id, $a_entry) {
		$a_tpl->setCurrentBlock("single_entry");
		
		$a_tpl->setVariable("ENTRY_ID", 'id="'.$a_id.'"');
		$this->_setActiveClass($a_tpl, $a_id);
		$a_tpl->setVariable("ENTRY_TARGET", $a_entry[2]);
		$a_tpl->setVariable("ENTRY_TITLE", $a_entry[3]);
		
		$a_tpl->parseCurrentBlock();
	}
	
	protected function _renderDropDownEntry($a_tpl, $a_id, $a_entry) {
		if ($a_id == self::IL_STANDARD_ADMIN) {
			$this->_renderAdminMenu($a_tpl);
		} 
		else {
			$a_tpl->setCurrentBlock("dropdown_entry");
			
			$trigger_id = $a_id;
			$target_id = $a_id."_ov";
			
			$a_tpl->setVariable("ENTRY_ID", 'id="'.$trigger_id.'"');
			$a_tpl->setVariable("ENTRY_ID_OV", 'id="'.$target_id.'"');
			$this->_setActiveClass($a_tpl, $a_id);
			$a_tpl->setVariable("ENTRY_TITLE", $a_entry[3]);
			
			$a_tpl->setVariable("ENTRY_CONT", $this->_renderDropDown($a_entry[2]));
			
			$ov = new ilOverlayGUI($target_id);
			$ov->setTrigger($trigger_id);
			$ov->setAnchor($trigger_id);
			$ov->setAutoHide(false);
			$ov->add();
			
			$a_tpl->parseCurrentBlock();
		}
	}
	
	protected function _renderDropDown($a_entries) {
		$gl = new ilGroupedListGUI();
		
		foreach($a_entries as $id => $entry) {
			if ($entry === null) {
				$gl->addSeperator();
			}
			else if ($entry[0]) {
				$gl->addEntry($entry[2], $entry[1], "_top");
			}
		}
		
		return $gl->getHTML();
	}
	
	protected function _renderAdminMenu($a_tpl) {
		$a_tpl->setCurrentBlock("admin_entry");
		require_once("./Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php");
		
		$selection = new ilAdvancedSelectionListGUI();
		
		$selection->setSelectionHeaderSpanClass("MMSpan");
		$selection->setItemLinkClass("small");
		$selection->setUseImages(false);

		$selection->setListTitle($this->gLng->txt(self::IL_STANDARD_ADMIN));
		$selection->setId(self::IL_STANDARD_ADMIN);
		$selection->setAsynch(true);
		$selection->setAsynchUrl("ilias.php?baseClass=ilAdministrationGUI&cmd=getDropDown&cmdMode=asynch");
		
		$a_tpl->setVariable("ADMIN_DROP_DOWN", $selection->getHTML());
		$a_tpl->parseCurrentBlock();
	}
	
	protected function _setActiveClass($a_tpl, $a_title) {
		if($this->active == $a_title) {
			$a_tpl->setVariable("MM_CLASS", "MMActive");
		}
		else {
			$a_tpl->setVariable("MM_CLASS", "MMInactive");
		}
	}
}

?>
