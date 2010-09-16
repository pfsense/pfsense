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
	var d, tbody, tr, td, bgc, i, ii, j;
	d = document;
	tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
	tr = d.createElement("tr");
	for (i = 0; i < field_counter_js; i++) {
		td = d.createElement("td");
		if(rowtype[i] == 'textbox') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input size='" + rowsize[i] + "' class='formfld unknown' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		} else if(rowtype[i] == 'password') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><input type='password' size='" + rowsize[i] + "' class='formfld pwd' name='" + rowname[i] + totalrows + "' id='" + rowname[i] + totalrows + "'></input> ";
		} else if(rowtype[i] == 'select') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><select size='1' name='" + rowname[i] + totalrows + "'><option value=\"32\" selected>32</option><option value=\"31\" >31</option><option value=\"30\" >30</option><option value=\"29\" >29</option><option value=\"28\" >28</option><option value=\"27\" >27</option><option value=\"26\" >26</option><option value=\"25\" >25</option><option value=\"24\" >24</option><option value=\"23\" >23</option><option value=\"22\" >22</option><option value=\"21\" >21</option><option value=\"20\" >20</option><option value=\"19\" >19</option><option value=\"18\" >18</option><option value=\"17\" >17</option><option value=\"16\" >16</option><option value=\"15\" >15</option><option value=\"14\" >14</option><option value=\"13\" >13</option><option value=\"12\" >12</option><option value=\"11\" >11</option><option value=\"10\" >10</option><option value=\"9\" >9</option><option value=\"8\" >8</option><option value=\"7\" >7</option><option value=\"6\" >6</option><option value=\"5\" >5</option><option value=\"4\" >4</option><option value=\"3\" >3</option><option value=\"2\" >2</option><option value=\"1\" >1</option></select> ";
		} else if(rowtype[i] == 'select_source') {
			td.innerHTML="<INPUT type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "'></input><select size='1' name='" + rowname[i] + totalrows + "'><option value=\"32\" selected>32</option><option value=\"31\" >31</option><option value=\"30\" >30</option><option value=\"29\" >29</option><option value=\"28\" >28</option><option value=\"27\" >27</option><option value=\"26\" >26</option><option value=\"25\" >25</option><option value=\"24\" >24</option><option value=\"23\" >23</option><option value=\"22\" >22</option><option value=\"21\" >21</option><option value=\"20\" >20</option><option value=\"19\" >19</option><option value=\"18\" >18</option><option value=\"17\" >17</option><option value=\"16\" >16</option><option value=\"15\" >15</option><option value=\"14\" >14</option><option value=\"13\" >13</option><option value=\"12\" >12</option><option value=\"11\" >11</option><option value=\"10\" >10</option><option value=\"9\" >9</option><option value=\"8\" >8</option><option value=\"7\" >7</option><option value=\"6\" >6</option><option value=\"5\" >5</option><option value=\"4\" >4</option><option value=\"3\" >3</option><option value=\"2\" >2</option><option value=\"1\" >1</option></select> ";
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
