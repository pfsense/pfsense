<?php

/*
	pfSense_MODULE:	util
*/

/*
		Copyright (C) 2009 Janne Enberg <janne.enberg@lietu.net>
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

		DISABLE_PHP_LINT_CHECKING
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

/****f* itemid/get_next_id
 * NAME
 *   get_next_id - find the next available id from an item list
 * INPUTS
 *   $array      - array of items to get the id for
 * RESULT
 *   integer     - the next available id
 ******/
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