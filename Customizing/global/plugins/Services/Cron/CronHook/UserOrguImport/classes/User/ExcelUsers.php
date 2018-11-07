<?php

#MUCH TO DO HERE

namespace CaT\IliasUserOrguImport\User;

use CaT\IliasUserOrguImport\Data as Data;
use CaT\IliasUserOrguImport\Filesystem\Filesystem as Filesystem;
use CaT\UserOrguImport\User as User;
use CaT\IliasUserOrguImport as Base;

/**
 * Extract user information from a xlsx file.
 */
class ExcelUsers
{

	const COLUMN_LE = 'le';
	const COLUMN_RESSORT = 'ressort';
	const COLUMN_DEPARTMENT = 'departement';
	const COLUMN_GROUP = 'group';
	const COLUMN_TEAM = 'team';


	protected $files;
	protected $extractor;


	public static $conversions = [
			//'BuKr' => UdfWrapper::PROP_FLAG_KU,
			//'Ressort' => self::COLUMN_RESSORT,
			//'Abteilung' => self::COLUMN_DEPARTMENT,
			//'Gruppe' => self::COLUMN_GROUP,
			//'Team' => self::COLUMN_TEAM,
			//'P.Nr.' => UdfWrapper::PROP_PNR,
			//'NACHN' => UdfWrapper::PROP_LASTNAME,
			//'VORNA' => UdfWrapper::PROP_FIRSTNAME,
			//'Anstellungsverhältn.' => UdfWrapper::PROP_FUNCTION,
			'Buchungskreis' => UdfWrapper::PROP_FLAG_KU,
			'LE' => self::COLUMN_LE,
			'RESSORT' => self::COLUMN_RESSORT,
			'ABTEILUNG' => self::COLUMN_DEPARTMENT,
			'GRUPPE' => self::COLUMN_GROUP,
			'TEAM' => self::COLUMN_TEAM,
			'Personalnummer' => UdfWrapper::PROP_PNR,
			'Nachname' => UdfWrapper::PROP_LASTNAME,
			'Vorname' => UdfWrapper::PROP_FIRSTNAME,
			'Emailadresse MitArb' => UdfWrapper::PROP_EMAIL,
			'Kostenstelle' => UdfWrapper::PROP_COST_CENTRE,
			'Kostenstelle lang' => UdfWrapper::PROP_COST_CENTRE_LONG,
			'Anstellungsverhältnis' => UdfWrapper::PROP_FUNCTION,
			'Geschlecht' => UdfWrapper::PROP_GENDER,
			'Geburtsdatum' => UdfWrapper::PROP_BIRTHDAY ,
			'Eintrittsdatum in KU' => UdfWrapper::PROP_ENTRY_DATE_KU,
			'Eintrittsdatum in KO' => UdfWrapper::PROP_ENTRY_DATE_KO,
			'Inaktiv von' => UdfWrapper::PROP_INACTIVE_BEGIN,
			'Inaktiv bis' => UdfWrapper::PROP_INACTIVE_END,
			'Name Vorgesetzter' => UdfWrapper::PROP_SUPERIOR_OF_USR,
			'Austrittsdatum' => UdfWrapper::PROP_EXIT_DATE,
			'IDD-betroffen ab' => UdfWrapper::PROP_IDD_AFFECTED_START,
			'IDD-betroffen bis' => UdfWrapper::PROP_IDD_AFFECTED_END,
			'Standort' => UdfWrapper::PROP_LOCATION_MA
			];

	public static $delivered_pnrs_conversions = [
			'Personalnummer' => UdfWrapper::PROP_PNR
	];

	public function __construct(
		Base\Filesystem\ImportFiles $import_files,
		Data\DataExtractor $extractor,
		UserIdentifier $ident,
		Base\ErrorReporting\ErrorCollection $e_c
	) {

		$this->import_files = $import_files;
		$this->extractor = $extractor;
		$this->ident = $ident;
		$this->e_c = $e_c;
	}


	/**
	 * Get User data from an excel file
	 *
	 * @return User\Users
	 */
	public function users()
	{
		$ident = $this->ident;
		$users = new User\Users($ident);
		$path = $this->import_files->getCurrentUserFilePath();
		if (!$path) {
			$this->e_c->addError('User data file not accessible');
			return null;
		}
		foreach ($this->extractor->extractContent($path, self::$conversions) as $row) {
			$row = $this->preprocessRow($row);
			$row = $this->postprocessRow($row);
			if ($this->checkRow($row)) {
				$users->add(new User\User($row, $ident));
			}
		}
		return $users;
	}

	/**
	 * Get PNRs delivered with current import file.
	 *
	 * @return	string[]
	 */
	public function deliveredPNRs()
	{
		$path = $this->import_files->getCurrentUserFilePath();
		$return = [];
		foreach ($this->extractor->extractContent($path, self::$conversions) as $row) {
			$return[] = trim($row[UdfWrapper::PROP_PNR]);
		}
		return array_unique($return);
	}

