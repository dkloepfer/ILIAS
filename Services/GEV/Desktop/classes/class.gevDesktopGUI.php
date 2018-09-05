<?php

/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */#

/**
* Desktop for the Generali
*
* @author	Richard Klees <richard.klees@concepts-and-training.de>
* @author	Stefan Hecken <stefan.hecken@concepts-and-training.de>
* @version	$Id$
*
* @ilCtrl_Calls gevDesktopGUI: gevMyCoursesGUI
* @ilCtrl_Calls gevDesktopGUI: gevCourseSearchGUI
* @ilCtrl_Calls gevDesktopGUI: ilAdminSearchGUI
* @ilCtrl_Calls gevDesktopGUI: gevBookingGUI
* @ilCtrl_Calls gevDesktopGUI: gevStaticpagesGUI
* @ilCtrl_Calls gevDesktopGUI: gevUserProfileGUI
* @ilCtrl_Calls gevDesktopGUI: gevWBDTPServiceRegistrationGUI
* @ilCtrl_Calls gevDesktopGUI: gevWBDTPBasicRegistrationGUI
* @ilCtrl_Calls gevDesktopGUI: gevMyTrainingsApGUI
* @ilCtrl_Calls gevDesktopGUI: gevEmployeeBookingsGUI
* @ilCtrl_Calls gevDesktopGUI: gevDecentralTrainingGUI
* @ilCtrl_Calls gevDesktopGUI: ilFormPropertyDispatchGUI
* @ilCtrl_Calls gevDesktopGUI: gevDecentralTrainingBuildingBlockAdminGUI
* @ilCtrl_Calls gevDesktopGUI: gevDecentralTrainingCourseCreatingBuildingBlockGUI
* @ilCtrl_Calls gevDesktopGUI: gevDecentralTrainingCourseCreatingBuildingBlock2GUI
* @ilCtrl_Calls gevDesktopGUI: ilObjCourseGUI
* @ilCtrl_Calls gevDesktopGUI: gevDecentralTrainingCreateMailPreviewDataGUI
* @ilCtrl_Calls gevDesktopGUI: gevDecentralTrainingCreateBuildingBlockDataGUI
* @ilCtrl_Calls gevDesktopGUI: gevCrsMailingGUI
* @ilCtrl_Calls gevDesktopGUI: gevMyTrainingsAdminGUI
* @ilCtrl_Calls gevDesktopGUI: ilInfoScreenGUI
* @ilCtrl_Calls gevDesktopGUI: WBTLocatorGUI
* @ilCtrl_Calls gevDesktopGUI: gevMyVAPassGUI
*/

class gevDesktopGUI
{
	public function __construct()
	{
		global $lng, $ilCtrl, $tpl;

		$this->lng = &$lng;
		$this->ctrl = &$ilCtrl;
		$this->tpl = &$tpl;

		$this->lng->loadLanguageModule("gev");
		$this->tpl->getStandardTemplate();
	}

