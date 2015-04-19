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

			options.each(function(){
				if ($(this).val() == selectedValue)
					return;

				targets = $('.toggle-'+ $(this).val() +'.in:not(.toggle-'+ selectedValue +')');

				// Hide related collapsables which are visible (.in)
				targets.collapse('hide');

				// Disable all invisible inputs
				targets.find(':input').prop('disabled', true);
			});

			$('.toggle-' + selectedValue).collapse('show').find(':input').prop('disabled', false);
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
		});
	};

	// Find all ipaddress masks and make dynamic based on address family of input
	var syncIpAddressMasks = function()
	{
		$('span.pfIpMask + select').each(function (idx, select){
			var input = $(select).prevAll('input[type=text]');

			input.on('change', function(e){
				var isV6 = (input.val().indexOf(':') != -1), min = 0, max = 128;
				if (!isV6)
					max = 32;

				if (input.val() == "")
					return;

				while (select.options.length > max)
					select.remove(0);

				if (select.options.length < max)
				{
					for (var i=select.options.length; i<=max; i++)
						select.options.add(new Option(i, i), 0);
				}
			})

			// Fire immediately
			input.change();
		});
	};

	// Add confirm to all btn-danger buttons
	$('.btn-danger').on('click', function(e){
		if (!confirm('Are you sure you wish to '+ $.trim(this.textContent) +'?'))
			e.preventDefault();
	});

	// Enable popovers globally
	$('[data-toggle="popover"]').popover()

	// Focus first input
	$(':input:enabled:visible:first').focus();

	runEvents();
	bindCollapseToOptions();
	allowUserGroupDuplication();
	syncIpAddressMasks();
});