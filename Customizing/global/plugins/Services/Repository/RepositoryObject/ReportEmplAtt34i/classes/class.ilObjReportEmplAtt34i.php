<?php

require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.ilObjReportBase.php';
require_once 'Services/GEV/Utils/classes/class.gevCourseUtils.php';

ini_set("memory_limit", "2048M");
ini_set('max_execution_time', 0);
set_time_limit(0);

class ilObjReportEmplAtt34i extends ilObjReportBase
{
	protected $relevant_parameters = array();

	public function __construct($ref_id = 0)
	{
		parent::__construct($ref_id);
		global $lng;
		$this->gLng = $lng;
	}

	public function initType()
	{
		 $this->setType("x34i");
	}

	protected function createLocalReportSettings()
	{
		$this->local_report_settings =
			$this->s_f->reportSettings('rep_robj_rea34i')
						->addSetting($this->s_f
							->settingBool('is_local', $this->plugin->txt('is_local')));
	}

	protected function getFilterSettings()
	{
		$filter = $this->filter();
		if ($this->filter_settings) {
			$settings = call_user_func_array(array($filter, "content"), $this->filter_settings);
		}
		return $settings;
	}

	protected function mapToStatus($field)
	{
		$map = [];
		foreach (gevSettings::$IDHGBAAD_STATUS_MAPPING as $role => $status) {
			if (!array_key_exists($status, $map)) {
				$map[$status] = [];
			}
			$map[$status][] = $role;
		}
		$transform_statement = 'CASE '.PHP_EOL;
		foreach ($map as $status => $roles) {
			$transform_statement
				.= '	WHEN '.$this->gIldb->in($field, $roles, false, 'text')
					.' 	THEN '.$this->gIldb->quote($status, 'text').PHP_EOL;
		}
		$transform_statement .= '	ELSE NULL '.PHP_EOL
								.'END ';
		return $transform_statement;
	}

	/**
	 * @inheritdoc
	 */
	protected function buildQuery($query)
	{
		$this->filter_selections = $this->getFilterSettings();
		$settings = gevSettings::getInstance();
		$idd_affected_start = $settings->get(gevSettings::USR_UDF_IDD_AFFECTED_START);
		$idd_affected_end = $settings->get(gevSettings::USR_UDF_IDD_AFFECTED_END);
		$location_ma = $settings->get(gevSettings::USR_UDF_LOCATION_MA);

		$query
			->select("usr.lastname")
			->select("usr.firstname")
			->select("usr.email")
			->select('usr.mobile_phone_nr')
			->select_raw("usr.adp_number")
			->select_raw("usr.job_number")
			->select("orgu_all.org_unit_above1")
			->select("orgu_all.org_unit_above2")
			->select_raw("GROUP_CONCAT(DISTINCT orgu_all.orgu_title SEPARATOR ', ') AS org_unit")
			->select_raw("GROUP_CONCAT(DISTINCT role.rol_title ORDER BY role.rol_title SEPARATOR ', ') AS roles")
			->select_raw('GROUP_CONCAT(DISTINCT '.$this->mapToStatus('role.rol_title').' SEPARATOR \', \') AS status')
			->select("usr.position_key")
			->select("crs.custom_id")
			->select("crs.title")
			->select("crs.venue")
			->select("crs.type")
			->select("usrcrs.credit_points")
			->select("usrcrs.participation_status")
			->select("usrcrs.begin_date")
			->select("usrcrs.end_date")
			->select_raw("idd_affected_start.value AS idd_affected_start")
			->select_raw("idd_affected_end.value AS idd_affected_end")
			->select_raw("location_ma.value AS location_ma")
			->from("hist_user usr")
			->join("hist_usercoursestatus usrcrs")
				->on("usr.user_id = usrcrs.usr_id AND usrcrs.hist_historic = 0")
			->join("hist_course crs")
				->on("crs.crs_id = usrcrs.crs_id AND crs.hist_historic = 0 AND crs.type != ".$this->gIldb->quote(gevCourseUtils::CRS_TYPE_COACHING, "text"))
			->left_join("hist_userorgu orgu_all")
				->on("orgu_all.usr_id = usr.user_id")
			->left_join("hist_userrole role")
				->on("role.usr_id = usr.user_id")
			->left_join("udf_text idd_affected_start")
				->on("idd_affected_start.usr_id = usr.user_id AND idd_affected_start.field_id = ".$idd_affected_start)
			->left_join("udf_text idd_affected_end")
				->on("idd_affected_end.usr_id = usr.user_id AND idd_affected_end.field_id = ".$idd_affected_end)
			->left_join("udf_text location_ma")
				->on("location_ma.usr_id = usr.user_id AND location_ma.field_id = ".$location_ma)
		;

		if ($this->orgusFiltered()) {
			$query->join("hist_userorgu orgu_filter")
				->on("orgu_filter.usr_id = usr.user_id "
					." 	AND ".$this->gIldb->in('orgu_filter.orgu_id', $this->getSelectedAndRecursiveOrgus(), false, 'integer')
					."	AND orgu_filter.action >=0 "
					."	AND orgu_filter.hist_historic = 0 ");
		}
		$query
			->group_by("usr.user_id")
			->group_by("usrcrs.crs_id")
			->compile()
			;
		return $query;
	}

