<?php
/*
 * SelectInputCombo.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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

class Form_SelectInputCombo extends Form_Input {
	protected $_select;

	public function __construct($name, $placeholder, $value, $attributes = [])
	{
		parent::__construct($name, $placeholder, 'text', $value);

		if (is_array($attributes) && !empty($attributes)) {
			if (!array_key_exists('class', $attributes)) {
				$attributes['class'] = array('form-control' => true);
			}
			foreach ($attributes as $attr_name => $attr_value)
				$this->_attributes[$attr_name] = $attr_value;
		}

		parent::setPlaceholder($placeholder);
	}

	public function addSelect($name, $value, $selectValues)
	{
		$this->_select = new Form_Select(
			$name,
			null,
			$value,
			$selectValues
		);

		$this->_select->addClass("match-selection");

		return $this;
	}

	protected function _getInput()
	{
		$input = parent::_getInput();

		if (!isset($this->_select))
			return $input;

		return <<<EOT
		<div class="inputselectcombo">
			{$this->_select}
			<span>$input</span>
		</div>
		EOT;
	}
}

