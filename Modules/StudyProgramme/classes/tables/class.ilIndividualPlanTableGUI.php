<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once("Services/CaTUIComponents/classes/class.catTableGUI.php");

/**
 * Shows study programme and course of members VA Pass as a table
 *
 * @author Stefan Hecken 	<stefan.hecken@concepts-and-training.de>
 */
class ilIndividualPlanTableGUI extends catTableGUI
{
	/**
	 * @var ilLanguage
	 */
	protected $g_lng;

	/**
	 * @var ilCtrl;
	 */
	protected $g_ctrl;

	/**
	 * @var ilTree
	 */
	protected $g_tree;

	public function __construct($a_parent_obj, $children, $assignment_id, $user_id, $a_parent_cmd = "", $a_template_context = "")
	{
		$this->setID("va_pass_member");

		parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);

		global $lng, $ilCtrl, $tree;

		$this->g_lng = $lng;
		$this->g_ctrl = $ilCtrl;
		$this->g_tree = $tree;
		$this->user_id = $user_id;
		$this->assignment_id = $assignment_id;

		$this->confugireTable();
		$this->addColums();

		$entries = array();
		foreach ($children as $current_child_key => $child) {
			require_once("Modules/StudyProgramme/classes/tables/class.ilIndividualPlanEntry.php");
			$entry = new ilIndividualPlanEntry();
			$entry->setTitle($child->getTitle());
			$entry->setObjId($child->getId());
			$entry->setRefId($child->getRefId());
			$entry->setHasLpChildren($child->hasLPChildren());
			$entry->setHasChildren($child->hasChildren());

			list($status, $finished) = $this->getLpStatusFor($child, $this->user_id);
			$entry->setStatus($status);
			$entry->setFinished($finished);

			$finish_until = $this->getCourseStartNextSP($children, $current_child_key + 1);
			if ($finish_until) {
				$entry->setFinishUntil($finish_until);
			}

			$entries[] = $entry;
		}

		$this->setData($entries);
	}

	public function fillRow($a_set)
	{
		$this->g_ctrl->setParameter($this->parent_obj, "spRefId", $a_set->getRefId());
		$this->g_ctrl->setParameter($this->parent_obj, "user_id", $this->user_id);
		$this->g_ctrl->setParameter($this->parent_obj, "assignment_id", $this->assignment_id);
		$link = $this->g_ctrl->getLinkTargetByClass("ilIndividualPlanGUI", "view");
		$this->g_ctrl->setParameter($this->parent_obj, "spRefId", null);
		$this->g_ctrl->setParameter($this->parent_obj, "user_id", null);
		$this->g_ctrl->setParameter($this->parent_obj, "assignment_id", null);
		$this->tpl->setVariable("HREF", $link);

		$this->tpl->setVariable("TITLE", $a_set->getTitle());
		$this->tpl->setVariable("STATUS", $this->parent_obj->getStatusIcon($a_set->getStatus()));
		if ($a_set->getFinished()) {
			$this->tpl->setVariable("FINISHED", $a_set->getFinished());
		} else {
			$this->tpl->setVariable("FINISHED","-");
		}

		$finish_until = $a_set->getFinishUntil();
		if ($finish_until) {
			$this->tpl->setVariable("FINISH_UNTIL", $finish_until->get(IL_CAL_FKT_DATE, "d.m.Y"));
		}
		else {
			$this->tpl->setVariable("FINISH_UNTIL","-");
		}

		$this->g_ctrl->setParameter($this->parent_obj, "selectedRefId", null);
	}

	/**
	 * Configures the table settings
	 *
	 * @return null
	 */
	// TODO: This name has a twist...
	protected function confugireTable()
	{
		$this->setEnableTitle(true);
		$this->setExternalSegmentation(false);
		$this->setExternalSorting(true);
		$this->setTopCommands(false);
		$this->setEnableHeader(true);
		$this->setFormAction($this->g_ctrl->getFormAction($this->parent_obj, "view"));

		$this->setRowTemplate("tpl.individual_plan_row.html", "Modules/StudyProgramme");
		$this->useLngInTitle(false);
	}

	/**
	 * Add needed columns
	 *
	 * @return null
	 */
	protected function addColums()
	{
		$this->addColumn($this->g_lng->txt("prg_modul"));
		$this->addColumn($this->g_lng->txt("prg_state"));
		$this->addColumn($this->g_lng->txt("prg_finish_until"));
		$this->addColumn($this->g_lng->txt("prg_finished"));
	}

	protected function getLpStatusFor(\ilObjStudyProgramme $sp, $user_id)
	{
		$lp = array();
		require_once("Services/Tracking/classes/class.ilLPStatus.php");
		$status = ilLPStatus::_lookupStatus($sp->getId(), $user_id);

		if ($status == ilLPStatus::LP_STATUS_COMPLETED_NUM) {
			$finished = ilLPStatus::_lookupStatusChanged;
			return [$status, $finished];
		}

		if ($this->hasBookedCourseIn($sp, $user_id)) {
			return [ilLPStatus::LP_STATUS_IN_PROGRESS_NUM, null];
		}

		return [ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM, null];
	}

	protected function hasBookedCourseIn(\ilObjStudyProgramme $sp, $user_id) {
		require_once("Services/ContainerReference/classes/class.ilContainerReference.php");
		require_once("Services/Tracking/classes/class.ilLPStatus.php");

		$node_data = $this->g_tree->getNodeData($sp->getRefId());
		$crsrs = $this->g_tree->getSubTree($node_data, true, "crsr");

		foreach ($crsrs as $ref) {
			$crs_id = ilContainerReference::_lookupTargetId($ref["obj_id"]);
			$crs_utils = gevCourseUtils::getInstance($crs_id);
			if ($crs_utils->isMember($user_id)) {
				return true;
			}
		}
		return false;
	}

	protected function getCourseStartNextSP($children, $next_child_key, $finish_until = null)
	{
		$next_children = array_slice($children, $next_child_key);

		foreach ($next_children as $next_sp) {
			if ($this->parent_obj->isRelevant($this->assignment_id, $next_sp->getId(), $this->user_id)) {
				if ($next_sp->hasChildren()) {
					$finish_until =  $this->getCourseStartNextSP($next_sp->getChildren(), 0, $finish_until);
				}

				if ($next_sp->hasLPChildren()) {
					$finish_until = $this->getMinimumStartDate($next_sp->getLPChildren(), $finish_until);
				}
			}
		}

		return $finish_until;
	}

	protected function getMinimumStartDate($lp_children, $finish_until)
	{
		foreach ($lp_children as $key => $value) {
			$is_member = ilParticipants::_isParticipant($value->getTargetRefId(), $this->user_id);
			if ($is_member) {
				$crs_id = $value->getTargetId();

				if (!$this->targetIsSelflearning($crs_id)) {
					$startdate = $this->getStatDateOfTarget($crs_id);
					if ($startdate === null) {
						continue;
					}

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

	// TODO: There is a mistake hidden in this name.
	protected function getStatDateOfTarget($crs_id)
	{
		return $this->getCrsUtils($crs_id)->getStartDate();
	}

	protected function currentFinishUntilIsLater(\ilDateTime $finish_until, \ilDateTime $startdate)
	{
		return $finish_until->get(IL_CAL_UNIX) > $startdate->get(IL_CAL_UNIX);
	}
}
