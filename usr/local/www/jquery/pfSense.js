/*
 * This file should only contain functions that will be used on more than 2 pages
 */

$(function() {
	$(function() {
		enableGlobalPopovers();
		bindCollapseToOptions();
	});

	/**
	 * Enable popovers globally
	 */
	var enableGlobalPopovers = function()
	{
		$('[data-toggle="popover"]').popover();

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
})(jQuery);

// Custom handler for data-toggle=disable
+function ($) {
	'use strict';

	var Disable = function (element, options) {
		this.$element = $(element)
		this.options = $.extend({}, Disable.DEFAULTS, options)
		this.$trigger = $(this.options.trigger).filter('[href="#' + element.id + '"], [data-target="#' + element.id + '"]')
		this.transitioning = null
	}

	Disable.VERSION = '1.0'

	Disable.DEFAULTS = {
		trigger: '[data-toggle="disable"]'
	}

	Disable.prototype.show = function () {
		this.$element
			.prop('disabled', false);
	}

	Disable.prototype.hide = function () {
		this.$element
			.prop('disabled', true);
	}

	Disable.prototype.toggle = function () {
		this[this.$element.prop('disabled') ? 'show' : 'hide']()
	}

	function Plugin(option) {
		return this.each(function () {
			var $this	= $(this)
			var data	= $this.data('disable')
			var options	= $.extend({}, Disable.DEFAULTS, $this.data(), typeof option == 'object' && option)

			if (!data) $this.data('disable', (data = new Disable(this, options)))
			if (typeof option == 'string') data[option]()
		})
	}

	var old = $.fn.disable

	$.fn.disable = Plugin
	$.fn.disable.Constructor = Disable

	$.fn.disable.noConflict = function () {
		$.fn.disable = old
		return this
	}

	$(document).on('click.disable.data-api', '[data-toggle="disable"]', function (e) {
		var $this = $(this)

		if (!$this.attr('data-target')) e.preventDefault()

		var $target = $($this.attr('data-target'))
		var data = $target.data('disable')
		var option = data ? 'toggle' : $.extend({}, $this.data(), { trigger: this })

		Plugin.call($target, option)
	})

}(jQuery);