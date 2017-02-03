<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once("Services/CaTUIComponents/classes/class.catTableGUI.php");

/**
 * Shows study programme and course of members VA Pass as a table
 *
 * @author Stefan Hecken 	<stefan.hecken@concepts-and-training.de>
 */
class gevMyVAPassTableGUI extends catTableGUI
{
	/**
	 * @var ilLanguage
	 */
	protected $g_lng;

	/**
	 * @var ilCtrl;
	 */
	protected $g_ctrl;

	public function __construct($a_parent_obj, $sp_base_node_id, $user_id, $assignment_id, $a_parent_cmd = "", $a_template_context = "")
	{
		$this->setID("va_pass_member");

		parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);

		global $lng, $ilCtrl;

		$this->g_lng = $lng;
		$this->g_ctrl = $ilCtrl;
		$this->user_id = $user_id;
		$this->assignment_id = $assignment_id;

		$this->success = '<img src="'.ilUtil::getImagePath("gev_va_pass_success_icon.png").'" />';
		$this->in_progress = '<img src="'.ilUtil::getImagePath("gev_va_pass_progress_icon.png").'" />';
		$this->faild = '<img src="'.ilUtil::getImagePath("gev_va_pass_failed_icon.png").'" />';
		$this->not_attemped = '<img src="'.ilUtil::getImagePath("gev_va_pass_not_attemped_icon.png").'" />';

		$this->confugireTable();
		$this->addColums();

		require_once("Modules/StudyProgramme/classes/class.ilObjStudyProgramme.php");
		require_once("Modules/StudyProgramme/classes/class.ilStudyProgrammeUserProgress.php");
		$root_sp = new ilObjStudyProgramme($sp_base_node_id);

		$this->setTitle($root_sp->getTitle());
		$this->setSubtitle($root_sp->getDescription());

		$entries = array();
		$children = $root_sp->getChildren();
		foreach ($children as $current_child_key => $child) {
			if (!$this->isRelevant($assignment_id, $child->getId(), $this->user_id)) {
				continue;
			}

			require_once("Services/GEV/VAPass/classes/class.gevMyVAPassEntry.php");
			$entry = new gevMyVAPassEntry();
			$entry->setTitle($child->getTitle());
			$entry->setObjId($child->getId());
			$entry->setRefId($child->getRefId());
			$entry->setHasLpChildren($child->hasLPChildren());
			$entry->setHasChildren($child->hasChildren());

			$lp_status = $this->getLpStatusFor($child->getId(), $this->user_id);
			$entry->setStatus($lp_status["status"]);
			$entry->setFinished($lp_status["finished"]);

			$finish_until = $this->getCourseStartNextSP($children, $current_child_key + 1, $assignment_id);
			if ($finish_until) {
				$entry->setFinishUntil($finish_until);
			}

			$entries[] = $entry;
		}

