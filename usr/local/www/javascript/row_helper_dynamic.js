// Global Variables
var rowname				= new Array(99);
var rowtype				= new Array(99);
var newrow 				= new Array(99);
var rowsize  			= new Array(99);
var rowhelper_onChange 	= '';

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
	var d, tbody, tr, td, bgc, i, ii, j;
	d = document;
	tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
	tr = d.createElement("tr");
	totalrows++;
	if (!objectSize)
		objectSize = rowsize[i];
	for (i = 0; i < field_counter_js; i++) {
		td = d.createElement("td");
		if(typeof(rowtype[i]) == 'function') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input>" + rowtype[i](rowname[i], objectSize, totalrows) + " ";
		} else if(rowtype[i] == 'textbox') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input " + rowhelper_onChange + " size='" + rowsize[i] + "' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		} else if(rowtype[i] == 'select') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><select " + rowhelper_onChange + " name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'>" + newrow[i] + "</select> ";
		} else if(rowtype[i] == 'select_source') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><select " + rowhelper_onChange + " name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'>" + newrow[i] + "</select> ";
		} else if(rowtype[i] == 'checkbox') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input " + rowhelper_onChange + " type='checkbox'name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		} else if(rowtype[i] == 'input') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input " + rowhelper_onChange + " class='formfld unknown' size='" + objectSize + "' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		} else if(rowtype[i] == 'password') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input " + rowhelper_onChange + " class='formfld pwd' type='password' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		}
		tr.appendChild(td);
	}
	td = d.createElement("td");
	td.rowSpan = "1";
	td.innerHTML = '<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/' + theme + '/images/icons/icon_x.gif" /></a>';
	tr.appendChild(td);
	tbody.appendChild(tr);
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

function function_exists (function_name) {
    // http://kevin.vanzonneveld.net
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Steve Clay
    // +   improved by: Legaev Andrey
    // *     example 1: function_exists('isFinite');
    // *     returns 1: true
    if (typeof function_name == 'string'){
        return (typeof this.window[function_name] == 'function');
    } else{
        return (function_name instanceof Function);
    }
}