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


	
