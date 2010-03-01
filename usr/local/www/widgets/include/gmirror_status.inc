<?php
/*
    gmirror_status.widget.php
    Copyright (C) 2009-2010 Jim Pingle

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

function gmirror_get_status() {
	$status = "";
	exec("/sbin/gmirror status", $status);
	$mirrors = array();

	/* Empty output = no mirrors found */
	if (count($status) > 0) {
		/* We don't need the first row, it's just a header */
		$status = array_slice($status, 1);

		/* Loop through gmirror status output. */
		foreach ($status as $line) {
			/* Split the line by whitespace */
			$all = preg_split("/[\s\t]+/", trim($line), 3);
			if (count($all) == 3) {                        
				/* If there are three items on a line, it is mirror name, status, and component */
				$currentmirror = $all[0];
				$mirrors[$currentmirror]["name"] = $all[0];
				$mirrors[$currentmirror]["status"] = $all[1];
				$mirrors[$currentmirror]["components"] = array();
				$mirrors[$currentmirror]["components"][] = $all[2];
			} elseif ((trim($line) != "") && (count($all) > 0)) {
				/* If there is just one item on a line, it is a component name of the previous mirror */
				$mirrors[$currentmirror]["components"][] = trim($line);
			}
		}
	}
	/* Return an hash of mirrors and components */	
	return $mirrors;
}

function gmirror_html_status() {
	$mirrors = gmirror_get_status();
	$output = "";
	if (count($mirrors) > 0) {
		$output .= "<tr>\n";
		$output .= "<td width=\"40%\" class=\"vncellt\">Name</td>\n";
		$output .= "<td width=\"40%\" class=\"vncellt\">Status</td>\n";
		$output .= "<td width=\"20%\" class=\"vncellt\">Component</td>\n";
		$output .= "</tr>\n";
		foreach ($mirrors as $mirror => $name) {
			$components = count($name["components"]);
			$output .= "<tr>\n";
			$output .= "<td width=\"40%\" rowspan=\"{$components}\" class=\"listr\">{$name['name']}</td>\n";
			$output .= "<td width=\"40%\" rowspan=\"{$components}\" class=\"listr\">{$name['status']}</td>\n";
			$output .= "<td width=\"20%\" class=\"listr\">{$name['components'][0]}</td>\n";
			$output .= "</tr>\n";
			if (count($name["components"]) > 1) {
				$morecomponents = array_slice($name["components"], 1);
				foreach ($morecomponents as $component) {
					$output .= "<tr>\n";
					$output .= "<td width=\"20%\" class=\"listr\">{$component}</td>\n";
					$output .= "</tr>\n";
				}
			}
		}
	} else {
		$output .= "<tr><td colspan=\"3\" class=\"listr\">No Mirrors Found</td></tr>\n";
	}
	// $output .= "<tr><td colspan=\"3\" class=\"listr\">Updated at " . date("F j, Y, g:i:s a") . "</td></tr>\n";
	return $output;
}
?>