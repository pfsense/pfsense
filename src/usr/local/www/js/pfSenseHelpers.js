/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

// These helper functions are used on many/most UI pages to hide/show/disable/enable form elements where required

// Hides the <div> in which the specified input element lives so that the input, its label and help text are hidden
function hideInput(id, hide) {
	if (hide)
		$('#' + id).parent().parent('div').addClass('hidden');
	else
		$('#' + id).parent().parent('div').removeClass('hidden');
}

// Hides the <div> in which the specified group input element lives so that the input,
// its label and help text are hidden
function hideGroupInput(id, hide) {
	if (hide)
		$('#' + id).parent('div').addClass('hidden');
	else
		$('#' + id).parent('div').removeClass('hidden');
}

// Hides the <div> in which the specified checkbox lives so that the checkbox, its label and help text are hidden
function hideCheckbox(id, hide) {
	if (hide)
		$('#' + id).parent().parent().parent('div').addClass('hidden');
	else
		$('#' + id).parent().parent().parent('div').removeClass('hidden');
}

// Disables the specified input element
function disableInput(id, disable) {
	$('#' + id).prop("disabled", disable);
}

// Hides all elements of the specified class. This will usually be a section
function hideClass(s_class, hide) {
	if (hide)
		$('.' + s_class).hide();
	else
		$('.' + s_class).show();
}

// Hides all elements of the specified class assigned to a group. This will usually be a group
function hideGroupClass(s_class, hide) {
	if (hide)
		$('.' + s_class).parent().parent().parent().hide();
	else
		$('.' + s_class).parent().parent().parent().show();
}

function hideSelect(id, hide) {
	if (hide)
		$('#' + id).parent('div').parent('div').addClass('hidden');
	else
		$('#' + id).parent('div').parent('div').removeClass('hidden');
}

function hideMultiCheckbox(id, hide) {
	if (hide)
		$("[name=" + id + "]").parent().addClass('hidden');
	else
		$("[name=" + id + "]").parent().removeClass('hidden');
}

// Hides the <div> in which the specified IP address element lives so that the input, its label and help text are hidden
function hideIpAddress(id, hide) {
	if (hide)
		$('#' + id).parent().parent().parent('div').addClass('hidden');
	else
		$('#' + id).parent().parent().parent('div').removeClass('hidden');
}

// Hides all elements of the specified class belonging to a multiselect.
function hideMultiClass(s_class, hide) {
	if (hide)
		$('.' + s_class).parent().parent().hide();
	else
		$('.' + s_class).parent().parent().show();
}

// Hides div whose label contains the specified text. (Good for StaticText)
function hideLabel(text, hide) {

	var element = $('label:contains(' + text + ')');

	if (hide)
		element.parent('div').addClass('hidden');
	else
		element.parent('div').removeClass('hidden');
}

// Hides the '/' and the subnet mask of an Ip_Address/subnet_mask group
function hideMask(name, hide) {
	if(hide) {
		$('[id^=' + name + ']').hide();
		$('[id^=' + name + ']').prev('span').hide();
		$('[id^=' + name + ']').parent('div').removeClass('input-group');
	} else {
		$('[id^=' + name + ']').show();
		$('[id^=' + name + ']').prev('span').show();
		$('[id^=' + name + ']').parent('div').addClass('input-group');
	}
}

// Toggle table row checkboxes and background colors on the pages that use sortable tables:
//	/usr/local/www/firewall_nat.php
//	/usr/local/www/firewall_nat_1to1.php
//	/usr/local/www/firewall_nat_out.php
//	/usr/local/www/firewall_rules.php
//	/usr/local/www/vpn_ipsec.php
// Striping of the tables is handled here, NOT with the Bootstrap table-striped class because it would
// get confused when rows are sorted or deleted.

function fr_toggle(id, prefix) {
	if (!prefix)
		prefix = 'fr';

	var checkbox = document.getElementById(prefix + 'c' + id);
	checkbox.checked = !checkbox.checked;
	fr_bgcolor(id, prefix);
}

