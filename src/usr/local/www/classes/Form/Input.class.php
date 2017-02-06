<?php
/*
 * Input.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

class Form_Input extends Form_Element
{
	public $column;
	protected $_tagName = 'input';
	protected $_tagSelfClosing = false;
	protected $_attributes = array(
		'class' => array('form-control' => true),
		'name' => null,
		'id' => null,
		'title' => null,
	);
	protected $_title;
	protected $_help;
	protected $_helpParams = array();
	protected $_columnWidth;

	public function __construct($name, $title, $type = 'text', $value = null, array $attributes = array())
	{
		$this->column = new Form_Element;

		$this->_attributes['name'] = $name;
		$this->_attributes['id'] = $name;
		$this->_title = $title;

		if (isset($type))
			$this->_attributes['type'] = $type;

		switch ($type)
		{
			case 'number':
				$attributes += array('min' => 1, 'step' => 1);
			break;
			case 'file':
				unset($this->_attributes['class']['form-control']);
			break;
		}

		if (isset($value))
			$this->_attributes['value'] = $value;

		foreach ($attributes as $attr_name => $attr_value)
			$this->_attributes[$attr_name] = $attr_value;
	}

	public function getTitle()
	{
		return $this->_title;
	}

	public function getValue()
	{
		return $this->_attributes['value'];
	}

	public function getName()
	{
		return $this->_attributes['name'];
	}

	public function setName($nm)
	{
		$this->_attributes['name'] = $nm;
		$this->_attributes['id'] = $nm;
	}

	public function setValue($val)
	{
		$this->_attributes['value'] = $val;
	}

	public function setType($tp)
	{
		$this->_attributes['type'] = $tp;
	}

	public function getId()
	{
		return $this->_attributes['id'];
	}

	public function getType()
	{
		return $this->_attributes['type'];
	}

	public function getTagName()
	{
		return $this->_tagName;
	}

	public function setHelp($help, array $params = array())
	{
		$this->_help = $help;
		$this->_helpParams = $params;

		return $this;
	}

	public function getWidth()
	{
		return $this->_columnWidth;
	}

	public function setWidth($size)
	{
		if ($size < 1 || $size > Form::MAX_INPUT_WIDTH)
			throw new Exception('Incorrect size, pass a number between 1 and '.Form::MAX_INPUT_WIDTH);

		$this->column->removeClass('col-sm-'. $this->_columnWidth);

		$this->_columnWidth = (int)$size;

		$this->column->addClass('col-sm-'. $this->_columnWidth);

		return $this;
	}

	public function setCols($size)
	{
		$this->_attributes['cols'] = $size;

		return $this;
	}

	public function setReadonly()
	{
		$this->_attributes['readonly'] = 'readonly';

		return $this;
	}

	public function setDisabled()
	{
		$this->_attributes['disabled'] = 'disabled';

		return $this;
	}

	public function setIsRequired()
	{
		$this->_attributes['required'] = true;

		return $this;
	}

	public function toggles($selector = null, $type = 'collapse')
	{
		if (isset($selector))
			$this->_attributes['data-target'] = $selector;

		$this->_attributes['data-toggle'] = $type;

		return $this;
	}

	public function setPattern($regexp)
	{
		$this->_attributes['pattern'] = $regexp;

		return $this;
	}

	public function setPlaceholder($text)
	{
		$placeholder_input_types = array('email', 'number', 'password', 'search', 'tel', 'text', 'url');
		if (in_array(strtolower($this->_attributes['type']), $placeholder_input_types))
			$this->_attributes['placeholder'] = $text;

		return $this;
	}

	public function hasAttribute($name)
	{
		// not strict, null should return false as well
		return isset($this->_attributes[$name]);
	}

	public function setIsRepeated()
	{
		$this->_attributes['name'] .= '[]';
		// No I don't like this. Yes it works fine
		$this->_attributes['id'] .= ':'.substr(uniqid(), 9);

		return $this;
	}

	// These methods required by pkg_edit and the wizards that map xml element definitions to Form elements
	public function setOnclick($text)
	{
		if ($text)
			$this->_attributes['onclick'] = $text;

		return $this;
	}

	public function setOnchange($text)
	{
		if ($text)
			$this->_attributes['onchange'] = $text;

		return $this;
	}

	protected function _getInput()
	{
		return parent::__toString();
	}

	public function __toString()
	{
		$input = $this->_getInput();
		$column = (string)$this->column;

		// Don't add an empty <div>, skip it instead
		if (!isset($this->_help) && '<div>' == $column)
			return (string)$input;

		if (!empty($this->_help))
		{
			/* Strings longer than this will break gettext. */
			if (strlen($this->_help) < 4096) {
				$help = gettext($this->_help);
			} else {
				$help = $this->_help;
			}

			if (!empty($this->_helpParams))
				$help = call_user_func_array('sprintf', array_merge([$help], $this->_helpParams));

			$help = '<span class="help-block">'. $help .'</span>';
		}

		return <<<EOT
	{$column}
		{$input}

		{$help}
	</div>
EOT;
	}
}
