<?php
/*
 * Form.class.php
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

class Form extends Form_Element
{
	const LABEL_WIDTH = 2;
	const MAX_INPUT_WIDTH = 10;
	protected $_tagName = 'form';
	protected $_attributes = array(
		'class' => array('form-horizontal' => true),
		'method' => 'post',
	);
	protected $_sections = array();
	protected $_global = array();

	public function __construct($submit = null)
	{
		if (!isset($submit)) {
			$submit = gettext('Save');
		}

		if (gettype($submit) == 'string') {
			$submit = new Form_Button(
				'save',
				$submit,
				null,
				'fa-save'
			);
			$submit->addClass('btn-primary');
		}

		if (false !== $submit)
			$this->addGlobal($submit);

		if (!isset($this->_attributes['action']))
			$this->_attributes['action'] = $_SERVER['REQUEST_URI'];
	}

	public function add(Form_Section $section)
	{
		array_push($this->_sections, $section);
		$section->_setParent($this);

		return $section;
	}

	public function setAction($url)
	{
		$this->_attributes['action'] = $url;

		return $this;
	}

	public function addGlobal(Form_Input $input)
	{
		array_push($this->_global, $input);

		return $input;
	}

	public function setMultipartEncoding()
	{
		$this->_attributes['enctype'] = 'multipart/form-data';

		return $this;
	}

	protected function _setParent()
	{
		throw new Exception('Form does not have a parent');
	}

	public function __toString()
	{
		$element = parent::__toString();
		$html = implode('', $this->_sections);
		$buttons = '';

		foreach ($this->_global as $global)
		{
			if ($global instanceof Form_Button)
				$buttons .= $global;
			else
				$html .= $global;
		}

		if (!empty($buttons))
		{
			$group = new Form_Element;
			$group->addClass('col-sm-'. Form::MAX_INPUT_WIDTH, 'col-sm-offset-'. Form::LABEL_WIDTH);

			$html .= $group . $buttons .'</div>';
		}

		return <<<EOT
	{$element}
		{$html}
	</form>
EOT;
	}
}
