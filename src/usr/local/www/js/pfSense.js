/*
 * pfSense.js
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2026 Rubicon Communications, LLC (Netgate)
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

/*
 * This file should only contain functions that will be used on more than 2 pages
 */

$(function() {
	// Search the menu entries already filtered for the current user's privileges.
	(function()
	{
		var search = $('#menu-search-input');
		var searchItem = search.closest('.menu-search');
		var searchToggle = $('#menu-search-toggle');
		var items = [];
		var setSearchExpanded = function(expanded, restoreFocus) {
			searchItem.toggleClass('menu-search-expanded open', expanded);
			searchToggle.attr('aria-expanded', expanded ? 'true' : 'false');
			search.prop('hidden', !expanded);

			if (expanded) {
				search.trigger('focus');
			} else {
				search.autocomplete('close').val('');
				if (restoreFocus) {
					searchToggle.trigger('focus');
				}
			}
		};

		searchToggle.on('click', function(event) {
			event.preventDefault();
			setSearchExpanded(!searchItem.hasClass('menu-search-expanded'), true);
		});

		$('#pf-navbar > ul.navbar-nav:not(.navbar-right) > li.dropdown').each(function() {
			var section = $.trim($(this).children('.dropdown-toggle').clone().children().remove().end().text());

			$(this).find('.dropdown-menu a.navlnk:not([usepost])').each(function() {
				if ((this.protocol != window.location.protocol) || (this.host != window.location.host)) {
					return;
				}

				items.push({
					label: section + ' \u2192 ' + $.trim($(this).text()),
					section: section.toLowerCase(),
					page: $.trim($(this).text()).toLowerCase(),
					url: this.href
				});
			});
		});

		search.autocomplete({
			minLength: 1,
			position: { my: 'right top', at: 'right bottom', collision: 'flipfit' },
			source: function(request, response) {
				var query = $.trim(request.term).toLowerCase();
				var matches = $.grep(items, function(item) {
					return (item.page.indexOf(query) != -1) || (item.section.indexOf(query) != -1);
				});

				matches.sort(function(left, right) {
					var leftRank = left.page.indexOf(query) == 0 ? 0 : left.section.indexOf(query) == 0 ? 1 : 2;
					var rightRank = right.page.indexOf(query) == 0 ? 0 : right.section.indexOf(query) == 0 ? 1 : 2;
					return (leftRank - rightRank) || left.label.localeCompare(right.label);
				});

				response(matches.slice(0, 10));
			},
			focus: function() {
				return false;
			},
			select: function(event, ui) {
				window.location.assign(ui.item.url);
				return false;
			}
		});

		search.autocomplete('instance')._renderItem = function(list, item) {
			var query = $.trim(this.term);
			var offset = item.label.toLowerCase().indexOf(query.toLowerCase());
			var label = $('<div>');

			label.append(document.createTextNode(item.label.substring(0, offset)));
			label.append($('<strong>').text(item.label.substring(offset, offset + query.length)));
			label.append(document.createTextNode(item.label.substring(offset + query.length)));

			return $('<li>').append(label).appendTo(list);
		};
		search.autocomplete('widget').addClass('menu-search-results');

		search.on('keydown', function(event) {
			if (event.key == 'Escape') {
				setSearchExpanded(false, true);
			}
		});

		search.on('blur', function() {
			window.setTimeout(function() {
				setSearchExpanded(false, false);
			}, 0);
		});
	})();

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
	// This time making the buttons col-2 wide so they can fit on the same line as the
	// rest of the group (providing the total width of the group is col-8 or less)
	(function()
	{
		var groups = $('div.form-group.user-duplication-horiz');
		var controlsContainer = $('<div class="col-sm-2"></div>');
		var plus = $('<a class="btn btn-sm btn-success"><i class="fa-solid fa-plus icon-embed-btn"></i>Add</a>');
		var minus = $('<a class="btn btn-sm btn-warning"><i class="fa-solid fa-trash-can icon-embed-btn"></i>Delete</a>');

		minus.on('click', function(){
			$(this).parents('div.form-group').remove();
		});

		plus.on('click', function(){
			var group = $(this).parents('div.form-group');

			var clone = group.clone(true);
			clone.find('*').val('');
			clone.appendTo(group.parent());
		});

		groups.each(function(idx, group){
			var controlsClone = controlsContainer.clone(true).appendTo(group);
			minus.clone(true).appendTo(controlsClone);

			if (group == group.parentNode.lastElementChild)
				plus.clone(true).appendTo(controlsClone);
		});
	})();

	// Add +/- buttons to certain Groups; to allow adding multiple entries
	(function()
	{
		var groups = $('div.form-group.user-duplication');
		var controlsContainer = $('<div class="col-sm-10 col-sm-offset-2 controls"></div>');
		var plus = $('<a class="btn btn-xs btn-success"><i class="fa-solid fa-plus icon-embed-btn"></i>Add</a>');
		var minus = $('<a class="btn btn-xs btn-warning"><i class="fa-solid fa-trash-can icon-embed-btn"></i>Delete</a>');

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

	// Add +/- buttons to certain Groups; to allow adding multiple entries
	(function()
	{
		var groups = $('div.form-listitem.user-duplication');
		var fg = $('<div class="form-group"></div>');
		var controlsContainer = $('<div class="col-sm-10 col-sm-offset-2 controls"></div>');
		var plus = $('<a class="btn btn-xs btn-success"><i class="fa-solid fa-plus icon-embed-btn"></i>Add</a>');
		var minus = $('<a class="btn btn-xs btn-warning"><i class="fa-solid fa-trash-can icon-embed-btn"></i>Delete</a>');

		minus.on('click', function(){
			var groups = $('div.form-listitem.user-duplication');
			if (groups.length > 1) {
				$(this).parents('div.form-listitem').remove();
			}
		});

		plus.on('click', function(){
			var group = $(this).parents('div.form-listitem');
			var clone = group.clone(true);
			bump_input_id(clone);
			clone.appendTo(group.parent());
		});

		groups.each(function(idx, group){
			var fgClone = fg.clone(true).appendTo(group);
			var controlsClone = controlsContainer.clone(true).appendTo(fgClone);
			minus.clone(true).appendTo(controlsClone);
			plus.clone(true).appendTo(controlsClone);
		});
	})();

	// Automatically change IpAddress mask selectors to 128/32 options for IPv6/IPv4 addresses
	$('span.pfIpMask + select').each(function (idx, select){
		var input = $(select).prevAll('input[type=text]');

		input.on('change', function(e){
			var isV6 = (input.val().indexOf(':') != -1), min = 0, max = 128;

			if (!isV6)
				max = 32;

			if (input.val() == "") {
				return;
			}

			var attr = $(select).attr('disabled');

			// Don't do anything if the mask selector is disabled
			if (typeof attr === typeof undefined || attr === false) {
				// Eat all of the options with a value greater than max. We don't want them to be available
				while (select.options[0].value > max)
					select.remove(0);

				if (select.options.length < max) {
					for (var i=select.options.length; i<=max; i++)
						select.options.add(new Option(i, i), 0);

					if (isV6) {
						// Make sure index 0 is selected otherwise it will stay in "32" for V6
						select.options.selectedIndex = "0";
					}
				}
			}
		});

		// Fire immediately
		input.change();
	});

	// Add confirm to all btn-danger buttons and fa-trash-can icons
	// Use element title in the confirmation message, or if not available
	// the element value
	$('.btn-danger, .fa-trash-can').on('click', function(e){
		if (!($(this).hasClass('no-confirm')) && !($(this).hasClass('icon-embed-btn'))) {
			// Anchors using the automatic get2post system (pfSenseHelpers.js) perform the confirmation dialog
			// in those functions
			var attr = $(this).attr('usepost');
			if (typeof attr === typeof undefined || attr === false) {
				var msg = $.trim(this.textContent).toLowerCase();

				if (!msg)
					var msg = $.trim(this.value).toLowerCase();

				var q = 'Are you sure you wish to '+ msg +'?';

				if ($(this).attr('title') != undefined)
					q = 'Are you sure you wish to '+ $(this).attr('title').toLowerCase() + '?';

				if (!confirm(q)) {
					e.preventDefault();
					e.stopPropagation();	// Don't leave ancestor(s) selected.
				}
			}
		}
	});

	// Add toggle-all when there are multiple checkboxes
	$('.control-label + .checkbox.multi').each(function() {
		var a = $('<a name="btntoggleall" class="btn btn-xs btn-info"><i class="fa-regular fa-square-check icon-embed-btn"></i>Toggle All</a>');

		a.on('click', function() {
			var wrap = $(this).parents('.form-group').find('.checkbox.multi'),
				all = wrap.find('input[type=checkbox]'),
				checked = wrap.find('input[type=checkbox]:checked');

			all.prop('checked', (all.length != checked.length));
		});

		if ( ! $(this).parent().hasClass("notoggleall")) {
			a.appendTo($(this));
		}
	});

	// The need to NOT hide the advanced options if the elements therein are not set to the system
	// default values makes it better to handle advanced option hiding in each PHP file so this is being
	// disabled for now by changing the class name it acts on to "auto-advanced"

	// Hide advanced inputs by default
	if ($('.auto-advanced').length > 0)
	{
		var advButt = $('<a id="toggle-advanced" class="btn btn-default">toggle advanced options</a>');
		advButt.on('click', function() {
			$('.advanced').parents('.form-group').collapse('toggle');
		});

		advButt.insertAfter($('#save'));

		$('.auto-advanced').parents('.form-group').collapse({toggle: true});
	}

	var originalLeave = $.fn.popover.Constructor.prototype.leave;
	$.fn.popover.Constructor.prototype.leave = function(obj){
	  var self = obj instanceof this.constructor ?
	    obj : $(obj.currentTarget)[this.type](this.getDelegateOptions()).data('bs.' + this.type)
	  var container, timeout;

	  originalLeave.call(this, obj);

	  if (self.$tip && self.$tip.length) {
	    container = self.$tip;
	    timeout = self.timeout;
	    container.one('mouseenter', function(){
	      //We entered the actual popover - call off the dogs
	      clearTimeout(timeout);
	      //Let's monitor popover content instead
	      container.one('mouseleave', function(){
	        $.fn.popover.Constructor.prototype.leave.call(self, self);
	      });
	    })
	  }
	};

	// Bootstrap 3.4.1 sanitizes the contents of popovers even when data-html is specified
	// Add table tags to the list of elements permitted by the sanitizer
	var defaultWhiteList = $.fn.tooltip.Constructor.DEFAULTS.whiteList

	defaultWhiteList.table = []
	defaultWhiteList.thead = []
	defaultWhiteList.tr = ["class"]
	defaultWhiteList.th = ["style"]
	defaultWhiteList.tbody = []
	defaultWhiteList.td = ["style"]

	// Enable popovers globally
	$('[data-toggle="popover"]').popover({ delay: {show: 50, hide: 400} });

	// Force correct initial state for toggleable checkboxes
	$('input[type=checkbox][data-toggle="collapse"]:not(:checked)').each(function() {
		$( $(this).data('target') ).addClass('collapse');
	});

	$('input[type=checkbox][data-toggle="disable"]:not(:checked)').each(function() {
		$( $(this).data('target') ).prop('disabled', true);
	});

	$('.table-rowdblclickedit>tbody>tr').dblclick(function () {
		$(this).find(".fa-pencil")[0].click();
	});

	// Focus first input
	$(':input:enabled:visible:first').focus();

	$(".resizable").each(function() {
		$(this).css('height', 80).resizable({minHeight: 80, minWidth: 200}).parent().css('padding-bottom', 0);
		$(this).css('height', 78);
	});

	// Run in-page defined events
	while (func = window.events.shift())
		func();
});

// Implement data-toggle=disable
// Source: https://github.com/visionappscz/bootstrap-ui/blob/master/src/js/disable.js
;(function($, window, document) {
	'use strict';

	var Disable = function($element) {
		this.$element = $element;
	};

	Disable.prototype.toggle = function() {
		this.$element.prop('disabled', !this.$element.prop('disabled'));
	};

	function Plugin(options) {
		$(document).trigger('toggle.sui.disable');

		this.each(function() {
			var $this = $(this);
			var data = $this.data('sui.disable');

			if (!data) {
				$this.data('sui.disable', (data = new Disable($this)));
			}

			if (options === 'toggle') {
				data.toggle();
			}
		});

		$(document).trigger('toggled.sui.disable');

		return this;
	}

	var old = $.fn.disable;

	$.fn.disable = Plugin;
	$.fn.disable.Constructor = Disable;

	$.fn.disable.noConflict = function() {
		$.fn.disable = old;
		return this;
	};

	(function(Plugin, $, window) {
		$(window).on("load", function() {
			var $controls = $('[data-toggle=disable]');

			$controls.each(function() {
				var $this = $(this);
				var eventType = $this.data('disable-event');
				if (!eventType) {
					eventType = 'change';
				}
				$this.on(eventType + '.sui.disable.data-api', function() {
					Plugin.call($($this.data('target')), 'toggle');
				});
			});
		});
	}(Plugin, $, window, document));
}(jQuery, window, document));
