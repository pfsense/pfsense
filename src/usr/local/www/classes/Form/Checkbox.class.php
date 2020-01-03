<?php
/*
 * Checkbox.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Sjon Hortensius
 * Copyright (c) 2015-2016 Electric Sheep Fencing
 * Copyright (c) 2015-2020 Rubicon Communications, LLC (Netgate)
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

class Form_Checkbox extends Form_Input
{
	protected $_attributes = array(
		'class' => array(),
	);
	protected $_description;

	public function __construct($name, $title, $description, $checked, $value = 'yes')
	{
		parent::__construct($name, $title, 'checkbox', $value);

		$this->_description = $description;

		if ($checked)
			$this->_attributes['checked'] = 'checked';

		$this->column->addClass('checkbox');
	}

	public function displayAsRadio($id = null)
	{
		$this->_attributes['type'] = 'radio';

		if ($id != null) {
			$this->_attributes['id'] = $id;
		} else {
			$this->_attributes['id'] = $this->_attributes['name'] . '_' . $this->_attributes['value'] . ':' .substr(uniqid(), 9);
		}

		return $this;
	}

	protected function _getInput()
	{
		$input = parent::_getInput();

		if (!empty($this->_description) || is_numeric($this->_description))
			return '<label class="chkboxlbl">'. $input .' '. htmlspecialchars(gettext($this->_description)) .'</label>';

		return '<label class="chkboxlbl">'. $input .'</label>';
	}
}
