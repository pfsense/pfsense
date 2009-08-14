<?php

/**
* Delete an item with ['id'] = $id from $array
* @param int The ID to delete
* @param array The list of items to search for the ID from
* @return boolean If the item was found or not
* @author Janne Enberg
*/
function delete_id($id, &$array){
	// Index to delete
	$delete_index = NULL;

	// Search for the item in the array
	foreach ($array as $key => $item){
		// If this item is the one we want to delete
		if(isset($item['id']) && $item['id']==$id ){
			$delete_index = $key;
			break;
		}
	}

	// If we found the item, unset it
	if( $delete_index!==NULL ){
		unset($array[$delete_index]);
		return true;
	} else {
		return false;
	}

}

/**
* Get the next available ID from an item list
* @param array The list of items to generate the ID for
* @return int The next available ID
* @author Janne Enberg
*/
function get_next_id($array){
	// Default value
	$next_id = 1;

	// Search for IDs
	foreach ($array as $item){
		// If this item has an ID, and it's higher or equal to the current "next ID", use that + 1 as the next ID
		if(isset($item['id']) && $item['id']>=$next_id ){
			$next_id = $item['id'] + 1;
		}
	}
	return $next_id;
}

?>