<?php
/*
	Form_MultiCheckboxGroup.class.php

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
class Form_MultiCheckboxGroup extends Form_Group
{
	public function add(Form_MultiCheckbox $input)
	{
		return parent::add($input);
	}

	public function __toString()
	{
		$element = Form_Element::__toString();
		$column = new Form_Element;
		$column->addClass('checkbox', 'multi', 'col-sm-10');

		$inputs = implode('', $this->_inputs);
		$help = $this->_getHelp();

		$label = new Form_Element('label');
		$label->addClass('col-sm-'.Form::LABEL_WIDTH, 'control-label');

		$title = htmlspecialchars(gettext($this->_title));

		return <<<EOT
	{$element}
		{$label}
			{$title}
		</label>

		{$column}
			{$inputs}
		</div>

		{$help}
	</div>
EOT;
	}
}