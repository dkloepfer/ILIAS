<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * This GUI will show the member of an VA Pass the historie of his own learning progress
 * and which study programme and course he has to do
 *
 * @author Stefan Hecken 	<stefan.hecken@concepts-and-training.de>
 */
class ilIndividualPlanGUI
{
	/**
	 * @var array
	 */
	protected $studyprogramme = array();

	/**
	 * @var ilCtrl
	 */
	protected $g_ctrl;

	/**
	 * @var ilTemplate
	 */
	protected $g_tpl;

	/**
	 * @var ilUsr;
	 */
	protected $g_user;

	/**
	 * @var bool
	 */
	protected $isPost;

	/**
	 * @var integer
	 */
	protected $assignment_id;

	/**
	 * @var integer
	 */
	protected $sp_ref_id;

	/**
	 * @var string
	 */
	protected $success;

	/**
	 * @var string
	 */
	protected $in_progress;

	/**
	 * @var string
	 */
	protected $failed;

	/**
	 * @var string
	 */
	protected $not_attemped;

	/**
	 * @var integer
	 */
	protected $user_id;

	public function __construct()
	{
		global $ilCtrl, $tpl, $lng;
		$lng->loadLanguageModule("prg");
		$this->g_lng = $lng;
		$this->g_ctrl = $ilCtrl;
		$this->g_tpl = $tpl;
		$this->isPost = false;
		$this->success  = '<img src="'.ilUtil::getImagePath("GEV_img/ico-key-green.png").'" />';
		$this->in_progress = '<img src="'.ilUtil::getImagePath("GEV_img/ico-key-orange.png").'" />';
		$this->failed = '<img src="'.ilUtil::getImagePath("GEV_img/ico-key-red.png").'" />';
		$this->not_attemped = '<img src="'.ilUtil::getImagePath("GEV_img/ico-key-neutral.png").'" />';
	}

	public function executeCommand()
	{
		global $ilLog;
		$ilLog->write(get_class($this));
		$cmd = $this->g_ctrl->getCmd("view");

		switch ($cmd) {
			case "view":
			case "showContent":
				$this->view();
				break;
			default:
				throw new Exception("command unkown: $cmd");
		}
		$this->$cmd();
	}

	protected function view()
	{
		$this->findUserId();
		$this->findSPRefId();
		$this->findAssignmentId();
		$this->showContent();
	}

	protected function showContent()
	{
		global $ilUser;
		$this->g_user  = $ilUser;
		$relevant_children = $this->getRelevantChildren();
		$with_children = $this->getSPWithChildrenBelow($relevant_children);
		$with_lp_children = $this->getSPWithLPChildren($relevant_children);

		if(count($with_children) === 0 && count($with_lp_children) === 0) {
			ilUtil::sendFailure($this->g_lng->txt('rep_robj_xsp_no_sp_children'), true);
		}

		$html = "";
		if (count($with_children) > 0) {
			require_once("Modules/StudyProgramme/classes/tables/class.ilIndividualPlanTableGUI.php");
			$tbl_children = new ilIndividualPlanTableGUI($this, $with_children, $this->getAssignmentId(), $this->getUserId(), "view");
			if($this->getUserId() == $this->g_user->getId()) {
				$tbl_children->setTitle($this->getStudyProgramme()->getTitle());
			}else{
				$tbl_children->setTitle($this->getStudyProgramme()->getTitle()
										. " " . $this->g_user->getLastname()
										. ", " . $this->g_user->getFirstname());
			}
			$tbl_children->setSubtitle($this->getStudyProgramme()->getDescription());
			$tbl_children->setLegend($this->createLegend());

			$html = $tbl_children->getHtml();
		}

		if (count($with_lp_children) > 0) {
			require_once("Modules/StudyProgramme/classes/tables/class.ilIndividualPlanDetailTableGUI.php");
			$tbl_lp_children = new ilIndividualPlanDetailTableGUI($this, $with_lp_children, $this->getAssignmentId(), $this->getUserId(), "view");

			if ($html == "") {
				if($this->getUserId() == $this->g_user->getId()) {
					$tbl_lp_children->setTitle($this->getStudyProgramme()->getTitle());
				}else{
					$tbl_lp_children->setTitle($this->getStudyProgramme()->getTitle()
											. " " . $this->g_user->getLastname()
											. ", " . $this->g_user->getFirstname());
				}
				$tbl_lp_children->setSubtitle($this->getStudyProgramme()->getDescription());
				$tbl_lp_children->setLegend($this->createLegend());
				$html = $tbl_lp_children->getHtml();
			} else {
				$html .= "<br />".$tbl_lp_children->getHtml();
			}
		}

		$this->g_tpl->setContent($html);
	}

