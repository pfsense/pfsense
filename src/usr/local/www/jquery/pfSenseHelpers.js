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
		$('#' + id).parent().addClass('hidden');
	else
		$('#' + id).parent().removeClass('hidden');
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