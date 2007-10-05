/* $Id$ */
/*
 * sorttable.js - Renders standard HTML tables into sortable tables.
 *
 * Based on "sorttable" by Stuart Langridge, published under the MIT licence.
 * Modified and extended by Daniel Berlin.
 *
 * Usage:
 *  - All rows (except for those with class "sort_footer") should NOT contain <td>s with
 *    the "colspan" property set. Otherwise sorting might not work correctly.
 *
 *   <table class="myClass sortable">		<-- Add class "sortable"
 *    <tr>									<-- The first row will automatically
 *     <td>Number</td>							be treated as the header line
 *     <td>Value</td>
 *     <td class="sort_ignore">...</td>		<-- Class "sort_ignore" prevents
 *    </tr>										sorting by this column
 *    <tr>
 *     <td>1</td>
 *     <td>Hello World</td>
 *     <td>...</td>
 *    </tr>
 *    <tr>
 *     <td>2</td>
 *     <td>Bye World</td>
 *     <td>...</td>
 *    </tr>
 *    <tr class="sort_footer">				<-- Class "sort_footer" excludes this line
 *     <td>Footer</td>							from sorting (may appear several times)
 *     <td colspan="2">...</td>				<-- In the footer(s), "colspan" is harmless
 *    </tr>
 *  </table>
 */

var ts_CASE_SENSITIVE = false;			//	<-- Set this to true to do case-sensitive sorting

/*** Main ***/

var ts_SORT_FUNCTION;
var ts_COLUMN_INDEX;

new ts_EventHandler(
	window, "load",
	function() {
		if(! document.getElementsByTagName) return;

		// Find all tables with class "sortable" and make them sortable
		var tabs = document.getElementsByTagName("table");
		for(var i = 0; i < tabs.length; i++)
			if(ts_hasClass(tabs[i], "sortable"))
				ts_tableMakeSortable(tabs[i]);
	}
);

/*** Event Handlers ***/

// Make the table sortable
function ts_tableMakeSortable(table) {
	if(table.rows && table.rows.length > 0) {
		var firstRow = table.rows[0];
	}
	if(! firstRow) return;

	// We have a first row: assume it's the header
	for(var i = 0; i < firstRow.cells.length; i++) {
		var cell = firstRow.cells[i];

		// Ignore header cells with class "sort_ignore"
		if(ts_hasClass(cell, "sort_ignore"))
			continue;

		// Make it clickable
		new ts_EventHandler(cell, "click", ts_tableResort, cell);
		ts_setCursor(cell, "pointer");

		cell.innerHTML = ts_getInnerText(cell) +
			'<span class="sortarrow" style="font-weight:bold;">&nbsp;&nbsp;&nbsp;</span>';
	}
}

