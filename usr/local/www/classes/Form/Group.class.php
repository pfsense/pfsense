<?php
/*
	Group.class.php

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
class Form_Group extends Form_Element
{
	protected $_title;
	protected $_inputs = array();
	protected $_labelTarget;
	protected $_help;

	public function __construct($title)
	{
		$this->_title = gettext($title);
		$this->addClass('form-group');

		return $this;
	}

	public function add(Form_Input $input)
	{
		array_push($this->_inputs, $input);
		$input->_setParent($this);

		// Defaults to first input
		if (!isset($this->_labelTarget))
			$this->_labelTarget = $input;

		return $input;
	}

	public function setLabelTarget(Form_Input $input)
	{
		$this->_labelTarget = $input;
	}

	// Potentially allow overloading
	public function getLabelWidth()
	{
		return $this->_parent->getLabelWidth();
	}

	public function setHelp($help)
	{
		$this->_help = $help;

		return $this;
	}

	public function __toString()
	{
		// Automatically determine width for inputs without explicit set
		$spaceLeft = 12 - $this->getLabelWidth();
		$missingWidth = array();

		foreach ($this->_inputs as $input)
		{
			$width = $input->getWidth();

			if (isset($width))
				$spaceLeft -= $width;
			else
				array_push($missingWidth, $input);
		}

		foreach ($missingWidth as $input)
			$input->setWidth($spaceLeft / count($missingWidth));

		$target = $this->_labelTarget->getName();
		$inputs = implode('', $this->_inputs);
		$help = isset($this->_help) ? '<div class="col-sm-'. (12 - $this->getLabelWidth()) .' col-sm-offset-'. $this->getLabelWidth() .'"><span class="help-block">'. gettext($this->_help). '</span></div>' : '';

		return <<<EOT
	<div {$this->getHtmlClass()}>
		<label for="{$target}" class="col-sm-{$this->getLabelWidth()} control-label">
			{$this->_title}
		</label>

		{$inputs}
		{$help}
	</div>
EOT;
	}
}
