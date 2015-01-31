<?php

class Form_Select extends Form_Input
{
	protected $_values;

	public function __construct($title, $value, array $values, $allowMultiple = false)
	{
		parent::__construct($title, null);

		if ($allowMultiple)
			$this->_attributes['multiple'] = 'multiple';

		$this->_values = $values;
	}

	protected function _getInput()
	{
		$element = preg_replace('~^<input(.*)/>$~', 'select\1', parent::_getInput());

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