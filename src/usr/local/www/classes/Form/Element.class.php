<?php
/*
 * Element.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Sjon Hortensius
 * Copyright (c) 2015-2016 Electric Sheep Fencing
 * Copyright (c) 2015-2020 Rubicon Communications, LLC (Netgate)
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

class Form_Element
{
	protected $_tagName;
	protected $_tagSelfClosing;
	protected $_attributes;
	protected $_parent;

	public function __construct($tagName = 'div', $selfClose = false, $attributes = array('class' => array()))
	{
		$this->_tagName = $tagName;
		$this->_tagSelfClosing = $selfClose;
		$this->_attributes = $attributes;
	}

	public function addClass()
	{
		foreach (func_get_args() as $class)
			$this->_attributes['class'][$class] = true;

		return $this;
	}

	public function removeClass($class)
	{
		unset($this->_attributes['class'][$class]);

		return $this;
	}

	public function setAttribute($key, $value = null)
	{
		$this->_attributes[ $key ] = $value;
		return $this;
	}

	public function __toString()
	{
		$attributes = '';
		foreach ($this->_attributes as $key => $value)
		{
			if (is_array($value))
			{
				// Used for classes. If it's empty, we don't want the attribute at all
				if (!empty($value))
					$value = implode(' ', array_keys($value));
				else
					$value = null;
			}

			if ($value === null)
				continue;

			if ($key == "icon")
				continue;

			$attributes .= ' '. $key;
			if ($value !== true)
				$attributes .= '="' . htmlspecialchars($value) . '"';
		}

		if (isset($this->_attributes['icon'])) {
			$rv = '<'. $this->_tagName . $attributes .'>' .
				'<i class="fa ' . $this->_attributes['icon'] . ' icon-embed-btn' . '">' . ' </i>' .
				htmlspecialchars($this->_attributes['value']);

			if ($this->_tagName != 'a') {
				$rv .= '</' . $this->_tagName . '>';
			}

			return $rv;
		} else {
			return '<'. $this->_tagName . $attributes . ($this->_tagSelfClosing ? '/' : '') .'>';
		}
	}

	protected function _setParent(Form_Element $parent)
	{
		$this->_parent = $parent;
	}
}
