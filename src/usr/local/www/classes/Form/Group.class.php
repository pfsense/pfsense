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
	protected $_tagName = 'div';
	protected $_attributes = array(
		'class' => array('form-group' => true),
	);
	protected $_title;
	protected $_inputs = array();
	protected $_labelTarget;
	protected $_help;
	protected $_helpParams = array();

	public function __construct($title)
	{
		$this->_title = $title;
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

	public function setHelp($help, array $params = array())
	{
		$this->_help = $help;
		$this->_helpParams = $params;

		return $this;
	}

	public function enableDuplication($max = null, $horiz = false)
	{
		if($horiz)
			$this->addClass('user-duplication-horiz');	// added buttons are 2 cols wide with no offset
		else
			$this->addClass('user-duplication');		// added buttons 10 cols wide with 2 col offset

		if (isset($max))
			$this->_attributes('data-duplicate-max', $max);

		foreach ($this->_inputs as $input)
			$input->setIsRepeated();

		return $this;
	}

	protected function _getHelp()
	{
		if (!isset($this->_help))
			return null;

		$group = new Form_Element;
		$group->addClass('col-sm-'. Form::MAX_INPUT_WIDTH, 'col-sm-offset-'. Form::LABEL_WIDTH);

		$help = gettext($this->_help);

		if (!empty($this->_helpParams))
			$help = call_user_func_array('sprintf', array_merge([$help], $this->_helpParams));

		return <<<EOT
	{$group}
		<span class="help-block">
			{$help}
		</span>
	</div>
EOT;
	}

	public function __toString()
	{
		global $config;

		$element = parent::__toString();

		// Automatically determine width for inputs without explicit set
		$spaceLeft = Form::MAX_INPUT_WIDTH;
		$missingWidth = array();

		foreach ($this->_inputs as $input)
		{
			if (count($this->_inputs) > 1 && !$input->hasAttribute('placeholder'))
				$input->setPlaceholder($input->getTitle());

			$width = $input->getWidth();

			if (isset($width))
				$spaceLeft -= $width;
			else
				array_push($missingWidth, $input);
		}

		foreach ($missingWidth as $input)
			$input->setWidth($spaceLeft / count($missingWidth));

		if (strtolower($this->_labelTarget->get_Type()) == 'hidden')
			$hidden = true;

		$form_controls = array('input', 'select', 'button', 'textarea', 'option', 'optgroup', 'fieldset', 'label');
		if (in_array(strtolower($this->_labelTarget->gettagName()), $form_controls) && !$hidden)
			$target = $this->_labelTarget->getId();

		$inputs = implode('', $this->_inputs);
		$help = $this->_getHelp();

		if (!isset($config['system']['webgui']['webguileftcolumnhyper']))
			$target = null;

		$label = new Form_Element('label', false, ['for' => $target]);
		$label->addClass('col-sm-'.Form::LABEL_WIDTH, 'control-label');

		$title = htmlspecialchars(gettext($this->_title));

		return <<<EOT
	{$element}
		{$label}
			{$title}
		</label>
		{$inputs}
		{$help}
	</div>
EOT;
	}
}