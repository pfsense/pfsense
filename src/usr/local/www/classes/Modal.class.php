<?php
/*
 * Modal.class.php
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

class Modal extends Form_Section
{
	protected $_attributes = array(
		'id' => null,
		'class' => array(
			'modal' => true,
			'fade' => true,
		),
		'role' => 'dialog',
		'aria-labelledby' => null,
		'aria-hidden' => 'true',
	);
	protected $_global = array();
	protected $_isLarge;

	public function __construct($title, $id, $isLarge = false, $submit = null)
	{
		$this->_title = $title;
		$this->_attributes['id'] = $this->_attributes['aria-labelledby'] = $id;
		$this->_isLarge = $isLarge;

		if (gettype($submit) == 'string')
			$submit = (new Form_Button(
				'save',
				$submit
			))->setAttribute('data-dismiss', 'modal');

		if (false !== $submit)
			array_push($this->_global, $submit);
	}

	public function __toString()
	{
		$element = Form_Element::__toString();
		$title = htmlspecialchars(gettext($this->_title));
		$html = implode('', $this->_groups);
		$footer = implode('', $this->_global);
		$modalClass = $this->_isLarge ? 'modal-lg' : 'modal-sm';

		return <<<EOT
	{$element}
		<div class="modal-dialog {$modalClass}">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
					<h3 class="modal-title">{$title}</h3>
				</div>
<!--				<form class="form-horizontal" action="" method="post"> -->
					<div class="modal-body">
						{$html}
					</div>
					<div class="modal-footer">
						{$footer}
					</div>
<!--				</form> -->
			</div>
		</div>
	</div>
EOT;
	}
}
