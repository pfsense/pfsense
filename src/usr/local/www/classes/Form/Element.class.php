<?php
/*
	Element.class.php

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