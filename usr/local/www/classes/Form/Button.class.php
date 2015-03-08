<?php

class Form_Button extends Form_Input
{
	protected $_link;

	public function __construct($name, $title, $link = null)
	{
		// If we have a link; we're actually an <a class='btn'>
		if (isset($link))
		{
			$this->_link = $link;
			$this->addClass('btn-default');
			$type = null;
		}
		else
		{
			$this->addClass('btn-primary');
			$type = 'submit';
		}

		parent::__construct($name, null, $type, $title);

		$this->removeClass('form-control')->addClass('btn');
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