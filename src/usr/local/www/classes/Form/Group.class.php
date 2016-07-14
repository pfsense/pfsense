<?php
/*
 * Group.class.php
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

class Form_Group extends Form_Element
{
	protected $_tagName = 'div';
	protected $_attributes = array(
		'class' => array('form-group' => true),
	);
	protected $_title;
	protected $_inputs = array();
	protected $_labelTarget;
	protected $_help;
	protected $_helpParams = array();

	public function __construct($title)
	{
		$this->_title = $title;
	}

	public function add(Form_Input $input)
	{
		array_push($this->_inputs, $input);
		$input->_setParent($this);

		// Defaults to first input
		if (!isset($this->_labelTarget))
			$this->_labelTarget = $input;

		return $input;
	}

	public function setLabelTarget(Form_Input $input)
	{
		$this->_labelTarget = $input;
	}

	public function setHelp($help, array $params = array())
	{
		$this->_help = $help;
		$this->_helpParams = $params;

		return $this;
	}

	public function enableDuplication($max = null, $horiz = false)
	{
		if($horiz)
			$this->addClass('user-duplication-horiz');	// added buttons are 2 cols wide with no offset
		else
			$this->addClass('user-duplication');		// added buttons 10 cols wide with 2 col offset

		if (isset($max))
			$this->_attributes('data-duplicate-max', $max);

		foreach ($this->_inputs as $input)
			$input->setIsRepeated();

		return $this;
	}

	protected function _getHelp()
	{
		if (empty($this->_help))
			return null;

		$group = new Form_Element;
		$group->addClass('col-sm-'. Form::MAX_INPUT_WIDTH, 'col-sm-offset-'. Form::LABEL_WIDTH);

		$help = gettext($this->_help);

		if (!empty($this->_helpParams))
			$help = call_user_func_array('sprintf', array_merge([$help], $this->_helpParams));

		return <<<EOT
	{$group}
		<span class="help-block">
			{$help}
		</span>
	</div>
EOT;
	}

	public function __toString()
	{
		global $config, $user_settings;

		$element = parent::__toString();

		// Automatically determine width for inputs without explicit set
		$spaceLeft = Form::MAX_INPUT_WIDTH;
		$missingWidth = array();

		foreach ($this->_inputs as $input)
		{
			if (count($this->_inputs) > 1 && !$input->hasAttribute('placeholder'))
				$input->setPlaceholder($input->getTitle());

			$width = $input->getWidth();

			if (isset($width))
				$spaceLeft -= $width;
			else
				array_push($missingWidth, $input);
		}

		foreach ($missingWidth as $input)
			$input->setWidth($spaceLeft / count($missingWidth));

		if (strtolower($this->_labelTarget->getType()) == 'hidden')
			$hidden = true;

		$form_controls = array('input', 'select', 'button', 'textarea', 'option', 'optgroup', 'fieldset', 'label');
		if (in_array(strtolower($this->_labelTarget->getTagName()), $form_controls) && !$hidden)
			$target = $this->_labelTarget->getId();

		$inputs = implode('', $this->_inputs);
		$help = $this->_getHelp();

		if (!$user_settings['webgui']['webguileftcolumnhyper'])
			$target = null;

		$label = new Form_Element('label', false, ['for' => $target]);
		$label->addClass('col-sm-'.Form::LABEL_WIDTH, 'control-label');

		if (!empty(trim($this->_title)) || is_numeric($this->_title)) {
			$title = htmlspecialchars(gettext($this->_title));
		}

		return <<<EOT
	{$element}
		{$label}
			{$title}
		</label>
		{$inputs}
		{$help}
	</div>
EOT;
	}
}
