/*
        pool.js
        part of pfSense (https://www.pfsense.org/)

        Copyright (C) 2005-2008 Bill Marquette <bill.marquette@gmail.com>.
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

/* Add server to virtual server pool
 * operates on whatever form is passed to it
 */
function AddServerToPool(form) {
	var ServerPort = form.ipaddr.value;
	form['servers[]'].options[form['servers[]'].options.length] = new Option(ServerPort,ServerPort);
}


function AllServers(id, selectAll) {
   var opts = document.getElementById(id).getElementsByTagName('option');
   for (i = 0; i < opts.length; i++)
   {
       opts[i].selected = selectAll;
   }
}


function RemoveServerFromPool(form, field)
{
	var theSel = form[field];
	var selIndex = theSel.selectedIndex;
	if (selIndex != -1) {
		for(i=theSel.length-1; i>=0; i--)
		{
			if(theSel.options[i].selected)
			{
				theSel.options[i] = null;
			}
		}
		if (theSel.length > 0) {
			theSel.selectedIndex = selIndex == 0 ? 0 : selIndex - 1;
		}
	}
}

function addOption(theSel, theText, theValue)
{
	var newOpt = new Option(theText, theValue);
	var selLength = theSel.length;
	theSel.options[selLength] = newOpt;
}

function deleteOption(theSel, theIndex)
{ 
	var selLength = theSel.length;
	if(selLength>0)
	{
		theSel.options[theIndex] = null;
	}
}

function moveOptions(theSelFrom, theSelTo)
{
	var selLength = theSelFrom.length;
	var selectedText = new Array();
	var selectedValues = new Array();
	var selectedCount = 0;

	var i;

	// Find the selected Options in reverse order
	// and delete them from the 'from' Select.
	for(i=selLength-1; i>=0; i--)
	{
		if(theSelFrom.options[i].selected)
		{
			selectedText[selectedCount] = theSelFrom.options[i].text;
			selectedValues[selectedCount] = theSelFrom.options[i].value;
			deleteOption(theSelFrom, i);
			selectedCount++;
		}
	}

	// Add the selected text/values in reverse order.
	// This will add the Options to the 'to' Select
	// in the same order as they were in the 'from' Select.
	for(i=selectedCount-1; i>=0; i--)
	{
		addOption(theSelTo, selectedText[i], selectedValues[i]);
	}
}

function checkPoolControls() {
	var active = document.iform.serversSelect;
	var inactive = document.iform.serversDisabledSelect;
	if (jQuery("#mode").val() == "failover") {
		if (jQuery("#serversSelect option").length > 0) {
			jQuery("#moveToEnabled").prop("disabled",true);
		} else {
			jQuery("#moveToEnabled").prop("disabled",false);
		}
	} else {
		jQuery("#moveToEnabled").prop("disabled",false);;
	}
}

function enforceFailover() {
	if (jQuery("#mode").val() != "failover") {
		return;
	}
	var active = document.iform.serversSelect;
	var inactive = document.iform.serversDisabledSelect;
	var count = 0;
	var moveText = new Array();
	var moveVals = new Array();
	var i;
	if (active.length > 1) {
		// Move all but one entry to the disabled list
		for (i=active.length-1; i>0; i--) {
			moveText[count] = active.options[i].text;
			moveVals[count] = active.options[i].value;
			deleteOption(active, i);
			count++;
		}
		for (i=count-1; i>=0; i--) {
			addOption(inactive, moveText[i], moveVals[i]);
		}
	}
}

// functions up() and down() modified from http://www.babailiica.com/js/sorter/

function up(obj) {
	var sel = new Array();
	for (var i=0; i<obj.length; i++) {
		if (obj[i].selected == true) {
			sel[sel.length] = i;
		}
	}
	for (i in sel) {
		if (sel[i] != 0 && !obj[sel[i]-1].selected) {
			var tmp = new Array(obj[sel[i]-1].text, obj[sel[i]-1].value);
			obj[sel[i]-1].text = obj[sel[i]].text;
			obj[sel[i]-1].value = obj[sel[i]].value;
			obj[sel[i]].text = tmp[0];
			obj[sel[i]].value = tmp[1];
			obj[sel[i]-1].selected = true;
			obj[sel[i]].selected = false;
		}
	}
}

function down(obj) {
	var sel = new Array();
	for (var i=obj.length-1; i>-1; i--) {
		if (obj[i].selected == true) {
			sel[sel.length] = i;
		}
	}
	for (i in sel) {
		if (sel[i] != obj.length-1 && !obj[sel[i]+1].selected) {
			var tmp = new Array(obj[sel[i]+1].text, obj[sel[i]+1].value);
			obj[sel[i]+1].text = obj[sel[i]].text;
			obj[sel[i]+1].value = obj[sel[i]].value;
			obj[sel[i]].text = tmp[0];
			obj[sel[i]].value = tmp[1];
			obj[sel[i]+1].selected = true;
			obj[sel[i]].selected = false;
		}
	}
}
