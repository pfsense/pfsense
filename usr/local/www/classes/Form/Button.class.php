<?php

class Form_Button extends Form_Input
{
	protected $_link;

	public function __construct($name, $value, $link = null)
	{
		$this->_link = $link;

		$type = isset($this->_link) ? null : 'submit';

		parent::__construct($name, $value, $type);

		$this->removeClass('form-control')->addClass('btn');

		if ('submit' == $type)
			$this->addClass('btn-primary');
		else
			$this->addClass('btn-default');
	}

	protected function _getInput()
	{
		if (!isset($this->_link))
			return parent::_getInput();

		$element = preg_replace('~^<input(.*)/>$~', 'a\1', parent::_getInput());

		return <<<EOT
	<{$element} href="{$this->_link}">
		{$this->_title}
	</a>
EOT;
	}
}