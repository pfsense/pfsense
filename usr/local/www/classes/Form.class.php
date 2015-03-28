<?php
/*
	Form.class.php

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

require_once('classes/Form/Element.class.php');
require_once('classes/Form/Input.class.php');
foreach (glob('classes/Form/*.class.php') as $file)
	require_once($file);

class Form extends Form_Element
{
	protected $_tagName = 'form';
	protected $_attributes = array(
		'class' => array('form-horizontal' => true),
		'method' => 'post',
		// Empty is interpreted by all browsers to submit to the current URI
		'action' => '',
	);
	protected $_sections = array();
	protected $_global = array();
	protected $_labelWidth = 2;

	public function __construct()
	{
		$this->addGlobal(new Form_Button(
			'save',
			'Save'
		));
	}

	public function add(Form_Section $section)
	{
		array_push($this->_sections, $section);
		$section->_setParent($this);

		return $section;
	}

	public function setLabelWidth($size)
	{
		if ($size < 1 || $size > 12)
			throw new Exception('Incorrect size, pass a number between 1 and 12');

		$this->_labelWidth = (int)$size;
	}

	public function setAction($url)
	{
		$this->_attributes['action'] = $url;

		return $this;
	}

	public function getLabelWidth()
	{
		return $this->_labelWidth;
	}

	public function addGlobal(Form_Input $input)
	{
		array_push($this->_global, $input);

		return $input;
	}

	protected function _setParent()
	{
		throw new Exception('Form does not have a parent');
	}

	public function __toString()
	{
		$element = parent::__toString();
		$html = implode('', $this->_sections);

		if (isset($this->_submit))
		{
			$this->_submit->setWidth(12 - $this->getLabelWidth());
			$this->_submit->addColumnClass('col-sm-offset-'. $this->_labelWidth);
			$$html .= $this->_submit;
		}

		$html .= implode('', $this->_global);

		return <<<EOT
	{$element}
		{$html}
	</form>
EOT;
	}
}