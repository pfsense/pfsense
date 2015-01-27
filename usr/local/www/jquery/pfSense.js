/*
 *  This file should only contain functions that will be used on more than 2 pages
 */

$(function() {
	// Enable popovers globally
	$('[data-toggle="popover"]').popover()

	while (func = window.events.shift())
		func();
});