<?
	/* $Id:  */

	/*
	 *	Two Arrays the hold the information for what javascript files
	 *	need to be loaded at the top and or bottom of each page listed
	 *	in the arrays.
	 */

	$top_javascript_files = array();
	$bottom_javascript_files = array();


	$top_javascript_files['firewall_rules_edit.php'][] = 'autosuggest.js';
	$top_javascript_files['firewall_rules_edit.php'][] = 'suggestions.js';
	$top_javascript_files['firewall_rules_edit.php'][] = 'firewall_rules_edit.js';

?>