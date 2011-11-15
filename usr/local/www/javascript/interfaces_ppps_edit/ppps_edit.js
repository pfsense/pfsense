
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
	var adv_rows = parseInt(jQuery('#adv_rows').html());
	var adv_show = Boolean(parseInt(jQuery('#adv_show').html()));
	var status = Boolean(parseInt(hide));
	if (status){
		jQuery('#advanced_').hide();
		for(var j=0; j < adv_rows; j++){
			var advanced = "#advanced_" + j.toString();
			jQuery(advanced).show();
		}
		jQuery('#adv_show').html = "1";
		show_hide_linkfields(select_list);
	} else {
		jQuery('#advanced_').show();
		for(var j=0; j < adv_rows; j++){
			var advanced = "#advanced_" + j.toString();
			jQuery(advanced).hide();
		}
		jQuery('#adv_show').html("0");
		show_hide_linkfields(select_list);
	}
}

function show_hide_linkfields(options){
	var i = 0;
	var port_count = parseInt(jQuery('#port_count').html());
	var adv_show = Boolean(parseInt(jQuery('#adv_show').html()));
	for(var j=0; j < port_count; j++){
		var count = j.toString();
		var type = jQuery('#type').val();
		var link = "#link" + count;
		var lnklabel = "#linklabel" + count;
		var bw = "#bandwidth" + count;
		var bwlabel = "#bwlabel" + count;
		var mtu = "#mtu" + count;
		var mru = "#mru" + count;
		var mrru = "#mrru" + count;
		var ipfields = "#ip_fields" + count;
		var gwfields = "#gw_fields" + count;
		var localip = "#localip" + count;
		var localiplabel = "#localiplabel" + count;
		var subnet = "#subnet" + count;
		var gateway = "#gateway" + count;
		var gatewaylabel = "#gatewaylabel" + count;
		
		jQuery(ipfields + ',' + gwfields + ',' + link).hide();
		jQuery(subnet).prop('disabled',true);
		
		jQuery(bw).attr("name","bandwidth[]");
		jQuery(mtu).attr("name","mtu[]");
		jQuery(mru).attr("name","mru[]");
		jQuery(mrru).attr("name","mrru[]");
		jQuery(localip).attr("name","localip[]");
		jQuery(subnet).attr("name","subnet[]");
		jQuery(gateway).attr("name","gateway[]");
		
		while(i < options.length){
			if (options[i].selected ){
				jQuery(lnklabel).html("Link Parameters (" + options[i].value + ")");
				jQuery(bwlabel).html("Bandwidth (" + options[i].value + ")");
				jQuery(bw).attr("name","bandwidth[" + options[i].value + "]");
				jQuery(mtu).attr("name","mtu[" + options[i].value + "]");
				jQuery(mru).attr("name","mru[" + options[i].value + "]");
				jQuery(mrru).attr("name","mrru[" + options[i].value + "]");
				jQuery(localiplabel).html("Local IP (" + options[i].value + ")");
				jQuery(gatewaylabel).html("Gateway (" + options[i].value + ")");
				jQuery(localip).attr("name","localip[" + options[i].value + "]");
				jQuery(subnet).attr("name","subnet[" + options[i].value + "]");
				jQuery(gateway).attr("name","gateway[" + options[i].value + "]");
				if (type == 'ppp' && adv_show){
					jQuery(ipfields + ',' + gwfields).show();
				}
				if (type == 'pptp' || type == 'l2tp'){
					jQuery(subnet).prop("disabled",false);
					jQuery(ipfields + ',' + gwfields).show();
				}
				if (adv_show){
					jQuery(link).show();
				}
				i++;
				break;
			}
			i++;
		}
	}
}


