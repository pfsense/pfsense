<?php
/*
 * Select.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

class Form_Select extends Form_Input
{
	protected $_tagName = 'select';
	protected $_values;
	protected $_value;

	public function __construct($name, $title, $value, array $values, $allowMultiple = false)
	{
		if ($allowMultiple)
			$name .= '[]';

		parent::__construct($name, $title, null);

		if ($allowMultiple)
			$this->_attributes['multiple'] = 'multiple';

		$this->_value = $value;
		$this->_values = $values;
	}

	protected function _getInput()
	{
		$element = parent::_getInput();

		$options = '';
		foreach ($this->_values as $value => $name)
		{
			// Things can get weird if we have mixed types
			$sval = $this->_value;

			if ((gettype($value) == "integer") && (gettype($sval) == "string"))
				$value = strval($value);

			if (isset($this->_attributes['multiple'])) {
				$selected = in_array($value, (array)$sval);
			} else {
				$selected = ($sval == $value);
			}

			if (!empty(trim($name)) || is_numeric($name)) {
				$name_str = htmlspecialchars(gettext($name));
			} else {
				// Fixes HTML5 validation: Element option without attribute label must not be empty
				$name_str = "&nbsp;";
			}

			$options .= '<option value="'. htmlspecialchars($value) .'"'.($selected ? ' selected' : '').'>'. $name_str .'</option>';
		}

		return <<<EOT
	{$element}
		{$options}
	</select>
EOT;
	}
}