// Re-Sort the table
function ts_tableResort() {
	// Note: 'this' refers to the cell, the user clicked on

	var tab = ts_getParentByTag(this, "table");
	if(tab.rows.length <= 1) return;

	var txt = ts_getInnerText(tab.rows[1].cells[this.cellIndex]);

	// Determine, how we sort (the order of tests matters):
	//  1. Case (in)sensitive (default)
	ts_SORT_FUNCTION = ts_CASE_SENSITIVE ? ts_sortCaseSensitive : ts_sortCaseInsensitive;

	//  2. Date
	//  TODO: this needs to be extended to match a wider range of dates
	if(txt.match(/^\d\d[\/-]\d\d[\/-]\d\d\d\d$/)) ts_SORT_FUNCTION = ts_sortDate;
	if(txt.match(/^\d\d[\/-]\d\d[\/-]\d\d$/)    ) ts_SORT_FUNCTION = ts_sortDate;

	//  3. Currency
	//  Note: commented out, because we don't need it
	//var regexp = new RegExp("^\s*[$" + String.fromCharCode(163, 165, 8364) + "]");
	//if(regexp.test(txt)) ts_SORT_FUNCTION = ts_sortCurrency;

	//  4. Numeric
	if(txt.match(/^\s*[\d\.]+\s*$/)) ts_SORT_FUNCTION = ts_sortNumeric;

	//  5. IP-Address
	if(txt.match(/^\s*[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+\s*$/)) ts_SORT_FUNCTION = ts_sortIpAddress;

	ts_COLUMN_INDEX = this.cellIndex;

	var bodyRows   = new Array();
	var bottomRows = new Array();

	for(var i = 1; i < tab.rows.length; i++)
		if(ts_hasClass(tab.rows[i], "sort_footer"))
			bottomRows.push(tab.rows[i]);
		else
			bodyRows.push(tab.rows[i]);
			
	bodyRows.sort(ts_sort);

	var span = ts_getChildByTagAndClass(this, "span", "sortarrow");
	if(span.getAttribute("sortdir") == "down") {
		var arrow = "&nbsp;&nbsp;&uarr;";
		span.setAttribute("sortdir", "up");
		bodyRows.reverse();
	}
	else {
		var arrow = "&nbsp;&nbsp;&darr;";
		span.setAttribute("sortdir", "down");
	}

	// We appendChild rows that already exist to the <tbody>,
	// so it moves them rather than creating new ones:
	//  1. Append body-rows in sorted order
	for(i = 0; i < bodyRows.length; i++)
		tab.tBodies[0].appendChild(bodyRows[i]);

	//  2. Append bottom line(s) in original order to the end
	for(i = 0; i < bottomRows.length; i++)
		tab.tBodies[0].appendChild(bottomRows[i]);

	// Delete any other arrows there may be showing
	var spans = document.getElementsByTagName("span");
	for(var i = 0; i < spans.length; i++)
		if(ts_hasClass(spans[i], "sortarrow") && ts_getParentByTag(spans[i], "table") == tab)
			spans[i].innerHTML = '&nbsp;&nbsp;&nbsp;';

	span.innerHTML = arrow;
}

/*** Sort Functions ***/

function ts_sort(a, b) {
	if(typeof a.cells[ts_COLUMN_INDEX] == "undefined" ||
	   typeof b.cells[ts_COLUMN_INDEX] == "undefined")
		return 0;

	return ts_SORT_FUNCTION(
		ts_getInnerText(a.cells[ts_COLUMN_INDEX]),
		ts_getInnerText(b.cells[ts_COLUMN_INDEX])
	);
}

// 1.1. Case insensitive
function ts_sortCaseInsensitive(a, b) {
	return ts_sortCaseSensitive(
		a.toLowerCase(), b.toLowerCase()
	);
}

// 1.2. Case sensitive
function ts_sortCaseSensitive(a, b) {
	if(a == b) return  0;
	if(a <  b) return -1;
	return 1;
}

// 2. Date
//  TODO: this needs to be extended to match a wider range of dates
function ts_sortDate(a, b) {
	var d1, d2;

	// Y2k note: two digit years < 50 are treated as 20xx, >= 50 are treated as 19xx
	if(a.length == 10)
		d1 = a.substr(6, 4) + a.substr(3, 2) + a.substr(0, 2);
	else {
		var yr = a.substr(6, 2);
		if(parseInt(yr) < 50) yr = "20" + yr;
		else                  yr = "19" + yr;
		d1 = yr + a.substr(3, 2) + a.substr(0, 2);
	}
	if(b.length == 10)
		d2 = b.substr(6, 4) + b.substr(3, 2) + b.substr(0, 2);
	else {
		var yr = b.substr(6, 2);
		if(parseInt(yr) < 50) yr = "20" + yr;
		else                  yr = "19" + yr;
		d2 = yr + b.substr(3, 2) + b.substr(0, 2);
	}
	if(d1 == d2) return  0;
	if(d1 <  d2) return -1;
	return 1;
}

// 3. Currency
//    Note: commented out, because we don't need it
/*
function ts_sortCurrency(a, b) { 
	a = a.replace(/[^0-9.]/g, "");
	b = b.replace(/[^0-9.]/g, "");

	return parseFloat(a) - parseFloat(b);
}
*/

// 4. Numeric
function ts_sortNumeric(a, b) { 
	a = parseFloat(a); if(isNaN(a)) a = 0;
	b = parseFloat(b); if(isNaN(b)) b = 0;

	return a - b;
}

// 5. IP-Address
function ts_sortIpAddress(a, b) {
	var oa = a.split(".");
	var ob = b.split(".");

	for(var i = 0; i < 4; i++) {
		if(parseInt(oa[i]) < parseInt(ob[i])) return -1;
		if(parseInt(oa[i]) > parseInt(ob[i])) return  1;
	}
	return 0;
}

/*** Internal Functions ***/

// Get a parentNode by it's tagName property
function ts_getParentByTag(element, pTag) {
	if(typeof element != "object")
		return null;
	else if(element.nodeType == 1 && element.tagName.toLowerCase() == pTag.toLowerCase())
		return element;
	else
		return ts_getParentByTag(element.parentNode, pTag);
}

// Get a childnode by it's tagName property and class
function ts_getChildByTagAndClass(element, pTag, pClass) {
	if(typeof element != "object")
		return null;

	var childs = element.childNodes;
	for(var i = 0; i < childs.length; i++)
		if(childs[i].nodeType == 1 /* ELEMENT_NODE */             &&
			childs[i].tagName.toLowerCase() == pTag.toLowerCase() &&
			ts_hasClass(childs[i], pClass) >= 0)
				return childs[i];

	return null;
}

// Determine, whether an element has a specific class in it's className
function ts_hasClass(element, eClass) {
	if(! element.className)
		return false;

	if(element.className)
		var classes = element.className.split(/\s+/g);
	else if(element.hasAttribute && element.hasAttribute("class"))
		var classes = element.getAttribute("class").split(/\s+/g);
	else
		return false;

	for(var i = 0; i < classes.length; i++)
		if(classes[i] == eClass)
			return true;

	return false;
}

// Get an element's innerText property
function ts_getInnerText(element) {
	if(typeof element == "string")
		return element;
	else if (typeof element == "undefined")
		return "";

	if(element.innerText) return element.innerText;

	var str = "";
	var childs = element.childNodes;
	var l = childs.length;
	for(var i = 0; i < l; i++) {
		switch(childs[i].nodeType) {
			case 1: // ELEMENT_NODE
				str += ts_getInnerText(childs[i]);
				break;
			case 3:	// TEXT_NODE
				str += childs[i].nodeValue;
				break;
		}
	}
	return str;
}

/* Class to add an event handler to an element
 *  <element>   - element to add the event handler to    (Object)
 *  <event>     - event to listen on                     (String)
 *  <handler>   - function to execute if an event fires  (Function)
 *  [<context>] - context to execute the handler in      (Object)
 */
function ts_EventHandler(element, event, handler, context) {
	var self     = this;
	this.context = typeof(context) == "object" ? context : null;

	event = event.toLowerCase();
	if(event.substring(0, 2) == "on")
		event = event.substring(2);

	var evHandler = this.context
		? function(ev) { handler.call(self.context, ev); }
		: function(ev) { handler     (ev);               }

	// W3C DOM, Level 2 (Event Specification)
	if(element.addEventListener) {
		element.addEventListener(event, evHandler, false);
		return true;
	}
	// Microsoft Event Model
	else if(element.attachEvent)
		return element.attachEvent("on" + event, evHandler);
	else
		return false;
}

// Set an element's cursor-style
function ts_setCursor (element, type) {
	if (! type) type = "auto";
	/*@cc_on
		// MSIE <= 5.5
		@if (@_jscript_version <= 5.5)
			if (type == "pointer") type = "hand";
		@end
	@*/

	element.style.cursor = type;
}
