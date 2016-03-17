/***
**
** Polyfill for older browsers that don't yet have the newer string "includes()" method implemented.
** Source: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/includes?#Polyfill
** Documentation: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/includes
**
***/

if (!String.prototype.includes) {
	String.prototype.includes = function(search, start) {
		'use strict';
		if (typeof start !== 'number') {
			start = 0;
		}

		if (start + search.length > this.length) {
			return false;
		} else {
			return this.indexOf(search, start) !== -1;
		}
	};
}
