<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once 'Services/Mail/classes/class.ilMailTemplateDataProvider.php';
require_once 'Services/Mail/classes/class.ilMailTemplate.php';
require_once 'Services/Mail/classes/class.ilMailTemplateService.php';
require_once 'Services/Mail/classes/class.ilMailTemplateGenericContext.php';

/**
 * Class ilMailTemplateGUI
 * @author            Nadia Ahmad <nahmad@databay.de>
 * @author            Michael Jansen <mjansen@databay.de>
 * @ilCtrl_isCalledBy ilMailTemplateGUI: ilObjMailGUI
 */
class ilMailTemplateGUI
{
	/**
	 * @var $form ilPropertyFormGUI
	 */
	protected $form;

	/**
	 * @var ilTemplate
	 */
	protected $tpl;

	/**
	 * @var ilCtrl
	 */
	protected $ctrl;

	/**
	 * @var ilLanguage
	 */
	protected $lng;

	/**
	 * @var ilToolbarGUI
	 */
	protected $toolbar;

	/**
	 * 
	 */
	public function __construct()
	{
		/**
		 * @var $tpl       ilTemplate
		 * @var $ilCtrl    ilCtrl
		 * @var $lng       ilLanguage
		 * @var $ilToolbar ilToolbarGUI
		 */
		global $tpl, $ilCtrl, $lng, $ilToolbar;

		$this->tpl     = $tpl;
		$this->ctrl    = $ilCtrl;
		$this->lng     = $lng;
		$this->toolbar = $ilToolbar;

		$this->lng->loadLanguageModule('meta');

		$this->provider = new ilMailTemplateDataProvider();
	}

	/**
	 *
	 */
	public function executeCommand()
	{
		$next_class = $this->ctrl->getNextClass($this);
		$cmd        = $this->ctrl->getCmd();

		switch($next_class)
		{
			default:
				if(!$cmd || !method_exists($this, $cmd))
				{
					$cmd = 'showTemplates';
				}

				$this->$cmd();
				break;
		}
	}

	/**
	 *
	 */
	protected function showTemplates()
	{
		require_once 'Services/UIComponent/Button/classes/class.ilLinkButton.php';
		require_once 'Services/Mail/classes/class.ilMailTemplateTableGUI.php';

		$contexts = ilMailTemplateService::getTemplateContexts();
		if(count($contexts) <= 1)
		{
			ilUtil::sendFailure($this->lng->txt('mail_template_no_context_available'));
		}
		else
		{
			$create_tpl_button = ilLinkButton::getInstance();
			$create_tpl_button->setCaption('mail_new_template');
			$create_tpl_button->setUrl($this->ctrl->getLinkTarget($this, 'showInsertTemplateForm'));
			$this->toolbar->addButtonInstance($create_tpl_button);
		}

		$tbl = new ilMailTemplateTableGUI($this, 'showTemplates');
		$tbl->setData($this->provider->getTableData());

		$this->tpl->setContent($tbl->getHTML());
	}

	/**
	 *
	 */
	protected function insertTemplate()
	{
		$form = $this->getTemplateForm();

		if(!$form->checkInput())
		{
			$form->setValuesByPost();
			$this->showInsertTemplateForm($form);
			return;
		}

		$generic_context = new ilMailTemplateGenericContext();
		if($form->getInput('context') == $generic_context->getId())
		{
			$form->getItemByPostVar('context')->setAlert($this->lng->txt('mail_template_no_valid_context'));
			$form->setValuesByPost();
			$this->showInsertTemplateForm($form);
			return;
		}

		try
		{
			$context = ilMailTemplateService::getTemplateContextById($form->getInput('context'));
			$template = new ilMailTemplate();
			$template->setTitle($form->getInput('title'));
			$template->setContext($context->getId());
			$template->setLang($form->getInput('lang'));
			$template->setSubject($form->getInput('m_subject'));
			$template->setMessage($form->getInput('m_message'));
			$template->insert();

			ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
			$this->ctrl->redirect($this, 'showTemplates');
		}
		catch(Exception $e)
		{
			$form->getItemByPostVar('context')->setAlert($this->lng->txt('mail_template_no_valid_context'));
			ilUtil::sendFailure($this->lng->txt('form_input_not_valid'));
		}

		$form->setValuesByPost();
		$this->showInsertTemplateForm($form);
	}

