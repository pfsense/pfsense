<?php

class Form_StaticText extends Form_Input
{
	protected $_text;

	public function __construct($title, $text)
	{
		parent::__construct($title, null);

		$this->_text = $text;
	}

	protected function _getInput()
	{
		return $this->_text;
	}
}