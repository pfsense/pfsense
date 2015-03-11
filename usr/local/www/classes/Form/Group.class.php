<?php

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
