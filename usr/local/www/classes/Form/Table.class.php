<?php
/*
	Table.class.php

	Copyright© 2015 Rubicon Communications, LLC (Netgate)
	This file is a part of pfSense®

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
/*
	class expects a title and an 'array of arrrays', representing an array of rows in the table
	Row 0 is assumed to contain the table column headers, rows 1 -> X contain the table data
*/
class Form_Table extends Form_Input
{
	protected $_text;

	public function __construct($title, $data)
	{
		parent::__construct(null, $title);

		$text = "<table class=\"table table-condensed\"><thead>";
		$text .= "<tr>";

		/* Assume the number of elements in row 0 represents hte width of hte table */
		$rows = count($data);
		$columns = count($data[0]);

		/* Draw the table tile row */
		for($c=0; $c<$columns; $c++)
			$text .= "<th>" . $data[0][$c] . "</th>";

		$text .= "</tr></thead>";
		$text .= "<tbody>";

		/* Draw each row */
		for($r=1; $r<$rows; $r++) {
			$text .= "<tr>";

			for($c=0; $c<$columns;$c++) {
				$text .= "<td>" . $data[$r][$c] . "</td>";
			}

			$text .= "</tr>";
		}

		$text .= "</tbody></table>";

		$this->_text = $text;
	}

	protected function _getInput()
	{
		return $this->_text;
	}
}