	/**
	 * @param ilPropertyFormGUI $form
	 */
	protected function showInsertTemplateForm(ilPropertyFormGUI $form = NULL)
	{
		if(!($form instanceof ilPropertyFormGUI))
		{
			$form = $this->getTemplateForm();
		}
		$this->tpl->setContent($form->getHTML());
	}

	/**
	 *
	 */
	protected function updateTemplate()
	{
		if(!isset($_POST['tpl_id']) || !strlen($_POST['tpl_id']))
		{
			ilUtil::sendFailure($this->lng->txt('mail_template_missing_id'));
			$this->showTemplates();
			return;
		}

		$template = $this->provider->getTemplateById((int)$_POST['tpl_id']);
		if(!($template instanceof ilMailTemplate))
		{
			ilUtil::sendFailure($this->lng->txt('mail_template_missing_id'));
			$this->showTemplates();
			return;
		}

		$form = $this->getTemplateForm();
		if(!$form->checkInput())
		{
			$form->setValuesByPost();
			$this->showEditTemplateForm($form);
			return;
		}

		$generic_context = new ilMailTemplateGenericContext();
		if($form->getInput('context') == $generic_context->getId())
		{
			$form->getItemByPostVar('context')->setAlert($this->lng->txt('mail_template_no_valid_context'));
			$form->setValuesByPost();
			$this->showEditTemplateForm($form);
			return;
		}

		try
		{
			$context = ilMailTemplateService::getTemplateContextById($form->getInput('context'));
			$template->setTitle($form->getInput('title'));
			$template->setContext($context->getId());
			$template->setLang($form->getInput('lang'));
			$template->setSubject($form->getInput('m_subject'));
			$template->setMessage($form->getInput('m_message'));
			$template->update();

			ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
			$this->ctrl->redirect($this, 'showTemplates');
		}
		catch(Exception $e)
		{
			$form->getItemByPostVar('context')->setAlert($this->lng->txt('mail_template_no_valid_context'));
			ilUtil::sendFailure($this->lng->txt('form_input_not_valid'));
		}

		$form->setValuesByPost();
		$this->showEditTemplateForm($form);
	}

