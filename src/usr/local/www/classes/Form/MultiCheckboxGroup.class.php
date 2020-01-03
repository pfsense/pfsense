<?php
/*
 * MultiCheckboxGroup.class.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

class Form_MultiCheckboxGroup extends Form_Group
{
	public function add(Form_Input $input)
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

		if (!empty(trim($this->_title)) || is_numeric($this->_title))
			$title = htmlspecialchars(gettext($this->_title));
		else
			$title = '';

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