	protected function checkRow(array $row)
	{
		$pnr_set = trim((string)$row[UdfWrapper::PROP_PNR]) !== '';
		if (!$pnr_set) {
			$this->e_c->addError('No pnr set for user');
			return false;
		}
		$kz_ku_set = trim((string)$row[UdfWrapper::PROP_FLAG_KU]) !== '';
		if (!$kz_ku_set) {
			$this->e_c->addError('No ku set for user with pnr '.$row[UdfWrapper::PROP_PNR]);
		}
		$orgus_set = trim((string)$row[UdfWrapper::PROP_ORGUS]) !== '';
		if (!$orgus_set) {
			$this->e_c->addError('No orgu set for user with pnr '.$row[UdfWrapper::PROP_PNR]);
		}
		$mail_set = trim((string)$row[UdfWrapper::PROP_EMAIL]) !== '';
		if (!$mail_set) {
			$this->e_c->addError('No mail set for user with pnr '.$row[UdfWrapper::PROP_PNR]);
		}
		$firstname_set = trim((string)$row[UdfWrapper::PROP_FIRSTNAME]) !== '';
		if (!$firstname_set) {
			$this->e_c->addError('No firsname set for user with pnr '.$row[UdfWrapper::PROP_PNR]);
		}
		$lastname_set = trim((string)$row[UdfWrapper::PROP_LASTNAME]) !== '';
		if (!$lastname_set) {
			$this->e_c->addError('No lastname set for user with pnr '.$row[UdfWrapper::PROP_PNR]);
		}

		$gender = trim((string)$row[UdfWrapper::PROP_GENDER]);
		$gender_set = $gender === 'm' || $gender === 'f';
		if (!$gender_set) {
			$this->e_c->addError('No gender set for user with pnr '.$row[UdfWrapper::PROP_PNR]);
		}
		$dates_set =
			$row[UdfWrapper::PROP_INACTIVE_BEGIN] !== false &&
			$row[UdfWrapper::PROP_INACTIVE_END] !== false &&
			$row[UdfWrapper::PROP_ENTRY_DATE_KU] !== false &&
			$row[UdfWrapper::PROP_ENTRY_DATE_KO] !== false &&
			$row[UdfWrapper::PROP_EXIT_DATE] !== false &&
			$row[UdfWrapper::PROP_IDD_AFFECTED_START] !== false &&
			$row[UdfWrapper::PROP_IDD_AFFECTED_END] !== false
		;
		if (!$dates_set) {
			$this->e_c->addError('Ivalid dates for user with pnr '.$row[UdfWrapper::PROP_PNR]);
		}
		return	$kz_ku_set
			&&	$pnr_set
			&&	$orgus_set
			&&	$mail_set
			&&	$firstname_set
			&&	$lastname_set
			&&	$gender_set
			&&	$dates_set;
	}

	protected function preprocessRow(array $row) {
		$ret = [];
		foreach ($row as $key => $value) {
			$ret[$key] = trim($value);
		}
		return $ret;
	}

	protected function postprocessRow(array $row)
	{
		$row[UdfWrapper::PROP_ORGUS] = implode(', ', Base\Orgu\ExcelOrgus::normalizedOrguPath($row));
		unset($row[self::COLUMN_LE]);
		unset($row[self::COLUMN_RESSORT]);
		unset($row[self::COLUMN_DEPARTMENT]);
		unset($row[self::COLUMN_GROUP]);
		unset($row[self::COLUMN_TEAM]);
		switch ($row[UdfWrapper::PROP_GENDER]) {
			case 'männlich':
			case 'Herr':
				$gender = 'm';
				break;
			case 'weiblich':
			case 'Frau':
				$gender = 'f';
				break;
			default:
				$gender = '';
		}
		$row[UdfWrapper::PROP_PNR] = (string)$row[UdfWrapper::PROP_PNR];
		$row[UdfWrapper::PROP_GENDER] = $gender;
		$row[UdfWrapper::PROP_LOGIN] = trim($row[UdfWrapper::PROP_PNR]);
		$row[UdfWrapper::PROP_INACTIVE_BEGIN] = $this->tryFormatDate($row[UdfWrapper::PROP_INACTIVE_BEGIN]);
		$row[UdfWrapper::PROP_INACTIVE_END] = $this->tryFormatDate($row[UdfWrapper::PROP_INACTIVE_END]);
		$row[UdfWrapper::PROP_ENTRY_DATE_KU] = $this->tryFormatDate($row[UdfWrapper::PROP_ENTRY_DATE_KU]);
		$row[UdfWrapper::PROP_ENTRY_DATE_KO] = $this->tryFormatDate($row[UdfWrapper::PROP_ENTRY_DATE_KO]);
		$row[UdfWrapper::PROP_EXIT_DATE] = $this->tryFormatDate($row[UdfWrapper::PROP_EXIT_DATE]);
		$row[UdfWrapper::PROP_BIRTHDAY] = $this->tryFormatDate($row[UdfWrapper::PROP_BIRTHDAY]);
		$row[UdfWrapper::PROP_COST_CENTRE] = (string)$row[UdfWrapper::PROP_COST_CENTRE];
		$row[UdfWrapper::PROP_SUPERIOR_OF_USR] = (string)$row[UdfWrapper::PROP_SUPERIOR_OF_USR];
		return $row;
	}

	protected function tryFormatDate($date_string)
	{
		if ($date_string === '' || $date_string === '#') {
			return '';
		}
		try {
			$date_time = new \DateTime($date_string);
			return $date_time->format('Y-m-d');
		} catch (\Exception $e) {
			return false;
		}
	}
}
