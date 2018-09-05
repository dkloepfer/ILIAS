<?php
require_once 'Services/VIWIS/exceptions/class.WBTLocatorException.php';
/**
 * Takes care of storing feedback parameters for question related feedback ids
 * (a-la 1.3.4.5.6).
 */
class WBTLocator
{
	const WBT_TYPE_SINGLESCO = 'singlesco';
	const WBT_TYPE_MULTISCO = 'multisco';
	const WBT_TYPE_MULTISCO_2004 = 'multisco_2004';

	public function __construct(ilDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Get an assiciative array of question ids (like 1.2.3.4.5)
	 * to the corresponding link to open the corresponding wbt at the
	 * right spot. Slm = scorm learning module, if im not mistaking.
	 *
	 * @param	int	$slm_id
	 * @param	string	$type
	 * @return	string|int[][string]
	 */
	public function getRedirectLinkParametersById($slm_id, $type)
	{
		switch ($type) {
			case self::WBT_TYPE_SINGLESCO:
				return $this->extractJumpTosSinglesco($slm_id, $this->getManifestSinglesco($slm_id));
			case self::WBT_TYPE_MULTISCO:
				return $this->extractJumpTosMultisco($slm_id, $this->getManifestMultisco($slm_id));
			case self::WBT_TYPE_MULTISCO_2004:
				return $this->extractJumpTosMultisco2004($slm_id, $this->getManifestMultisco($slm_id));
			default:
				throw new WBTLocatorException('unknown type '.$type);
		}
	}

	/**
	 * Get all parameters necessary to redirect to a certain treenode of slm
	 * associated with $ref_id and having type $type and store to db.
	 *
	 * @param	int	$ref_id
	 * @param	string	$type
	 */
	public function extractManifestInfosByRefIdToDb($ref_id, $type)
	{
		$link_pars = $this->getRedirectLinkParametersById($this->getSlmIdByRefId($ref_id), $type);
		foreach ($link_pars as $question_ref => $wbt_item) {
			$this->storeLinkParametersToDb($ref_id, $wbt_item, $question_ref);
		}
	}

	protected function getSlmIdByRefId($ref_id)
	{
		if ($slm_id = ilObject::_lookupObjectId($ref_id)) {
			return $slm_id;
		}
		throw new WBTLocatorException('unknown reference '.$ref_id);
	}

	protected function extractJumpTosSinglesco($slm_id, $a_xml)
	{
		$return = array();
		$xml = new SimpleXMLElement($a_xml);
		$items = array();
		foreach ($xml->organizations->organization->item as $item) {
			$items[] = $item;
		}
		while ($item = array_shift($items)) {
			$ident = $item['identifier'];
			foreach ($item->metadata->lom->general->catalogentry as $cat) {
				$q_id = current($cat->entry->langstring);
				if (!isset($return[$q_id]) && $q_id) {
					//echo $q_id.'<br>';
					$return[$q_id] = $id_sco;
				}
			}
			foreach ($item->item as $sub_item) {
				array_push($items, $sub_item);
			}
		}
		return $return;
	}

	protected function extractJumpTosMultisco2004($slm_id, $a_xml)
	{
		$return = array();
		$xml = new SimpleXMLElement($a_xml);
		$items = array();
		foreach ($xml->organizations->organization->item as $item) {
			$items[] = $item;
		}
		while ($item = array_shift($items)) {
			$ident = $item['identifier'];
			$title = $item->title[0];
			if ($item->metadata) {
				foreach ($item->metadata->children('imsmd', true) as $lom) {
					if ($lom->getName() === 'lom') {
						foreach ($lom->children('imsmd', true) as $general) {
							if ($general->getName() === 'general') {
								foreach ($general->children('imsmd', true) as $catalogentry) {
									if ($catalogentry->getName() === 'catalogentry') {
										foreach ($catalogentry->children('imsmd', true) as $entry) {
											foreach ($entry->children('imsmd', true) as $langstring) {
												foreach (explode(',', $langstring) as $q_id) {
													$q_id = trim($q_id);
													if ($q_id !== '') {
														if (!isset($return[$q_id]) && $q_id) {
															$return[$q_id] = $ident;
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
			foreach ($item->item as $sub_item) {
				array_push($items, $sub_item);
			}
		}
		return $return;
	}

	protected function extractJumpTosMultisco($slm_id, $a_xml)
	{
		$return = array();
		$xml = new SimpleXMLElement($a_xml);
		$items = array();
		foreach ($xml->organizations->organization->item as $item) {
			$items[] = $item;
		}
		while ($item = array_shift($items)) {
			$ident = $item['identifier'];
			$title = $item->title[0];
			$q = 	'SELECT obj_id FROM scorm_object WHERE title = '.$this->db->quote($title, 'text')
					. '	AND c_type = '.$this->db->quote('sit', 'text').' AND slm_id = '.$this->db->quote($slm_id, 'integer');
			$res = $this->db->query($q);
			$id_sco = $this->db->fetchAssoc($res)['obj_id'];
			foreach ($item->metadata->lom->general->catalogentry as $cat) {
				$q_id = implode('.', array_map(function ($el) {
					return ltrim($el, '0');
				}, explode('.', current($cat->entry->langstring))));
				if (!isset($return[$q_id]) && $q_id) {
					//echo $q_id.'<br>';
					$return[$q_id] = $id_sco;
				}
			}
			foreach ($item->item as $sub_item) {
				array_push($items, $sub_item);
			}
		}
		return $return;
	}

	protected function getManifestSinglesco($slm_id)
	{
		return file_get_contents(
			CLIENT_WEB_DIR.DIRECTORY_SEPARATOR.'lm_data'.DIRECTORY_SEPARATOR.'lm_'.$slm_id.DIRECTORY_SEPARATOR.'imsmanifest_org.xml'
		);
	}

	protected function getManifestMultisco($slm_id)
	{
		return file_get_contents(
			CLIENT_WEB_DIR.DIRECTORY_SEPARATOR.'lm_data'.DIRECTORY_SEPARATOR.'lm_'.$slm_id.DIRECTORY_SEPARATOR.'imsmanifest.xml'
		);
	}

	protected function storeLinkParametersToDb($ref_id, $wbt_item, $question_ref)
	{
		$obj_id = ilObject::_lookupObjectId($ref_id);
		$this->db->insert('viwis_refs', array(
						'obj_id' =>	array( 'integer', $obj_id),
						'wbt_item' =>	array('text', $wbt_item),
						'question_ref' =>	array('text', $question_ref)));
	}

	/**
	 * Find out link params beonging to $question_ref
	 *
	 * @param	string	$question_ref
	 * @return	string[string]
	 */
	public function getRedirectParameterForQuestionRef($question_ref)
	{
		$q = 'SELECT obj_id, wbt_item FROM viwis_refs WHERE question_ref = '.$this->db->quote($question_ref, 'text');
		return $this->db->fetchAssoc($this->db->query($q));
	}
}
