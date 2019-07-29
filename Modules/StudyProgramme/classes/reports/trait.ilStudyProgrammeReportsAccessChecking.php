<?php

trait ilStudyProgrammeReportsAccessChecking
{
	protected $admin_gui;

	protected function checkAccess(ilAccess $access)
	{
		if (!$access->checkAccess("read", "", $this->admin_gui->object->getRefId())) {
			throw new ilException("no access!");
		}
	}

	public function withAdminGUI(ilObjStudyProgrammeAdminGUI $admin_gui)
	{
		$this->admin_gui = $admin_gui;
		return $this;
	}

	public function adminGUI() : ilObjStudyProgrammeAdminGUI
	{
		return $this->admin_gui;
	}
}