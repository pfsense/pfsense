/*
 * This file should only contain functions that will be used on more than 2 pages
 */

$(function() {
	// Run in-page defined events
	var runEvents = function()
	{
		while (func = window.events.shift())
			func();
	};

	// Attach collapsable behaviour to select options
	var bindCollapseToOptions = function()
	{
		var selects = $('select[data-toggle="collapse"]');

		selects.on('change', function(){
			var options = $(this).find('option');
			var selectedValue = $(this).find(':selected').val();

			// Hide related collapsables which are visible (.in)
			options.each(function(){
				if ($(this).val() == selectedValue)
					return;

				$('.toggle-' + $(this).val() + '.in').collapse('hide');

				// Disable all invisible inputs
//				$('.toggle-' + $(this).val() + ' input').prop('disabled', true);
			});

			$('.toggle-' + selectedValue).collapse('show');
		});

		// Trigger change to open currently selected item
		selects.trigger('change');
	};

	// Add +/- buttons to certain Groups; to allow adding multiple entries
	var allowUserGroupDuplication = function()
	{
		var groups = $('div.form-group.user-duplication');
		var plus = $('<a><i class="icon icon-plus"></i></a>');
		var minus = $('<a><i class="icon icon-minus"></i></a>')

		minus.on('click', function(){
			$(this).parent('div.form-group').remove();
		});

		plus.on('click', function(){
			var group = $(this).parent('div.form-group');

			var clone = group.clone(true);
			clone.find('*').removeAttr('value');
			clone.appendTo(group.parent());
		});

		groups.each(function(idx, group){
			minus.clone(true).appendTo(group);

			if (group == group.parentNode.lastElementChild)
				plus.clone(true).appendTo(group);
		})
	};

	// Enable popovers globally
	$('[data-toggle="popover"]').popover()

	runEvents();
	bindCollapseToOptions();
	allowUserGroupDuplication();
});