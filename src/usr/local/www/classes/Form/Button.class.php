<?php
/*
 * Button.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Sjon Hortensius
 * Copyright (c) 2015-2016 Electric Sheep Fencing
 * Copyright (c) 2015-2021 Rubicon Communications, LLC (Netgate)
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

class Form_Button extends Form_Input
{
	protected $_tagSelfClosing = false;
	protected $_attributes = array(
		'class' => array(
			'btn' => true,
		),
		'type' => 'submit',
	);

	public function __construct($name, $title, $link = null, $icon = null)
	{
		if (!empty($title) || is_numeric($title)) {
			$title_str = gettext($title);
		} else {
			$title_str = "";
		}

		// If we have a link; we're actually an <a class='btn'>
		if (isset($link))
		{
			$this->_attributes['href'] = $link;
			$this->_tagName = 'a';
			$this->addClass('btn-default');
			unset($this->_attributes['type']);
			if (isset($icon)) {
				$this->_attributes['icon'] = $icon;
			}
		}
		else if (isset($icon))
		{
			$this->_tagSelfClosing = false;
			$this->_tagName = 'button';
			$this->_attributes['value'] = $title_str;
			$this->_attributes['icon'] = $icon;
		}
		else
		{
			$this->_tagSelfClosing = true;
			$this->_attributes['value'] = $title_str;
			$this->addClass('btn-primary');
		}

		parent::__construct($name, $title, null);

		if (isset($link))
			unset($this->_attributes['name']);
	}

	protected function _getInput()
	{
		$input = parent::_getInput();

		if (!isset($this->_attributes['href']))
			return $input;

		return $input . htmlspecialchars($this->_title) .'</a>';
	}
}
