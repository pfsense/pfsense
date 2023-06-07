<?php
/*
 * Textarea.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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

class Form_Textarea extends Form_Input
{
	protected $_tagName = 'textarea';
	protected $_value;
	protected $_attributes = array(
		'rows' => 5,
		'class' => array('form-control' => true)
	);

	public function __construct($name, $title, $value)
	{
		parent::__construct($name, $title, null);

		$this->_value = $value;
	}

	public function setRows($size)
	{
		$this->_attributes['rows'] = $size;

		return $this;
	}

	public function setNoWrap()
	{
		$this->_attributes['style'] = 'white-space: pre;';

		return $this;
	}

	protected function _getInput()
	{
		$element = parent::_getInput();
		$value = htmlspecialchars($this->_value);

		return <<<EOT
	{$element}{$value}</textarea>
EOT;
	}
}
