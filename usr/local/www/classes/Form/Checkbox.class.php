<?php

class Form_Checkbox extends Form_Input
{
	protected $_description;

	public function __construct($title, $description, $checked, $value = 'yes')
	{
		parent::__construct($title, 'checkbox');

		$this->_description = $description;
		$this->removeClass('form-control');

		if ($checked)
			$this->_attributes['checked'] = 'checked';
	}

	protected function _getInput()
	{
		$this->addColumnClass('checkbox');
		$input = parent::_getInput();

		if (!isset($this->_description))
			return $input;

		return '<label>'. $input .' '. gettext($this->_description) .'</label>';
	}
}