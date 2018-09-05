<?php
require_once 'Services/VIWIS/exceptions/class.WBTLocatorException.php';
require_once 'Services/VIWIS/classes/class.WBTLocator.php';
/**
 * Redirect to the appropriate WBT.
 */
class WBTLocatorGUI
{

	public function __construct()
	{
		global $ilDB, $ilCtrl, $lng, $ilAccess;
		$this->ctrl = $ilCtrl;
		$this->locator = new WBTLocator($ilDB);
		$this->lng = $lng;
		$this->access = $ilAccess;
	}

	public function executeCommand()
	{
		$cmd = $this->ctrl->getCmd();
		switch ($cmd) {
			case 'redirect_viwis':
				$redirect_params = $this->locator->getRedirectParameterForQuestionRef($_GET['q_ref']);
				if ($redirect_params !== null) {
					foreach (ilObject::_getAllReferences($redirect_params['obj_id']) as $ref_id) {
						$redirect_ref_id = $ref_id;
						if ($this->access->checkAccess('read', '', $redirect_ref_id)) {
							break;
						}
					}
					$redirect = "ilias.php?baseClass=ilSAHSPresentationGUI"
						."&ref_id=".$redirect_ref_id
						.'&redirect_viwis='.$redirect_params['wbt_item'];
					ilUtil::redirect($redirect);
				} else {
					ilUtil::sendInfo(sprintf($this->lng->txt('no_wbt_found_for_ref'), $_GET['q_ref']));
				}
				break;
			case 'redirect_viwis_2004':
				$redirect_params = $this->locator->getRedirectParameterForQuestionRef($_GET['q_ref']);
				if ($redirect_params !== null) {
					foreach (ilObject::_getAllReferences($redirect_params['obj_id']) as $ref_id) {
						$redirect_ref_id = $ref_id;
						if ($this->access->checkAccess('read', '', $redirect_ref_id)) {
							break;
						}
					}
					$redirect = "data/".CLIENT_ID."/lm_data/lm_".$redirect_params['obj_id']
								."/index.html?#/sco/"
								.$redirect_params['wbt_item']."?entry=".$redirect_params['wbt_item'];
					ilUtil::redirect($redirect);
				} else {
					ilUtil::sendInfo(sprintf($this->lng->txt('no_wbt_found_for_ref'), $_GET['q_ref']));
				}
				break;
			default:
				throw new WBTLocatorException('unknown command: '.$cmd);
		}
	}
}
