/*
 * This file should only contain functions that will be used on more than 2 pages
 */

$(function() {
	// Attach collapsable behaviour to select options
	(function()
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
	})();

	// Add +/- buttons to certain Groups; to allow adding multiple entries
	(function()
	{
		var groups = $('div.form-group.user-duplication');
		var controlsContainer = $('<div class="col-sm-10 col-sm-offset-2 controls"></div>');
		var plus = $('<a class="btn btn-xs btn-success">Duplicate</a>');
		var minus = $('<a class="btn btn-xs btn-danger">Delete</a>')

		minus.on('click', function(){
			$(this).parents('div.form-group').remove();
		});

		plus.on('click', function(){
			var group = $(this).parents('div.form-group');

			var clone = group.clone(true);
			clone.find('*').removeAttr('value');
			clone.appendTo(group.parent());
		});

		groups.each(function(idx, group){
			var controlsClone = controlsContainer.clone(true).appendTo(group);
			minus.clone(true).appendTo(controlsClone);

			if (group == group.parentNode.lastElementChild)
				plus.clone(true).appendTo(controlsClone);
		});
	})();

	// Find all ipaddress masks and make dynamic based on address family of input
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
		});

		// Fire immediately
		input.change();
	});

	// Add confirm to all btn-danger buttons
	$('.btn-danger').on('click', function(e){
		var q = 'Are you sure you wish to '+ $.trim(this.textContent) +'?';

		if ($(this).attr('title') != undefined)
			q = $(this).attr('title')+'?';

		if (!confirm(q))
			e.preventDefault();
	});

	// Add toggle-all when there are multiple checkboxes
	$('.control-label + .checkbox.multi').each(function() {
		var a = $('<a class="btn btn-xs btn-default">toggle all</a>');

		a.on('click', function() {
			var wrap = $(this).parents('.form-group').find('.checkbox.multi'),
				all = wrap.find('input[type=checkbox]'),
				checked = wrap.find('input[type=checkbox]:checked');

			all.prop('checked', (all.length != checked.length));
		});

		a.appendTo($(this));
	});

	// Enable popovers globally
	$('[data-toggle="popover"]').popover();

	// Force correct initial state for toggleable checkboxes
	$('input[type=checkbox][data-toggle="collapse"]:not(:checked)').each(function() {
		$( $(this).data('target') ).addClass('collapse');
	});

	// Focus first input
	$(':input:enabled:visible:first').focus();

	// Run in-page defined events
	while (func = window.events.shift())
		func();
});