	private function orgusFiltered()
	{
		return count($this->filter_selections['orgus']) > 0;
	}

	private function crsTopicsFiltered()
	{
		return count($this->filter_selections['crs_topics']) > 0;
	}

	private function getSelectedAndRecursiveOrgus()
	{
		if ($this->filter_selections['recursive']) {
			return array_unique($this->addRecursiveOrgusToSelection($this->filter_selections['orgus']));
		}
		return  $this->filter_selections['orgus'];
	}

	private function addRecursiveOrgusToSelection(array $selection)
	{
		require_once 'Services/GEV/Utils/classes/class.gevOrgUnitUtils.php';
		$aux = $selection;
		foreach ($selection as $orgu_id) {
			$ref_id = gevObjectUtils::getRefId($orgu_id);
			$aux[] = $orgu_id;
			foreach (gevOrgUnitUtils::getAllChildren(array($ref_id)) as $child) {
				$aux[] = $child["obj_id"];
			}
		}
		return $aux;
	}

	/**
	 * @inheritdoc
	 */
	protected function buildOrder($order)
	{
		$order->mapping("date", "crs.begin_date")
				->mapping("od_bd", array("org_unit_above1", "org_unit_above2"))
				->defaultOrder("lastname", "ASC")
				;
		return $order;
	}

	/**
	 * @inheritdoc
	 */
	protected function buildTable($table)
	{
		$table
			->column("lastname", $this->plugin->txt("lastname"), true)
			->column("firstname", $this->plugin->txt("firstname"), true)
			->column("email", $this->plugin->txt("email"), true)
			->column("idd_affected_start", $this->plugin->txt("idd_affected_start"), true)
			->column("idd_affected_end", $this->plugin->txt("idd_affected_end"), true)
			->column("mobile_phone_nr", $this->plugin->txt("mobile_phone_nr"), true)
			->column("adp_number", $this->plugin->txt("adp_number"), true)
			->column("job_number", $this->plugin->txt("job_number"), true)
			->column("od_bd", $this->plugin->txt("od_bd"), true, "", false, false)
			->column("org_unit", $this->plugin->txt("org_unit_short"), true)
			->column("lacation_ma", $this->plugin->txt("location_ma"), true)
			->column("roles", $this->plugin->txt("roles"), true)
			->column("status", $this->plugin->txt("status"), true)
			->column("custom_id", $this->plugin->txt("training_id"), true)
			->column("title", $this->plugin->txt("title"), true)
			->column("venue", $this->plugin->txt("location"), true)
			->column("type", $this->plugin->txt("learning_type"), true)
			->column("date", $this->plugin->txt("date"), true)
			->column("credit_points", $this->plugin->txt("wb_time"), true)
			->column("participation_status", $this->plugin->txt("participation_status"), true)
			;
		return parent::buildTable($table);
	}

	protected function filterTemplates(array $crs_ids)
	{
		$res = $this->gIldb->query('SELECT crs_id'
								.'	FROM hist_course'
								.'	WHERE hist_historic = 0'
								.'		AND is_template = '.$this->gIldb->quote('Ja', 'text')
								.'		AND '.$this->gIldb->in('crs_id', $crs_ids, false, 'integer'));
		$return = [];
		while ($rec = $this->gIldb->fetchAssoc($res)) {
			$return[] = $rec['crs_id'];
		}
		return $return;
	}

	protected function queryWhere()
	{
		$filter_courses_conditions = '';
		if ($this->settings['is_local']) {
			$sub_course_tpls = $this->filterTemplates($this->getSubtreeTypeIdsBelowParentType('crs', 'cat'));
			$filter_courses_conditions
				= '		AND '.$this->gIldb->in('crs.template_obj_id', $sub_course_tpls, false, 'integer');
		}
		$visible_users = array_unique($this->user_utils->getEmployeesWhereUserCanViewEduBios());
		return '	WHERE'.PHP_EOL
				.'		usr.hist_historic = 0'
				.'		AND '.$this->gIldb->in("usr.user_id", $visible_users, false, "integer")
				.'		AND usrcrs.booking_status = \'gebucht\''
				.'		AND usrcrs.participation_status IS NOT NULL'
				.'		AND '.$this->gIldb->in('usrcrs.participation_status', ['teilgenommen','nicht gesetzt'], false, 'text')
				.'		AND orgu_all.action >= 0'
				.'		AND orgu_all.hist_historic = 0'
				.'		AND role.action = 1'
				.'		AND role.hist_historic = 0'
				.'		'.$filter_courses_conditions
				.$this->participationStausFilter()
				.$this->wbdImportedFilter()
				.$this->templateTitleFilter()
				.$this->datePeriodFilter()
				;
	}