// Change background color of selected row based on state of checkbox
function fr_bgcolor(id, prefix) {
	if (!prefix)
		prefix = 'fr';

	var row = $('#' + prefix + id);

	if ($('#' + prefix + 'c' + id).prop('checked') ) {
		row.addClass('active');
	} else {
		row.removeClass('active');
	}
}

// The following functions are used by Form_Groups assigned a class of "repeatable" and provide the ability
// to add/delete rows of sequentially numbered elements, their labels and their help text
// See firewall_aliases_edit.php for an example

// NOTE: retainhelp is a global var that when defined prevents any help text from being deleted as lines are inserted.
// IOW it causes every row to have help text, not just the last row

function setMasks() {
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

			if (select.options.length < max) {
				for (var i=select.options.length; i<=max; i++)
					select.options.add(new Option(i, i), 0);
			}
		});

		// Fire immediately
		input.change();
	});
}

// Complicated function to move all help text associated with this input id to the same id
// on the row above. That way if you delete the last row, you don't lose the help
function moveHelpText(id) {
	$('#' + id).parent('div').parent('div').find('input, select, checkbox').each(function() {	 // For each <span></span>
		var fromId = this.id;
		var toId = decrStringInt(fromId);
		var helpSpan;

		if (!$(this).hasClass('pfIpMask') && !$(this).hasClass('btn')) {
			if ($('#' + decrStringInt(fromId)).parent('div').hasClass('input-group')) {
				helpSpan = $('#' + fromId).parent('div').parent('div').find('span:last').clone();
			} else {
				helpSpan = $('#' + fromId).parent('div').find('span:last').clone();
			}
			if ($(helpSpan).hasClass('help-block')) {
				if ($('#' + decrStringInt(fromId)).parent('div').hasClass('input-group')) {
					$('#' + decrStringInt(fromId)).parent('div').after(helpSpan);
				} else {
					$('#' + decrStringInt(fromId)).after(helpSpan);
				}
			}
		}
	});
}

// Increment the number at the end of the string
function bumpStringInt( str )	{
  var data = str.match(/(\D*)(\d+)(\D*)/), newStr = "";

  if (data)
	newStr = data[ 1 ] + ( Number( data[ 2 ] ) + 1 ) + data[ 3 ];

  return newStr || str;
}

// Decrement the number at the end of the string
function decrStringInt( str )	{
  var data = str.match(/(\D*)(\d+)(\D*)/), newStr = "";

  if (data)
	newStr = data[ 1 ] + ( Number( data[ 2 ] ) - 1 ) + data[ 3 ];

  return newStr || str;
}

// Called after a delete so that there are no gaps in the numbering. Most of the time the config system doesn't care about
// gaps, but I do :)
function renumber() {
	var idx = 0;

	$('.repeatable').each(function() {

		$(this).find('input').each(function() {
			$(this).prop("id", this.id.replace(/\d+$/, "") + idx);
			$(this).prop("name", this.name.replace(/\d+$/, "") + idx);
		});

		$(this).find('select').each(function() {
			$(this).prop("id", this.id.replace(/\d+$/, "") + idx);
			$(this).prop("name", this.name.replace(/\d+$/, "") + idx);
		});

//		$(this).find('label').attr('for', $(this).find('label').attr('for').replace(/\d+$/, "") + idx);

		idx++;
	});
}

function delete_row(rowDelBtn) {
	var rowLabel;

	// If we are deleting row zero, we need to save/restore the label
	if (rowDelBtn == "deleterow0") {
		rowLabel = $('#' + rowDelBtn).parent('div').parent('div').find('label').text();
	}

	$('#' + rowDelBtn).parent('div').parent('div').remove();
	renumber();
	checkLastRow();

	if (rowDelBtn == "deleterow0") {
		$('#' + rowDelBtn).parent('div').parent('div').find('label').text(rowLabel);
	}
}

function checkLastRow() {
	if ($('.repeatable').length <= 1) {
		$('#deleterow0').hide();
	} else {
		$('[id^=deleterow]').show();
	}
}

