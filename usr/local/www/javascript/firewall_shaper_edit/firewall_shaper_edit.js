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


window.onload = function () {
	var oTextbox1 = new AutoSuggestControl(document.getElementById("src"), new StateSuggestions(addressarray));
	var oTextbox2 = new AutoSuggestControl(document.getElementById("srcbeginport_cust"), new StateSuggestions(customarray));
	var oTextbox3 = new AutoSuggestControl(document.getElementById("srcendport_cust"), new StateSuggestions(customarray));
	var oTextbox1 = new AutoSuggestControl(document.getElementById("dst"), new StateSuggestions(addressarray));
	var oTextbox2 = new AutoSuggestControl(document.getElementById("dstbeginport_cust"), new StateSuggestions(customarray));
	var oTextbox3 = new AutoSuggestControl(document.getElementById("dstendport_cust"), new StateSuggestions(customarray));
}

//-->