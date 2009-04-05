<?php
function get_gmirror_status() {
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
} ?>