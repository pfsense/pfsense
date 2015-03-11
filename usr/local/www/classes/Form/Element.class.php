<?php

class Form_Element
{
	protected $_classes = array();
	protected $_parent;

	public function addClass()
	{
		foreach (func_get_args() as $class) {
			$this->_classes[$class] = true;
		}

		return $this;
	}

	public function removeClass($class)
	{
		unset($this->_classes[$class]);

		return $this;
	}

	public function getHtmlClass($wrapped = true)
	{
		if (empty($this->_classes))
				return '';

		$list = implode(' ', array_keys($this->_classes));

		if (!$wrapped)
			return $list;

		return 'class="'. $list .'"';
	}

	protected function _setParent(Form_Element $parent)
	{
		$this->_parent = $parent;
	}
}
