<?php
/*
 * Form.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

	public function __construct($submit = null, $enabled = true)
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

			if (!$enabled) {
				$submit->setAttribute("disabled", true);
			}

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

	protected function _setParent(Form_Element $parent)
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
