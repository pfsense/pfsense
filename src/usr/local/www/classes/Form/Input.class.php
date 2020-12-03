<?php
/*
 * Input.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

	public function setHelp()
	{
		$args = func_get_args();
		$arg0_len = strlen($args[0]);

		if (($arg0_len > 0) && ($arg0_len < 4096)) {
			$args[0] = gettext($args[0]);
		}

		if (func_num_args() == 1) {
			$this->_help = $args[0];
		} else {
			$this->_help = call_user_func_array('sprintf', $args);
		}

		$this->_helpParams = "";

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

		if (!empty($this->_help)) {
			if ($this->_help[0] == '*') { // Used to indicate a required item
				$help = '<span class="help-block-underlined">' . substr($this->_help, 1) . '</span>';
			} else {
				$help = '<span class="help-block">'. $this->_help .'</span>';
			}
		}

		return <<<EOT
	{$column}
		{$input}

		{$help}
	</div>
EOT;
	}
}
