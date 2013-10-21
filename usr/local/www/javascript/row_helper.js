// Global Variables
var rowname = new Array(4999);
var rowtype = new Array(4999);
var newrow  = new Array(4999);
var rowsize = new Array(4999);

for (i = 0; i < 4999; i++) {
	rowname[i] = '';
	rowtype[i] = '';
	newrow[i] = '';
	rowsize[i] = '30';
}

var field_counter_js = 0;
var loaded = 0;
var is_streaming_progress_bar = 0;
var temp_streaming_text = "";

var addRowTo = (function() {
    return (function (tableId) {
	var $ = jQuery;
	var d, tbody, tr, td, bgc, i, ii, j;
	d = document;
	tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
	tr = d.createElement("tr");
	for (i = 0; i < field_counter_js; i++) {
		td = d.createElement("td");
		if(typeof(rowtype[i]) == 'function') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input>" + rowtype[i](rowname[i], rowsize[i], totalrows) + " ";
		} else if(rowtype[i] == 'textbox') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input size='" + rowsize[i] + "' class='formfld unknown' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		} else if(rowtype[i] == 'textbox,ipv4v6') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input size='" + rowsize[i] + "' class='formfld unknown ipv4v6' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		} else if(rowtype[i] == 'password') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input type='password' size='" + rowsize[i] + "' class='formfld pwd' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		} else if(rowtype[i] == 'select') {
                        var cidr;
			var str;
			for (cidr = 128; cidr>= 1; cidr--) {
				str=str + "<option value=\"" + cidr + "\" >" + cidr + "</option>";
			}
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><select size='1' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'>" + str + "</select> ";
		} else if(rowtype[i] == 'select,ipv4v6') {
                        var cidr;
			var str;
			for (cidr = 128; cidr>= 1; cidr--) {
				str=str + "<option value=\"" + cidr + "\" >" + cidr + "</option>";
			}
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><select class='ipv4v6' size='1' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'>" + str + "</select> ";
		} else if(rowtype[i] == 'select_source') {
                        var cidr;
			var str;
			for (cidr = 128; cidr>= 1; cidr--) {
				str=str + "<option value=\"" + cidr + "\" >" + cidr + "</option>";
			}
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><select size='1' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'>" + str + "</select> ";
		} else {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input type='checkbox' name='" + rowname[i] + totalrows + "'></input> ";
		}
		tr.appendChild(td);
	}
	td = d.createElement("td");
	td.rowSpan = "1";

	td.innerHTML = '<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/' + theme + '/images/icons/icon_x.gif" /></a>';
	tr.appendChild(td);
	tbody.appendChild(tr);
	totalrows++;
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