function add_row() {
	// Find the last repeatable group
	var lastRepeatableGroup = $('.repeatable:last');

	// Clone it
	var newGroup = lastRepeatableGroup.clone();
	// Increment the suffix number for each input element in the new group
	$(newGroup).find('input').each(function() {
		$(this).prop("id", bumpStringInt(this.id));
		$(this).prop("name", bumpStringInt(this.name));
		if (!$(this).is('[id^=delete]'))
			$(this).val('');
	});

	// Increment the suffix number for the deleterow button element in the new group
	$(newGroup).find('[id^=deleterow]').each(function() {
		$(this).prop("id", bumpStringInt(this.id));
		$(this).prop("name", bumpStringInt(this.name));
	});

	// Do the same for selectors
	$(newGroup).find('select').each(function() {
		$(this).prop("id", bumpStringInt(this.id));
		$(this).prop("name", bumpStringInt(this.name));
		// If this selector lists mask bits, we need it to be reset to all 128 options
		// and no items selected, so that automatic v4/v6 selection still works
		if ($(this).is('[id^=address_subnet]')) {
			$(this).empty();
			for (idx=128; idx>0; idx--) {
				$(this).append($('<option>', {
					value: idx,
					text: idx
				}));
			}
		}
	});

	// And for "for" tags
//	$(newGroup).find('label').attr('for', bumpStringInt($(newGroup).find('label').attr('for')));

	$(newGroup).find('label').text(""); // Clear the label. We only want it on the very first row

	// Insert the updated/cloned row
	$(lastRepeatableGroup).after(newGroup);

	// Delete any help text from the group we have cloned
	$(lastRepeatableGroup).find('.help-block').each(function() {
		if ((typeof retainhelp) == "undefined")
			$(this).remove();
	});

	setMasks();

	checkLastRow();

	// Autocomplete
	if ( typeof addressarray !== 'undefined') {
		$('[id^=address]').each(function() {
			if (this.id.substring(0, 8) != "address_") {
				$(this).autocomplete({
					source: addressarray
				});
			}
		});
	}

	// Now that we are no longer cloning the event handlers, we need to remove and re-add after a new row
	// has been added to the table
	$('[id^=delete]').unbind();
	$('[id^=delete]').click(function(event) {
		if ($('.repeatable').length > 1) {
			if ((typeof retainhelp) == "undefined")
				moveHelpText(event.target.id);

			delete_row(event.target.id);
		} else {
			alert('The last row may not be deleted.');
		}
	});

}

// These are action buttons, not submit buttons
$('[id^=addrow]').prop('type','button');
$('[id^=delete]').prop('type','button');

// on click . .
$('[id^=addrow]').click(function() {
	add_row();
});

$('[id^=delete]').click(function(event) {
	if ($('.repeatable').length > 1) {
		if ((typeof retainhelp) == "undefined")
			moveHelpText(event.target.id);

		delete_row(event.target.id);
	} else {
		alert('The last row may not be deleted.');
	}
});

// "More information" handlers --------------------------------------------------------------------

// If there is an infoblock, automatically add an info icon that toggles its display

var sfx = 0;

$('.infoblock').each(function() {
	// If the block has the class "blockopen" it is initially open
	if (! $(this).hasClass("blockopen")) {
		$(this).hide();
	} else {
		$(this).removeClass("blockopen");
	}

	// Add the "i" icon before the infoblock, incrementing the icon id for each block (in case there are multiple infoblocks on a page)
	$(this).before('<i class="fa fa-info-circle icon-pointer" style="color: #337AB7; font-size:20px; margin-left: 10px; margin-bottom: 10px;" id="showinfo' + sfx.toString() + '" title="More information"></i>');
	$(this).removeClass("infoblock");
	$(this).addClass("infoblock" + sfx.toString());
	sfx++;
});

// Show the help on clicking the info icon
$('[id^="showinfo"]').click(function() {
	var id = $(this).attr("id");

	$('.' + "infoblock" + id.substr(8)).toggle();
	document.getSelection().removeAllRanges();		// Ensure the text is un-selected (Chrome browser quirk)
});
// ------------------------------------------------------------------------------------------------

// Put a dummy row into any empty table to keep IE happy
$('tbody').each(function(){
	$(this).html($.trim($(this).html()))
});

$('tbody:empty').html("<tr><td></td></tr>");

