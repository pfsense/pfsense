<?php

foreach (glob('Form/*.class.php') as $file)
	require($file);

class Form
{
	protected $_sections = array();

	public function add(Form_Section $section)
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
