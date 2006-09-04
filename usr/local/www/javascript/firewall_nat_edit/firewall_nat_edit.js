<!--
function ext_change() {
	if (document.iform.beginport.selectedIndex == 0) {
		document.iform.beginport_cust.disabled = 0;
	} else {
		document.iform.beginport_cust.value = "";
		document.iform.beginport_cust.disabled = 1;
	}
	if (document.iform.endport.selectedIndex == 0) {
		document.iform.endport_cust.disabled = 0;
	} else {
		document.iform.endport_cust.value = "";
		document.iform.endport_cust.disabled = 1;
	}
	if (document.iform.localbeginport.selectedIndex == 0) {
		document.iform.localbeginport_cust.disabled = 0;
	} else {
		document.iform.localbeginport_cust.value = "";
		document.iform.localbeginport_cust.disabled = 1;
	}
}

function ext_rep_change() {
	document.iform.endport.selectedIndex = document.iform.beginport.selectedIndex;
	document.iform.localbeginport.selectedIndex = document.iform.beginport.selectedIndex;
}

function check_for_aliases() {
	/*  if External port range is an alias, then disallow
	 *  entry of Local port
	 */
	for(i=0; i<customarray.length; i++) {
		if(document.iform.beginport_cust.value == customarray[i]) {
			document.iform.endport_cust.value = customarray[i];
			document.iform.localbeginport_cust.value = customarray[i];
			document.iform.endport_cust.disabled = 1;
			document.iform.localbeginport.disabled = 1;
			document.iform.localbeginport_cust.disabled = 1;
			document.iform.endport_cust.disabled = 0;
			document.iform.localbeginport.disabled = 0;
			document.iform.localbeginport_cust.disabled = 0;
		}
		if(document.iform.beginport.value == customarray[i]) {
			document.iform.endport_cust.value = customarray[i];
			document.iform.localbeginport_cust.value = customarray[i];
			document.iform.endport_cust.disabled = 1;
			document.iform.localbeginport.disabled = 1;
			document.iform.localbeginport_cust.disabled = 1;
			document.iform.endport_cust.disabled = 0;
			document.iform.localbeginport.disabled = 0;
			document.iform.localbeginport_cust.disabled = 0;
		}
		if(document.iform.endport_cust.value == customarray[i]) {
			document.iform.endport_cust.value = customarray[i];
			document.iform.localbeginport_cust.value = customarray[i];
			document.iform.endport_cust.disabled = 1;
			document.iform.localbeginport.disabled = 1;
			document.iform.localbeginport_cust.disabled = 1;
			document.iform.endport_cust.disabled = 0;
			document.iform.localbeginport.disabled = 0;
			document.iform.localbeginport_cust.disabled = 0;
		}
		if(document.iform.endport.value == customarray[i]) {
			document.iform.endport_cust.value = customarray[i];
			document.iform.localbeginport_cust.value = customarray[i];
			document.iform.endport_cust.disabled = 1;
			document.iform.localbeginport.disabled = 1;
			document.iform.localbeginport_cust.disabled = 1;
			document.iform.endport_cust.disabled = 0;
			document.iform.localbeginport.disabled = 0;
			document.iform.localbeginport_cust.disabled = 0;
		}
	}
}

function proto_change() {
	if(document.iform.proto.selectedIndex > 2) {
		document.iform.beginport_cust.disabled = 1;
		document.iform.endport_cust.disabled = 1;
		document.iform.beginport.disabled = 1;
		document.iform.endport.disabled = 1;
		document.iform.localbeginport_cust.disabled = 1;
		document.iform.localbeginport.disabled = 1;
	} else {
		document.iform.beginport_cust.disabled = 0;
		document.iform.endport_cust.disabled = 0;
		document.iform.beginport.disabled = 0;
		document.iform.endport.disabled = 0;
		document.iform.localbeginport_cust.disabled = 0;
		document.iform.localbeginport.disabled = 0;
	}
}

window.onload = function () {
	var oTextbox1 = new AutoSuggestControl(document.getElementById("localip"), new StateSuggestions(addressarray));
	var oTextbox2 = new AutoSuggestControl(document.getElementById("beginport_cust"), new StateSuggestions(customarray));
	var oTextbox3 = new AutoSuggestControl(document.getElementById("endport_cust"), new StateSuggestions(customarray));
	var oTextbox4 = new AutoSuggestControl(document.getElementById("localbeginport_cust"), new StateSuggestions(customarray));
}

//-->