	/**
	 * @param ilPropertyFormGUI $form
	 */
	protected function showEditTemplateForm(ilPropertyFormGUI $form = NULL)
	{
		if(!($form instanceof ilPropertyFormGUI))
		{
			if(!isset($_GET['tpl_id']) || !strlen($_GET['tpl_id']))
			{
				ilUtil::sendFailure($this->lng->txt('mail_template_missing_id'));
				$this->showTemplates();
				return;
			}

			$template = $this->provider->getTemplateById((int)$_GET['tpl_id']);
			if(!($template instanceof ilMailTemplate))
			{
				ilUtil::sendFailure($this->lng->txt('mail_template_missing_id'));
				$this->showTemplates();
				return;
			}

			$form = $this->getTemplateForm($template);
			$this->populateFormWithTemplate($form, $template);
		}

		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * @param ilPropertyFormGUI $form
	 * @param ilMailTemplate    $template
	 */
	protected function populateFormWithTemplate(ilPropertyFormGUI $form, ilMailTemplate $template)
	{
		$form->setValuesByArray(array(
			'tpl_id'    => $template->getTplId(),
			'title'     => $template->getTitle(),
			'context'   => $template->getContext(),
			'lang'      => $template->getLang(),
			'm_subject' => $template->getSubject(),
			'm_message' => $template->getMessage()
		));
	}

	/**
	 *
	 */
	protected function confirmDeleteTemplate()
	{
		if(isset($_POST['tpl_id']) && is_array($_POST['tpl_id']) && count($_POST['tpl_id']) > 0)
		{
			$tpl_ids = array_filter(array_map('intval', $_POST['tpl_id']));
		}
		else if(isset($_GET['tpl_id']) && strlen($_GET['tpl_id']))
		{
			$tpl_ids = array_filter(array((int)$_GET['tpl_id']));
		}
		else
		{
			$tpl_ids = array();
		}

		if(count($tpl_ids) == 0)
		{
			ilUtil::sendFailure($this->lng->txt('select_one'));
			$this->showTemplates();
			return;
		}

		require_once 'Services/Utilities/classes/class.ilConfirmationGUI.php';
		$confirm = new ilConfirmationGUI();
		$confirm->setFormAction($this->ctrl->getFormAction($this, 'deleteTemplate'));
		$confirm->setHeaderText($this->lng->txt('mail_sure_delete_entry'));
		$confirm->setConfirm($this->lng->txt('confirm'), 'deleteTemplate');
		$confirm->setCancel($this->lng->txt('cancel'), 'showTemplates');

		foreach($tpl_ids as $tpl_id)
		{
			$template = $this->provider->getTemplateById((int)$tpl_id);
			$confirm->addItem('tpl_id[]', $tpl_id, $template->getTitle());
		}
		$this->tpl->setContent($confirm->getHTML());
	}

	/**
	 *
	 */
	protected function deleteTemplate()
	{
		if(isset($_POST['tpl_id']) && is_array($_POST['tpl_id']) && count($_POST['tpl_id']) > 0)
		{
			$tpl_ids = array_filter(array_map('intval', $_POST['tpl_id']));
			if(0 == count($tpl_ids))
			{
				ilUtil::sendFailure($this->lng->txt('select_one'));
				$this->showTemplates();
				return;
			}
		}
		else
		{
			ilUtil::sendFailure($this->lng->txt('select_one'));
			$this->showTemplates();
			return;
		}

		$this->provider->deleteTemplates($tpl_ids);

		if(1 == count($tpl_ids))
		{
			ilUtil::sendSuccess($this->lng->txt('mail_tpl_deleted_s'), true);
		}
		else
		{
			ilUtil::sendSuccess($this->lng->txt('mail_tpl_deleted_p'), true);
		}
		$this->ctrl->redirect($this, 'showTemplates');
	}

	public function getAjaxPlaceholdersById()
	{
		$context_id = ilUtil::stripSlashes($_GET['context_id']);
		require_once 'Services/Mail/classes/Form/class.ilManualPlaceholderInputGUI.php';
		$placeholders = new ilManualPlaceholderInputGUI($this->ctrl->getLinkTarget($this, 'getAjaxPlaceholdersById', '', true, false));
		$context = ilMailTemplateService::getTemplateContextById($context_id);
		foreach( $context->getPlaceholders() as $key => $value)
		{
			$placeholders->addPlaceholder($value['placeholder'], $value['label'] );
		}
		$placeholders->render(true);
		exit();
	}
	
	/**
	 * @param ilMailTemplate $template
	 * @return ilPropertyFormGUI
	 */
	protected function getTemplateForm(ilMailTemplate $template = null)
	{
		$form  = new ilPropertyFormGUI();

		$title = new ilTextInputGUI($this->lng->txt('mail_template_title'), 'title');
		$title->setRequired(true);
		$form->addItem($title);

		$context  = new ilRadioGroupInputGUI($this->lng->txt('mail_template_context'), 'context');
		$contexts = ilMailTemplateService::getTemplateContexts();

		if(count($contexts) <= 1)
		{
			ilUtil::sendFailure($this->lng->txt('mail_template_no_context_available'), true);
			$this->ctrl->redirect($this, 'showTemplates');
		}

		$context_sort    = array();
		$context_options = array();
		$generic_context = new ilMailTemplateGenericContext();
		foreach($contexts as $ctx)
		{
			if($ctx->getId() != $generic_context->getId())
			{
				$context_options[$ctx->getId()] = $ctx;
				$context_sort[$ctx->getId()]    = $ctx->getTitle();
			}
		}
		asort($context_sort);
		$first = null;
		foreach($context_sort as $id => $title)
		{
			$ctx    = $context_options[$id];
			$option = new ilRadioOption($ctx->getTitle(), $ctx->getId());
			$option->setInfo($ctx->getDescription());
			$context->addOption($option);
			
			if(!$first)
			{
				$first = $id;
			}
		}
		$context->setValue($first);
		$context->setRequired(true);
		$form->addItem($context);

		/*$lang = new ilSelectInputGUI($this->lng->txt('language'), 'lang');
		$options = array();
		foreach($this->lng->getInstalledLanguages() as $iso2code)
		{
			$options[$iso2code] = $this->lng->txt('meta_l_' . $iso2code);
		}
		asort($options);
		$lang->setOptions(array('' => $this->lng->txt('please_choose')) + $options);
		$lang->setRequired(true);
		$form->addItem($lang);*/
		$hidde_language = new ilHiddenInputGUI('lang');
		$hidde_language->setValue($this->lng->getLangKey());
		$form->addItem($hidde_language);

		$subject = new ilTextInputGUI($this->lng->txt('subject'), 'm_subject');
		$subject->setRequired(true);
		$subject->setSize(50);
		$form->addItem($subject);

		$message = new ilTextAreaInputGUI($this->lng->txt('message'), 'm_message');
		$message->setRequired(true);
		$message->setCols(60);
		$message->setRows(10);
		$form->addItem($message);

		require_once 'Services/Mail/classes/Form/class.ilManualPlaceholderInputGUI.php';
		$placeholders = new ilManualPlaceholderInputGUI($this->ctrl->getLinkTarget($this, 'getAjaxPlaceholdersById', '', true, false));
		
		if( $template === null )
		{
			$context_id = $generic_context->getId();
		}
		else
		{
			$context_id = $template->getContext();
		}
		$context = ilMailTemplateService::getTemplateContextById($context_id);
		foreach( $context->getPlaceholders() as $key => $value)
		{
			$placeholders->addPlaceholder($value['placeholder'], $value['label'] );
		}
		$form->addItem($placeholders);
		if($template instanceof ilMailTemplate && $template->getTplId() > 0)
		{
			$id = new ilHiddenInputGUI('tpl_id');
			$form->addItem($id);

			$form->setTitle($this->lng->txt('mail_edit_tpl'));
			$form->setFormAction($this->ctrl->getFormaction($this, 'updateTemplate'));
			$form->addCommandButton('updateTemplate', $this->lng->txt('save'));
		}
		else
		{
			$form->setTitle($this->lng->txt('mail_create_tpl'));
			$form->setFormAction($this->ctrl->getFormaction($this, 'insertTemplate'));
			$form->addCommandButton('insertTemplate', $this->lng->txt('save'));
		}

		$form->addCommandButton('showTemplates', $this->lng->txt('cancel'));
		return $form;
	}

	// cat-tms-patch start
	/**
	 * Show a preview of the mail template
	 *
	 * @return void
	 */
	protected function showPreview() {
		$get = $_GET;

		if(!isset($get['tpl_id']) || !strlen($get['tpl_id']))
		{
			ilUtil::sendFailure($this->lng->txt('mail_template_missing_id'));
			$this->showTemplates();
			return;
		}

		require_once 'Services/Mail/classes/Preview/class.ilMailPreviewGUI.php';
		require_once("Services/Mail/classes/Preview/ilPreviewFactory.php");
		$template = $this->provider->getTemplateById((int)$get['tpl_id']);
		$gui = new ilMailPreviewGUI($template, new ilPreviewFactory());

		$this->tpl->setContent($gui->getHTML());
	}
	// cat-tms-patch start
}
