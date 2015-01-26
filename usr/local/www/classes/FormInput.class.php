<?php
class FormInput
{
	protected $_title;
	protected $_name;
	protected $_attributes;
	protected $_help;
	protected $_columnWidth;
	protected $_columnClasses = array();
	protected $_parent;

	public function __construct($title, $type = 'text', $value = null, array $attributes = array())
	{
		if (isset($type))
			$attributes['type'] = $type;

		if (isset($value))
			$attributes['value'] = $value;

		$attributes['class'] .= ' form-control';

		$this->_title = $title;
		$this->_name = preg_replace('~[^a-z0-9_]+~', '-', strtolower($title));
		$this->_attributes = $attributes;
	}

	public function forceName($name)
	{
		$this->_name = $name;

		return $this;
	}

	public function getTitle()
	{
		return $this->_title;
	}

	public function getName()
	{
		return $this->_name;
	}

	public function setHelp($help)
	{
		$this->_help = $help;

		return $this;
	}

	public function getColumnWidth()
	{
		return $this->_columnWidth;
	}

	public function setWidth($count, $soft = false)
	{
		if ($soft && isset($this->_columnWidth))
			return;

		$this->_columnWidth = (int)$count;
	}

	// Should be used by Form* classes only, that's why it has _ prefix
	public function _setParent(FormGroup $parent)
	{
		$this->_parent = $parent;
	}

	public function addColumnClass($class)
	{
		array_push($this->_columnClasses, $class);
	}

	protected function _getInput()
	{
		$html = '<input';
		foreach ($this->_attributes as $key => $value)
			$html .= ' '.$key.'="'. htmlspecialchars($value).'"';

		return $html .'/>';
	}

	public function __toString()
	{
		$input = $this->_getInput();
		$columnClasses = implode(' ', $this->_columnClasses);
		$help = isset($this->_help) ? '<span class="help-block">'. gettext($this->_help). '</span>' : '';

		return <<<EOT
	<div class="col-sm-{$this->_columnWidth} {$columnClasses}">
		{$input}

		{$help}
	</div>
EOT;
	}
}
