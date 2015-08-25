// Global Variables
var rowname				= new Array(99);
var rowtype				= new Array(99);
var newrow 				= new Array(99);
var rowsize  			= new Array(99);

// Global variables.  Set to javascript code
// that will be eval() after change, add & delete.
var rowhelper_onChange 	= '';
var rowhelper_onAdd		= '';
var rowhelper_onDelete	= '';

for (i = 0; i < 99; i++) {
	rowname[i] = '';
	rowtype[i] = '';
	newrow[i] = '';
	rowsize[i] = '25';
}

var field_counter_js = 0;
var loaded = 0;
var is_streaming_progress_bar = 0;
var temp_streaming_text = "";

var addRowTo = (function() {
    return (function (tableId, objectSize) {
	var $ = jQuery;
	var d, tbody, tr, td, bgc, i, ii, j, size, className;
	d = document;
	tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
	tr = d.createElement("tr");
	totalrows++;
	size = objectSize;
	for (i = 0; i < field_counter_js; i++) {
		if (!objectSize)
			size = rowsize[i];
		td = d.createElement("td");
		if(typeof(rowtype[i]) == 'function') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' />" + rowtype[i](rowname[i], size, totalrows) + " ";
		} else if(rowtype[i] == 'textbox') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><input " + rowhelper_onChange + " size='" + rowsize[i] + "' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "' /> ";
		} else if(rowtype[i] == 'textbox,ipv4v6') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><input " + rowhelper_onChange + " size='" + rowsize[i] + "' name='" + rowname[i] + totalrows + "' class='ipv4v6' id='" + rowname[i] + totalrows + "' /> ";
		} else if(rowtype[i] == 'select') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><select " + rowhelper_onChange + " name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'>" + newrow[i] + "</select> ";
		} else if(rowtype[i] == 'select,ipv4v6') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><select " + rowhelper_onChange + " name='" + rowname[i] + totalrows + "' class='ipv4v6' id='" + rowname[i] + totalrows + "'>" + newrow[i] + "</select> ";
		} else if(rowtype[i] == 'interfaces_selection') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><select " + rowhelper_onChange + " name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'>" + newrow[i] + "</select> ";
		} else if(rowtype[i] == 'select_source') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><select " + rowhelper_onChange + " name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'>" + newrow[i] + "</select> ";
		} else if(rowtype[i] == 'checkbox') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><input " + rowhelper_onChange + " type='checkbox'name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "' /> ";
		} else if(rowtype[i] == 'input') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><input " + rowhelper_onChange + " class='formfld unknown' size='" + size + "' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "' /> ";
		} else if(rowtype[i] == 'password') {
			td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><input " + rowhelper_onChange + " class='formfld pwd' type='password' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "' /> ";
		}
		tr.appendChild(td);
	}
	td = d.createElement("td");
	td.rowSpan = "1";
	td.innerHTML = '<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/' + theme + '/images/icons/icon_x.gif" /></a>';
	tr.appendChild(td);
	tbody.appendChild(tr);
	if(rowhelper_onAdd != '') 
		eval(rowhelper_onAdd);
	if($(tr).ipv4v6ify)
		$(tr).ipv4v6ify();
    });
})();

function removeRow(el) {
    var cel;
    while (el && el.nodeName.toLowerCase() != "tr")
	    el = el.parentNode;

    if (el && el.parentNode) {
	cel = el.getElementsByTagName("td").item(0);
	el.parentNode.removeChild(el);
    }
	if(rowhelper_onDelete != '') 
		eval(rowhelper_onDelete);
}

function find_unique_field_name(field_name) {
	// loop through field_name and strip off -NUMBER
	var last_found_dash = 0;
	for (var i = 0; i < field_name.length; i++) {
		// is this a dash, if so, update
		//    last_found_dash
		if (field_name.substr(i,1) == "-" )
			last_found_dash = i;
	}
	if (last_found_dash < 1)
		return field_name;
	return(field_name.substr(0,last_found_dash));
}
