<?php
/*
 * ListItem.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015 Sjon Hortensius
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class Form_ListItem extends Form_Group
{
	protected $_tagName = 'div';
	protected $_attributes = array(
		'class' => array('form-listitem' => true),
	);
	protected $_title;
	protected $_inputs = array();
	protected $_labelTarget;
	protected $_help;
	protected $_helpParams = array();

	public function __construct($title)
	{
		$this->_title = $title;
	}

	public function add(Form_Element $input)
	{
		if ($input instanceof Form_Input) {
			$group = new Form_Group($input->getTitle());
			$group->add($input);
			$input = $group;
		}


		array_push($this->_inputs, $input);

		return $input;
	}

	public function setLabelTarget(Form_Input $input)
	{
		$this->_labelTarget = $input;
	}

	public function setHelp()
	{
		$args = func_get_args();
		$arg0_len = strlen($args[0]);

		if (($arg0_len > 0) && ($arg0_len < 4096)) {
			$args[0] = gettext($args[0]);
		}

		if (func_num_args() == 1) {
			$this->_help = $args[0];
		} else {
			$this->_help = call_user_func_array('sprintf', $args);
		}

		$this->_helpParams = "";

		return $this;
	}

	public function enableDuplication($max = null, $horiz = false)
	{
		if ($horiz)
			$this->addClass('user-duplication-horiz');	// added buttons are 2 cols wide with no offset
		else
			$this->addClass('user-duplication');		// added buttons 10 cols wide with 2 col offset

		if (isset($max))
			$this->_attributes('data-duplicate-max', $max);

		foreach ($this->_inputs as $input) {
			if ($input instanceof Form_Input)
				$input->setIsRepeated();
		}

		return $this;
	}

	protected function _getHelp()
	{
		if (empty($this->_help))
			return null;

		$group = new Form_Element;
		$group->addClass('col-sm-'. Form::MAX_INPUT_WIDTH, 'col-sm-offset-'. Form::LABEL_WIDTH);

		$help = $this->_help;

		return <<<EOT
	{$group}
		<span class="help-block">
			{$help}
		</span>
	</div>
EOT;
	}

	public function __toString()
	{
		global $config, $user_settings;

		$element = Form_Element::__toString();

		// Automatically determine width for inputs without explicit set
		$spaceLeft = Form::MAX_INPUT_WIDTH;
		$missingWidth = array();

		foreach ($this->_inputs as $input)
		{
			if ($input instanceof Form_Input) {
				$width = $input->getWidth();
			} else {
				unset($width);
			}
			if (isset($width))
				$spaceLeft -= $width;
			else
				array_push($missingWidth, $input);
		}

		if ($this->_labelTarget instanceof Form_Input) {
			if (strtolower($this->_labelTarget->getType()) == 'hidden') {
				$hidden = true;
			}

			$form_controls = array('input', 'select', 'button', 'textarea', 'option', 'optgroup', 'fieldset', 'label');

			if (in_array(strtolower($this->_labelTarget->getTagName()), $form_controls) && !$hidden) {
				$target = $this->_labelTarget->getId();
			}
		}
		$inputs = implode('', $this->_inputs);
		$help = $this->_getHelp();

		if (!$user_settings['webgui']['webguileftcolumnhyper']) {
			$target = null;
		}

		if (!empty(trim($this->_title)) || is_numeric($this->_title)) {
			$title = htmlspecialchars(gettext($this->_title));

			// If the element tile (label) begins with a '*', remove the '*' and add a span with class
			// 'element-required'. Text decoration can then be added in the CSS to indicate that this is a
			// required field
			if (substr($title, 0, 1 ) === "*" ) {
				$title = '<span class="element-required">' . substr($title, 1) . '</span>';
			} else {
				$title = '<span>' . $title . '</span>';
			}
		}

		/*return <<<EOT
	<div class="hoihoi">
		{$inputs}
	</div>
EOT;*/

		return <<<EOT
	{$element}
		{$label}
			{$title}
		</label>
		{$inputs}
		{$help}
	</div>
EOT;
	}
}