		$this->setData($entries);
	}

	public function fillRow($a_set)
	{
		if ($a_set->getHasLpChildren()) {
			$link = "LP_CHILDREN";
		}

		if ($a_set->getHasChildren()) {
			$this->g_ctrl->setParameter($this->parent_obj, "nodeRefId", $a_set->getRefId());
			$this->g_ctrl->setParameter($this->parent_obj, "user_id", $this->user_id);
			$this->g_ctrl->setParameter($this->parent_obj, "assignment_id", $this->assignment_id);
			$link = $this->g_ctrl->getLinkTarget($this->parent_obj, "view");
			$this->g_ctrl->setParameter($this->parent_obj, "nodeRefId", null);
			$this->g_ctrl->setParameter($this->parent_obj, "user_id", null);
			$this->g_ctrl->setParameter($this->parent_obj, "assignment_id", null);
		}

		if ($link) {
			$this->tpl->setVariable("HREF", $link);
		}

		$this->tpl->setVariable("TITLE", $a_set->getTitle());
		$this->tpl->setVariable("STATUS", $this->getStatusIcon($a_set->getStatus()));
		$this->tpl->setVariable("FINISHED", $a_set->getFinished());

		$finish_until = $a_set->getFinishUntil();
		if ($finish_until) {
			$this->tpl->setVariable("FINISH_UNTIL", $finish_until->get(IL_CAL_FKT_DATE, "d.m.Y"));
		}

		$this->g_ctrl->setParameter($this->parent_obj, "selectedRefId", null);
	}

	/**
	 * Configures the table settings
	 *
	 * @return null
	 */
	protected function confugireTable()
	{
		$this->setEnableTitle(true);
		$this->setExternalSegmentation(false);
		$this->setExternalSorting(true);
		$this->setTopCommands(false);
		$this->setEnableHeader(true);
		$this->setFormAction($this->g_ctrl->getFormAction($this->parent_obj, "view"));
		$this->setLegend($this->createLegend());
		$this->setRowTemplate("tpl.gev_my_va_pass_row.html", "Services/GEV/VAPass");
		$this->useLngInTitle(false);
	}

	/**
	 * Add needed columns
	 *
	 * @return null
	 */
	protected function addColums()
	{
		$this->addColumn($this->g_lng->txt("gev_va_pass_modul"));
		$this->addColumn($this->g_lng->txt("gev_va_pass_state"));
		$this->addColumn($this->g_lng->txt("gev_va_pass_finish_until"));
		$this->addColumn($this->g_lng->txt("gev_va_pass_finished"));
	}

	/**
	 * Creates the legend for title
	 *
	 * @return catLegendGUI
	 */
	public function createLegend()
	{
		$legend = new catLegendGUI();
		$legend->addItem($this->success, "gev_va_pass_success")
			   ->addItem($this->in_progress, "gev_va_pass_progress")
			   ->addItem($this->faild, "gev_va_pass_failed")
			   ->addItem($this->not_attemped, "gev_va_pass_not_attemped");

		return $legend;
	}

	protected function getLpStatusFor($obj_id, $user_id)
	{
		$lp = array();
		require_once("Services/Tracking/classes/class.ilLPStatus.php");
		$status = ilLPStatus::_lookupStatus($obj_id, $user_id);
		$lp["status"] = $status;
		$lp["finished"] = "";

		if ($status == ilLPStatus::LP_STATUS_COMPLETED_NUM) {
			$finished = ilLPStatus::_lookupStatusChanged;
			$lp["finished"] = $finished;
		}

		return $lp;
	}

	protected function getStatusIcon($status)
	{
		switch ($status) {
			case ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM:
	            return $this->not_attemped;
			case ilLPStatus::LP_STATUS_IN_PROGRESS_NUM:
	            return $this->in_progress;
			case ilLPStatus::LP_STATUS_COMPLETED_NUM:
	            return $this->success;
			case ilLPStatus::LP_STATUS_FAILED_NUM:
	            return $this->faild;
			default:
	            return "";
		}
	}

	protected function getCourseStartNextSP($children, $next_child_key, $ass_id, $finish_until = null)
	{
		$next_children = array_slice($children, $next_child_key);

		foreach ($next_children as $next_sp) {
			if ($this->isRelevant($ass_id, $next_sp->getId(), $this->user_id)) {
				if ($next_sp->hasChildren()) {
					$finish_until =  $this->getCourseStartNextSP($next_sp->getChildren(), 0, $ass_id, $finish_until);
				}

				if ($next_sp->hasLPChildren()) {
					$finish_until = $this->getMinimumStartDate($next_sp->getLPChildren(), $finish_until);
				}
			}
		}

		return $finish_until;
	}

	protected function isRelevant($ass_id, $sp_id, $user_id)
	{
		$progress = ilStudyProgrammeUserProgress::getInstance($ass_id, $sp_id, $user_id);
		return $progress->isRelevant();
	}

	protected function getMinimumStartDate($lp_children, $finish_until)
	{
		foreach ($lp_children as $key => $value) {
			$is_member = ilParticipants::_isParticipant($value->getTargetRefId(), $this->user_id);
			if ($is_member) {
				$crs_id = $value->getTargetId();

				if (!$this->targetIsSelflearning($crs_id)) {
					$startdate = $this->getStatDateOfTarget($crs_id);

					if ($finish_until === null) {
						$finish_until = $startdate;
					} else {
						if ($this->currentFinishUntilIsLater($finish_until, $startdate)) {
							$finish_until = $startdate;
						}
					}
				}
			}
		}

		return $finish_until;
	}

	protected function getCrsUtils($crs_id)
	{
		return gevCourseUtils::getInstance($crs_id);
	}

	protected function targetIsSelflearning($crs_id)
	{
		return $this->getCrsUtils($crs_id)->isSelflearning();
	}

	protected function getStatDateOfTarget($crs_id)
	{
		return $this->getCrsUtils($crs_id)->getStartDate();
	}

	protected function currentFinishUntilIsLater($finish_until, $startdate)
	{
		return $finish_until->get(IL_CAL_UNIX) > $startdate->get(IL_CAL_UNIX);
	}
}
