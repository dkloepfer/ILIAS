<?php

require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.ilObjReportBaseGUI.php';
require_once 'Services/GEV/Utils/classes/class.gevCourseUtils.php';
/**
* User Interface class for example repository object.
* ...
* @ilCtrl_isCalledBy ilObjReportEmplAtt34iGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
* @ilCtrl_Calls ilObjReportEmplAtt34iGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI,
* @ilCtrl_Calls ilObjReportEmplAtt34iGUI: ilCommonActionDispatcherGUI
*/
class ilObjReportEmplAtt34iGUI extends ilObjReportBaseGUI
{

	public static $od_regexp;
	public static $bd_regexp;

	public function getType()
	{
		return 'x34i';
	}

	protected function render()
	{
		$this->gTpl->setTitle(null);
		return 	$this->title->render()
				.$this->renderFilter()
				.($this->spacer !== null ? $this->spacer->render() : "")
				.$this->renderTable();
	}

	public function renderQueryView()
	{
		include_once "Services/Form/classes/class.ilNonEditableValueGUI.php";
		$this->object->prepareReport();
		$content = $this->renderFilter('query_view');

		$form = new ilNonEditableValueGUI($this->gLng->txt("report_query_text"));
		$form->setValue($this->object->buildQueryStatement());

		$settings_form = new ilPropertyFormGUI();
		$settings_form->addItem($form);
		$content .= $settings_form->getHTML();
		$this->gTpl->setContent($content);
	}

	protected function renderFilter()
	{
		global $ilCtrl;
		require_once("Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.catFilterFlatViewGUI.php");
		$filter_flat_view = new catFilterFlatViewGUI($this, $this->filter, $this->display, $ilCtrl->getCmd());
		return $filter_flat_view->render($this->filter_settings, (string)$_POST['filtered'] === '1');
	}

	protected function prepareTitle($a_title)
	{
		$a_title = parent::prepareTitle($a_title);
		$a_title->image("GEV_img/ico-head-edubio.png");
		return $a_title;
	}

	protected function afterConstructor()
	{
		parent::afterConstructor();
		if ($this->object->plugin) {
			$this->tpl->addCSS($this->object->plugin->getStylesheetLocation('report.css'));
		}

		if ($this->object) {
			$this->filter = $this->object->filter();
			$this->display = new \CaT\Filter\DisplayFilter(
				new \CaT\Filter\FilterGUIFactory,
				new \CaT\Filter\TypeFactory
			);
		}
		$this->loadFilterSettings();
	}

	protected function loadFilterSettings()
	{
		if (isset($_POST['filter'])) {
			$this->filter_settings = $_POST['filter'];
		}
		if (isset($_GET['filter'])) {
			$this->filter_settings = unserialize(base64_decode($_GET['filter']));
		}
		if ($this->filter_settings) {
			$this->object->addRelevantParameter('filter', base64_encode(serialize($this->filter_settings)));
			$this->object->filter_settings = $this->display->buildFilterValues($this->filter, $this->filter_settings);
		}
	}

	public static function transformResultRow($rec)
	{
		//date
		if ($rec["begin_date"] && $rec["end_date"]
			&& ($rec["begin_date"] != '0000-00-00' && $rec["end_date"] != '0000-00-00' )
			) {
			$start = new ilDate($rec["begin_date"], IL_CAL_DATE);
			$end = new ilDate($rec["end_date"], IL_CAL_DATE);
			$date = '<nobr>' .ilDatePresentation::formatPeriod($start, $end) .'</nobr>';
		} elseif ($rec["begin_date"] && $rec["begin_date"] != "0000-00-00") {
			$start = new ilDate($rec["begin_date"], IL_CAL_DATE);
			$date = '<nobr>'.ilDatePresentation::formatDate($start).'</nobr>';
		} else {
			$date = '-';
		}
		$rec['date'] = $date;

		return self::transformResultRowCommon($rec);
	}

	public static function transformResultRowXLSX($rec)
	{
		//date
		if ($rec["begin_date"] && $rec["end_date"]
			&& ($rec["begin_date"] != '0000-00-00' && $rec["end_date"] != '0000-00-00' )
			) {
			$start = new ilDate($rec["begin_date"], IL_CAL_DATE);
			$end = new ilDate($rec["end_date"], IL_CAL_DATE);
			$date = ilDatePresentation::formatPeriod($start, $end);
		} elseif ($rec["begin_date"] && $rec["begin_date"] != "0000-00-00") {
			$start = new ilDate($rec["begin_date"], IL_CAL_DATE);
			$date = ilDatePresentation::formatDate($start);
		} else {
			$date = '-';
		}
		$rec['date'] = $date;

		return self::transformResultRowCommon($rec);
	}

	protected static function transformResultRowCommon($rec)
	{
		global $lng;
		// credit_points

		if ($rec["credit_points"] == -1) {
			$rec["credit_points"] = $lng->txt("gev_table_no_entry");
		} else {
			$rec["credit_points"] = gevCourseUtils::convertCreditpointsToFormattedDuration((int)$rec["credit_points"]);
		}

		// od_bd
		if (!self::$od_regexp || !self::$bd_regexp) {
			require_once './Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/config/od_bd_strings.php';
		}
		$orgu_above1 =  $rec['org_unit_above1'];
		$orgu_above2 =  $rec['org_unit_above2'];
		if (preg_match(self::$od_regexp, $orgu_above1)) {
			$od = $orgu_above1;
		} elseif (preg_match(self::$od_regexp, $orgu_above2)) {
			$od = $orgu_above2;
		} else {
			$od = '-';
		}
		if (preg_match(self::$bd_regexp, $orgu_above1)) {
			$bd = $orgu_above1;
		} elseif (preg_match(self::$bd_regexp, $orgu_above2)) {
			$bd = $orgu_above2;
		} else {
			$bd = '-';
		}
		$rec['od_bd'] = $od .'/' .$bd;

		switch ($rec['participation_status']) {
			case 'nicht gesetzt':
				$rec['participation_status'] = 'begonnen';
				break;
			case 'teilgenommen':
				$rec['participation_status'] = 'abgeschlossen';
				break;
		}

		if ($rec["idd_affected_start"] != null && $rec["idd_affected_start"] != "") {
			$idd_affected_start = new ilDate($rec["idd_affected_start"], IL_CAL_DATE);
			$rec["idd_affected_start"] = ilDatePresentation::formatDate($idd_affected_start);
		}

		if ($rec["idd_affected_end"] != null && $rec["idd_affected_end"] != "") {
			$idd_affected_end = new ilDate($rec["idd_affected_end"], IL_CAL_DATE);
			$rec["idd_affected_end"] = ilDatePresentation::formatDate($idd_affected_end);
		}

		return parent::transformResultRow($rec);
	}
}
