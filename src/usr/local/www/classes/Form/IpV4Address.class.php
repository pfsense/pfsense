<?php
/*
 * IpV4Address.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

class Form_IpV4Address extends Form_IpAddress
{
	protected $_mask;

	public function __construct($name, $title, $value)
	{
		parent::__construct($name, $title, $value);

		$this->_attributes['pattern'] = '[0-9.]*';
		$this->_attributes['title'] = 'An IPv4 address like 1.2.3.4 zzz';
	}

	// $min is provided to allow for VPN masks in which '0' is valid
	public function addMask($name, $value, $max = 32, $min = 1)
	{
		return parent::addMask($name, $value, $max, $min);
	}

}
