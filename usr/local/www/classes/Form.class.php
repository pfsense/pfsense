<?php

require_once('classes/Form/Element.class.php');
require_once('classes/Form/Input.class.php');
foreach (glob('classes/Form/*.class.php') as $file)
	require_once($file);

class Form extends Form_Element
{
	protected $_sections = array();
	protected $_global = array();
	protected $_labelWidth = 2;
	// Empty is interpreted by all browsers to submit to the current URI
	protected $_action;

	public function __construct()
	{
		$this->addGlobal(new Form_Button(
			'save',
			'Save'
		));
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

	public function setAction($uri)
	{
		$this->_action = $uri;
	}

	public function getLabelWidth()
	{
		return $this->_labelWidth;
	}

	public function addGlobal(Form_Input $input)
	{
		array_push($this->_global, $input);

		return $input;
	}

	protected function _setParent()
	{
		throw new Exception('Form does not have a parent');
	}

	public function __toString()
	{
		$html = implode('', $this->_sections);

		if (isset($this->_submit))
		{
			$this->_submit->setWidth(12 - $this->getLabelWidth());
			$this->_submit->addColumnClass('col-sm-offset-'. $this->_labelWidth);
			$$html .= $this->_submit;
		}

		$html .= implode('', $this->_global);

		return <<<EOT
	<form class="form-horizontal" action="{$this->_action}" method="post">
		{$html}
	</form>
EOT;
	}
}