	private function participationStausFilter()
	{
		$selection = $this->filter_selections['p_status'];
		if (count($selection) > 0) {
			return '	AND '.$this->gIldb->in('usrcrs.participation_status', $selection, false, 'text');
		}
		return '';
	}

	private function templateTitleFilter()
	{
		$selection = $this->filter_selections['tpl_title'];
		if (count($selection) > 0) {
			return '	AND '.$this->gIldb->in('crs.template_title', $selection, false, 'text');
		}
		return '';
	}

	private function wbdImportedFilter()
	{
		if ($this->filter_selections['no_wbd']) {
			return '	AND crs.crs_id > 0';
		}
		return '';
	}

	private function datePeriodFilter()
	{
		if ($this->filter_selections['start'] !== null) {
			$start = $this->filter_selections['start']->format('Y-m-d');
		} else {
			$start = date('Y').'-01-01';
		}
		if ($this->filter_selections['end'] !== null) {
			$end = $this->filter_selections['end']->format('Y-m-d');
		} else {
			$end = date('Y').'-12-31';
		}
		return
			' AND ('
			.'		( '.$this->gIldb->in('usrcrs.end_date', ['-empty-','0000-00-00'], true, 'text').' AND usrcrs.end_date IS NOT NULL '
			.' 			AND `usrcrs`.`begin_date` <= '.$this->gIldb->quote($end, 'date')
			.'			AND `usrcrs`.`end_date` >= '.$this->gIldb->quote($start, 'date').')'
			.'	OR 	(	`usrcrs`.`begin_date` <= '.$this->gIldb->quote($end, 'date')
			.'			AND `usrcrs`.`begin_date` >= '.$this->gIldb->quote($start, 'date').')'
			.'	OR	(	('.$this->gIldb->in('usrcrs.end_date', ['-empty-','0000-00-00'], false, 'text').' OR usrcrs.end_date IS NULL )'
			.'			AND ('.$this->gIldb->in('usrcrs.begin_date', ['-empty-','0000-00-00'], false, 'text').' OR usrcrs.begin_date IS NULL ))'
			.')';
	}

	/**
	 * @inheritdoc
	 */
	protected function buildFilter($filter)
	{
		return null;
	}

	private function templateTitles()
	{
		$return = array();
		foreach (gevCourseUtils::getTemplateTitleFromHisto() as $title) {
			$return[$title] = $title;
		}
		return $return;
	}

	public function filter()
	{
		$pf = new \CaT\Filter\PredicateFactory();
		$tf = new \CaT\Filter\TypeFactory();
		$f = new \CaT\Filter\FilterFactory($pf, $tf);
		$txt = function ($id) {
			return $this->plugin->txt($id);
		};
		global $lng;
		return
			$f->sequence(
				$f->option(
					$lng->txt('gev_org_unit_recursive'),
					''
				)->clone_with_checked(true),
				$f->multiselect(
					$lng->txt("gev_org_unit_short"),
					'',
					$this->getRelevantOrgus()
				),
				$f->sequence(
					$f->dateperiod(
						$txt("period"),
						''
					),
					$f->option(
						$txt('filter_no_wbd_imported'),
						''
					),
					$f->multiselect(
						$txt("participation_status"),
						'',
						array(	'teilgenommen' => 'abgeschlossen'
								,'nicht gesetzt' => 'begonnen')
					)
				)
			)->map(
				function ($recursive, $orgus, $start, $end, $no_wbd, $p_status) {
						return array(
								'recursive' => $recursive
								,'orgus' => $orgus
								,'start' => $start
								,'end' => $end
								,'no_wbd' => $no_wbd
								,'p_status' => $p_status
							);
				},
				$tf->dict(
					array(
								'recursive' => $tf->bool()
								,'orgus' => $tf->lst($tf->int())
								,'start' => $tf->cls('DateTime')
								,'end' => $tf->cls('DateTime')
								,'no_wbd' => $tf->bool()
								,'p_status' => $tf->lst($tf->string())
							)
				)
			);
	}

	private function getRelevantOrgus()
	{
		$orgu_ids = array_unique(array_map(
			function ($ref_id) {
				return ilObject::_lookupObjectId($ref_id);
			},
			$this->user_utils->getOrgUnitsWhereUserCanViewEduBios()
		));
		$return = array();
		foreach ($orgu_ids as $orgu_id) {
			$return[$orgu_id] = ilObject::_lookupTitle($orgu_id);
		}
		return $return;
	}

	private function getTopics()
	{
		require_once 'Services/GEV/Utils/classes/class.gevAMDUtils.php';
		require_once 'Services/GEV/Utils/classes/class.gevSettings.php';
		return	gevAMDUtils::getInstance()->getOptions(gevSettings::CRS_AMD_TOPIC);
	}

	protected function getRowTemplateTitle()
	{
		return "tpl.gev_attendance_by_employee_row.html";
	}

	public function getRelevantParameters()
	{
		return $this->relevant_parameters;
	}
}
