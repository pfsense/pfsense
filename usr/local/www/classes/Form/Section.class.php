<?php

class Form_Section extends Form_Element
{
	protected $_title;
	protected $_groups = array();

	public function __construct($title)
	{
		$this->_title = $title;
		$this->addClass('panel', 'panel-default');

		return $this;
	}

	public function add(Form_Group $group)
	{
		array_push($this->_groups, $group);
		$group->_setParent($this);

		return $group;
	}

	// Shortcut, adds a group for the specified input
	public function addInput(Form_Input $input)
	{
		$group = new Form_Group($input->getTitle());
		$group->add($input);

		$this->add($group);

		return $input;
	}

	// Potentially allow overloading
	public function getLabelWidth()
	{
		return $this->_parent->getLabelWidth();
	}

	public function __toString()
	{
		$title = gettext($this->_title);
		$body = implode('', $this->_groups);

		return <<<EOT
	<div {$this->getHtmlClass()}>
		<div class="panel-heading">
			<h2 class="panel-title">{$title}</h2>
		</div>

		<div class="panel-body">{$body}</div>
	</div>
EOT;
	}
}