	public function executeCommand()
	{
		global $ilLog;
		$next_class = $this->ctrl->getNextClass();
		$cmd = $this->ctrl->getCmd();
		$this->checkProfileComplete($cmd, $next_class);

		if ($next_class != "gevuserprofilegui" && $cmd != "toMyProfile") {
			$this->checkNeedsWBDRegistration($cmd, $next_class);
		}

		if ($cmd == "") {
			$cmd = "toMyCourses";
		}
		global $ilMainMenu;
		switch ($next_class) {
			case "gevmycoursesgui":
				$ilMainMenu->setActive("gev_me_menu");
				require_once("Services/GEV/Desktop/classes/class.gevMyCoursesGUI.php");
				$gui = new gevMyCoursesGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "gevcoursesearchgui":
				$ilMainMenu->setActive("gev_search_menu");
				require_once("Services/GEV/CourseSearch/classes/class.gevCourseSearchGUI.php");
				$gui = new gevCourseSearchGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "iladminsearchgui":
				$ilMainMenu->setActive("gev_admin_menu");
				require_once("Services/GEV/Desktop/classes/class.ilAdminSearchGUI.php");
				$gui = new ilAdminSearchGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;

			case "gevbookinggui":
				require_once("Services/GEV/Desktop/classes/class.gevBookingGUI.php");
				$gui = new gevBookingGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "gevstaticpagesgui":
				require_once("Services/GEV/Desktop/classes/class.gevStaticPagesGUI.php");
				$gui = new gevStaticpagesGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "gevuserprofilegui":
				$ilMainMenu->setActive("gev_me_menu");
				require_once("Services/GEV/Desktop/classes/class.gevUserProfileGUI.php");
				$gui = new gevUserProfileGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "gevmytrainingsapgui":
				$ilMainMenu->setActive("gev_me_menu");
				require_once("Services/GEV/Desktop/classes/class.gevMyTrainingsApGUI.php");
				$gui = new gevMyTrainingsApGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "gevwbdtpserviceregistrationgui":
				require_once("Services/GEV/Registration/classes/class.gevWBDTPServiceRegistrationGUI.php");
				$gui = new gevWBDTPServiceRegistrationGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "gevwbdtpbasicregistrationgui":
				require_once("Services/GEV/Registration/classes/class.gevWBDTPBasicRegistrationGUI.php");
				$gui = new gevWBDTPBasicRegistrationGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;

			case "gevemployeebookingsgui":
				$ilMainMenu->setActive("gev_others_menu");
				require_once("Services/GEV/Reports/classes/class.gevEmployeeBookingsGUI.php");
				$gui = new gevEmployeeBookingsGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;

			case "gevdecentraltraininggui":
				$ilMainMenu->setActive("gev_others_menu");
				require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingGUI.php");
				$gui = new gevDecentralTrainingGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;

			case "gevdecentraltrainingbuildingblockadmingui":
				$ilMainMenu->setActive("gev_admin_menu");
				require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingBuildingBlockAdminGUI.php");
				$gui = new gevDecentralTrainingBuildingBlockAdminGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "gevdecentraltrainingcoursecreatingbuildingblock2gui":
				$ilMainMenu->setActive("gev_admin_menu");
				require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCourseCreatingBuildingBlock2GUI.php");
				$crs_obj_id = null;

				if (isset($_GET["crs_obj_id"])) {
					$crs_obj_id = (int)$_GET["crs_obj_id"];
				}

				if (isset($_POST["crs_obj_id"])) {
					$crs_obj_id = (int)$_POST["crs_obj_id"];
				}

				if (isset($_GET["crs_ref_id"])) {
					require_once("Services/GEV/Utils/classes/class.gevObjectUtils.php");
					$crs_obj_id = (int)gevObjectUtils::getObjId((int)$_GET["crs_ref_id"]);
				}

				if (isset($_POST["crs_ref_id"])) {
					require_once("Services/GEV/Utils/classes/class.gevObjectUtils.php");
					$crs_obj_id = (int)gevObjectUtils::getObjId((int)$_POST["crs_ref_id"]);
				}

				$gui = new gevDecentralTrainingCourseCreatingBuildingBlock2GUI($crs_obj_id);
				$ret = $this->ctrl->forwardCommand($gui);
				break;

			case "ilobjcoursegui":
				require_once("Modules/Course/classes/class.ilObjCourseGUI.php");
				$gui = new ilObjCourseGUI();
				$this->ctrl->forwardCommand($gui);
				break;
			case "gevdecentraltrainingcreatemailpreviewdatagui":
				require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCreateMailPreviewDataGUI.php");
				$gui = new gevDecentralTrainingCreateMailPreviewDataGUI();
				$this->ctrl->forwardCommand($gui);
				break;
			case "gevdecentraltrainingcreatebuildingblockdatagui":
				require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCreateBuildingBlockDataGUI.php");
				$gui = new gevDecentralTrainingCreateBuildingBlockDataGUI();
				$this->ctrl->forwardCommand($gui);
				break;
			case "gevcrsmailinggui":
				require_once("Services/GEV/Mailing/classes/class.gevCrsMailingGUI.php");
				$gui = new gevCrsMailingGUI();
				$this->ctrl->forwardCommand($gui);
				break;
			case "gevmytrainingsadmingui":
				$ilMainMenu->setActive("gev_me_menu");
				require_once("Services/GEV/Desktop/classes/class.gevMyTrainingsAdminGUI.php");
				$gui = new gevMyTrainingsAdminGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "ilinfoscreengui":
				$ilMainMenu->setActive("gev_me_menu");
				require_once("Services/InfoScreen/classes/class.ilInfoScreenGUI.php");
				$gui = new ilInfoScreenGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case "ilmyobservationsgui":
				$this->plugin = ilPlugin::getPluginObject(
					IL_COMP_SERVICE,
					"Repository",
					"robj",
					ilPlugin::lookupNameForId(IL_COMP_SERVICE, "Repository", "robj", "xtas")
				);

				if (!$this->plugin->active) {
					throw new Exception("Plugin Talent Assessment is not active");
				}
				require_once("Customizing/global/plugins/Services/Repository/RepositoryObject/TalentAssessment/classes/Observations/class.ilMyObservationsGUI.php");
				$gui = new \ilMyObservationsGUI($this, \ilMyObservationsGUI::MODE_MY);
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			case 'wbtlocatorgui':
				require_once 'Services/VIWIS/classes/class.WBTLocatorGUI.php';
				$locator_gui = new WBTLocatorGUI();
				$ret = $this->ctrl->forwardCommand($locator_gui);
				break;
			case "gevmyvapassgui":
				$ilMainMenu->setActive("gev_me_menu");
				require_once("Services/GEV/VAPass/classes/class.gevMyVAPassGUI.php");
				$gui = new gevMyVAPassGUI();
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			default:
				$this->dispatchCmd($cmd);
				break;
		}

		if (isset($ret)) {
			$this->tpl->setContent($ret);
		}

		$this->tpl->show();
	}

