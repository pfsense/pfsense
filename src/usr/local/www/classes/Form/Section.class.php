<?php
/*
 * Section.class.php
 *
 * part of pfsense (https://www.pfsense.org)
 * copyright (c) 2015 sjon hortensius
 * copyright (c) 2015-2016 electric sheep fencing, llc
 * all rights reserved.
 *
 * redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. all advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "this product includes software developed by the pfsense project
 *    for use in the pfsense® software distribution. (http://www.pfsense.org/).
 *
 * 4. the names "pfsense" and "pfsense project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. for written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. products derived from this software may not be called "pfsense"
 *    nor may "pfsense" appear in their names without prior written
 *    permission of the electric sheep fencing, llc.
 *
 * 6. redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "this product includes software developed by the pfsense project
 * for use in the pfsense software distribution (http://www.pfsense.org/).
 *
 * this software is provided by the pfsense project ``as is'' and any
 * expressed or implied warranties, including, but not limited to, the
 * implied warranties of merchantability and fitness for a particular
 * purpose are disclaimed. in no event shall the pfsense project or
 * its contributors be liable for any direct, indirect, incidental,
 * special, exemplary, or consequential damages (including, but
 * not limited to, procurement of substitute goods or services;
 * loss of use, data, or profits; or business interruption)
 * however caused and on any theory of liability, whether in contract,
 * strict liability, or tort (including negligence or otherwise)
 * arising in any way out of the use of this software, even if advised
 * of the possibility of such damage.
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
