<?php
require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.ilObjReportBase.php';
require_once 'Services/GEV/Utils/classes/class.gevSettings.php';

ini_set("memory_limit", "2048M");
ini_set('max_execution_time', 0);
class ilObjReportEmplEduBios extends ilObjReportBase
{

	protected $relevant_parameters = array();
	const EARLIEST_CERT_START = "2013-09-01";

	public function initType()
	{
		 $this->setType("xeeb");
	}

	public function __construct($ref_id = 0)
	{
		parent::__construct($ref_id);
		global $lng;
		$this->gLng = $lng;
	}

	protected function createLocalReportSettings()
	{
		$this->local_report_settings =
			$this->s_f->reportSettings('rep_robj_reeb')
				->addSetting($this->s_f
								->settingBool('truncate_orgu_filter', $this->plugin->txt('truncate_orgu_filter')));
	}

	protected function getRoleIdsForRoleTitles(array $titles)
	{
		$query = 'SELECT obj_id FROM object_data '
				.'	WHERE '.$this->gIldb->in('title', $titles, false, 'text')
				.'		AND type = '.$this->gIldb->quote('role', 'text');
		$res = $this->gIldb->query($query);
		$return = array();
		while ($rec = $this->gIldb->fetchAssoc($res)) {
			$return[] = $rec['obj_id'];
		}
		return $return;
	}

	protected function getWbdRelevantRoleIds()
	{
		return $this->getRoleIdsForRoleTitles(gevWBD::$wbd_relevant_roles);
	}

	protected function getTpServiceRoleIds()
	{
		return $this->getRoleIdsForRoleTitles(gevWBD::$wbd_tp_service_roles);
	}

	public function buildQueryStatement()
	{
		$settings = gevSettings::getInstance();
		$sap_personal_number = $settings->get(gevSettings::USR_UDF_PERSONAL_ID);
		$idd_affected_start = $settings->get(gevSettings::USR_UDF_IDD_AFFECTED_START);
		$idd_affected_end = $settings->get(gevSettings::USR_UDF_IDD_AFFECTED_END);
		$location_ma = $settings->get(gevSettings::USR_UDF_LOCATION_MA);

		$query =
			'SELECT'
			.'	usr.user_id'
			.'	,usr.lastname'
			.'	,usr.firstname'
			.'	,usrd.login'
			.'	,usr.adp_number'
			.'	,usr.job_number'
			.'	,orgu_all.org_unit'
			.'	,orgu_all.org_unit_above1'
			.'	,orgu_all.org_unit_above2'
			.'	,usr.begin_of_certification'
			.'	,sap_personal_number.value AS sap_personal_number'
			.'	,location_ma.value AS location_ma'
			.'	,idd_affected_start.value AS idd_affected_start'
			.'	,idd_affected_end.value AS idd_affected_end'
			.'	,SUM(IF(usrcrs.participation_status = '.$this->gIldb->quote('teilgenommen', "text")
			.'     ,usrcrs.credit_points,0)) AS cp_passed'
			.'	FROM hist_user usr'
			.'	JOIN usr_data usrd'
			.'		ON usr.user_id = usrd.usr_id'
			.'	JOIN ('.$this->allOrgusOfUser().') AS orgu_all'
			.'		ON orgu_all.usr_id = usr.user_id'
			.'	LEFT JOIN hist_usercoursestatus as usrcrs'
			.'		ON usr.user_id = usrcrs.usr_id'
			.'			AND usrcrs.hist_historic = 0'
			// removed on wish of mr drewel and ordered by CG,
			// rentered on wish from Hr. Drewel and VR
			// Ticket gev_3741
			.'			AND usrcrs.credit_points > 0'
			.'			AND usrcrs.booking_status = \'gebucht\''
			.'			'.$this->filterWBDImported()
			.'	LEFT JOIN udf_text AS sap_personal_number'
			.'		ON sap_personal_number.usr_id = usr.user_id AND sap_personal_number.field_id = '.$sap_personal_number
			.'	LEFT JOIN udf_text AS idd_affected_start'
			.'		ON idd_affected_start.usr_id = usr.user_id AND idd_affected_start.field_id = '.$idd_affected_start
			.'	LEFT JOIN udf_text AS idd_affected_end'
			.'		ON idd_affected_end.usr_id = usr.user_id AND idd_affected_end.field_id = '.$idd_affected_end
			.'	LEFT JOIN udf_text AS location_ma'
			.'		ON location_ma.usr_id = usr.user_id AND location_ma.field_id = '.$location_ma
			.$this->whereConditions();

		$query .= '	GROUP BY usr.user_id'

					.'	'.$this->queryOrder();
		return $query;
	}

