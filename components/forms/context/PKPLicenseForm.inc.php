<?php
/**
 * @file controllers/form/context/PKPLicenseForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPLicenseForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's default licensing details.
 */
import('lib.pkp.components.forms.FormComponent');

define('FORM_LICENSE', 'license');

class PKPLicenseForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_LICENSE;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $context Context Journal or Press to change settings for
	 */
	public function __construct($action, $locales, $context) {
		$this->action = $action;
		$this->successMessage = __('manager.distribution.license.success');
		$this->locales = $locales;

		$licenseOptions = Application::getCCLicenseOptions();
		$licenseUrlOptions = [];
		foreach ($licenseOptions as $url => $label) {
			$licenseUrlOptions[] = [
				'value' => $url,
				'label' => __($label),
			];
		}
		$licenseUrlOptions[] = [
			'value' => 'other',
			'label' => __('manager.distribution.license.other'),
			'isInput' => true,
		];

		$copyrightHolder = $context->getData('copyrightHolderType');
		if (!empty($copyrightHolder) && !in_array($copyrightHolder, ['author', 'context'])) {
			$copyrightHolder = $context->getData('copyrightHolderOther');
		}

		$this->addField(new FieldRadioInput('copyrightHolder', [
				'label' => __('submission.copyrightHolder'),
				'helpTopic' => 'settings',
				'helpSection' => 'copyright-v-license',
				'type' => 'radio',
				'options' => [
					['value' => 'author', 'label' => __('user.role.author')],
					['value' => 'context', 'label' => __('context.context')],
					['value' => 'other', 'isInput' => true],
				],
				'value' => $copyrightHolder,
			]))
			->addField(new FieldRadioInput('licenseUrl', [
				'label' => __('manager.distribution.license'),
				'helpTopic' => 'settings',
				'helpSection' => 'copyright-v-license',
				'type' => 'radio',
				'options' => $licenseUrlOptions,
				'value' => $context->getData('licenseUrl'),
			]))
			->addField(new FieldOptions('copyrightYearBasis', [
				'label' => __('submission.copyrightYear'),
				'description' => __('manager.distribution.copyrightYearBasis.description'),
				'type' => 'radio',
				'options' => [
					['value' => 'issue', 'label' => __('manager.distribution.copyrightYearBasis.issue')],
					['value' => 'submission', 'label' => __('manager.distribution.copyrightYearBasis.submission')],
				],
				'value' => $context->getData('copyrightYearBasis'),
			]))
			->addField(new FieldRichTextArea('licenseTerms', [
				'label' => __('manager.distribution.licenseTerms'),
				'tooltip' => __('manager.distribution.licenseTerms.description'),
				'isMultilingual' => true,
				'value' => $context->getData('licenseTerms'),
			]));
	}
}
