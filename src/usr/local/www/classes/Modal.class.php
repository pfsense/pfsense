<?php
/*
 * Modal.class.php
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
