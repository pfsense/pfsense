<?php
/*
	Textarea.class.php

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
		$this->_attributes['style'] = 'white-space: nowrap; width: auto;';

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