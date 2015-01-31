<?php

class Form_Select extends Form_Input
{
	protected $_values;

	public function __construct($title, $value, array $values, $allowMultiple = false)
	{
		parent::__construct($title);

		if ($allowMultiple)
			$this->_attributes['multiple'] = 'multiple';

		$this->_values = $values;
	}

	protected function _getInput()
	{
		$element = 'select';

		$attributes = $this->_attributes;
		if (isset($this->_name))
			$attributes['name'] = $this->_name;

		foreach ($attributes as $key => $value)
			$element .= ' '.$key.'="'. htmlspecialchars($value).'"';

		$options = '';
		foreach ($this->_values as $value => $name)
			$options .= '<option value="'. htmlspecialchars($value) .'">'. gettext($name) .'</option>';

		return <<<EOT
	<{$element}>
		{$options}
	</select>
EOT;
	}
}