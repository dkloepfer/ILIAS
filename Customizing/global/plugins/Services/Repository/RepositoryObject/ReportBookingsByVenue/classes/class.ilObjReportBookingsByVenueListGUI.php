<?php
require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.ilObjReportBaseListGUI.php';
  /**
   * ListGUI implementation for Example object plugin. This one
   * handles the presentation in container items (categories, courses, ...)
   * together with the corresponfing ...Access class.
   *
   * PLEASE do not create instances of larger classes here. Use the
   * ...Access class to get DB data and keep it small.
   */
class ilObjReportBookingsByVenueListGUI extends ilObjReportBaseListGUI {

	/**
	 * Init type
	 */
	public function initType() {
		$this->setType("xbbv");
		parent::initType();
	}

	/**
	 * Get name of gui class handling the commands
	 */
	public function getGuiClass() {
		return "ilObjReportBookingsByVenueGUI";
	}

	public function getProperties() {

		$props = array();

		$this->plugin->includeClass("class.ilObjReportBookingsByVenueAccess.php");
		if (!ilObjReportBookingsByVenueAccess::checkOnline($this->obj_id)) {
			$props[] = array("alert" => true, "property" => $this->lng->txt("status"),
			"value" => $this->lng->txt("offline"));
		}
		 
		return $props;
	}
}