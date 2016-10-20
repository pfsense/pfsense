<?php
/*
 * IpAddress.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015 Sjon Hortensius
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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
				$this->_attributes['title'] = 'An IPv4 address like 1.2.3.4 or an IPv6 address like 1:2a:3b:ffff::1';
				$this->_attributes['onChange'] = 'javascript:this.value=this.value.toLowerCase();';
				break;

			case "V4":
				$this->_attributes['pattern'] = '[0-9.]*';
				$this->_attributes['title'] = 'An IPv4 address like 1.2.3.4';
				break;

			case "V6":
				$this->_attributes['pattern'] = '[a-f0-9:]*';
				$this->_attributes['title'] = 'An IPv6 address like 1:2a:3b:ffff::1';
				$this->_attributes['onChange'] = 'javascript:this.value=this.value.toLowerCase();';
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

		$this->_mask->addClass("pfIpMask");

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
