<?php

/*
	itemid.inc

	part of pfSense (https://www.pfsense.org)
	Copyright (c) 2009-2016 Electric Sheep Fencing, LLC.
	Copyright (C) 2009 Janne Enberg <janne.enberg@lietu.net>
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in
	   the documentation and/or other materials provided with the
	   distribution.

	3. All advertising materials mentioning features or use of this software
	   must display the following acknowledgment:
	   "This product includes software developed by the pfSense Project
	   for use in the pfSense® software distribution. (http://www.pfsense.org/).

	4. The names "pfSense" and "pfSense Project" must not be used to
	   endorse or promote products derived from this software without
	   prior written permission. For written permission, please contact
	   coreteam@pfsense.org.

	5. Products derived from this software may not be called "pfSense"
	   nor may "pfSense" appear in their names without prior written
	   permission of the Electric Sheep Fencing, LLC.

	6. Redistributions of any form whatsoever must retain the following
	   acknowledgment:

	"This product includes software developed by the pfSense Project
	for use in the pfSense software distribution (http://www.pfsense.org/).

	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
	OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/****f* itemid/delete_id
 * NAME
 *   delete_id - delete an item with ['id'] = $id from $array
 * INPUTS
 *   $id       - int: The ID to delete
 *   $array    - array to delete the item from
 * RESULT
 *   boolean   - true if item was found and deleted
 ******/
function delete_id($id, &$array) {
	// Index to delete
	$delete_index = NULL;

	if (!is_array($array)) {
		return false;
	}

	// Search for the item in the array
	foreach ($array as $key => $item) {
		// If this item is the one we want to delete
		if (isset($item['associated-rule-id']) && $item['associated-rule-id'] == $id) {
			$delete_index = $key;
			break;
		}
	}

	// If we found the item, unset it
	if ($delete_index !== NULL) {
		unset($array[$delete_index]);
		return true;
	} else {
		return false;
	}

}

/****f* itemid/get_id
 * NAME
 *   get_id - Get an item id with ['associated-rule-id'] = $id from $array
 * INPUTS
 *   $id       - string: The ID to get
 *   $array    - array to get the item from
 * RESULT
 *   mixed   - The id, NULL if not found
 ******/
function get_id($id, &$array) {
	// Use $foo = &get_id('id', array('id'=>'value'));

	if (!is_array($array)) {
		return false;
	}

	// Search for the item in the array
	foreach ($array as $key => $item) {
		// If this item is the one we want to delete
		if (isset($item['associated-rule-id']) && $item['associated-rule-id'] == $id) {
			return $key;
		}
	}

	return false;
}

/****f* itemid/get_unique_id
 * NAME
 *   get_unique_id - get a unique identifier
 * RESULT
 *   string     - unique id
 ******/
function get_unique_id() {

	return uniqid("nat_", true);
}

?>