// Hide configuration button for panels without configuration
$('.container .panel-heading a.config').each(function (idx, el){
	var config = $(el).parents('.panel').children('.panel-footer');
	if (config.length == 1)
		$(el).removeClass('hidden');
});

// Initial state & toggle icons of collapsed panel
$('.container .panel-heading a[data-toggle="collapse"]').each(function (idx, el){
	var body = $(el).parents('.panel').children('.panel-body')
	var isOpen = body.hasClass('in');

	$(el).children('i').toggleClass('fa-plus-circle', !isOpen);
	$(el).children('i').toggleClass('fa-minus-circle', isOpen);

	body.on('shown.bs.collapse', function(){
		$(el).children('i').toggleClass('fa-minus-circle', true);
		$(el).children('i').toggleClass('fa-plus-circle', false);
	});

	body.on('hidden.bs.collapse', function(){
		$(el).children('i').toggleClass('fa-minus-circle', false);
		$(el).children('i').toggleClass('fa-plus-circle', true);
	});
});

// Separator bar stuff ------------------------------------------------------------------------

// Globals
gColor = 'bg-info';
newSeparator = false;
saving = false;
dirty = false;

$("#addsep").prop('type' ,'button');

$("#addsep").click(function() {
	if (newSeparator) {
		return(false);
	}

	gColor = 'bg-info';
	// Insert a temporary bar in which the user can enter some optional text
	sepcols = $( "#ruletable tr th" ).length - 2;

	$('#ruletable > tbody:last').append('<tr>' +
		'<td class="' + gColor + '" colspan="' + sepcols + '"><input id="newsep" placeholder="' + svbtnplaceholder + '" class="col-md-12" type="text" /></td>' +
		'<td class="' + gColor + '" colspan="2"><button class="btn btn-primary btn-sm" id="btnnewsep"><i class="fa fa-save icon-embed-btn"></i>' + svtxt + '</button>' +
		'<button class="btn btn-info btn-sm" id="btncncsep"><i class="fa fa-undo icon-embed-btn"></i>' + cncltxt + '</button>' +
		'&nbsp;&nbsp;&nbsp;&nbsp;' +
		'&nbsp;&nbsp;<a id="sepclrblue" value="bg-info"><i class="fa fa-circle text-info icon-pointer"></i></a>' +
		'&nbsp;&nbsp;<a id="sepclrred" value="bg-danger"><i class="fa fa-circle text-danger icon-pointer"></i></a>' +
		'&nbsp;&nbsp;<a id="sepclrgreen" value="bg-success"><i class="fa fa-circle text-success icon-pointer"></i></a>' +
		'&nbsp;&nbsp;<a id="sepclrorange" value="bg-warning"><i class="fa fa-circle text-warning icon-pointer"></i></button>' +
		'</td></tr>');

	$('#newsep').focus();
	newSeparator = true;

	$("#btnnewsep").prop('type' ,'button');

	// Watch escape and enter keys
	$('#newsep').keyup(function(e) {
		if (e.which == 27) {
			$('#btncncsep').trigger('click');
		}
	});

	$('#newsep').keypress(function(e) {
		if (e.which == 13) {
			$('#btnnewsep').trigger('click');
		}
	});

	handle_colors();

	// Replace the temporary separator bar with the final version containing the
	// user's text and a delete icon
	$("#btnnewsep").click(function() {
		var septext = escapeHtml($('#newsep').val());
		sepcols = $( "#ruletable tr th" ).length - 1;

		$(this).parents('tr').replaceWith('<tr class="ui-sortable-handle separator">' +
			'<td class="' + gColor + '" colspan="' + sepcols + '">' + '<span class="' + gColor + '">' + septext + '</span></td>' +
			'<td class="' + gColor + '"><a href="#"><i class="fa fa-trash sepdel"></i></a>' +
			'</td></tr>');

		$('#order-store').removeAttr('disabled');
		newSeparator = false;
		dirty = true;
	});

	// Cancel button
	$('#btncncsep').click(function(e) {
		e.preventDefault();
		$(this).parents('tr').remove();
		newSeparator = false;
	});
});

