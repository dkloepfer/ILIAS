<?php

require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.ilObjReportBase.php';

ini_set("memory_limit","2048M"); 
ini_set('max_execution_time', 0);
set_time_limit(0);

class ilObjReportWBDPoints extends ilObjReportBase {
	protected $relevant_parameters = array();

	public function initType() {
		$this->setType("xwbp");
	}
	
	protected function createLocalReportSettings() {
		$this->local_report_settings =
			$this->s_f->reportSettings('rep_robj_wbp');
	}

	protected function getRowTemplateTitle() {
		return "tpl.gev_wbd_edupoints_row.html";
	}

	public function getRelevantParameters() {
		return $this->relevant_parameters;
	}

	protected function buildQuery($query) {
		$query	->distinct()
				->select("usr.firstname")
				->select("usr.lastname")
				->select("usr.birthday")
				->select("usr.bwv_id")
				->select("usr.wbd_type")
				->select("crs.title")
				->select_raw(" IF ( crs.custom_id <> '-empty-'"
							."    , crs.custom_id "
							."    , IF (usrcrs.gev_id IS NULL"
							."         , '-'"
							."         , usrcrs.gev_id"
							."         )"
							."    ) as custom_id")
				->select("crs.type")
				->select("usrcrs.begin_date")
				->select("usrcrs.end_date")
				->select("usrcrs.credit_points")
				->select("usrcrs.wbd_booking_id")
				->from("hist_usercoursestatus usrcrs")
				->join("hist_user usr")
					->on("usrcrs.usr_id = usr.user_id AND usr.hist_historic = 0")
				->join("hist_course crs")
					->on("usrcrs.crs_id = crs.crs_id AND crs.hist_historic = 0")
				->compile();
		return $query;
	}

	protected function buildTable($table) {
		$table	->column("firstname", $this->plugin->txt("firstname"), true)
				->column("lastname", $this->plugin->txt("lastname"), true)
				->column("birthday", $this->plugin->txt("birthday"), true)
				->column("bwv_id", $this->plugin->txt("bwv_id"), true)
				->column("wbd_type", $this->plugin->txt("wbd_type"), true)
				->column("title", $this->plugin->txt("crs_title"), true)
				->column("begin_date", $this->plugin->txt("begin_date"), true)
				->column("end_date", $this->plugin->txt("end_date"), true)
				->column("credit_points", $this->plugin->txt("credit_points"), true)
				->column("wbd_booking_id", $this->plugin->txt("wbd_booking_id"), true)
				->column("custom_id", $this->plugin->txt("training_id_2"), true)
				->column("type", $this->plugin->txt("course_type"), true);
		return parent::buildTable($table);
	}

	protected function buildFilter($filter) {
		$filter ->dateperiod( "period"
							, $this->plugin->txt("period")
							, $this->plugin->txt("until")
							, "usrcrs.begin_date"
							, "usrcrs.end_date"
							, date("Y")."-01-01"
							, date("Y")."-12-31"
							, false
							, " OR usrcrs.hist_historic IS NULL"
							)
				->multiselect("wbd_type"
							 , $this->plugin->txt("filter_wbd_type")
							 , "wbd_type"
							 , catFilter::getDistinctValues('wbd_type', 'hist_user')
							 , array()
							 , ""
							 , 300
							 , 160
							 )
				->textinput( "lastname"
						   , $this->plugin->txt("lastname_filter")
						   , "usr.lastname"
						   )
				->static_condition(" usrcrs.hist_historic = 0")
				->static_condition(" usrcrs.wbd_booking_id IS NOT NULL")
				->static_condition(" usr.hist_historic = 0")
				->static_condition(" crs.hist_historic = 0")
				->action($this->filter_action)
				->compile();
		return $filter;
	}

	protected function buildOrder($order) {
		return $order;
	}
}