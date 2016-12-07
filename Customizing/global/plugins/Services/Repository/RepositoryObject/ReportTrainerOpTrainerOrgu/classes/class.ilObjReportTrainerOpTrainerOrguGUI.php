<?php

require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.ilObjReportBaseGUI.php';
/**
* User Interface class for example repository object.
* ...
* @ilCtrl_isCalledBy ilObjReportTrainerOpTrainerOrguGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
* @ilCtrl_Calls ilObjReportTrainerOpTrainerOrguGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI
* @ilCtrl_Calls ilObjReportTrainerOpTrainerOrguGUI: ilCommonActionDispatcherGUI
*/
class ilObjReportTrainerOpTrainerOrguGUI extends ilObjReportBaseGUI {
	const CMD_SHOW_CONTENT = "showContent";

	public function getType() {
		return 'xoto';
	}

	protected function afterConstructor() {
		parent::afterConstructor();
		if($this->object->plugin) {
			$this->tpl->addCSS($this->object->plugin->getStylesheetLocation('css/report.css'));
		}

		if($this->object) {
			$this->filter = $this->object->filter();
			$this->display = new \CaT\Filter\DisplayFilter
						( new \CaT\Filter\FilterGUIFactory
						, new \CaT\Filter\TypeFactory
						);
		}
		$this->loadFilterSettings();
	}

	protected function loadFilterSettings() {
		if(isset($_POST['filter'])) {
			$this->filter_settings = $_POST['filter'];
		}
		if(isset($_GET['filter'])) {
			$this->filter_settings = unserialize(base64_decode($_GET['filter']));
		}
		if($this->filter_settings) {
			$this->object->filter_settings = $this->display->buildFilterValues($this->filter, $this->filter_settings);
		}
	}

	protected function render() {
		$res = $this->renderFilter()."<br />";
		$res .= $this->renderTable();
		return $res;
	}

	protected function renderFilter() {
		require_once("Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.catFilterFlatViewGUI.php");
		$filter_flat_view = new catFilterFlatViewGUI($this, $this->filter, $this->display, self::CMD_SHOW_CONTENT);
		return $filter_flat_view->render($this->filter_settings);
	}

	protected function renderTable() {
		$this->gCtrl->setParameter($this, 'filter', base64_encode(serialize($this->filter_settings)));
		$table = parent::renderTable();
		$this->gCtrl->setParameter($this, 'filter', null);
		return $sum_table.$table;
	}

	protected function prepareTitle($a_title) {
		$a_title = parent::prepareTitle($a_title);
		$a_title->image("GEV_img/ico-head-edubio.png");
		return $a_title;
	}

	public static function transformResultRow($rec) {
		return $rec;
	}

	public static function transformResultRowXLSX($rec) {
		$rec["title"] =  preg_replace("#&[a-z0-9]{2,8};#i","",strip_tags($rec['title']));
		return $rec;
	}
}