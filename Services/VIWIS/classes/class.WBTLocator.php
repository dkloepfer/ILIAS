<?php
require_once 'Services/VIWIS/config/cfg.wbt_data.php';
require_once 'Services/VIWIS/exceptions/class.WBTLocatorException.php';
class WBTLocator {
	const WBT_TYPE_SINGLESCO = 'singlesco';
	const WBT_TYPE_MULTISCO = 'multisco';

	public function __construct(ilDB $db) {
		$this->db = $db;
	}

	public static $wbt_locations;

	/**
	 * Get an assiciative array of question ids (like 1.2.3.4.5)
	 * to the corresponding link to open the corresponding wbt at the
	 * right spot.
	 */
	public function getRedirectLinksById($wbt_id) {
		$data = self::$wbt_locations[$wbt_id];

		$ref_id = $data['ref_id'];
		if(!$ref_id) {
			throw new WBTLocatorException('no corresponding wbt found for '.$wbt_id);
		}

		$type = $daty['type'];
		switch($type) {
			case self::WBT_TYPE_SINGLESCO:
				$ids = $this->getJumpTosByRefIdSinglesco($this->getManifestSinglesco($ref_id));
			case self::WBT_TYPE_MULTISCO:
				$ids = $this->getJumpTosByRefIdMultisco($this->getManifestMultisco($ref_id));
			default:
				throw new WBTLocatorException('unknown type '.$type);
		}
		return $this->getLinksByIds($ids, $wbt_id);
	}

	protected function getLinksByIds(array $ids, $wbt_id) {

	}

	protected function getManifestSinglesco($ref_id) {
		$slm_id = ilObject::_lookupObjId($ref_id);
		return file_get_contents(
				ilUtil::getDataDir().DIRECTORY_SEPARATOR.'lm_data'.DIRECTORY_SEPARATOR.'lm_'.$slm_id.DIRECTORY_SEPARATOR.'imsmanifest.xml'
				);
	}

	protected function getManifestMultisco($ref_id) {
		$slm_id = ilObject::_lookupObjId($ref_id);
		return file_get_contents(
				ilUtil::getDataDir().DIRECTORY_SEPARATOR.'lm_data'.DIRECTORY_SEPARATOR.'lm_'.$slm_id.DIRECTORY_SEPARATOR.'imsmanifest_org.xml'
				);
	}

	protected function extractRelevantDataFromManifiest($xml) {
		
	}
}