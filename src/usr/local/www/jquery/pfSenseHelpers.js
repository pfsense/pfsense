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
	if(hide)
		$('#' + id).parent().parent('div').addClass('hidden');
	else
		$('#' + id).parent().parent('div').removeClass('hidden');
}

// Hides the <div> in which the specified group input element lives so that the input,
// its label and help text are hidden
function hideGroupInput(id, hide) {
	if(hide)
		$('#' + id).parent('div').addClass('hidden');
	else
		$('#' + id).parent('div').removeClass('hidden');
}

// Hides the <div> in which the specified checkbox lives so that the checkbox, its label and help text are hidden
function hideCheckbox(id, hide) {
	if(hide)
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
	if(hide)
		$('.' + s_class).hide();
	else
		$('.' + s_class).show();
}

// Hides all elements of the specified class assigned to a group. This will usually be a group
function hideGroupClass(s_class, hide) {
	if(hide)
		$('.' + s_class).parent().parent().parent().hide();
	else
		$('.' + s_class).parent().parent().parent().show();
}

function hideSelect(id, hide) {
	if(hide)
		$('#' + id).parent('div').parent('div').addClass('hidden');
	else
		$('#' + id).parent('div').parent('div').removeClass('hidden');
}

function hideMultiCheckbox(id, hide) {
	if(hide)
		$("[name=" + id + "]").parent().addClass('hidden');
	else
		$("[name=" + id + "]").parent().removeClass('hidden');
}

// Hides the <div> in which the specified IP address element lives so that the input, its label and help text are hidden
function hideIpAddress(id, hide) {
	if(hide)
		$('#' + id).parent().parent().parent('div').addClass('hidden');
	else
		$('#' + id).parent().parent().parent('div').removeClass('hidden');
}

// Hides all elements of the specified class belonging to a multiselect.
function hideMultiClass(s_class, hide) {
	if(hide)
		$('.' + s_class).parent().parent().hide();
	else
		$('.' + s_class).parent().parent().show();
}

// Hides div whose label contains the specified text. (Good for StaticText)
function hideLabel(text, hide) {

	var element = $('label:contains(' + text + ')');

	if(hide)
		element.parent('div').addClass('hidden');
	else
		element.parent('div').removeClass('hidden');
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

// NOTE: retainhelp is a global var that defined prevents any help text from being deleted as lines are inserted.
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

			if (select.options.length < max)
			{
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

		if(!$(this).hasClass('pfIpMask') && !$(this).hasClass('btn')) {
			if($('#' + decrStringInt(fromId)).parent('div').hasClass('input-group')) {
				helpSpan = $('#' + fromId).parent('div').parent('div').find('span:last').clone();
			} else {
				helpSpan = $('#' + fromId).parent('div').find('span:last').clone();
			}
			if($(helpSpan).hasClass('help-block')) {
				if($('#' + decrStringInt(fromId)).parent('div').hasClass('input-group')) {
					$('#' + decrStringInt(fromId)).parent('div').after(helpSpan);
				}
				else {
					$('#' + decrStringInt(fromId)).after(helpSpan);
				}
			}
		}
	});
}

// Increment the number at the end of the string
function bumpStringInt( str )	{
  var data = str.match(/(\D*)(\d+)(\D*)/), newStr = "";

  if( data )
	newStr = data[ 1 ] + ( Number( data[ 2 ] ) + 1 ) + data[ 3 ];

  return newStr || str;
}

// Decrement the number at the end of the string
function decrStringInt( str )	{
  var data = str.match(/(\D*)(\d+)(\D*)/), newStr = "";

  if( data )
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

function delete_row(row) {
	$('#' + row).parent('div').parent('div').remove();
	renumber();
	checkLastRow();
}

function checkLastRow() {
	if($('.repeatable').length <= 1) {
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
		if(!$(this).is('[id^=delete]'))
			$(this).val('');
	});

	// Do the same for selectors
	$(newGroup).find('select').each(function() {
		$(this).prop("id", bumpStringInt(this.id));
		$(this).prop("name", bumpStringInt(this.name));
		// If this selector lists mask bits, we need it to be reset to all 128 options
		// and no items selected, so that automatic v4/v6 selection still works
		if($(this).is('[id^=address_subnet]')) {
			$(this).empty();
			for(idx=128; idx>0; idx--) {
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
		if((typeof retainhelp) == "undefined")
			$(this).remove();
	});

	setMasks();

	checkLastRow();

	// Autocomplete
	if ( typeof addressarray !== 'undefined') {
		$('[id^=address]').each(function() {
			if(this.id.substring(0, 8) != "address_") {
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
		if($('.repeatable').length > 1) {
			if((typeof retainhelp) == "undefined")
				moveHelpText(event.target.id);

			delete_row(event.target.id);
		}
		else
			alert('You may not delete the last row!');
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
	if($('.repeatable').length > 1) {
		if((typeof retainhelp) == "undefined")
			moveHelpText(event.target.id);

		delete_row(event.target.id);
	}
	else
		alert('You may not delete the last row!');
});

// "More information" handlers

// If there is an infoblock, automatically add an info icon that toggles its display
if($('.infoblock,.infoblock_open,#infoblock').length != 0) {
	$('.infoblock,.infoblock_open,#infoblock').before('<i class="fa fa-info-circle icon-pointer" style="color: #337AB7;; font-size:20px; margin-left: 10px; margin-bottom: 10px;" id="showinfo" title="More information"></i>');

	// and remove the 'X' button from the last text box (Which we assume to be the infoblock)
	$('.close :last').remove();
}

// Hide information on page load
$('.infoblock,#infoblock').hide();

// Show the help on clicking the info icon
$('#showinfo').click(function() {
	$('.infoblock,.infoblock_open,#infoblock').toggle();
});

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

		if($(el).closest('a').attr('id') != 'widgets-available') {
			updateWidgets();
		}
	});

	body.on('hidden.bs.collapse', function(){
		$(el).children('i').toggleClass('fa-minus-circle', false);
		$(el).children('i').toggleClass('fa-plus-circle', true);

		if($(el).closest('a').attr('id') != 'widgets-available') {
			updateWidgets();
		}
	});
});