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
		foreach ($this->_attributes as $key => $value)
			$element .= ' '.$key.'="'. htmlspecialchars($value).'"';

		$options = '';
		foreach ($this->_values as $value => $name)
			$options .= '<option value="'. htmlspecialchars($value) .'">'. $name .'</option>';

		return <<<EOT
	<{$element}>
		{$options}
	</select>
EOT;
	}
}
