<?php
##DONE


namespace CaT\IliasUserOrguImport\User;

/**
 * ilias udf IO
 */
class UdfWrapper
{
	protected $udf_field_ids;
	protected $udf_keywords;

	public function __construct(UserConfig $uc, $db)
	{
		$this->udf_field_ids = $uc->udfFields();
		$this->udf_keywords = array_flip($this->udf_field_ids);
		$this->db = $db;
	}

	/**
	 * Udf field id for a keyword.
	 *
	 * @param	string	$keyword
	 * @return	inst|null
	 */
	public function fieldId($keyword)
	{
		assert('is_string($keyword)');
		return $this->udf_field_ids[$keyword];
	}

	/**
	 * Possible keywords for udf.
	 *
	 * @return	string[]
	 */
	public static function possibleUdfKeywords()
	{
		return [
			self::PROP_FLAG_KU,
			self::PROP_ORGUS,
			self::PROP_PNR,
			self::PROP_COST_CENTRE,
			self::PROP_COST_CENTRE_LONG,
			self::PROP_FUNCTION,
			self::PROP_ENTRY_DATE_KU,
			self::PROP_ENTRY_DATE_KO,
			self::PROP_INACTIVE_BEGIN,
			self::PROP_INACTIVE_END,
			self::PROP_SUPERIOR_OF_USR,
			self::PROP_EXIT_DATE,
			self::PROP_IDD_AFFECTED_START,
			self::PROP_IDD_AFFECTED_END,
			self::PROP_LOCATION_MA
		];
	}

	/**
	 * Konfigured keywords.
	 *
	 * @return	string[]
	 */
	public function availiableUdfKeywords()
	{
		return array_intersect(self::possibleUdfKeywords(), $this->keywords());
	}

	/**
	 * All keywords.
	 *
	 * @return	string[]
	 */
	protected function keywordsAll()
	{
		return [
			self::PROP_FLAG_KU,
			self::PROP_ORGUS,
			self::PROP_PNR,
			self::PROP_LOGIN,
			self::PROP_LASTNAME,
			self::PROP_FIRSTNAME,
			self::PROP_EMAIL,
			self::PROP_COST_CENTRE,
			self::PROP_COST_CENTRE_LONG,
			self::PROP_FUNCTION,
			self::PROP_ENTRY_DATE_KU,
			self::PROP_ENTRY_DATE_KO,
			self::PROP_ENTRY_INACTIVE_BEGIN,
			self::PROP_ENTRY_INACTIVE_END,
			self::PROP_SUPERIOR_OF_USR,
			self::PROP_GENDER,
			self::PROP_BIRTHDAY,
			self::PROP_IDD_AFFECTED_START,
			self::PROP_IDD_AFFECTED_END,
			self::PROP_LOCATION_MA
		];
	}

	/**
	 * All ids => keywords.
	 *
	 * @return	string[]
	 */
	public function keywords()
	{
		return $this->udf_keywords;
	}

	/**
	 * All keywords => ids.
	 *
	 * @return	int[string]
	 */
	public function ids()
	{
		return $this->udf_field_ids;
	}

	/**
	 * Keyword for an id.
	 *
	 * @param	int	$id
	 * @return	string
	 */
	public function keyword($id)
	{
		assert('is_int($id)');
		return $this->udf_keywords[$id];
	}

	/**
	 * Get an ass. array usr_id => udf ot users.
	 *
	 * @param	int[]
	 * @return	mixed[int]
	 */
	public function udfDataOfUsers(array $il_usr_ids)
	{
		require_once 'Services/User/classes/class.ilUserDefinedData.php';
		$return = [];
		foreach (\ilUserDefinedData::lookupData($il_usr_ids, $this->udf_field_ids) as $usr_id => $data) {
			$aux = [];
			foreach ($data as $udf_id => $value) {
				$keyword = $this->keyword((int)$udf_id);
				if ($keyword !== null) {
					$aux[$keyword] = (string)$value;
				}
			}
			$return[$usr_id] = $aux;
		}
		return $return;
	}

