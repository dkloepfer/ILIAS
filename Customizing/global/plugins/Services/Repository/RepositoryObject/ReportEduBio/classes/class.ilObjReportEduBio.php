<?php

require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.ilObjReportBase.php';
require_once 'Services/GEV/Utils/classes/class.gevUserUtils.php';
require_once 'Services/GEV/Utils/classes/class.gevSettings.php';
require_once 'Services/GEV/Utils/classes/class.gevCourseUtils.php';
require_once 'Services/UserCourseStatusHistorizing/classes/class.ilCertificateStorage.php';
require_once 'Services/User/classes/class.ilUserDefinedData.php';

ini_set("memory_limit", "2048M");
ini_set('max_execution_time', 0);
set_time_limit(0);

class ilObjReportEduBio extends ilObjReportBase
{
	protected $relevant_parameters = array();

	public function __construct($a_ref_id = 0)
	{
		parent::__construct($a_ref_id);
		$this->certificate_storage = new ilCertificateStorage();
		$self_id = $this->user_utils->getId();
		$target_user_id = $_POST["target_user_id"]
					  ? $_POST["target_user_id"]
					  : ( $_GET["target_user_id"]
					  	? $_GET["target_user_id"]
					  	: $self_id
					  	);
		$this->target_user_id = $target_user_id;
	}

	public function initType()
	{
		 $this->setType("xreb");
	}

	protected function createLocalReportSettings()
	{
		$this->local_report_settings =
			$this->s_f->reportSettings('rep_robj_reb');
	}

	public function prepareRelevantParameters()
	{
		$self_id = $this->user_utils->getId();
		if ($this->target_user_id != $self_id) {
			if (!in_array($this->target_user_id, $this->user_utils->getEmployeesWhereUserCanViewEduBios())) {
				ilUtil::sendFailure($this->plugin->txt('u_access_violation_show_self'));
				$this->target_user_id = $self_id;
			}
		}
		$this->addRelevantParameter("target_user_id", $this->target_user_id);
	}

	protected function buildTable($table)
	{
		$table	->column("title", $this->plugin->txt("title"), true)
				->column("type", $this->plugin->txt("learning_type"), true)
				->column("date", $this->plugin->txt("date"), true, "112px", true)
				->column("venue", $this->plugin->txt("location"), true)
				->column("provider", $this->plugin->txt("provider"), true)
				->column("tutor", $this->plugin->txt("crs_tutor"), true)
				->column("fee", $this->plugin->txt("fee"), true)
				->column("status", $this->plugin->txt("status"), true)
				->column("wb_time", $this->plugin->txt("wb_time"), true, "40px")
				->column("action", '<img src="'.ilUtil::getImagePath("gev_action.png").'" />', true, "", true, false);
		return parent::buildTable($table);
	}

	protected function getRowTemplateTitle()
	{
		return "tpl.gev_edu_bio_row.html";
	}

	protected function buildOrder($order)
	{
		$order	->defaultOrder('date', 'asc')
				->mapping('date', array('usrcrs.begin_date'))
				->mapping('status', array("usrcrs.participation_status"))
				->mapping('wbd', array("usrcrs.okz"));
				return $order;
	}

	protected function buildFilter($filter)
	{
		$filter	->dateperiod("period", $this->plugin->txt("period"), $this->plugin->txt("until"), "usrcrs.begin_date", "usrcrs.end_date", date("Y")."-01-01", date("Y")."-12-31")
				->static_condition("usr.user_id = ".$this->gIldb->quote($this->target_user_id, "integer"))
				->static_condition("usrcrs.hist_historic = 0")
				->static_condition('(crs.is_cancelled IS NULL OR crs.is_cancelled = '.$this->gIldb->quote('Nein', 'text').')')
				->static_condition($this->gIldb
										->in("usrcrs.booking_status", array( "gebucht", "kostenpflichtig storniert"), false, "text"))
				->static_condition("(crs.crs_id < 0 OR oref.deleted IS NULL)")
				->static_condition("crs.type != ".$this->gIldb->quote(gevCourseUtils::CRS_TYPE_COACHING, "text"))
				->action($this->filter_action)
				->compile();
		return $filter;
	}

