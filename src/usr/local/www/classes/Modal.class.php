<?php
/*
 * Modal.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
