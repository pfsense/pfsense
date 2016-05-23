<?php
/*
	Checkbox.class.php

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

		if (!isset($this->_description))
			return $input;

		return '<label class="chkboxlbl">'. $input .' '. htmlspecialchars(gettext($this->_description)) .'</label>';
	}
}