<?php
require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportSettings/class.reportSettingsException.php';
require_once 'Services/Form/classes/class.ilNumberInputGUI.php';
require_once 'Services/Form/classes/class.ilCheckboxInputGUI.php';
require_once 'Services/Form/classes/class.ilTextInputGUI.php';
require_once 'Services/Form/classes/class.ilTextAreaInputGUI.php';


/**
 *	This class implements the logic of commmunication between a report object and a FormSettingsGui
 *	It covers writing data into a form and fetching form inputs. It also takes care of pre-
 *	or postprocessing this data by means of ToForm and FromForm closures in settings.
 */
class reportSettingsFormHandler {
	/**
	 * Add form fields to $settings_form corresponding to settings in $settings
	 * @param	ilPropertyFormGUI	$settings_form
	 * @param	reportSettings	$settings
	 * @return	ilPropertyFormGUI	$settings_form
	 */
	public function addToForm(ilPropertyFormGUI $settings_form, reportSettings $settings) {
		$fields = $settings->settingIds();

		foreach ($fields as $field) {
			$setting = $settings->setting($field);
			$settings_form->addItem($this->formElementForSetting($setting));
		}
	}

	/**
	 * Get form entries in some ilPropertyFormGUI
	 * @param	ilPropertyFormGUI	$settings_form
	 * @param	reportSettings	$settings
	 * @return	array	$settings_data
	 */
	public function extractValues(ilPropertyFormGUI $settings_form, reportSettings $settings) {
		$settings_data = array();
		$fields = $settings->settingIds();

		foreach ($fields as $field) {
			$setting = $settings->setting($field);
			$form_member = $settings_form->getItemByPostVar($field);
			$settings_data[$field] = $this->extractSettingFromFormMember($setting, $form_member);
		}
		return $settings_data;
	}

	/**
	 * Adds Form fields to $settings_form corresponding to settings in $settings
	 * @param	ilPropertyFormGUI	$settings_form
	 * @param	reportSettings	$settings
	 * @return	ilPropertyFormGUI	$settings_form
	 */
	public function insertValues(array $settings_data, ilPropertyFormGUI $settings_form, reportSettings $settings) {
		$fields = $settings->settingIds();

		foreach ($fields as $field) {
			$setting = $settings->setting($field);
			$setting_data = $settings_data[$field];
			$form_member = $settings_form->getItemByPostVar($field);
			$this->insertSettingIntoFormMember($setting_data, $setting, $form_member);
		}
		return $settings_form;
	}

	protected function formElementForSetting(setting $setting) {
		$name = $setting->name();
		$id = $setting->id();
		if($setting instanceof settingInt) {
			return new ilNumberInputGUI($name, $id);
		}
		if($setting instanceof settingFloat) {
			$return = new ilNumberInputGUI($name, $id);
			$return->allowDecimals(true);
			return $return;
		}
		if($setting instanceof settingBool) {
			$return = new ilCheckboxInputGUI($name, $id);
			$return->setValue(1);
			return $return;
		}
		if($setting instanceof settingString) {
			return new ilTextInputGUI($name, $id);
		}
		if($setting instanceof settingText) {
			return new ilTextAreaInputGUI($name, $id);
		}
		if($setting instanceof settingRichText) {
			return new ilTextAreaInputGUI($name, $id);
		}
		if($setting instanceof settingListInt) {
			$return = new ilSelectInputGUI($name, $id);
			$return->setOptions($setting->options());
			return $return;

		}
		if($setting instanceof settingHidden) {
			return new ilHiddenInputGUI($name, $id);
		}
		throw new reportSettingsException("no formtype defined for setting");
	}

	protected function validSettingGUIRelation(setting $setting, ilSubEnabledFormPropertyGUI $form_member_gui) {
		if($setting instanceof settingInt && $form_member_gui instanceof ilNumberInputGUI) {
			return true;
		} elseif($setting instanceof settingFloat && $form_member_gui instanceof ilNumberInputGUI) {
			return true;
		} elseif($setting instanceof settingBool && $form_member_gui instanceof ilCheckboxInputGUI) {
			return true;
		} elseif($setting instanceof settingString && $form_member_gui instanceof ilTextInputGUI) {
			return true;
		} elseif($setting instanceof settingText && $form_member_gui instanceof ilTextAreaInputGUI) {
			return true;
		} elseif($setting instanceof settingRichText && $form_member_gui instanceof ilTextAreaInputGUI) {
			return true;
		} elseif($setting instanceof settingListInt && $form_member_gui instanceof ilSelectInputGUI) {
			return true;
		} elseif($setting instanceof settingHidden && $form_member_gui instanceof ilHiddenInputGUI) {
			return true;
		} else {
			return false;
		}
	}

	protected function extractSettingFromFormMember(setting $setting, ilSubEnabledFormPropertyGUI $form_member_gui) {
		assert('$this->validSettingGUIRelation($setting, $form_member_gui)');
		if($setting instanceof settingBool && $form_member_gui instanceof ilCheckboxInputGUI) {
			return call_user_func($setting->fromForm(), $form_member_gui->getChecked());
		}
		if($setting instanceof settingListInt && $form_member_gui instanceof  ilSelectInputGUI) {
			if(!in_array((int)$form_member_gui->getValue(),array_keys($setting->options()))) {
				throw new reportSettingsException("unknown option");
			}
		}
		return call_user_func($setting->fromForm(),  $form_member_gui->getValue());

	}

	protected function insertSettingIntoFormMember($setting_data, setting $setting, ilSubEnabledFormPropertyGUI $form_member_gui) {
		assert('$this->validSettingGUIRelation($setting, $form_member_gui)');
		if($setting instanceof settingBool && $form_member_gui instanceof ilCheckboxInputGUI) {
			if($setting_data) {
				$form_member_gui->setChecked(true);
			}
			return;
		}
		$form_member_gui->setValue(call_user_func($setting->toForm(), $setting_data));
		return;
	}
}