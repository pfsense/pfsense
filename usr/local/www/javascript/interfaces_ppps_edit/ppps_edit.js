
function update_select_list(new_options, select_list){
	var option_array = new_options.split("|");
	var i = 0;
	select_list.length = 0;
	for(var j=0; j < option_array.length-1; j++){
		var option = option_array[j].split(",");
		var selected = Boolean(parseInt(option[2]));
		select_list[j] = new Option(option[0], option[1], false, selected);
		//for testing and debugging
		//select_list.options[option_array.length-1+j] = new Option(option[2].toString() +" "+ selected.toString());
		//select_list.options[option_array.length-1+j] = new Option("Link Label: " + linklabel + " Label Text:" + label_text);
	}
	show_hide_linkfields(select_list);
}

function clear_selected(list_name){
	var select_list = document.iform[list_name];
	for(var j=0; j < select_list.options.length; j++){
		select_list.options[j].selected = 0;
	}
}

function show_hide_linkfields(options){
	var i = 0;
	var port_count = parseInt($('port_count').innerHTML);
	for(var j=0; j < port_count; j++){
		var count = j.toString();
		var type = $('type').value;
		var link = "link" + count;
		var lnklabel = "linklabel" + count;
		var bw = "bandwidth" + count;
		var bwlabel = "bwlabel" + count;
		var mtu = "mtu" + count;
		var mru = "mru" + count;
		var ipfields = "ipfields" + count;
		var localip = "localip" + count;
		var localiplabel = "localiplabel" + count;
		var subnet = "subnet" + count;
		var gateway = "gateway" + count;
		var gatewaylabel = "gatewaylabel" + count;
		
		$(ipfields,link).invoke('hide');
		$(subnet).disabled = true;
		
		$(bw).name = "bandwidth[]";
		$(mtu).name = "mtu[]";
		$(mru).name = "mru[]";
		$(localip).name = "localip[]";
		$(subnet).name = "subnet[]";
		$(gateway).name = "gateway[]";
		
		while(i < options.length){
			if (options[i].selected ){
				$(lnklabel).innerHTML = "Link Parameters (" + options[i].value + ")";
				$(bwlabel).innerHTML = "Bandwidth (" + options[i].value + ")";
				$(bw).name = "bandwidth[" + options[i].value + "]";
				$(mtu).name = "mtu[" + options[i].value + "]";
				$(mru).name = "mru[" + options[i].value + "]";
				$(localiplabel).innerHTML = "Local IP (" + options[i].value + ")";
				$(gatewaylabel).innerHTML = "Gateway (" + options[i].value + ")";
				$(localip).name = "localip[" + options[i].value + "]";
				$(subnet).name = "subnet[" + options[i].value + "]";
				$(gateway).name = "gateway[" + options[i].value + "]";
				if (type == 'pptp' || type == 'ppp' || type == 'l2tp'){
					$(ipfields).show();
					if (type == 'pptp' || type == 'l2tp'){
						$(subnet).disabled = false;
					}
				}
				$(link).show();
				i++;
				break;
			}
			i++;
		}
	}
}


function updateType(t){
	var serialports = $('serialports').innerHTML;
	var ports = $('ports').innerHTML;
	var select_list = document.iform["interfaces[]"].options;
	switch(t) {
		case "select": {
			$('ppp','pppoe','pptp','prefil_ppp').invoke('hide');
			select_list.length = 0;
			select_list[0] = new Option("Select Link Type First","");
			break;
		}
		case "ppp": {
			update_select_list(serialports, select_list);
			$('select','pppoe','pptp').invoke('hide');
			$('prefil_ppp').show();
			break;
		}
		case "pppoe": {
			update_select_list(ports, select_list);
			$('select','ppp','pptp','prefil_ppp').invoke('hide');
			break;
		}
		case "l2tp": {
			t = "pptp";
		}
		case "pptp": {
			update_select_list(ports, select_list);
			$('select','ppp','pppoe','prefil_ppp').invoke('hide');
			break;
		}
		default:
			select_list.length = 0;
			select_list[0] = new Option("Select Link Type First","");
			break;
	}
	if (t != ''){
		$(t).show();
	}
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
