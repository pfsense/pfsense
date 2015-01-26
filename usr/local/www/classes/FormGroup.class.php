<?php
class FormGroup
{
	protected $_title;
	protected $_inputs = array();
	protected $_labelTarget;
	protected $_labelWidth = 2;
	protected $_help;
	protected $_parent;

	public function __construct($title)
	{
		$this->_title = gettext($title);
	}

	public function add(FormInput $input)
	{
		array_push($this->_inputs, $input);
		$input->_setParent($this);

		// Defaults to first input
		if (!isset($this->_labelTarget))
			$this->_labelTarget = $input;

		return $input;
	}

	public function setLabelTarget(FormInput $input)
	{
		$this->_labelTarget = $input;
	}

	public function setLabelWidth($count)
	{
		$this->_labelWidth = (int)$count;
	}

	public function setHelp($help)
	{
		$this->_help = $help;

		return $this;
	}

	// Should be used by Form* classes only, that's why it has _ prefix
	public function _setParent(FormSection $parent)
	{
		$this->_parent = $parent;
	}

	public function __toString()
	{
		// Automatically determine width for inputs without explicit set
		$spaceLeft = 12 - $this->_labelWidth;
		$missingWidth = array();

		foreach ($this->_inputs as $input)
		{
			$width = $input->getColumnWidth();

			if (isset($width))
				$spaceLeft -= $width;
			else
				array_push($missingWidth, $input);
		}

		foreach ($missingWidth as $input)
			$input->setWidth($spaceLeft / count($missingWidth));

		$target = $this->_labelTarget->getName();
		$inputs = implode('', $this->_inputs);
		$help = isset($this->_help) ? '<div class="col-sm-'. (12 - $this->_labelWidth) .' col-sm-offset-'.$this->_labelWidth.'"><span class="help-block">'. gettext($this->_help). '</span></div>' : '';

		return <<<EOT
	<div class="form-group">
		<label for="{$target}" class="col-sm-{$this->_labelWidth} control-label">
			{$this->_title}
		</label>

		{$inputs}
		{$help}
	</div>
EOT;
	}
}
