<?php

foreach (array('FormSection', 'FormGroup', 'FormInput', 'FormSelect', 'FormCheckbox') as $class)
	require($class.'.class.php');

class Form
{
	protected $_sections = array();

	public function add(FormSection $section)
	{
		array_push($this->_sections, $section);
		$section->_setParent($this);

		return $section;
	}

	public function __toString()
	{
		$sections = implode('', $this->_sections);

		return <<<EOT
	<form class="form-horizontal" method="post">
		{$sections}
	</form>
EOT;
	}
}
