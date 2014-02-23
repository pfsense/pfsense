/*jslint white: true, sloppy: true, vars: true, eqeq: true */
/*jslint browser: true, devel: true */
/*global show_hide_linkfields, jQuery, country_list */

function update_select_list(new_options, select_list){
	var option_array = new_options.split("|");
	var i = 0;
	var j;
	select_list.length = 0;
	for(j=0; j < option_array.length-1; j++){
		var option = option_array[j].split(",");
		var selected = Boolean(parseInt(option[2], 10));
		select_list[j] = new Option(option[0], option[1], false, selected);
		//for testing and debugging
		//select_list.options[option_array.length-1+j] = new Option(option[2].toString() +" "+ selected.toString());
		//select_list.options[option_array.length-1+j] = new Option("Link Label: " + linklabel + " Label Text:" + label_text);
	}
	show_hide_linkfields(select_list);
}

function show_advanced(hide){
	var select_list = document.iform["interfaces[]"].options;
	var adv_rows = parseInt(jQuery('#adv_rows').html(), 10);
	var adv_show = Boolean(parseInt(jQuery('#adv_show').html(), 10));
	var status = Boolean(parseInt(hide, 10));
	var j, advanced;
	if (status){
		jQuery('#advanced_').hide();
		for(j=0; j < adv_rows; j++){
			advanced = "#advanced_" + j.toString();
			jQuery(advanced).show();
		}
		jQuery('#adv_show').html = "1";
		show_hide_linkfields(select_list);
	} else {
		jQuery('#advanced_').show();
		for(j=0; j < adv_rows; j++){
			advanced = "#advanced_" + j.toString();
			jQuery(advanced).hide();
		}
		jQuery('#adv_show').html("0");
		show_hide_linkfields(select_list);
	}
}

function show_hide_linkfields(options){
	var i = 0;
	var port_count = parseInt(jQuery('#port_count').html(), 10);
	var adv_show = Boolean(parseInt(jQuery('#adv_show').html(), 10));
	var j, count, type, link, lnklabel, bw, bwlabel, mtu, mru, mrru, ipfields, gwfields, localip,
	    localiplabel, subnet, gateway, gatewaylabel;
	for(j=0; j < port_count; j++){
		count = j.toString();
		type = jQuery('#type').val();
		link = "#link" + count;
		lnklabel = "#linklabel" + count;
		bw = "#bandwidth" + count;
		bwlabel = "#bwlabel" + count;
		mtu = "#mtu" + count;
		mru = "#mru" + count;
		mrru = "#mrru" + count;
		ipfields = "#ip_fields" + count;
		gwfields = "#gw_fields" + count;
		localip = "#localip" + count;
		localiplabel = "#localiplabel" + count;
		subnet = "#subnet" + count;
		gateway = "#gateway" + count;
		gatewaylabel = "#gatewaylabel" + count;
		
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
		case "select":
			jQuery('#ppp,#pppoe,#ppp_provider,#phone_num,#apn_').hide();
			select_list.length = 0;
			select_list[0] = new Option("Select Link Type First","");
			break;
		case "ppp":
			update_select_list(serialports, select_list);
			jQuery('#select,#pppoe').hide();
			jQuery('#ppp_provider,#phone_num,#apn_').show();
			country_list();
			break;
		case "pppoe":
			update_select_list(ports, select_list);
			jQuery('#select,#ppp,#ppp_provider,#phone_num,#apn_').hide();
			break;
		case "l2tp":
		case "pptp":
			update_select_list(ports, select_list);
			jQuery('#select,#ppp,#pppoe,#ppp_provider,#phone_num,#apn_').hide();
			break;
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
	jQuery('#provider_list option').remove();
	jQuery('#providerplan option').remove();
	jQuery('#country').append(new Option('', ''));
	jQuery.ajax("getserviceproviders.php",{
		success: function(responseText) {
			var responseTextArr = responseText.split("\n");
			var value, i, country;
			responseTextArr.sort();
			for (i = 0; i < responseTextArr.length; i += 1) {
				value = responseTextArr[i];
				if (/\S/.test(value)) {
					country = value.split(":");
					jQuery('#country').append(new Option(country[0],country[1]));
				}
			}
		}
	});
	jQuery('#trcountry').css("display","table-row");
}

function providers_list() {
	jQuery('#provider_list option').remove();
	jQuery('#providerplan option').remove();
	jQuery('#provider_list').append(new Option('', ''));
	jQuery.ajax("getserviceproviders.php",{
		type: 'POST',
		data: {country : jQuery('#country').val()},
		success: function(responseText) {
			var responseTextArr = responseText.split("\n");
			var value, i;
			responseTextArr.sort();
			for (i = 0; i < responseTextArr.length; i += 1) {
				value = responseTextArr[i];
				if (/\S/.test(value)) {
					jQuery('#provider_list').append(new Option(value, value));
				}
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
		data: {country : jQuery('#country').val(), provider : jQuery('#provider_list').val()},
		success: function(responseText) {
			var responseTextArr = responseText.split("\n");
			var value, providerplan, i;
			responseTextArr.sort();
			for (i = 0; i < responseTextArr.length; i += 1) {
				value = responseTextArr[i];
				if (/\S/.test(value)) {
					providerplan = value.split(":");
					jQuery('#providerplan').append(new Option(providerplan[0] + " - " + providerplan[1],
										  providerplan[1]));
				}
			}
		}
	});
	jQuery('#trproviderplan').css("display","table-row");
}

function prefill_provider() {
	jQuery.ajax("getserviceproviders.php",{
		type: "POST",
		data: {country : jQuery('#country').val(), provider : jQuery('#provider_list').val(), plan : jQuery('#providerplan').val()},
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
