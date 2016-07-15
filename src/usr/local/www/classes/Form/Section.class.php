<?php
/*
 * Section.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2015 Sjon Hortensius
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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
	// The confirm password element is created by apprnding "_confirm" to the name supplied
	// The value is overwritten with a default pattern (So the user cannot see it)
	public function addPassword(Form_Input $input)
	{
		$group = new Form_Group($input->getTitle());
		if($input->getValue() != "") {
			$input->setValue(DMYPWD);
		}

		$input->setType("password");
		$group->add($input);
		$confirm = clone $input;
		$confirm->setName($confirm->getName() . "_confirm");
		$confirm->setHelp("Confirm");
		$group->add($confirm);
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

		if ($this->_collapsible & COLLAPSIBLE) {
			$hdricon = '<span class="widget-heading-icon">' .
				'<a data-toggle="collapse" href="#' . $this->_attributes['id'] . '_panel-body">' .
					'<i class="fa fa-plus-circle"></i>' .
				'</a>' .
			'</span>';
			$bodyclass = '<div id="' . $this->_attributes['id'] . '_panel-body" class="panel-body collapse ';
			if (($this->_collapsible & SEC_CLOSED)) {
				$bodyclass .= 'out">';
			} else {
				$bodyclass .= 'in">';
			}
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
