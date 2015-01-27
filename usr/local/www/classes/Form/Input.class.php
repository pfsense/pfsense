<?php

class Form_Input extends Form_Element
{
	protected $_title;
	protected $_name;
	protected $_attributes;
	protected $_help;
	protected $_columnWidth;
	protected $_columnClasses = array();

	public function __construct($title, $type = 'text', $value = null, array $attributes = array())
	{
		if (isset($type))
			$attributes['type'] = $type;

		if (isset($value))
			$attributes['value'] = $value;

		$this->_attributes = $attributes;
		$this->addClass('form-control');

		if (isset($title))
		{
			$this->_title = $title;
			$this->_name = preg_replace('~[^a-z0-9_]+~', '-', strtolower($title));
		}
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

	public function addColumnClass($class)
	{
		array_push($this->_columnClasses, $class);
	}

	protected function _getInput()
	{
		$html = '<input '. $this->getHtmlClass();

		$attributes = $this->_attributes;
		if (isset($this->_name))
			$attributes['name'] = $this->_name;

		foreach ($attributes as $key => $value)
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
