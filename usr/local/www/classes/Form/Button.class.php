<?php
/*
	Button.class.php

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
class Form_Button extends Form_Input
{
	protected $_link;

	public function __construct($name, $title, $link = null)
	{
		// If we have a link; we're actually an <a class='btn'>
		if (isset($link))
		{
			$this->_link = $link;
			$this->addClass('btn-default');
			$type = null;
		}
		else
		{
			$this->addClass('btn-primary');
			$type = 'submit';
		}

		parent::__construct($name, $title, $type);

		$this->removeClass('form-control')->addClass('btn');
	}

	protected function _getInput()
	{
		if (!isset($this->_link))
			return parent::_getInput();

		$element = preg_replace('~^<input(.*)/>$~', 'a\1', parent::_getInput());
		$link = htmlspecialchars($this->_link);
		$title = htmlspecialchars($this->_title);

		return <<<EOT
	<{$element} href="{$link}">
		{$title}
	</a>
EOT;
	}
}