	protected function buildQuery($query)
	{
		$one_year_befone_now  = (new DateTime())->sub(new DateInterval('P1Y'))->format('Y-m-d');
		$query ->select("crs.title")
				->select("crs.type")
				->select('crs.crs_id')
				->select("usrcrs.begin_date")
				->select("usrcrs.end_date")
				->select("crs.venue")
				->select("crs.provider")
				->select_raw("GROUP_CONCAT(DISTINCT tutors.usr_id SEPARATOR ';') AS tutor")
				->select_raw("IF(usrcrs.credit_points < 0, 0, usrcrs.credit_points) AS credit_points")
				->select("crs.fee")
				->select("usrcrs.participation_status")
				->select("usrcrs.okz")
				->select("usrcrs.bill_id")
				->select("usrcrs.booking_status")
				->select("usrcrs.certificate_filename")
				->select("oref.ref_id")
				->from("hist_usercoursestatus usrcrs")
				->join("hist_user usr")
					->on("usr.user_id = usrcrs.usr_id AND usr.hist_historic = 0")
				->join("hist_course crs")
					->on("crs.crs_id = usrcrs.crs_id AND crs.hist_historic = 0")
				->left_join("object_reference oref")
					->on("crs.crs_id = oref.obj_id")
				->left_join('hist_usercoursestatus tutors')
					->on('tutors.crs_id = usrcrs.crs_id AND tutors.function = \'Trainer\''
						.'	AND tutors.hist_historic = 0')
				->group_by("usrcrs.crs_id")
				->compile();
		return $query;
	}

	public function prepareReport()
	{
		parent::prepareReport();
		$this->target_user_utils = gevUserUtils::getInstance($this->target_user_id);
		$this->dates = $this->getIDDAffectedDates();
		$this->points = $this->getPoints();
	}

	public function getIDDAffectedDates()
	{
		$dates = array();
		$settings = gevSettings::getInstance();
		$start_id = $settings->get(gevSettings::USR_UDF_IDD_AFFECTED_START);
		$end_id = $settings->get(gevSettings::USR_UDF_IDD_AFFECTED_END);
		$id = $this->target_user_id;

		$dat = \ilUserDefinedData::lookupData(
			array($id),
			array($start_id, $end_id)
		);

		$dates['idd_affected_start'] = "-";
		$dates['idd_affected_end'] = "-";

		if (count($dat) > 0) {
			$start_date = $dat[$id][$start_id];
			$end_date = $dat[$id][$end_id];
			$dates['idd_affected_start'] = (new DateTime($start_date))->format('d.m.Y');
			$dates['idd_affected_end'] = (new DateTime($end_date))->format('d.m.Y');
		}

		return $dates;
	}

	/**
	 * Get hist points and comming points from booked courses.
	 *
	 * @return array
	 */
	protected function getPoints()
	{
		$points = array();
		$period = $this->filter->get("period");
		$points['sum_hist_points'] = $this->getSumPoints($period["start"], $period["end"]);
		return $points;
	}