function updateType(t){
	var serialports = jQuery('#serialports').html();
	var ports = jQuery('#ports').html();
	var select_list = document.iform["interfaces[]"].options;
	jQuery('#adv_show').html("0");
	show_advanced('0');
	jQuery("#select").show();
	switch(t) {
		case "select": {
			jQuery('#ppp,#pppoe,#ppp_provider,#phone_num,#apn_').hide();
			select_list.length = 0;
			select_list[0] = new Option("Select Link Type First","");
			break;
		}
		case "ppp": {
			update_select_list(serialports, select_list);
			jQuery('#select,#pppoe').hide();
			jQuery('#ppp_provider,#phone_num,#apn_').show();
			country_list();
			break;
		}
		case "pppoe": {
			update_select_list(ports, select_list);
			jQuery('#select,#ppp,#ppp_provider,#phone_num,#apn_').hide();
			break;
		}
		case "l2tp":
		case "pptp": {
			update_select_list(ports, select_list);
			jQuery('#select,#ppp,#pppoe,#ppp_provider,#phone_num,#apn_').hide();
			break;
		}
		default:
			select_list.length = 0;
			select_list[0] = new Option("Select Link Type First","");
			break;
	}
	if (t == "pppoe" || t == "ppp"){
		jQuery("#" + t).show();
	}
}

function show_reset_settings(reset_type) {
	if (reset_type == 'preset') { 
		jQuery('#pppoepresetwrap').show(0);
		jQuery('#pppoecustomwrap').hide(0);
	} 
	else if (reset_type == 'custom') { 
		jQuery('#pppoecustomwrap').show(0);
		jQuery('#pppoepresetwrap').hide(0);
	} else {
		jQuery('#pppoecustomwrap').hide(0);
		jQuery('#pppoepresetwrap').hide(0);
	}
}

function country_list() {
	jQuery('#country option').remove();
	jQuery('#provider option').remove();
	jQuery('#providerplan option').remove();
	jQuery.ajax("getserviceproviders.php",{
		success: function(responseText) {
			var responseTextArr = responseText.split("\n");
			responseTextArr.sort();
			for(i in responseTextArr) {
				country = responseTextArr[i].split(":");
				jQuery('#country').append(new Option(country[0],country[1]));
			}
		}
	});
	jQuery('#trcountry').css("display","table-row");
}

function providers_list() {
	jQuery('#provider option').remove();
	jQuery('#providerplan option').remove();
	jQuery.ajax("getserviceproviders.php",{
		type: 'POST',
		data: {country : jQuery('#country').val()},
		success: function(responseText) {
			var responseTextArr = responseText.split("\n");
			responseTextArr.sort();
			for(i in responseTextArr) {
				jQuery('#provider').append(new Option(responseTextArr[i],responseTextArr[i]));
			}
		}
	});
	jQuery('#trprovider').css("display","table-row");
	jQuery('#trproviderplan').css("display","none");
}

function providerplan_list() {
	jQuery('#providerplan option').remove();
	jQuery('#providerplan').append( new Option('','') );
	jQuery.ajax("getserviceproviders.php",{
		type: 'POST',
		data: {country : jQuery('#country').val(), provider : jQuery('#provider').val()},
		success: function(responseText) {
			var responseTextArr = responseText.split("\n");
			responseTextArr.sort();
			for(i in responseTextArr) {
				if(responseTextArr[i] != "") {
					providerplan = responseTextArr[i].split(":");
					jQuery('#providerplan').append(new Option(providerplan[0] + " - " + providerplan[1],providerplan[1]));
				}
			}
		}
	});
	jQuery('#trproviderplan').css("display","table-row");
}

function prefill_provider() {
	jQuery.ajax("getserviceproviders.php",{
		type: "POST",
		data: {country : jQuery('#country').val(), provider : jQuery('#provider').val(), plan : jQuery('#providerplan').val()},
		success: function(responseXML) {
			var xmldoc = responseXML;
			var provider = xmldoc.getElementsByTagName('connection')[0];
			jQuery('#username').val('');
			jQuery('#password').val('');
			if(provider.getElementsByTagName('apn')[0].firstChild.data == "CDMA") {
				jQuery('#phone').val('#777');
				jQuery('#apn').val('');
			} else {
				jQuery('#phone').val('*99#');
				jQuery('#apn').val(provider.getElementsByTagName('apn')[0].firstChild.data);
			}
			jQuery('#username').val(provider.getElementsByTagName('username')[0].firstChild.data);
			jQuery('#password').val(provider.getElementsByTagName('password')[0].firstChild.data);
		}
	});
}