	public function dispatchCmd($a_cmd)
	{
		switch ($a_cmd) {
			case "toCourseSearch":
			case "toAdmCourseSearch":
			case "toMyCourses":
			case "toMyProfile":
			case "toStaticPages":
			case "toMyTrainingsAp":
			case "toBooking":
			case "toEmployeeBookings":
			case "createHAUnit":
			case "toDctBuildingBlockAdm":
			case "toSaveTrainingSettings":
			case "toAddCrsBuildingBlock":
			case "toDeleteCrsBuildingBlock":
			case "toUpdateBuildingBlock":
			case "toCancleCreation":
			case "toSaveRequest":
			case "toChangeCourseData":
			case "showOpenRequests":
			case "toWBDRegistration":
			case "toMyTrainingsAdmin":
			case "toMyAssessments":
			case "toAllAssessments":
			case 'redirectToViwis':
			case 'redirectToViwis2004':
			case "toMyVAPass":
				$this->$a_cmd();
				break;
			case "handleExplorerCommand":
				break;
			default:
				throw new Exception("gevDesktopGUI:Unknown command: ".$a_cmd);
		}
	}

	protected function redirectToViwis()
	{
		$this->ctrl->saveParameterByClass('WBTLocatorGUI', 'q_ref', $_GET['q_ref']);
		$this->ctrl->redirectByClass('WBTLocatorGUI', 'redirect_viwis');
	}

	protected function redirectToViwis2004()
	{
		$this->ctrl->saveParameterByClass('WBTLocatorGUI', 'q_ref', $_GET['q_ref']);
		$this->ctrl->redirectByClass('WBTLocatorGUI', 'redirect_viwis_2004');
	}


	protected function toDctBuildingBlockAdm()
	{
		$this->ctrl->redirectByClass("gevDecentralTrainingBuildingBlockAdminGUI");
	}

	protected function toCourseSearch()
	{
		$this->ctrl->redirectByClass("gevCourseSearchGUI");
	}

	protected function toAdmCourseSearch()
	{
		$this->ctrl->redirectByClass("ilAdminSearchGUI");
	}