	protected function clearEmpty(array $array)
	{
		$cleared = [];
		foreach ($array as $value) {
			if (trim((string)$value) !== '') {
				$cleared[] = $value;
			}
		}
		return $cleared;
	}


	/**
	 * Update user data by data of user id.
	 *
	 * @param	int	$il_usr_id
	 * @param 	mixed[string]	$data
	 */
	public function updateUserData(array $data, $il_usr_id)
	{
		require_once 'Services/User/classes/class.ilUserDefinedData.php';
		assert('is_int($il_usr_id)');
		$il_udf = new \ilUserDefinedData($il_usr_id);
		$data = $this->formatForUpdate($data);
		foreach ($this->availiableUdfKeywords() as $keyword) {
			if (array_key_exists($keyword, $data)) {
				$il_udf->set('f_'.$this->fieldId($keyword), $data[$keyword]);
			}
		}
		$il_udf->update();
	}

	protected function formatForUpdate(array $data)
	{
		return $data;
	}

	/**
	 * Get all values corresponding to $field id assigned to users.
	 * Possibly filter return value values.
	 *
	 * @param	int	$field_id
	 * @param	string|int[]	$valuee_filter
	 * @return	string[int]	usr_id => value
	 */
	public function userIdsByPropertyValues($field_id, array $values_filter)
	{
		return array_keys($this->userIdsFieldRelation($field_id, $values_filter));
	}

	/**
	 * Get all values corresponding to $field id assigned to users.
	 * Possibly filter return value values.
	 *
	 * @param	int	$field_id
	 * @param	string|int[]	$valuee_filter
	 * @return	string[int]	usr_id => value
	 */
	public function userIdsFieldRelation($field_id, array $values_filter = [])
	{
		assert('is_int($field_id)');
		if (!$this->keyword($field_id)) {
			throw new \InvalidArgumentException($field_id.' unknown');
		}
		$query = 'SELECT DISTINCT usr_id,value'
				.'	FROM udf_text'
				.'	WHERE field_id = '.$this->db->quote($field_id, 'integer')
				.'		AND value IS NOT NULL AND value != \'\'';
		if (count($values_filter) > 0) {
			$query .= '		AND '.$this->db->in('value', $values_filter, false, 'text');
		}
		$res = $this->db->query($query);
		$return = [];
		while ($rec = $this->db->fetchAssoc($res)) {
			$return[(int)$rec['usr_id']] = $rec['value'];
		}

		return $return;
	}

	const PROP_LOGIN = 'xuoi_usr_login';
	const PROP_FLAG_KU = 'xuoi_usr_flag_ku';
	const PROP_ORGUS = 'xuoi_usr_orgus';
	const PROP_PNR = 'xoui_usr_pnr';
	const PROP_LASTNAME = 'xoui_usr_lastname';
	const PROP_FIRSTNAME = 'xoui_usr_firstname';
	const PROP_EMAIL = 'xoui_usr_email';
	const PROP_COST_CENTRE = 'xoui_usr_cost_centre';
	const PROP_COST_CENTRE_LONG = 'xoui_usr_const_centre_long';
	const PROP_FUNCTION = 'xuoi_usr_function';
	const PROP_GENDER = 'xuoi_usr_gender';
	const PROP_BIRTHDAY = 'xuoi_usr_birthday';
	const PROP_ENTRY_DATE_KU =  'xuoi_usr_entry_date_ku';
	const PROP_ENTRY_DATE_KO = 'xuoi_usr_entry_date_ko';
	const PROP_INACTIVE_BEGIN = 'xuoi_usr_inactive_begin';
	const PROP_INACTIVE_END = 'xuoi_usr_inactive_end';
	const PROP_EXIT_DATE = 'xuoi_usr_exit_date';
	const PROP_SUPERIOR_OF_USR = 'xuoi_usr_superior_of_usr';
	const PROP_IDD_AFFECTED_START = 'xoui_idd_affected_start';
	const PROP_IDD_AFFECTED_END = 'xoui_idd_affected_end';
	const PROP_LOCATION_MA = 'xoui_location_ma';
}
