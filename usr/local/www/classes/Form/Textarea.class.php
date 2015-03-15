<?php

class Form_Textarea extends Form_Input
{
	protected $_value;

	public function __construct($name, $title, $value)
	{
		parent::__construct($name, $title, null);

		$this->_value = $value;
		$this->setAttribute('rows', 5);
	}

	protected function _getInput()
	{
		$element = preg_replace('~^<input(.*)/>$~', 'textarea\1', parent::_getInput());

		return <<<EOT
	<{$element}>
		{$options}
	</textarea>
EOT;
	}
}