<?php
/*
 * Input.class.php
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

		foreach ($attributes as $name => $value)
			$this->_attributes[$name] = $value;
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
		if($text)
			$this->_attributes['onclick'] = $text;

		return $this;
	}

	public function setOnchange($text)
	{
		if($text)
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
