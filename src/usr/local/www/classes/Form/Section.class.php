<?php
/*
 * Section.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015 Sjon Hortensius
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
	protected $_collapsible;

	public function __construct($title, $id = "", $collapsible = 0)
	{
		if (!empty($id)) {
			$this->_attributes['id'] = $id;
		}

		$this->_title = $title;
		$this->_collapsible = $collapsible;
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

	// Shortcut, adds a group with a password and a confirm password field.
	// The confirm password element is created by appending "_confirm" to the name supplied
	// The value is overwritten with a default pattern (So the user cannot see it)
	public function addPassword(Form_Input $input, $confirmfield=true)
	{
		$group = new Form_Group($input->getTitle());
		if ($input->getValue() != "") {
			$input->setValue(DMYPWD);
		}

		$input->setType("password");
		$input->setAttribute('autocomplete', 'new-password');
		$group->add($input);
		if ($confirmfield) {
			$confirm = clone $input;
			$confirm->setName($confirm->getName() . "_confirm");
			$confirm->setHelp("Confirm");
			$group->add($confirm);
		}
		$this->add($group);

		return $input;
	}

	public function __toString()
	{
		$element = parent::__toString();

		if (!empty(trim($this->_title)) || is_numeric($this->_title))
			$title = htmlspecialchars(gettext($this->_title));
		else
			$title = '';

		$body = implode('', $this->_groups);
		$hdricon = "";
		$bodyclass = '<div class="panel-body">';
		$id = $this->_attributes['id'];

		if (intval($this->_collapsible) & COLLAPSIBLE) {
			$hdricon = '<span class="widget-heading-icon">' .
				'<a data-toggle="collapse" href="#' . $id . '_panel-body">' .
					'<i class="fa fa-plus-circle"></i>' .
				'</a>' .
			'</span>';
			$bodyclass = '<div id="' . $id . '_panel-body" class="panel-body collapse ';
			if (($this->_collapsible & SEC_CLOSED)) {
				$bodyclass .= 'out">';
			} else {
				$bodyclass .= 'in">';
			}
		}

		if ($title == "NOTITLE") {
			return <<<EOT
	{$element}
		{$bodyclass}
			{$body}
		</div>
	</div>
EOT;
		} else if ($id == "") {
			return <<<EOT2
	{$element}
		<div class="panel-heading">
			<h2 class="panel-title">{$title}{$hdricon}</h2>
		</div>
		{$bodyclass}
			{$body}
		</div>
	</div>
EOT2;
		} else {
		// If an ID has been specified for this section, include an anchor tag in the header named with the ID
		// so that hrefs can jump directly to it

			return <<<EOT3
	{$element}
		<div class="panel-heading">
			<h2 class="panel-title"><a name="{$id}">{$title}</a>{$hdricon}</h2>
		</div>
		{$bodyclass}
			{$body}
		</div>
	</div>
EOT3;
		}
	}
}
