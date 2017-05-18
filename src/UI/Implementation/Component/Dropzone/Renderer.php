<?php
/**
 * Class Renderer
 *
 * Renderer implementation for file dropzones.
 *
 * @author  nmaerchy <nm@studer-raimann.ch>
 * @date    05.05.17
 * @version 0.0.8
 *
 * @package ILIAS\UI\Implementation\Component\Dropzone
 */

namespace ILIAS\UI\Implementation\Component\Dropzone;

use ILIAS\UI\Component\Component;
use ILIAS\UI\Implementation\DefaultRenderer;
use ILIAS\UI\Implementation\Render\AbstractComponentRenderer;
use ILIAS\UI\Implementation\Render\ResourceRegistry;

class Renderer extends AbstractComponentRenderer {

	/**
	 * @var $renderer DefaultRenderer
	 */
	private $renderer;

	/**
	 * @inheritDoc
	 */
	protected function getComponentInterfaceName() {
		return array(
			\ILIAS\UI\Component\Dropzone\Standard::class,
			\ILIAS\UI\Component\Dropzone\Wrapper::class
		);
	}


	/**
	 * @inheritdoc
	 */
	public function render(Component $component, \ILIAS\UI\Renderer $default_renderer) {
		$this->checkComponent($component);

		$this->renderer = $default_renderer;

		if ($component instanceof \ILIAS\UI\Component\Dropzone\Wrapper) {
			return $this->renderWrapperDropzone($component);
		}

		if ($component instanceof \ILIAS\UI\Component\Dropzone\Standard) {
			return $this->renderStandardDropzone($component);
		}
	}


	/**
	 * @inheritDoc
	 */
	public function registerResources(ResourceRegistry $registry) {
		parent::registerResources($registry);
		$registry->register("./src/UI/templates/js/Dropzone/dropzone-behavior.js");
		$registry->register("./src/UI/templates/js/libs/jquery.dragster.js");
	}


	/**
	 * Renders the passed in standerd dropzone.
	 *
	 * @param \ILIAS\UI\Component\Dropzone\Standard $standardDropzone the dropzone to render
	 *
	 * @return string the html representation of the passed in argument.
	 */
	private function renderStandardDropzone(\ILIAS\UI\Component\Dropzone\Standard $standardDropzone) {

		$dropzoneId = $this->createId();

		// setup javascript
		$jsHelper = new JavascriptHelper(
			SimpleDropzone::of()
				->setId($dropzoneId)
				->setDarkenedBackground($standardDropzone->isDarkenedBackground())
				->setRegisteredSignals($standardDropzone->getTriggeredSignals())
				->setUseAutoHighlight(false));

		$this->getJavascriptBinding()->addOnLoadCode($jsHelper->initializeStandardDropzone());


		// setup template
		$tpl = $this->getTemplate("tpl.standard-dropzone.html", true, true);
		$tpl->setVariable("ID", $dropzoneId);

		// set message if not empty
		if (strcmp($standardDropzone->getMessage(), "") !== 0) {
			$tpl->setCurrentBlock("with_message");
			$tpl->setVariable("MESSAGE", $standardDropzone->getMessage());
			$tpl->parseCurrentBlock();
		}

		return $tpl->get();
	}


	/**
	 * Renders the passed in wrapper dropzone.
	 *
	 * @param \ILIAS\UI\Component\Dropzone\Wrapper $wrapperDropzone the dropzone to render
	 *
	 * @return string the html representation of the passed in argument.
	 */
	private function renderWrapperDropzone(\ILIAS\UI\Component\Dropzone\Wrapper $wrapperDropzone) {

		$dropzoneId = $this->createId();

		// setup javascript
		$jsHelper = new JavascriptHelper(
			SimpleDropzone::of()
				->setId($dropzoneId)
				->setDarkenedBackground($wrapperDropzone->isDarkenedBackground())
				->setRegisteredSignals($wrapperDropzone->getTriggeredSignals())
				->setUseAutoHighlight(true));

		$this->getJavascriptBinding()->addOnLoadCode($jsHelper->initializeWrapperDropzone());

		// setup template
		$tpl = $this->getTemplate("tpl.wrapper-dropzone.html", true, true);
		$tpl->setVariable("ID", $dropzoneId);
		$tpl->setVariable("CONTENT", $this->renderComponentList($wrapperDropzone->getContent()));

		return $tpl->get();
	}


	/**
	 * Renders each component of the passed in array.
	 *
	 * @param Component[] $componentList an array of ILIAS UI components
	 *
	 * @return string the passed in components as html
	 */
	private function renderComponentList(array $componentList) {

		$contentHmtl = "";

		foreach ($componentList as $component) {
			$contentHmtl .= $this->renderer->render($component);
		}

		return $contentHmtl;
	}

}