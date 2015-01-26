<?php
class FormSection
{
	protected $_title;
	protected $_parent;
	protected $_groups = array();

	public function __construct($title)
	{
		$this->_title = $title;
	}

	public function add(FormGroup $group)
	{
		array_push($this->_groups, $group);
		$group->_setParent($this);

		return $group;
	}

	// Shortcut, adds a group for the specified input
	public function addInput(FormInput $input)
	{
		$group = new FormGroup($input->getTitle());
		$group->add($input);

		$this->add($group);

		return $input;
	}

	// Should be used by Form* classes only, that's why it has _ prefix
	public function _setParent(Form $parent)
	{
		$this->_parent = $parent;
	}

	public function __toString()
	{
		$title = gettext($this->_title);
		$body = implode('', $this->_groups);

		return <<<EOT
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">{$title}</h2>
		</div>

		<div class="panel-body">{$body}</div>
	</div>
EOT;
	}
}