	private function whereConditions()
	{
		$where =
			'	WHERE '.$this->gIldb->in('usr.user_id', $this->relevant_users, false, 'integer')
			.' 		AND usr.hist_historic = 0';
		$where = $this->possiblyAddLastnameCondition($where);
		$where = $this->possiblyAddYearCondition($where);
		return $where;
	}

	private function possiblyAddYearCondition($where)
	{
		$selection  = $this->filter_selections['year'];
		if(is_null($selection) || empty($selection)) {
			$selection = date("Y");
		}
		$start = $selection."-01-01";
		$end = ++$selection."-01-01";

		$where.= "	AND((usrcrs.begin_date >= ".$this->gIldb->quote($start, "text").PHP_EOL
				."		 AND usrcrs.begin_date < ".$this->gIldb->quote($end, "text").")".PHP_EOL
				."		OR (usrcrs.end_date >= ".$this->gIldb->quote($start, "text").PHP_EOL
				."		 AND usrcrs.end_date < ".$this->gIldb->quote($end, "text")."))".PHP_EOL
				;
		return $where;
	}

	private function possiblyAddLastnameCondition($where)
	{
		$input = $this->filter_selections['lastname'];
		if (is_string($input) && $input !== '') {
			return $where.'		AND usr.lastname LIKE '.$this->gIldb->quote($input.'%', 'text');
		}
		return $where;
	}

	private function filterWBDImported()
	{
		$selection = $this->filter_selections['no_wbd_imported'];
		if ($selection) {
			return 'AND usrcrs.crs_id > 0';
		}
		return '';
	}

	private function allOrgusOfUser()
	{
		$selection = $this->filter_selections['orgu_selection'];
		if ($selection === null) {
			if ($this->get) {
			}
		}
		return
			'SELECT usr_id, GROUP_CONCAT(DISTINCT orgu_title SEPARATOR \', \') as org_unit'.PHP_EOL
			.'		, GROUP_CONCAT(DISTINCT org_unit_above1 SEPARATOR \';;\') as org_unit_above1'.PHP_EOL
			.'		, GROUP_CONCAT(DISTINCT org_unit_above1 SEPARATOR \';;\') as org_unit_above2'.PHP_EOL
			.'	FROM hist_userorgu'.PHP_EOL
			.'	WHERE '.$this->gIldb->in("usr_id", $this->relevant_users, false, "integer").PHP_EOL
			.'		AND action >= 0 AND hist_historic = 0'.PHP_EOL
			.'	GROUP BY usr_id'.PHP_EOL;
	}

	protected function getFilterSettings()
	{
		$filter = $this->filter();
		if ($this->filter_settings) {
			$settings = call_user_func_array(array($filter, "content"), $this->filter_settings);
		}
		return $settings;
	}

	protected function buildQuery($query)
	{
		$this->filter_selections = $this->getFilterSettings();
		if ($this->filter_selections['orgu_selection'] !== null) {
			$this->relevant_users
				= $this->getRelevantUsersByOrguSelection(
					$this->filter_selections['orgu_selection'],
					$this->filter_selections['recursive']
				);
		} else {
			$this->relevant_users = $this->getRelevantUsersByOrguSelection(
				$this->defaultOrguChoice()
			);
		}
		return $query;
	}



	protected function buildFilter($filter)
	{
		return $filter;
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
		$self = $this;

		return
			$f->sequence(
				$f->multiselect(
					$lng->txt("gev_org_unit_short"),
					'',
					$this->getRelevantOrgus()
				)->default_choice($this->defaultOrguChoice()),
				$f->text(
					$txt('lastname_filter'),
					''
				),
				$f->singleselect(
					$txt('filter_year'),
					'',
					$this->yearOptions()
				)->default_choice((int)date('Y'))
			)->map(function ($orgu_selection, $lastname, $year) use ($self) {
								return array(
									'orgu_selection' => $orgu_selection,
									'lastname' => $lastname,
									'year' => $year
								);
			}, $tf->dict(
				array(
					'orgu_selection' => $tf->lst($tf->int()),
					'lastname' => $tf->string(),
					'year' => $tf->int()
				)
			));
	}

