/*
	NetUtils.js
	part of pfSense (https://www.pfsense.org)
	Various helper functions for IPv6 support.

	Copyright (C) 2007 Simon Cornelius P. Umacob <simoncpu@gmail.com>
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

*/

function NetUtils_changeIPVersionMask(field, version) {
	switch(version){
		case 'IPv4':
			NetUtils_clearOptions(document.getElementById(field));
			NetUtils_loadMaskIPv4(document.getElementById(field), 32);

			break;
		case 'IPv6':
			NetUtils_clearOptions(document.getElementById(field));
			NetUtils_loadMaskIPv6(document.getElementById(field), 64);

			break;
		case 'IPv4_net':
			NetUtils_clearOptions(document.getElementById(field));
			NetUtils_loadMaskIPv4(document.getElementById(field), 32, 1, 31);

			break;
		case 'IPv6_net':
			NetUtils_clearOptions(document.getElementById(field));
			NetUtils_loadMaskIPv6(document.getElementById(field), 64, 1, 63);

			break;
	}
}

function NetUtils_clearOptions(obj) {
	var	len = obj.length;

	for (var i = 0; i < len; i++) {
		obj[0] = null;
	}
}

function NetUtils_loadMaskIPv4(obj, sel, min, max) {
	var	min,
		max,
		j = 0;

	min = min == undefined ? 1 : min;
	max = max == undefined ? 32 : max;

	for (var i = max; i >= min; i--) {
		obj[j] = new Option(i, i);
		if (sel == i) {
			obj[j].selected = true;
		}
		j++;
	}
}

function NetUtils_loadMaskIPv6(obj, sel, min, max) {
	var	min,
		max,
		j = 0;

	min = min == undefined ? 1 : min;
	max = max == undefined ? 64 : max;

	if ((max % 4) != 0) {
		obj[j++] = new Option(max, max);

		/**
		 * NOTE: This solution is a kludge.
		 * If you have a better way, don't hesitate
		 * to change this.  Please send patches. :)
		 */
		for (var i = 1; i <= 3; i++) {
			if (((max - i) % 4) == 0) {
				max = max - i;
				break;
			}
		} 
	}

	for (var i = max; i >= min; i -= 4) {
		obj[j] = new Option(i, i);
		if (sel == i) {
			obj[j].selected = true;
		}
		j++;
	}
}

