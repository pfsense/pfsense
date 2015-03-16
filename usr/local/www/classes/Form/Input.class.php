<?php
/*
	Input.class.php

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
class Form_Input extends Form_Element
{
	protected $_title;
	protected $_help;
	protected $_helpParams = array();
	protected $_columnWidth;
	protected $_columnClasses = array();

	public function __construct($name, $title, $type = 'text', $value = null, array $attributes = array())
	{
		$this->setAttribute('name', $name);
		$this->setAttribute('id', $name);
		$this->_title = htmlspecialchars($title);
		$this->addClass('form-control');

		if (isset($type)) {
			$this->setAttribute('type', $type);
		}

		if (isset($value)) {
			$this->setAttribute('value', $value);
		}

		foreach($attributes as $name => $value) {
			$this->setAttribute($name, $value);
		}

		return $this;
	}

	public function getTitle()
	{
		return $this->_title;
	}

	public function setHelp($help, array $params = array())
	{
		$this->_help = $help;
		$this->_helpParams = $params;

		return $this;
	}

	public function getWidth()
	{
		return $this->_columnWidth;
	}

	public function setWidth($size)
	{
		if ($size < 1 || $size > 12) {
			throw new Exception('Incorrect size, pass a number between 1 and 12');
		}

		$this->removeColumnClass('col-sm-'. $this->_columnWidth);

		$this->_columnWidth = (int)$size;

		$this->addColumnClass('col-sm-'. $this->_columnWidth);

		return $this;
	}

	public function addColumnClass($class)
	{
		$this->_columnClasses[$class] = true;

		return $this;
	}

	public function removeColumnClass($class)
	{
		unset($this->_columnClasses[$class]);

		return $this;
	}

	public function getColumnHtmlClass()
	{
		if (empty($this->_columnClasses))
				return '';

		return 'class="'. implode(' ', array_keys($this->_columnClasses)).'"';
	}

	protected function _getInput()
	{
		return "<input{$this->getHtmlAttribute()}/>";
	}

	public function __toString()
	{
		$input = $this->_getInput();

		if (isset($this->_help)) {
			$help = gettext($this->_help);

			if (!empty($this->_helpParams))
				$help = call_user_func_array('sprintf', array_merge([$help], $this->_helpParams));

			$help = '<span class="help-block">'. $help .'</span>';

		} else {
			$columnClass = $this->getColumnHtmlClass();

			// No classes => no element. This is useful for global inputs
			if (empty($columnClass))
				return (string)$input;
		}

		return <<<EOT
	<div {$this->getColumnHtmlClass()}>
		{$input}
		{$help}
	</div>
EOT;
	}
}
