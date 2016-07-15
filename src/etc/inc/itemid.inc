<?php
/*
 * itemid.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2009 Janne Enberg <janne.enberg@lietu.net>
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
