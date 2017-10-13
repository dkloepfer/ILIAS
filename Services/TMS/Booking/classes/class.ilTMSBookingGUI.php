<?php
/**
 * cat-tms-patch start
 */

require_once("Services/TMS/Booking/classes/class.ilTMSBookingPlayerGUI.php");
require_once("Services/TMS/Booking/classes/class.ilTMSBookingPlayerStateDB.php");

/**
 * Displays the TMS booking 
 *
 * @author Richard Klees <richard.klees@concepts-and-training.de>
 */
class ilTMSBookingGUI {
	/**
	 * @var ilTemplate
	 */
	protected $g_tpl;

	/**
	 * @var ilCtrl
	 */
	protected $g_ctrl;

	/**
	 * @var ilObjUser
	 */
	protected $g_user;

	/**
	 * @var	ilLanguage
	 */
	protected $g_lng;

	/**
	 * @var	mixed
	 */
	protected $parent_gui;

	public function __construct() {
		global $DIC;

		$this->g_tpl = $DIC->ui()->mainTemplate();
		$this->g_ctrl = $DIC->ctrl();
		$this->g_user = $DIC->user();
		$this->g_lng = $DIC->language();

		$this->g_lng->loadLanguageModule('tms');
	}

	public function executeCommand() {
		// TODO: Check if current user may book course for other user here.
		assert('$this->g_user->getId() === $_GET["usr_id"]');

		global $DIC;
		$process_db = new ilTMSBookingPlayerStateDB();
		$player = new ilTMSBookingPlayerGUI($DIC, $_GET["crs_ref_id"], $_GET["usr_id"], $process_db);
		$cmd = $this->g_ctrl->getCmd("next");
		$content = $player->process($cmd, $_POST);
		assert('is_string($content)');
		$this->g_tpl->setContent($content);
		$this->g_tpl->show();

		switch($cmd) {
			case "next": 
				$this->show();
				break;
			default:
				throw new Exception("Unknown command: ".$cmd);
		}
	}
}

/**
 * cat-tms-patch end
 */
