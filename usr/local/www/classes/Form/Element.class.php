<?php

class Form_Element
{
	protected $_classes = array();
	protected $_parent;

	public function addClass($class)
	{
		$this->_classes[$class] = true;

		return $this;
	}

	public function removeClass($class)
	{
		unset($this->_classes[$class]);

		return $this;
	}

	public function getHtmlClass()
	{
		if (empty($this->_classes))
				return '';

		return ' class="'. implode(' ', array_keys($this->_classes)).'" ';
	}

	protected function _setParent(Form_Element $parent)
	{
		$this->_parent = $parent;
	}
}
