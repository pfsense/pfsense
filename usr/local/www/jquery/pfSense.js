/*
 * This file should only contain functions that will be used on more than 2 pages
 */

$(function() {
	// Enable popovers globally
	$('[data-toggle="popover"]').popover()

	runEvents();
	bindCollapseToOptions();

	/**
	 * Run in-page defined events
	 */
	var runEvents = function()
	{
		while (func = window.events.shift())
			func();
	};

	/**
	 * Attach collapsable behaviour to select options
	 */
	var bindCollapseToOptions = function()
	{
		var selects = $('select[data-toggle="collapse"]');

		selects.on('change', function () {
			var options = $(this).find('option');
			var selectedValue = $(this).find(':selected').val();

			// Hide related collapsables which are visible (.in)
			options.each(function () {
				if ($(this).val() == selectedValue)
					return;

				$('.toggle-' + $(this).val() + '.in').collapse('hide');
			});

			$('.toggle-' + selectedValue).collapse('show');
		});

		// Trigger change to open currently selected item
		selects.trigger('change');
	};
});