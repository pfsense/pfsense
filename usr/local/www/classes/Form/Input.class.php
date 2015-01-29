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

	public function getWidth()
	{
		return $this->_columnWidth;
	}

	public function setWidth($size)
	{
		if ($size < 1 || $size > 12)
			throw new Exception('Incorrect size, pass a number between 1 and 12');

		$this->removeColumnClass('col-sm-'. $this->_columnWidth);

		$this->_columnWidth = (int)$size;

		$this->addColumnClass('col-sm-'. $this->_columnWidth);
	}

	public function setAttribute($key, $value)
	{
		$this->_attributes[ $key ] = $value;

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
		$html = '<input';

		$attributes = $this->_attributes;
		if (isset($this->_name))
		{
			$attributes['name'] = $this->_name;
			$attributes['id'] = $this->_name;
		}

		foreach ($attributes as $key => $value)
			$html .= ' '.$key.'="'. htmlspecialchars($value).'"';

		return $html .'/>';
	}

	public function __toString()
	{
		$this->_attributes['class'] = $this->getHtmlClass(false);

		$input = $this->_getInput();
		$help = isset($this->_help) ? '<span class="help-block">'. gettext($this->_help). '</span>' : '';

		return <<<EOT
	<div {$this->getColumnHtmlClass()}>
		{$input}

		{$help}
	</div>
EOT;
	}
}