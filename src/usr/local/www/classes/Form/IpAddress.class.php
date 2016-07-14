<?php
/*
 * IpAddress.class.php
 *
 * part of pfsense (https://www.pfsense.org)
 * copyright (c) 2015 sjon hortensius
 * copyright (c) 2015-2016 electric sheep fencing, llc
 * all rights reserved.
 *
 * redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. all advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "this product includes software developed by the pfsense project
 *    for use in the pfsense® software distribution. (http://www.pfsense.org/).
 *
 * 4. the names "pfsense" and "pfsense project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. for written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. products derived from this software may not be called "pfsense"
 *    nor may "pfsense" appear in their names without prior written
 *    permission of the electric sheep fencing, llc.
 *
 * 6. redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "this product includes software developed by the pfsense project
 * for use in the pfsense software distribution (http://www.pfsense.org/).
 *
 * this software is provided by the pfsense project ``as is'' and any
 * expressed or implied warranties, including, but not limited to, the
 * implied warranties of merchantability and fitness for a particular
 * purpose are disclaimed. in no event shall the pfsense project or
 * its contributors be liable for any direct, indirect, incidental,
 * special, exemplary, or consequential damages (including, but
 * not limited to, procurement of substitute goods or services;
 * loss of use, data, or profits; or business interruption)
 * however caused and on any theory of liability, whether in contract,
 * strict liability, or tort (including negligence or otherwise)
 * arising in any way out of the use of this software, even if advised
 * of the possibility of such damage.
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
