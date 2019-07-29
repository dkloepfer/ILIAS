<?php

class ilStudyProgrammeQuotaReportGUI
{
	use ilStudyProgrammeReportsAccessChecking;

	public function __construct(
		ilTemplate $tpl,
		ilCtrl $ctrl,
		ilAccess $access,
		ilLanguage $lng,
		ilStudyProgrammeQuotaReport $report
	)
	{
		$this->tpl = $tpl;
		$this->ctrl = $ctrl;
		$this->access = $access;
		$this->lng = $lng;
		$this->report = $report;
	}

	const CMD_SHOW = 'cmd_show';

	public function executeCommand() {
		$this->checkAccess($this->access);
		$cmd = $this->ctrl->getCmd();
		switch ($cmd) {
			case self::CMD_SHOW:
				$this->showReport();
				break;
			default:
				throw new ilException("unknown command ".$cmd);
		}
	}

	protected function getTable() : ilTable2GUI
	{
		return new ilStudyProgrammeQuotaReportTableGUI(
			$this,
			self::CMD_SHOW,
			$this->report
		);
	}

	protected function showReport()
	{
		$this->tpl->setContent($this->getTable()->getHTML());
	}
}