	private function yearOptions()
	{
		$current = (int)date('Y');
		$return = [];
		for ($i = $current - 10; $i < $current + 4; $i++) {
			$return[$i] = (string)$i;
		}
		return $return;
	}

	private function getRelevantOrgus()
	{
		$orgu_refs = $this->user_utils->getOrgUnitsWhereUserCanViewEduBios();
		require_once "Services/GEV/Utils/classes/class.gevObjectUtils.php";
		$return = array();
		foreach ($orgu_refs as $ref_id) {
			$obj_id = gevObjectUtils::getObjId($ref_id);
			$return[$obj_id] = ilObject::_lookupTitle($obj_id);
		}
		return $return;

		//only truncate orgu filter settings if set
	}

	private function defaultOrguChoice()
	{
		if ((bool)$this->getSettingsDataFor("truncate_orgu_filter")) {
			return array_map(function ($v) {
				return $v["obj_id"];
			}, $this->user_utils->getOrgUnitsWhereUserIsDirectSuperior());
		}
		return array();
	}

	private function getRelevantUsersByOrguSelection(array $orgu_ids = array(), $recursive = false)
	{

		if (count($orgu_ids) > 0) {
			if ($recursive) {
				$orgu_ids = $this->addRecursiveOrgusToSelection($orgu_ids);
			}
			$query = 'SELECT usr_id FROM hist_userorgu'
					.'	WHERE hist_historic = 0 AND action >=0'
					.'		AND '.$this->gIldb->in('orgu_id', $orgu_ids, false, 'integer')
					.'	GROUP BY usr_id';
			$res = $this->gIldb->query($query);
			$aux = array();
			while ($rec = $this->gIldb->fetchAssoc($res)) {
				$aux[] = $rec['usr_id'];
			}
			return array_intersect($this->visibleUsers(), $aux);
		}
		return $this->visibleUsers();
	}

	private function addRecursiveOrgusToSelection(array $orgu_ids)
	{
		require_once 'Services/GEV/Utils/classes/class.gevOrgUnitUtils.php';
		$aux = $orgu_ids;
		foreach ($orgu_ids as $orgu_id) {
			$ref_id = gevObjectUtils::getRefId($orgu_id);
			$aux[] = $orgu_id;
			foreach (gevOrgUnitUtils::getAllChildren(array($ref_id)) as $child) {
				$aux[] = $child["obj_id"];
			}
		}
		return array_unique($aux);
	}

	private function visibleUsers()
	{
		return $this->user_utils->getEmployeesWhereUserCanViewEduBios();
	}

	protected function buildTable($table)
	{
		$table
						->column("lastname", $this->plugin->txt("lastname"), true)
						->column("firstname", $this->plugin->txt("firstname"), true)
						->column("login", $this->plugin->txt("login"), true)
						->column("cp_passed", $this->txt("cp_passed"), true)
						->column("sap_personal_number", $this->txt("sap_personal_number"), true)
						->column("adp_number", $this->plugin->txt("adp_number"), true)
						->column("job_number", $this->plugin->txt("job_number"), true)
						->column("od_bd", $this->plugin->txt("od_bd"), true, "", false, false)
						->column("org_unit", $this->plugin->txt("orgu_short"), true)
						->column("lacation_ma", $this->plugin->txt("location_ma"), true)
						->column("roles", $this->plugin->txt("roles"), true)
						->column("idd_affected_start", $this->plugin->txt("idd_affected_start"), true)
						->column("idd_affected_end", $this->plugin->txt("idd_affected_end"), true);
		return parent::buildTable($table);
	}

	public function buildOrder($order)
	{
		$order->mapping("date", "crs.begin_date")
				->mapping("od_bd", array("org_unit_above1", "org_unit_above2"))
				->defaultOrder("lastname", "ASC")
				;
		return $order;
	}

	protected function getRowTemplateTitle()
	{
		return "tpl.gev_employee_edu_bios_row.html";
	}


	public function getRelevantParameters()
	{
		return $this->relevant_parameters;
	}
}