	protected function toMyCourses()
	{
		$this->ctrl->redirectByClass("gevMyCoursesGUI");
	}

	protected function toStaticPages()
	{
		$this->ctrl->redirectByClass("gevStaticPagesGUI", $_REQUEST['ctpl_file']);
	}

	protected function toMyProfile()
	{
		$this->ctrl->redirectByClass("gevUserProfileGUI");
	}

	protected function toMyTrainingsAp()
	{
		$this->ctrl->redirectByClass("gevMyTrainingsApGUI");
	}

	protected function toMyTrainingsAdmin()
	{
		$this->ctrl->redirectByClass("gevMyTrainingsAdminGUI");
	}

	protected function toEmployeeBookings()
	{
		$this->ctrl->redirectByClass("gevEmployeeBookingsGUI");
	}

	protected function toSaveTrainingSettings()
	{
		$crs_request_id = (isset($_POST["crs_request_id"])) ? $_POST["crs_request_id"] : null;
		$crs_ref_id = (isset($_POST["crs_ref_id"])) ? $_POST["crs_ref_id"] : null;

		require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCourseCreatingBuildingBlock2GUI.php");
		$gui = new gevDecentralTrainingCourseCreatingBuildingBlock2GUI($crs_ref_id, $crs_request_id);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toAddCrsBuildingBlock()
	{
		$crs_request_id = (isset($_POST["crs_request_id"])) ? $_POST["crs_request_id"] : null;
		$crs_ref_id = (isset($_POST["crs_ref_id"])) ? $_POST["crs_ref_id"] : null;

		require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCourseCreatingBuildingBlock2GUI.php");
		$gui = new gevDecentralTrainingCourseCreatingBuildingBlock2GUI($crs_ref_id, $crs_request_id);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toDeleteCrsBuildingBlock()
	{
		$crs_request_id = (isset($_POST["crs_request_id"])) ? $_POST["crs_request_id"] : null;
		$crs_ref_id = (isset($_POST["crs_ref_id"])) ? $_POST["crs_ref_id"] : null;

		require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCourseCreatingBuildingBlock2GUI.php");
		$gui = new gevDecentralTrainingCourseCreatingBuildingBlock2GUI($crs_ref_id, $crs_request_id);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toUpdateBuildingBlock()
	{
		$crs_request_id = (isset($_POST["crs_request_id"])) ? $_POST["crs_request_id"] : null;
		$crs_ref_id = (isset($_POST["crs_ref_id"])) ? $_POST["crs_ref_id"] : null;

		require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCourseCreatingBuildingBlock2GUI.php");
		$gui = new gevDecentralTrainingCourseCreatingBuildingBlock2GUI($crs_ref_id, $crs_request_id);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toCancleCreation()
	{
		require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCourseCreatingBuildingBlock2GUI.php");
		$gui = new gevDecentralTrainingCourseCreatingBuildingBlock2GUI(null, null);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toSaveRequest()
	{
		require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCourseCreatingBuildingBlock2GUI.php");
		$gui = new gevDecentralTrainingCourseCreatingBuildingBlock2GUI(null, null);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toChangeCourseData()
	{
		require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingGUI.php");
		$gui = new gevDecentralTrainingGUI();
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function showOpenRequests()
	{
		require_once("Services/GEV/DecentralTrainings/classes/class.gevDecentralTrainingCourseCreatingBuildingBlock2GUI.php");
		$gui = new gevDecentralTrainingCourseCreatingBuildingBlock2GUI(null, null);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toBooking()
	{
		if (!$_GET["crs_id"]) {
			ilUtil::redirect("");
		}

		global $ilUser;

		$crs_id = intval($_GET["crs_id"]);
		$usr_id = $ilUser->getId();

		$this->ctrl->setParameterByClass("gevBookingGUI", "user_id", $usr_id);
		$this->ctrl->setParameterByClass("gevBookingGUI", "crs_id", $crs_id);
		$this->ctrl->redirectByClass("gevBookingGUI", "book");
	}

	protected function toWBDRegistration()
	{
		$this->ctrl->redirectByClass("gevWBDTPServiceRegistrationGUI");
	}

	protected function toMyAssessments()
	{
		$this->plugin = ilPlugin::getPluginObject(
			IL_COMP_SERVICE,
			"Repository",
			"robj",
			ilPlugin::lookupNameForId(IL_COMP_SERVICE, "Repository", "robj", "xtas")
		);

		if (!$this->plugin->active) {
			throw new Exception("Plugin Talent Assessment is not active");
		}

		require_once("Customizing/global/plugins/Services/Repository/RepositoryObject/TalentAssessment/classes/Observations/class.ilMyObservationsGUI.php");
		$gui = new \ilMyObservationsGUI($this, ilMyObservationsGUI::MODE_MY);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toAllAssessments()
	{
		$this->plugin = ilPlugin::getPluginObject(
			IL_COMP_SERVICE,
			"Repository",
			"robj",
			ilPlugin::lookupNameForId(IL_COMP_SERVICE, "Repository", "robj", "xtas")
		);

		if (!$this->plugin->active) {
			throw new Exception("Plugin Talent Assessment is not active");
		}

		require_once("Customizing/global/plugins/Services/Repository/RepositoryObject/TalentAssessment/classes/Observations/class.ilMyObservationsGUI.php");
		$gui = new \ilMyObservationsGUI($this, ilMyObservationsGUI::MODE_ALL);
		$ret = $this->ctrl->forwardCommand($gui);
	}

	protected function toMyVAPass()
	{
		$this->ctrl->redirectByClass("gevMyVAPassGUI");
	}

	protected function handleExplorerCommand()
	{
	}

	protected function checkProfileComplete($cmd, $next_class)
	{
		require_once("Services/GEV/Utils/classes/class.gevUserUtils.php");
		global $ilUser;
		$utils = gevUserUtils::getInstanceByObj($ilUser);
		if (!$utils->isProfileComplete() && !($cmd == "toMyProfile" || $next_class == "gevuserprofilegui")) {
			ilUtil::sendFailure($this->lng->txt("gev_profile_incomplete"), true);
			$this->ctrl->redirect($this, "toMyProfile");
		}
	}

	protected function checkNeedsWBDRegistration($cmd, $next_class)
	{
		require_once("Services/GEV/WBD/classes/class.gevWBD.php");
		global $ilUser;
		$wbd = gevWBD::getInstanceByObj($ilUser);
		if ($wbd->hasWBDRelevantRole() && !$wbd->hasDoneWBDRegistration()) {
			//two ways: GEV is TP or  TPBasic
			if ($wbd->canBeRegisteredAsTPService()) {
				if ($next_class != "gevwbdtpserviceregistrationgui") {
					$this->ctrl->redirectByClass("gevWBDTPServiceRegistrationGUI");
				}
			} else {
				if ($next_class != "gevwbdtpbasicregistrationgui") {
					$this->ctrl->redirectByClass("gevWBDTPBasicRegistrationGUI");
				}
			}
		}
	}

	protected function createHAUnit()
	{
		require_once("Services/GEV/Utils/classes/class.gevHAUtils.php");
		require_once("Services/GEV/Utils/classes/class.gevObjectUtils.php");
		$ha_utils = gevHAUtils::getInstance();

		global $ilUser;

		if ($ha_utils->hasHAUnit($ilUser->getId())) {
			throw new Exception("User ".$ilUser->getId()." already has an HA-Unit.");
		}

		$org_id = $ha_utils->createHAUnit($ilUser->getId());

		ilUtil::sendSuccess($this->lng->txt("gev_ha_org_unit_created"), true);

		$ref_id = gevObjectUtils::getRefId($org_id);
		$this->ctrl->setParameterByClass("ilLocalUserGUI", "ref_id", $ref_id);
		$this->ctrl->redirectByClass(array("ilAdministrationGUI","ilObjOrgUnitGUI","ilLocalUserGUI"), "index");
	}
}
