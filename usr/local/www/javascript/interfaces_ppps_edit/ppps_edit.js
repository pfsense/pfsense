
function update_select_list(new_options, select_list){
	var option_array = new_options.split("|");
	select_list.options.length = 0;
	for(var j=0; j < option_array.length-1; j++){
		var option = option_array[j].split(",");
		var selected = Boolean(parseInt(option[2]));
		select_list.options[j] = new Option(option[0], option[1], false, selected);
		var linklabel = "linklabel" + j.toString();
		var label_text = $(linklabel).innerHTML;
		$(linklabel).innerHTML = label_text + " (" + option[1] + ")";
		//for testing and debugging
		//select_list.options[option_array.length-1+j] = new Option(option[2].toString() +" "+ selected.toString());
		//select_list.options[option_array.length-1+j] = new Option("Link Label: " + linklabel + " Label Text:" + label_text);
	}
	show_hide_linkfields();
}

function clear_selected(list_name){
	var select_list = document.iform[list_name];
	for(var j=0; j < select_list.options.length; j++){
		select_list.options[j].selected = 0;
	}
}

function show_hide_linkfields(){
	var select_list = document.iform["interfaces[]"].options;
		for(var j=0; j < select_list.length; j++){
			var linklabel = "link" + j.toString();
			if (select_list[j].selected){
				$(linklabel).show();
				continue;
			}
			$(linklabel).hide();
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

function show_more_settings(checkbox_obj,element_id) {
	if (checkbox_obj.checked)
		$(element_id).show();
	else
		$(element_id).hide();
}

function show_reset_settings(reset_type) {
	if (reset_type == 'preset') { 
		Effect.Appear('pppoepresetwrap', { duration: 0.0 });
		Effect.Fade('pppoecustomwrap', { duration: 0.0 }); 
	} 
	else if (reset_type == 'custom') { 
		Effect.Appear('pppoecustomwrap', { duration: 0.0 });
		Effect.Fade('pppoepresetwrap', { duration: 0.0 });
	} else {
		Effect.Fade('pppoecustomwrap', { duration: 0.0 });
		Effect.Fade('pppoepresetwrap', { duration: 0.0 });
	}
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