	protected function getStudyProgramme()
	{
		$sp_ref_id = $this->getSPRefId();

		if (!array_key_exists($sp_ref_id, $this->studyprogramme)) {
			require_once("Modules/StudyProgramme/classes/class.ilObjStudyProgramme.php");
			$this->studyprogramme[$sp_ref_id] = new ilObjStudyProgramme($sp_ref_id);
		}

		return $this->studyprogramme[$sp_ref_id];
	}

	protected function getRelevantChildren($children)
	{
		$sp = $this->getStudyProgramme();
		$ret = array();

		foreach ($sp->getChildren() as $child) {
			if ($this->isRelevant($this->getAssignmentId(), $child->getId(), $this->getUserId())) {
				$ret[] = $child;
			}
		}

		return $ret;
	}

	protected function getSPWithChildrenBelow($children)
	{
		return array_filter($children, function ($child) {
			return $child->hasChildren();
		});
	}

	protected function getSPWithLPChildren($children)
	{
		return array_filter($children, function ($child) {
			return $child->hasLPChildren();
		});
	}

	/**
	 * Creates the legend for title
	 *
	 * @return catLegendGUI
	 */
	public function createLegend()
	{
		$legend = new catLegendGUI();
		$legend->addItem($this->success, "prg_success")
			   ->addItem($this->in_progress, "prg_progress")
			   ->addItem($this->failed, "prg_failed")
			   ->addItem($this->not_attemped, "prg_not_attemped");

		return $legend;
	}

	public function getStatusIcon($status)
	{
		switch ($status) {
			case ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM:
				return $this->not_attemped;
			case ilLPStatus::LP_STATUS_IN_PROGRESS_NUM:
				return $this->in_progress;
			case ilLPStatus::LP_STATUS_COMPLETED_NUM:
				return $this->success;
			case ilLPStatus::LP_STATUS_FAILED_NUM:
				return $this->failed;
			default:
				return "";
		}
	}

	public function isRelevant($ass_id, $sp_id, $user_id)
	{
		require_once("Modules/StudyProgramme/classes/class.ilStudyProgrammeUserProgress.php");
		$progress = ilStudyProgrammeUserProgress::getInstance($ass_id, $sp_id, $user_id);

		return $progress->isRelevant();
	}

	protected function getSPRefId()
	{
		if ($this->sp_ref_id === null) {
			throw new Exception("No studyprogramme node id given");
		}

		return $this->sp_ref_id;
	}

	protected function getUserId()
	{
		if ($this->user_id === null) {
			throw new Exception("No user id given");
		}

		return $this->user_id;
	}

	protected function getAssignmentId()
	{
		if ($this->assignment_id === null) {
			throw new Exception("No assignment id given");
		}

		return $this->assignment_id;
	}

	protected function findSPRefId()
	{
		$get = $_GET;

		if ($get["spRefId"] && $get["spRefId"] !== null && is_integer((int)$get["spRefId"])) {
			$this->sp_ref_id = (int)$_GET["spRefId"];
			$this->isPost = true;
		}
	}

	protected function findUserId()
	{
		$get = $_GET;

		if ($get["user_id"] && $get["user_id"] !== null && is_integer((int)$get["user_id"])) {
			$this->user_id = (int)$_GET["user_id"];
			$this->isPost = true;
		}
	}

	protected function findAssignmentId()
	{
		$get = $_GET;

		if ($get["assignment_id"] && $get["assignment_id"] !== null && is_integer((int)$get["assignment_id"])) {
			$this->assignment_id =  (int)$_GET["assignment_id"];
			$this->isPost = true;
		}
	}

	public function setAssignmentId($assignment_id)
	{
		$this->assignment_id = $assignment_id;
	}

	public function setSPRefId($sp_ref_id)
	{
		$this->sp_ref_id = $sp_ref_id;
	}

	public function setUserId($user_id)
	{
		$this->user_id = $user_id;
	}
}