	/**
	 * Get the sum of allready collected points.
	 *
	 * @return int
	 */
	protected function getSumPoints($start, $end)
	{
		$query = "SELECT SUM(usrcrs.credit_points) sum "
				."	FROM hist_usercoursestatus usrcrs "
				."	JOIN hist_course crs"
				."		ON crs.crs_id = usrcrs.crs_id "
				."	WHERE usrcrs.hist_historic = 0 "
				."		AND usrcrs.usr_id = ".$this->gIldb->quote($this->target_user_id, 'integer')
				."		AND crs.hist_historic = 0 "
				."		AND usrcrs.participation_status = ".$this->gIldb->quote('teilgenommen', 'text')
				."		AND ".$this->gIldb->in('usrcrs.function', array('Mitglied', 'Teilnehmer'), false, 'text')
				."		AND usrcrs.booking_status = ".$this->gIldb->quote('gebucht', 'text')
				."		AND usrcrs.credit_points > 0 "
				."		AND ( usrcrs.end_date >= ".$this->gIldb->quote($start->get(IL_CAL_DATE), "date")
				."			OR usrcrs.end_date = '0000-00-00')"
				."		AND usrcrs.begin_date <= ".$this->gIldb->quote($end->get(IL_CAL_DATE), "date")
				."		AND ((".$this->gIldb->in("crs.type", array('Selbstlernkurs'), false, 'text')
				."				AND usrcrs.begin_date > ".$this->gIldb->quote('2013-01-01', 'date').")"
				."			OR (".$this->gIldb->in("crs.type", array('Selbstlernkurs'), true, 'text')
				."				AND usrcrs.end_date > ".$this->gIldb->quote('2013-01-01', 'date')."))";

		$result = $this->gIldb->query($query);
		if($this->gIldb->numRows($result) == 0) {
			return 0;
		}
		$row = $this->gIldb->fetchAssoc($result);
		return $row['sum'];
	}

	public function validateBill($bill_id)
	{
		$res = $this->gIldb->query("SELECT crs_id"
								."  FROM hist_usercoursestatus "
								." WHERE usr_id = ".$this->gIldb->quote($this->target_user_id, "integer")
								."   AND bill_id = ".$this->gIldb->quote($bill_id, "text")
								."   AND hist_historic = 0");
		return $this->gIldb->numRows($res) == 1;
	}

	public function validateCertificate($crs_id, $usr_id, $cert_name)
	{
		$res = $this->gIldb->query('SELECT COUNT(*) cnt'
						.'	FROM hist_usercoursestatus '
						.'	WHERE usr_id = '.$this->gIldb->quote($usr_id, 'integer')
						.'		AND crs_id = '.$this->gIldb->quote($crs_id, 'integer')
						.'		AND certificate_filename = '.$this->gIldb->quote($cert_name, 'text')
						.'		AND (certificate_hash != '.$this->gIldb->quote('-empty-', 'text')
						.'			AND certificate_hash IS NOT NULL)');
		if ($this->gIldb->fetchAssoc($res)['cnt'] == 0) {
			return false;
		}
		return true;
	}

	public function deliverCertificate($cert_name)
	{
		return $this->certificate_storage->deliverCertificate($cert_name);
	}

	/**
	 *	Deivers the link to a users edubio.
	 *	We aussume for now that there is exactly one edu bio in the whole academy.
	 *
	 *	@param	int|null	$usr_id	if null, edubio points to calling user.
	 *	@return string	$return	link to a users edubio or an empty string,
	 *							if no edubio object is in the repository.
	 */
	public static function getEduBioLinkFor($usr_id = null)
	{
		global $ilCtrl, $ilUser, $ilAccess;

		$user = $ilUser->getId();
		//note: in the next line we assume that there is exactly one edu bio in the repository.
		$ref_id = current(ilObject::_getAllReferences(current(ilObject::_getObjectsDataForType('xreb', true))["id"]));
		if ($ref_id && $ilAccess->checkAccessOfUser($user, "read", "", $ref_id)) {
			$ilCtrl->setParameterByClass("ilObjReportEduBioGUI", "target_user_id", $usr_id);
			$ilCtrl->setParameterByClass("ilObjReportEduBioGUI", "ref_id", $ref_id);
			$return = $ilCtrl->getLinkTargetByClass(array("ilObjPluginDispatchGUI", "ilObjReportEduBioGUI"), '');
			$ilCtrl->setParameterByClass("ilObjReportEduBioGUI", "target_user_id", null);
			$ilCtrl->setParameterByClass("ilObjReportEduBioGUI", "ref_id", null);
			return $return;
		}
		return "";
	}

	public function getWBD()
	{
		if (!$this->wbd) {
			$this->wbd = gevWBD::getInstance($this->target_user_id);
		}
		return $this->wbd;
	}
}
