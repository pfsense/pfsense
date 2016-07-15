<?php
/*
 * IpAddress.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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

class Form_IpAddress extends Form_Input
{
	protected $_mask;

	public function __construct($name, $title, $value, $type = "BOTH")
	{
		parent::__construct($name, $title, 'text', $value);

		switch ($type) {
			case "BOTH":
				$this->_attributes['pattern'] = '[a-f0-9:.]*';
				break;

			case "V4":
				$this->_attributes['pattern'] = '[0-9.]*';
				break;

			case "V6":
				$this->_attributes['pattern'] = '[a-f0-9:]*';
				break;
		}
	}

	// $min is provided to allow for VPN masks in which '0' is valid
	public function addMask($name, $value, $max = 128, $min = 1)
	{
		$this->_mask = new Form_Select(
			$name,
			null,
			$value,
			array_combine(range($max, $min), range($max, $min))
		);

		return $this;
	}

	public function setIsRepeated()
	{
		if (isset($this->_mask))
			$this->_mask->setIsRepeated();

		return parent::setIsRepeated();
	}

	protected function _getInput()
	{
		$input = parent::_getInput();

		if (!isset($this->_mask))
			return $input;

		return <<<EOT
		<div class="input-group">
			$input
			<span class="input-group-addon input-group-inbetween pfIpMask">/</span>
			{$this->_mask}
		</div>
EOT;
	}
}