// Delete a separator row
$(function(){
	$('table').on('click','tr a .sepdel',function(e){
		e.preventDefault();
		$(this).parents('tr').remove();
		$('#order-store').removeAttr('disabled');
		dirty = true;
	});
});

// Compose an input array containing the row #, color and text for each separator
function save_separators() {
	var row = 0;
	var sepinput;
	var sepnum = 0;

	$('#ruletable > tbody > tr').each(function() {
		if ($(this).hasClass('separator')) {
			seprow = $(this).next('tr').attr("id");
			if (seprow == undefined) {
				seprow = "fr" + row;
			}

			sepinput = '<input type="hidden" name="separator[' + sepnum + '][row]" value="' + seprow + '"></input>';
			$('form').append(sepinput);
			sepinput = '<input type="hidden" name="separator[' + sepnum + '][text]" value="' + escapeHtml($(this).find('td').text()) + '"></input>';
			$('form').append(sepinput);
			sepinput = '<input type="hidden" name="separator[' + sepnum + '][color]" value="' + $(this).find('td').prop('class') + '"></input>';
			$('form').append(sepinput);
			sepinput = '<input type="hidden" name="separator[' + sepnum + '][if]" value="' + iface + '"></input>';
			$('form').append(sepinput);
			sepnum++;
		} else {
			if ($(this).parent('tbody').hasClass('user-entries')) {
				row++;
			}
		}
	});
}

function reindex_rules(section) {
	var row = 0;

	section.find('tr').each(function() {
		if (this.id) {
			$(this).attr("id", "fr" + row);
			$(this).attr("onclick", "fr_toggle(" + row + ")")
			$(this).find('input:checkbox:first').each(function() {
				$(this).attr("id", "frc" + row);
				$(this).attr("onclick", "fr_toggle(" + row + ")");
			});

			row++;
		}
	});
}

function handle_colors() {
	$('[id^=sepclr]').prop("type", "button");

	$('[id^=sepclr]').click(function () {
		var color =	 $(this).attr('value');
		// Clear all the color classes
		$(this).parent('td').prop('class', '');
		$(this).parent('td').prev('td').prop('class', '');
		// Install our new color class
		$(this).parent('td').addClass(color);
		$(this).parent('td').prev('td').addClass(color);
		// Set the global color
		gColor = color;
	});
}

//JS equivalent to PHP htmlspecialchars()
function escapeHtml(text) {
	var map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;'
	};

	return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
// --------------------------------------------------------------------------------------------

// Select every option in the specified multiselect
function AllServers(id, selectAll) {
   for (i = 0; i < id.length; i++)	   {
	   id.eq(i).prop('selected', selectAll);
   }
}

// Move all selected options from one multiselect to another
function moveOptions(From, To)	{
	var len = From.length;
	var option;

	if (len > 0) {
		for (i=0; i<len; i++) {
			if (From.eq(i).is(':selected')) {
				option = From.eq(i).val();
				value  = From.eq(i).text();
				To.append(new Option(value, option));
				From.eq(i).remove();
			}
		}
	}
}


// ------------- Service start/stop/restart functions.
// If a start/stop/restart button is clicked, parse the button name and make a POST via AJAX
$('[id*=restartservice-], [id*=stopservice-], [id*=startservice-]').click(function(event) {
	var args = this.id.split('-');
	var action, name, mode_zone, id;

	if (args[0] == "openvpn") {
		action = args[1];
		name = args[0];
		mode_zone = args[2];
		id = args[3];
	} else if (args[0] == "cpativeportal") {
		action = args[1];
		name = args[0];
		mode_zone = args[2];
		id = args[3];
	} else {
		action = args[0];
		name = args[1];
	}

	$(this).children('i').removeClass().addClass('fa fa-cog fa-spin text-success');
	this.blur();

	ajaxRequest = $.ajax(
		{
			url: "/status_services.php",
			type: "post",
			data: {
				ajax: 		"ajax",
				mode: 		action,
				service: 	name,
				vpnmode: 	mode_zone,
				zone: 		mode_zone,
				id: 		id
			}
		}
	);

	// Once the AJAX call has returned, refresh the page to show the new service
	ajaxRequest.done(function (response, textStatus, jqXHR) {
		location.reload(true);
	});
});
