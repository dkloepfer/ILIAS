<?php


class ilStudyProgrammeQuotaReportTableGUI extends ilTable2GUI
{
	public function __construct(
		$parent_obj,
		$parent_cmd,
		ilStudyProgrammeQuotaReport $report
	) {
		$this->report = $report;
		parent::__construct($parent_obj, $parent_cmd);

		$this->setRowTemplate('tpl.quota_report_row.html', 'Modules/StudyProgramme');
		$this->initColumns($this->report);
		$this->setData($this->report->getData());
		

	}

	protected function initColumns(ilStudyProgrammeQuotaReport $report) {
		foreach ($report->fields() as $id => $lang_var) {
			$this->addColumn(
				$this->lng->txt($lang_var),
				$id
			);
		}
	}

	protected function fillRow($a_set) {
		foreach ($this->report->fields() as $id => $lang_var) {
			$this->tpl->setVariable(strtoupper($id), $a_set[$id]);
		}
		$this->tpl->setVariable("PRG_LINK", ilLink::_getStaticLink($a_set["ref_id"],"prg"));
	}
}