
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

function show_advanced(hide){
	var select_list = document.iform["interfaces[]"].options;
	var adv_rows = parseInt($('adv_rows').innerHTML);
	var adv_show = Boolean(parseInt($('adv_show').innerHTML));
	var status = Boolean(parseInt(hide));
	if (status){
		$('advanced_').hide();
		for(var j=0; j < adv_rows; j++){
			var advanced = "advanced_" + j.toString();
			$(advanced).show();
		}
		$('adv_show').innerHTML = "1";
		show_hide_linkfields(select_list);
	} else {
		$('advanced_').show();
		for(var j=0; j < adv_rows; j++){
			var advanced = "advanced_" + j.toString();
			$(advanced).hide();
		}
		$('adv_show').innerHTML = "0";
		show_hide_linkfields(select_list);
	}
}

function show_hide_linkfields(options){
	var i = 0;
	var port_count = parseInt($('port_count').innerHTML);
	var adv_show = Boolean(parseInt($('adv_show').innerHTML));
	for(var j=0; j < port_count; j++){
		var count = j.toString();
		var type = $('type').value;
		var link = "link" + count;
		var lnklabel = "linklabel" + count;
		var bw = "bandwidth" + count;
		var bwlabel = "bwlabel" + count;
		var mtu = "mtu" + count;
		var mru = "mru" + count;
		var mrru = "mrru" + count;
		var ipfields = "ip_fields" + count;
		var gwfields = "gw_fields" + count;
		var localip = "localip" + count;
		var localiplabel = "localiplabel" + count;
		var subnet = "subnet" + count;
		var gateway = "gateway" + count;
		var gatewaylabel = "gatewaylabel" + count;
		
		$(ipfields, gwfields ,link).invoke('hide');
		$(subnet).disabled = true;
		
		$(bw).name = "bandwidth[]";
		$(mtu).name = "mtu[]";
		$(mru).name = "mru[]";
		$(mrru).name = "mrru[]";
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
				$(mrru).name = "mrru[" + options[i].value + "]";
				$(localiplabel).innerHTML = "Local IP (" + options[i].value + ")";
				$(gatewaylabel).innerHTML = "Gateway (" + options[i].value + ")";
				$(localip).name = "localip[" + options[i].value + "]";
				$(subnet).name = "subnet[" + options[i].value + "]";
				$(gateway).name = "gateway[" + options[i].value + "]";
				if (type == 'ppp' && adv_show){
					$(ipfields, gwfields).invoke('show');
				}
				if (type == 'pptp' || type == 'l2tp'){
					$(subnet).disabled = false;
					$(ipfields, gwfields).invoke('show');
				}
				if (adv_show){
					$(link).show();
				}
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
	$('adv_show').innerHTML = "0";
	show_advanced('0');
	switch(t) {
		case "select": {
			$('ppp','pppoe','ppp_provider','phone_num','apn_','pptp','route','proxyarp','ip-ranges','require-dns','enable-comp','enable-crypt','mppc-enable','pred1','deflate','mppe-enable','mppe-40','mppe-56','mppe-128','mppec-stateless','mppec-policy','dese-bis','ese-old','keep-alive','max-redial','chap','pap','eap').invoke('hide');
			select_list.length = 0;
			select_list[0] = new Option("Select Link Type First","");
			break;
		}
		case "ppp": {
			update_select_list(serialports, select_list);
			$('select','pppoe').invoke('hide');
			$('ppp_provider','phone_num','apn_').invoke('show');
			country_list();
			break;
		}
		case "pppoe": {
			update_select_list(ports, select_list);
			$('select','ppp','ppp_provider','phone_num','apn_').invoke('hide');
			break;
		}
		case "l2tp": {
			update_select_list(ports, select_list);
			$('select','ppp','pppoe','ppp_provider','phone_num','apn_','pptp','route','proxyarp','ip-ranges','require-dns','enable-comp','enable-crypt','mppc-enable','pred1','deflate','mppe-enable','mppe-40','mppe-56','mppe-128','mppec-stateless','mppec-policy','dese-bis','ese-old','keep-alive','max-redial','chap','pap','eap').invoke('hide');
			break;
		}
		case "pptp": {
			update_select_list(ports, select_list);
			$('select','ppp','pppoe','ppp_provider','phone_num','apn_').invoke('hide');
			$('pptp','route','proxyarp','ip-ranges','require-dns','enable-comp','enable-crypt','mppc-enable','pred1','deflate','mppe-enable','mppe-40','mppe-56','mppe-128','mppec-stateless','mppec-policy','dese-bis','ese-old','keep-alive','max-redial','chap','pap','eap').invoke('show');
			break;
		}
		default:
			select_list.length = 0;
			select_list[0] = new Option("Select Link Type First","");
			break;
	}
	if (t == "pppoe" || t == "ppp"){
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

function country_list() {
	$('country').childElements().each(function(node) { node.remove(); });
	$('provider').childElements().each(function(node) { node.remove(); });
	$('providerplan').childElements().each(function(node) { node.remove(); });
	new Ajax.Request("getserviceproviders.php",{
		onSuccess: function(response) {
			var responseTextArr = response.responseText.split("\n");
			responseTextArr.sort();
			responseTextArr.each( function(value) {
				var option = new Element('option');
				country = value.split(":");
				option.text = country[0];
				option.value = country[1];
				$('country').insert({ bottom : option });
			});
		}
	});
	$('trcountry').setStyle({display : "table-row"});
}

function providers_list() {
	$('provider').childElements().each(function(node) { node.remove(); });
	$('providerplan').childElements().each(function(node) { node.remove(); });
	new Ajax.Request("getserviceproviders.php",{
		parameters: {country : $F('country')},
		onSuccess: function(response) {
			var responseTextArr = response.responseText.split("\n");
			responseTextArr.sort();
			responseTextArr.each( function(value) {
				var option = new Element('option');
				option.text = value;
				option.value = value;
				$('provider').insert({ bottom : option });
			});
		}
	});
	$('trprovider').setStyle({display : "table-row"});
	$('trproviderplan').setStyle({display : "none"});
}

function providerplan_list() {
	$('providerplan').childElements().each(function(node) { node.remove(); });
	$('providerplan').insert( new Element('option') );
	new Ajax.Request("getserviceproviders.php",{
		parameters: {country : $F('country'), provider : $F('provider')},
		onSuccess: function(response) {
			var responseTextArr = response.responseText.split("\n");
			responseTextArr.sort();
			responseTextArr.each( function(value) {
				if(value != "") {
					providerplan = value.split(":");

					var option = new Element('option');
					option.text = providerplan[0] + " - " + providerplan[1];
					option.value = providerplan[1];
					$('providerplan').insert({ bottom : option });
				}
			});
		}
	});
	$('trproviderplan').setStyle({display : "table-row"});
}

function prefill_provider() {
	new Ajax.Request("getserviceproviders.php",{
		parameters: {country : $F('country'), provider : $F('provider'), plan : $F('providerplan')},
		onSuccess: function(response) {
			var xmldoc = response.responseXML;
			var provider = xmldoc.getElementsByTagName('connection')[0];
			$('username').setValue('');
			$('password').setValue('');
			if(provider.getElementsByTagName('apn')[0].firstChild.data == "CDMA") {
				$('phone').setValue('#777');
				$('apn').setValue('');
			} else {
				$('phone').setValue('*99#');
				$('apn').setValue(provider.getElementsByTagName('apn')[0].firstChild.data);
			}
			$('username').setValue(provider.getElementsByTagName('username')[0].firstChild.data);
			$('password').setValue(provider.getElementsByTagName('password')[0].firstChild.data);
		}
	});
}
