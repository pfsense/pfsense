<?php

class Form_Input extends Form_Element
{
	protected $_title;
	protected $_attributes;
	protected $_help;
	protected $_helpParams = array();
	protected $_columnWidth;
	protected $_columnClasses = array();

	public function __construct($title, $type = 'text', $value = null, array $attributes = array())
	{
		$this->_title = $title;
		$this->addClass('form-control');

		if (isset($type))
			$attributes['type'] = $type;

		if (isset($value))
			$attributes['value'] = $value;

		$attributes['name'] = preg_replace('~[^a-z0-9_]+~', '-', strtolower($title));
		$attributes['id'] = $attributes['name'];
		$this->_attributes = $attributes;

		return $this;
	}

	public function forceName($name)
	{
		$this->setAttribute('name', $name);

		return $this;
	}

	public function getTitle()
	{
		return $this->_title;
	}

	public function getName()
	{
		return $this->_attributes['name'];
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
		if ($size < 1 || $size > 12)
			throw new Exception('Incorrect size, pass a number between 1 and 12');

		$this->removeColumnClass('col-sm-'. $this->_columnWidth);

		$this->_columnWidth = (int)$size;

		$this->addColumnClass('col-sm-'. $this->_columnWidth);

		return $this;
	}

	public function setAttribute($key, $value = null)
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

		foreach ($this->_attributes as $key => $value)
			$html .= ' '.$key. (isset($value) ? '="'. htmlspecialchars($value).'"' : '');

		return $html .'/>';
	}

	public function __toString()
	{
		$this->_attributes['class'] = $this->getHtmlClass(false);

		$input = $this->_getInput();

		if (isset($this->_help))
		{
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