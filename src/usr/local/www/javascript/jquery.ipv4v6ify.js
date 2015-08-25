/*jslint browser: true, eqeqeq: true, undef: true */
/*global jQuery */
/******************************************************************************
Lines above are for jslint, the JavaScript verifier.  http://www.jslint.com/
******************************************************************************/

/* MIT-licensed code from https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/Array/some */
/* (C) 2007 Mozilla Developer Network and/or Jeff Walden */
if (!Array.prototype.some) {
	Array.prototype.some = function(fun /*, thisp */) {
		"use strict";
		if (!this) {
			throw new TypeError();
		}
		var t = Object(this);
		var len = t.length >>> 0;
		if (typeof fun !== "function") {
			throw new TypeError();
		}
		var thisp = arguments[1];
		for (var i = 0; i < len; i++) {
			if (i in t && fun.call(thisp, t[i], i, t)) {
				return true;
			}
		}
		return false;
	};
}

(function ($) {
	// --------------------------------------------------------------------
	// find pairs of <input class='ipv4v6'> (textbox for IPv4 or IPv6 addr)
	// and <select class='ipv4v6'> (dropdown for # bits in CIDR) and
	// activate behavior that restricts options in the <select> when an
	// ipv4 address is typed in the <input>.
	// --------------------------------------------------------------------
	var _ipv4v6ify = function (input1, input2) {
		var options = Array.prototype.slice.call(input2.options, 0);
		var has_128  = options.some(function (x) { return parseInt(x.value, 10) === 128; });
		var has_0    = options.some(function (x) { return parseInt(x.value, 10) === 0; });
		var max_ipv6 = has_128 ? 128 : 127;
		var min_ipv6 = has_0 ? 0 : 1;
		var max_ipv4 = has_128 ? 32 : 31;
		var min_ipv4 = has_0 ? 0 : 1;
		var was_ipv4 = undefined;
		var is_ipv4  = undefined;
		var restrict_bits_to_ipv4 = function () {
			input2.options.length = 0;
			for (var i = 0; i < options.length; i += 1) {
				var val = parseInt(options[i].value, 10);
				if (val >= min_ipv4 && val <= max_ipv4) {
					input2.options.add(options[i]);
				}
			}
		};
		var unrestrict_bits = function () {
			input2.options.length = 0;
			for (var i = 0; i < options.length; i += 1) {
				input2.options.add(options[i]);
			}
		};
		var onchange_handler = function () {
			was_ipv4 = is_ipv4;
			is_ipv4  = /\./.test(input1.value) && !/\:/.test(input1.value);
			// handle state transitions to gracefully change the
			// value in the dropdown.	
			var bits = parseInt($(input2).val(), 10);
			if (was_ipv4 === false && is_ipv4 === true) {
				restrict_bits_to_ipv4();
				/* min_ipv4 -> min_ipv4 */
				/*   ...    ->   ...    */
				/* max_ipv4 -> max_ipv4 */
				/*   ...    ->   ...    */
				/* max_ipv6 -> max_ipv4 */
				if (bits < min_ipv4) {
					$(input2).val(min_ipv4);
				}
				else if (bits < max_ipv4) {
					$(input2).val(bits);
				}
				else {
					$(input2).val(max_ipv4);
				}
			}
			else if (was_ipv4 === true && is_ipv4 === false) {
				unrestrict_bits();
				/* min_ipv4 -> min_ipv4 */
				/*   ...    ->   ...    */
				/* max_ipv4 -> max_ipv4 */
				if (bits < min_ipv4) {
					$(input2).val(min_ipv6);
				}
				else if (bits < max_ipv4) {
					$(input2).val(bits);
				}
				else {
					$(input2).val(max_ipv6);
				}
			}
			else if (was_ipv4 === undefined && is_ipv4 === true) {
				// initial value is an ipv4 address
				restrict_bits_to_ipv4();
				/* min_ipv4 -> min_ipv4 */
				/*   ...    ->   ...    */
				/* max_ipv4 -> max_ipv4 */
				/*   ...    ->   ...    */
				/* max_ipv6 -> max_ipv4 */
				if (bits < min_ipv4) {
					$(input2).val(min_ipv4);
				}
				else if (bits < max_ipv4) {
					$(input2).val(bits);
				}
				else {
					$(input2).val(max_ipv4);
				}
			}
		};
		$(input1).unbind("change").bind("change", onchange_handler).trigger("change");
	};
	$.fn.extend({
		"ipv4v6ify": function () {
			return this.each(function () {
				var inputs, i, input1, input2;
				inputs = $(this).find(":input.ipv4v6").toArray();
				for (i = 0; i < inputs.length - 1; i += 1) {
					input1 = inputs[i];
					input2 = inputs[i + 1];
					if (input1.type === "text" && input2.type === "select-one") {
						_ipv4v6ify(input1, input2);
					}
				}
			});
		}
	});
	$(function () {
		$(document).ipv4v6ify();
	});
})(jQuery);

