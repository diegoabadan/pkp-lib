<?php
/**
 * @file controllers/form/FieldTextarea.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldTextarea
 * @ingroup classes_controllers_form
 *
 * @brief A multiline textarea field in a form.
 */
import ('lib.pkp.components.forms.Field');
class FieldTextarea extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-textarea';

	/** @var string Optional. A preset size option. */
	public $size;

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		if (isset($this->size)) {
			$config['size'] = $this->size;
		}

		return $config;
	}
}
