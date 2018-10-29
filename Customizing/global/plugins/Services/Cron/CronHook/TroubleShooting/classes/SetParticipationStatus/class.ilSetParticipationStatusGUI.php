<?php

require_once __DIR__."/../PluginLanguage.php";
require_once "Services/GEV/Utils/classes/class.gevSettings.php";
require_once "Services/GEV/Utils/classes/class.gevCourseUtils.php";

class ilSetParticipationStatusGUI {
	use PluginLanguage;

	const F_PARTICIPATION_STATUS = "f_participation_status";
	const F_USR_LOGIN = "f_login";
	const F_LEARNING_TIME = "f_learning_time";
	const F_CRS_ID = "f_crs_id";

	const CRS_USR_STATE_SUCCESS = "status_successful";
	const CRS_USR_STATE_EXCUSED = "status_absent_excused";
	const CRS_USR_STATE_NOT_EXCUSED = "status_absent_not_excused";

	const CMD_SHOW_FORM = "showForm";
	const CMD_SET_PARTICIPATION_STATUS = "setParticipationStatus";
	const CMD_AUTOCOMPLETE = "userfieldAutocomplete";

	public function __construct(
		ilCtrl $ctrl,
		ilTabsGUI $tabs,
		ilTemplate $tpl,
		Closure $txt
	) {
		$this->ctrl = $ctrl;
		$this->tabs = $tabs;
		$this->tpl = $tpl;
		$this->txt = $txt;
	}

	public function executeCommand()
	{
		$cmd = $this->ctrl->getCmd();
		$next_class = $this->ctrl->getNextClass();

		switch($cmd) {
			case self::CMD_SHOW_FORM:
				$this->showForm();
				break;
			case self::CMD_AUTOCOMPLETE:
				$this->userfieldAutocomplete();
				break;
			case self::CMD_SET_PARTICIPATION_STATUS:
				$this->setParticipationStatus();
				break;
			default:
				throw new Exception("Unknown command: ".$cmd);
		}
	}

	protected function showForm(ilPropertyFormGUI $form = null)
	{
		if(is_null($form)) {
			$form = $this->initForm();
		}

		$this->tpl->setContent($form->getHtml());
	}

	protected function initForm()
	{
		require_once "Services/Form/classes/class.ilPropertyFormGUI.php";
		$form = new ilPropertyFormGUI();
		$form->setTitle($this->txt("set_status_title"));
		$form->setFormAction($this->ctrl->getFormAction($this));

		$ti = new ilTextInputGUI($this->txt("set_status_usr_id"), self::F_USR_LOGIN);
		$ti->setRequired(true);
		$autocomplete_link = $this->ctrl->getLinkTarget($this, self::CMD_AUTOCOMPLETE, "", true);
		$ti->setDataSource($autocomplete_link);
		$form->addItem($ti);

		$ti = new ilNumberInputGUI($this->txt("set_status_crs_id"), self::F_CRS_ID);
		$ti->setRequired(true);
		$form->addItem($ti);

		$options = array(
			gevSettings::CRS_USR_STATE_SUCCESS_VAL => self::CRS_USR_STATE_SUCCESS,
			gevSettings::CRS_USR_STATE_EXCUSED_VAL => self::CRS_USR_STATE_EXCUSED,
			gevSettings::CRS_USR_STATE_NOT_EXCUSED_VAL => self::CRS_USR_STATE_NOT_EXCUSED
		);

		$group = new ilRadioGroupInputGUI($this->txt("set_status_status"), self::F_PARTICIPATION_STATUS);
		$group->setRequired(true);

		foreach ($options as $key => $lang) {
			$option = new ilRadioOption($this->txt($lang));
			$option->setValue($key);

			if($key == gevSettings::CRS_USR_STATE_SUCCESS_VAL) {
				$ni = new ilNumberInputGUI($this->txt("set_status_wbd_time"), self::F_LEARNING_TIME);
				$ni->setInfo($this->txt("set_status_wbd_time_info"));
				$ni->setRequired(true);
				$ni->allowDecimals(false);
				$option->addSubItem($ni);
			}
			$group->addOption($option);
		}
		$form->addItem($group);

		$form->addCommandButton(self::CMD_SET_PARTICIPATION_STATUS, $this->txt("set_status_set"));
		$form->addCommandButton(self::CMD_SHOW_FORM, $this->txt("cancel"));

		return $form;
	}

	protected function setParticipationStatus()
	{
		$form = $this->initForm();

		if(!$form->checkInput()) {
			$form->setValuesByPost();
			$this->showForm($form);
			return;
		}

		$post = $_POST;
		$login = $post[self::F_USR_LOGIN];
		$user_id = (int)ilObjUser::_lookupId($login);

		$crs_ref_id = $post[self::F_CRS_ID];
		$crs = ilObjectFactory::getInstanceByRefId($crs_ref_id);

		$status = (int)$post[self::F_PARTICIPATION_STATUS];
		$learning_time = 0;
		if($status == gevSettings::CRS_USR_STATE_SUCCESS_VAL) {
			$learning_time = (int)$post[self::F_LEARNING_TIME];
		}

		$this->updateDataTable($user_id, $crs, $status, $learning_time);
		$this->executeHistorizingEvent($user_id, $crs->getId());

		ilUtil::sendSuccess($this->txt("set_status_success"), true);
		$this->ctrl->redirect($this, self::CMD_SHOW_FORM);
	}

	protected function userfieldAutocomplete()
	{
		include_once './Services/User/classes/class.ilUserAutoComplete.php';
		$auto = new ilUserAutoComplete();
		$auto->setSearchFields(array('login','firstname','lastname','email'));
		$auto->enableFieldSearchableCheck(false);
		if (($_REQUEST['fetchall'])) {
			$auto->setLimit(ilUserAutoComplete::MAX_ENTRIES);
		}
		echo $auto->getList($_REQUEST['term']);
		exit();
	}

	protected function updateDataTable($usr_id, $crs, $status, $learning_time) {
		$crs_utils = gevCourseUtils::getInstanceByObj($crs);
		$crs_utils->setParticipationStatusAndPoints($usr_id, $status, $learning_time);
	}

	protected function executeHistorizingEvent($usr_id, $crs_id) {
		$params = array("crs_obj_id" => $crs_id
						,"user_id" => $usr_id);

		require_once "Services/UserCourseStatusHistorizing/classes/class.ilUserCourseStatusHistorizingAppEventListener.php";
		ilUserCourseStatusHistorizingAppEventListener::handleEvent("Services/ParticipationStatus", "setStatusAndPoints", $params);
	}
}