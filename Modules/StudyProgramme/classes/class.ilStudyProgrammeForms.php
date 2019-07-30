<?php declare(strict_types=1);

class ilStudyProgrammeForms
{

	const PROP_DEADLINE_PERIOD = "deadline_period";
	const PROP_DEADLINE_DATE = "deadline_date";

	const OPT_NO_DEADLINE = 'opt_no_deadline';
	const OPT_DEADLINE_PERIOD = "opt_deadline_period";
	const OPT_DEADLINE_DATE = "opt_deadline_date";

	const PROP_VALIDITY_OF_QUALIFICATION_PERIOD = "validity_qualification_period";
	const PROP_VALIDITY_OF_QUALIFICATION_DATE = "validity_qualification_date";

	const OPT_NO_VALIDITY_OF_QUALIFICATION = 'opt_no_validity_qualification';
	const OPT_VALIDITY_OF_QUALIFICATION_PERIOD = "opt_validity_qualification_period";
	const OPT_VALIDITY_OF_QUALIFICATION_DATE = "opt_validity_qualification_date";

	const PROP_RESTART_PERIOD = "restart_period";

	const OPT_NO_RESTART = "opt_no_restart";
	const OPT_RESTART_PERIOD = "opt_restart_period";

	public function __construct(
		\ilLanguage $lng,
		\ILIAS\UI\Component\Input\Factory $input_factory,
		\ILIAS\Validation\Factory $validation,
		\ILIAS\Data\Factory $data_factory
	)
	{
		$this->lng = $lng;
		$this->input_factory = $input_factory;
		$this->validation = $validation;
		$this->data_factory = $data_factory;

	}

	public function getDeadlineSubform(ilObjStudyProgramme $prg)
	{
		$ff = $this->input_factory->field();
		$txt = function($id) { return $this->lng->txt($id); };
		$deadline_period_subform = $ff->numeric('',$txt('prg_deadline_period_desc'))
										->withAdditionalConstraint(
											$this->validation->greaterThan(-1)
									);
		$period = $prg->getDeadlinePeriod();
		$radio_option = self::OPT_NO_DEADLINE;
		if($period > 0) {
			$deadline_period_subform = $deadline_period_subform->withValue($period);
			$radio_option = self::OPT_DEADLINE_PERIOD;
		}
		$deadline_date = $prg->getDeadlineDate();
		$format = $this->data_factory->dateFormat()->germanShort();
		$deadline_date_subform = $ff
			->dateTime('',$txt('prg_deadline_date_desc'))
			->withFormat($format);
		if($deadline_date !== null) {
			$deadline_date_subform = $deadline_date_subform->withValue($deadline_date->format($format->toString()));
			$radio_option = self::OPT_DEADLINE_DATE;
		}
		$radio = $ff->radio("","")
			->withOption(
				self::OPT_NO_DEADLINE,
				$txt('prg_no_deadline'),
				''
			)
			->withOption(
				self::OPT_DEADLINE_PERIOD,
				$txt('prg_deadline_period'),
				'',
				[self::PROP_DEADLINE_PERIOD => $deadline_period_subform]
			)
			->withOption(
				self::OPT_DEADLINE_DATE,
				$txt('prg_deadline_date'),
				'',
				[self::PROP_DEADLINE_DATE => $deadline_date_subform]
			);

		return $radio->withValue($radio_option);
	}

	public function getValidityOfQualificationSubform(ilObjStudyProgramme $prg)
	{
		$ff = $this->input_factory->field();
		$txt = function($id) { return $this->lng->txt($id); };
		$vq_period_subform = $ff
			->numeric('',$txt('validity_qalification_period_desc'))
			->withAdditionalConstraint(
				$this->validation->greaterThan(-1)
			);
		$radio_option = self::OPT_NO_VALIDITY_OF_QUALIFICATION;
		$period = $prg->getValidityOfQualificationPeriod();
		if($period !== ilStudyProgrammeSettings::NO_VALIDITY_OF_QUALIFICATION_PERIOD) {
			$vq_period_subform = $vq_period_subform->withValue($period);
			$radio_option = self::OPT_VALIDITY_OF_QUALIFICATION_PERIOD;
		}
		$format = $this->data_factory->dateFormat()->germanShort();
		$vq_date_subform = $ff
			->dateTime('',$txt('validity_qalification_date_desc'))
			->withFormat($format);
		$date = $prg->getValidityOfQualificationDate();
		if($date !== null) {
			$vq_date_subform = $vq_date_subform->withValue($date->format($format->toString()));
			$radio_option = self::OPT_VALIDITY_OF_QUALIFICATION_DATE;
		}
		$radio = $ff->radio($txt('prg_validity_of_qualification_limit'),"")
			->withOption(
				self::OPT_NO_VALIDITY_OF_QUALIFICATION,
				$txt('prg_no_validity_qalification'),
				''
			)
			->withOption(
				self::OPT_VALIDITY_OF_QUALIFICATION_PERIOD,
				$txt('validity_qalification_period'),
				'',
				[self::PROP_VALIDITY_OF_QUALIFICATION_PERIOD => $vq_period_subform]
			)
			->withOption(
				self::OPT_VALIDITY_OF_QUALIFICATION_DATE,
				$txt('validity_qalification_date'),
				'',
				[self::PROP_VALIDITY_OF_QUALIFICATION_DATE => $vq_date_subform]
			);

		return $radio->withValue($radio_option);
	}

	public function getRestartSubform(ilObjStudyProgramme $prg)
	{
		$ff = $this->input_factory->field();
		$txt = function($id) { return $this->lng->txt($id); };
		$restart_period_subform = $ff
			->numeric('',$txt('restart_period_desc'))
			->withAdditionalConstraint(
				$this->validation->greaterThan(-1)
			);
		$radio_option = self::OPT_NO_RESTART;
		$restart_period = $prg->getRestartPeriod();
		if($restart_period !== ilStudyProgrammeSettings::NO_RESTART) {
			$radio_option = self::OPT_RESTART_PERIOD;
			$restart_period_subform = $restart_period_subform->withValue($restart_period);
		}
		$radio = $ff->radio($txt('prg_validity_of_qualification_restart'),"")
			->withOption(
				self::OPT_NO_RESTART,
				$txt('prg_no_restart'),
				''
			)
			->withOption(
				self::OPT_RESTART_PERIOD,
				$txt('restart_period'),
				'',
				[self::PROP_RESTART_PERIOD => $restart_period_subform]
			);
		return $radio->withValue($radio_option);
	}
}