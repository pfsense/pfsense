<?php
/*
	Select.class.php

	Copyright (C) 2015 Sjon Hortensius
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
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
			// Things can get wierd if we have mixed types
			$sval = $this->_value;

			if( (gettype($value) == "integer") && (gettype($sval) == "string") )
				$value = strval($value);

			if (isset($this->_attributes['multiple']))
				$selected = in_array($value, (array)$sval);
			else {
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
