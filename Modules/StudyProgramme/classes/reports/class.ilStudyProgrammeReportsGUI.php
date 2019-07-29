<?php

/**
 * @ilCtrl_Calls ilStudyProgrammeReportsGUI: ilStudyProgrammeQuotaReportGUI
 */
class ilStudyProgrammeReportsGUI
{
	use ilStudyProgrammeReportsAccessChecking;

	const CMD_SHOW = "cmd_show";
	const SUBTAB_QUOTA_REPORT = "quota_report";

	protected $ctrl;
	protected $quota_report;
	protected $admin_gui;

	public function __construct(
		ilCtrl $ctrl,
		ilAccess $access,
		ilLanguage $lng,
		ilTabsGUI $tabs,
		ilStudyProgrammeQuotaReportGUI $quota_report
	)
	{
		$this->ctrl = $ctrl;
		$this->access = $access;
		$this->lng = $lng;
		$this->tabs = $tabs;
		$this->quota_report = $quota_report;
	}

	public function executeCommand() {
		$this->checkAccess($this->access);
		$this->subTabs();
		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();
		switch ($next_class) {
			case 'ilstudyprogrammequotareportgui':
				$this->ctrl->forwardCommand($this->quota_report->withAdminGUI($this->adminGUI()));
				break;
			default:
				switch($cmd) {
					case self::CMD_SHOW:
						$this->redirectQuotaReport();
						break;
					default:
						throw new ilException("unknown command ".$cmd);
				}

		}
	}

	protected function quotaReport()
	{
		$this->tabs->setSubTabActive(self::SUBTAB_QUOTA_REPORT);
		$this->ctrl->forwardCommand($this->quota_report);
	}

	protected function redirectQuotaReport()
	{
		$this->ctrl->redirectByClass(
			["ilstudyprogrammereportsgui", "ilstudyprogrammequotareportgui"]
			,ilStudyProgrammeQuotaReportGUI::CMD_SHOW
		);
	}

	protected function subTabs() {
		$this->tabs->addSubTab(
			self::SUBTAB_QUOTA_REPORT,
			$this->lng->txt('quota_report'),
			$this->ctrl->getLinkTarget(
				$this->quota_report,
				ilStudyProgrammeQuotaReportGUI::CMD_SHOW
			)
		);
	}
}