<?php

require_once('classes/Form/Element.class.php');
require_once('classes/Form/Input.class.php');
foreach (glob('classes/Form/*.class.php') as $file)
	require_once($file);

class Form extends Form_Element
{
	protected $_sections = array();
	protected $_labelWidth = 2;
	protected $_submit;

	public function __construct()
	{
		$this->setSubmit(new Form_Input('Save', 'submit', 'Save'))->removeClass('form-control')->addClass('btn btn-primary');
	}

	public function add(Form_Section $section)
	{
		array_push($this->_sections, $section);
		$section->_setParent($this);

		return $section;
	}

	public function setLabelWidth($size)
	{
		if ($size < 1 || $size > 12)
			throw new Exception('Incorrect size, pass a number between 1 and 12');

		$this->_labelWidth = (int)$size;
	}

	public function getLabelWidth()
	{
		return $this->_labelWidth;
	}

	public function setSubmit(Form_Input $submit)
	{
		$this->_submit = $submit;

		return $submit;
	}

	protected function _setParent()
	{
		throw new Exception('Form does not have a parent');
	}

	public function __toString()
	{
		$sections = implode('', $this->_sections);

		if (isset($this->_submit))
		{
			$this->_submit->setWidth(12 - $this->getLabelWidth());
			$this->_submit->addColumnClass('col-sm-offset-'. $this->_labelWidth);
			$sections .= $this->_submit;
		}

		return <<<EOT
	<form class="form-horizontal" method="post">
		{$sections}
	</form>
EOT;
	}
}