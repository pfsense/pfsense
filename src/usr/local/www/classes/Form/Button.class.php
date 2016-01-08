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
	protected $_tagSelfClosing = false;
	protected $_attributes = array(
		'class' => array(
			'btn' => true,
		),
		'type' => 'submit',
	);

	public function __construct($name, $title, $link = null, $icon = null)
	{
		// If we have a link; we're actually an <a class='btn'>
		if (isset($link))
		{
			$this->_attributes['href'] = $link;
			$this->_tagName = 'a';
			$this->addClass('btn-default');
			unset($this->_attributes['type']);
			if(isset($icon)) {
				$this->_attributes['icon'] = $icon;
			}
		}
		else if(isset($icon))
		{
			$this->_tagSelfClosing = false;
			$this->_tagName = 'button';
			$this->_attributes['value'] = $title;
			$this->_attributes['icon'] = $icon;
		}
		else
		{
			$this->_tagSelfClosing = true;
			$this->_attributes['value'] = $title;
			$this->addClass('btn-primary');
		}

		parent::__construct($name, $title, null);

		if (isset($link))
			unset($this->_attributes['name']);
	}

	protected function _getInput()
	{
		$input = parent::_getInput();

		if (!isset($this->_attributes['href']))
			return $input;

		return $input . htmlspecialchars($this->_title) .'</a>';
	}
}