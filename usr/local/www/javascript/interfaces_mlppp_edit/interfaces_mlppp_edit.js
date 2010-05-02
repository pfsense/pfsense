
function update_select_list(new_options, select_list){
	var option_array = new_options.split("|");
	select_list.options.length = 0;
	for(var j=0; j < option_array.length-1; j++){
		var option = option_array[j].split(",");
		var selected = Boolean(parseInt(option[2]));
		select_list.options[j] = new Option(option[0], option[1], false, selected);
		//this line for testing and debugging
		//select_list.options[option_array.length-1+j] = new Option(option[2].toString() +" "+ selected.toString());
	}
	create_change_fields("","link_fields");
}

function clear_selected(list_name){
	var select_list = document.iform[list_name];
	for(var j=0; j < select_list.options.length; j++){
		select_list.options[j].selected = 0;
	}
	
	create_change_fields();

}

function create_change_fields(port, fields_template){

	// First check if "port" has an associated row already created. If so, hide it. 
	var select_list = document.iform["interfaces[]"].options;
	var row_id = port + "_params";
	var row = $(fields_template).innerHTML;
	var rows_count = $('interfacetable').rows.length;
	if (port == null)
		for(var j=0; j < select_list.length-1; j++){
			var row_id = select_list[j].value + "_params";
			$('interfacetable').insertRow(rows_count -1);
			$('interfacetable').rows[rows_count -1].id = row_id;
			$(row_id).innerHTML = row;
			if (select_list[j].selected){
				$(other_row).show();
			} else {
				$(row_id).hide();
			}
			name = $('interfacetable').rows[rows_count -1].cells[0].innerHTML;
			$('interfacetable').rows[rows_count -1].cells[0].innerHTML = name + " (" + port + ")" + " " + row_id;
		}
	}
}


function updateType(t){
	var serialports = $('serialports').innerHTML;
	var ports = $('ports').innerHTML;
	var select_list = document.iform["interfaces[]"];
	switch(t) {
		case "select": {
			$('ppp','pppoe','pptp','ipfields','prefil_ppp').invoke('hide');
			select_list.options.length = 0;
			select_list.options[0] = new Option("Select Link Type First","");
			break;
		}
		case "ppp": {
			update_select_list(serialports, select_list);
			$('select','pppoe','pptp','subnet').invoke('hide');
			$('ipfields','prefil_ppp').invoke('show');
			
			break;
		}
		case "pppoe": {
			update_select_list(ports, select_list);
			$('select','ppp','pptp','ipfields','prefil_ppp').invoke('hide');
			break;
		}
		case "pptp": {
			update_select_list(ports, select_list);
			$('select','ppp','pppoe','prefil_ppp').invoke('hide');
			$('ipfields','subnet').invoke('show');
			break;
		}
		default:
			select_list.options.length = 0;
			break;
	}
	$(t).show();
}

function show_more_settings(obj,element_id) {
	if (obj.checked)
		$(element_id).show();
	else
		$(element_id).hide();
}

function prefill_att() {
	$('initstr').value = "Q0V1E1S0=0&C1&D2+FCLASS=0";
	$('apn').value = "ISP.CINGULAR";
	$('apnum').value = "1";
	$('phone').value = "*99#";
	$('username').value = "att";
	$('password').value = "att";
}
function prefill_sprint() {
	$('initstr').value = "E1Q0";
	$('apn').value = "";
	$('apnum').value = "";
	$('phone').value = "#777";
	$('username').value = "sprint";
	$('password').value = "sprint";
}
function prefill_vzw() {
	$('initstr').value = "E1Q0s7=60";
	$('apn').value = "";
	$('apnum').value = "";
	$('phone').value = "#777";
	$('username').value = "123@vzw3g.com";
	$('password').value = "vzw";
}
