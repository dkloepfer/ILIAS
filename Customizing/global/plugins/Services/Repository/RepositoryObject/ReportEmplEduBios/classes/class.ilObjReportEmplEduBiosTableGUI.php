<?php
require_once("Services/CaTUIComponents/classes/class.catTableGUI.php");

class ilObjReportEmplEduBiosTableGUI extends catTableGUI {

	/**
	 * @inheritdoc
	 */
	protected function fillRow($a_set)
	{
		if($a_set['edu_bio_link'] === "") {
			$this->tpl->setCurrentBlock("withoutlink");
			$this->tpl->setVariable("VAL_LASTNAME", $a_set['lastname']);
			$this->tpl->setVariable("VAL_FIRSTNAME", $a_set['firstname']);
			$this->tpl->parseCurrentBlock();
			$this->tpl->setCurrentBlock("login");
			$this->tpl->setVariable("VAL_LOGIN", $a_set['login']);
			$this->tpl->parseCurrentBlock();
		} else {
			$this->tpl->setCurrentBlock("withlink");
			$this->tpl->setVariable("VAL_LASTNAME", $a_set['lastname']);
			$this->tpl->setVariable("VAL_FIRSTNAME", $a_set['firstname']);
			$this->tpl->setVariable("VAL_EDU_BIO_LINK", $a_set['edu_bio_link']);
			$this->tpl->parseCurrentBlock();

			$this->tpl->setCurrentBlock("login_link");
			$this->tpl->setVariable("VAL_LOGIN", $a_set['login']);
			$this->tpl->setVariable("VAL_EDU_BIO_LINK", $a_set['edu_bio_link']);
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setVariable("VAL_CP_PASSED", $a_set['cp_passed']);
		$this->tpl->setVariable("VAL_ADP_NUMBER", $a_set['adp_number']);
		$this->tpl->setVariable("VAL_JOB_NUMBER", $a_set['job_number']);
		$this->tpl->setVariable("VAL_OD_BD", $a_set['od_bd']);
		$this->tpl->setVariable("VAL_ORG_UNIT", $a_set['org_unit']);
		$this->tpl->setVariable("VAL_LOCATION_MA", $a_set['location_ma']);
		$this->tpl->setVariable("VAL_ROLES", $a_set['roles']);
	}
}
