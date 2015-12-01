<?php
/*
	Section.class.php

	Copyright (C) 2015 Sjon Hortensius
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
class Form_Section extends Form_Element
{
	protected $_tagName = 'div';
	protected $_attributes = array(
		'class' => array(
			'panel' => true,
			'panel-default' => true,
		),
	);
	protected $_title;
	protected $_groups = array();
	protected $_collapsable;

	public function __construct($title, $id = "", $collapsable = false)
	{
		if (!empty($id)) {
			$this->_attributes['id'] = $id;
		}
		$this->_title = $title;
		$this->_collapsable = $collapsable;
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

	public function __toString()
	{
		$element = parent::__toString();
		$title = htmlspecialchars(gettext($this->_title));
		$body = implode('', $this->_groups);
		$hdricon = "";
		$bodyclass = '<div class="panel-body">';

		if ($this->_collapsable) {
			$hdricon = '<span class="widget-heading-icon">' .
				'<a data-toggle="collapse" href="#' . $this->_attributes['id'] . ' .panel-body">' .
					'<i class="fa fa-plus-circle"></i>' .
				'</a>' .
			'</span>';
			$bodyclass = '<div class="panel-body collapse in">';
		}

		return <<<EOT
	{$element}
		<div class="panel-heading">
			<h2 class="panel-title">{$title}{$hdricon}</h2>
		</div>
		{$bodyclass}
			{$body}
		</div>
	</div>
EOT;
	}
}
