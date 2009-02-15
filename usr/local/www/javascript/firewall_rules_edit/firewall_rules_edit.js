<!--
var portsenabled = 1;

function ext_change() {
	if ((document.iform.srcbeginport.selectedIndex == 0) && portsenabled) {
		document.iform.srcbeginport_cust.disabled = 0;
	} else {
		document.iform.srcbeginport_cust.value = "";
		document.iform.srcbeginport_cust.disabled = 1;
	}
	if ((document.iform.srcendport.selectedIndex == 0) && portsenabled) {
		document.iform.srcendport_cust.disabled = 0;
	} else {
		document.iform.srcendport_cust.value = "";
		document.iform.srcendport_cust.disabled = 1;
	}
	if ((document.iform.dstbeginport.selectedIndex == 0) && portsenabled) {
		document.iform.dstbeginport_cust.disabled = 0;
	} else {
		document.iform.dstbeginport_cust.value = "";
		document.iform.dstbeginport_cust.disabled = 1;
	}
	if ((document.iform.dstendport.selectedIndex == 0) && portsenabled) {
		document.iform.dstendport_cust.disabled = 0;
	} else {
		document.iform.dstendport_cust.value = "";
		document.iform.dstendport_cust.disabled = 1;
	}

	if (!portsenabled) {
		document.iform.srcbeginport.disabled = 1;
		document.iform.srcendport.disabled = 1;
		document.iform.dstbeginport.disabled = 1;
		document.iform.dstendport.disabled = 1;
	} else {
		document.iform.srcbeginport.disabled = 0;
		document.iform.srcendport.disabled = 0;
		document.iform.dstbeginport.disabled = 0;
		document.iform.dstendport.disabled = 0;
	}
}

function show_source_port_range() {
	document.getElementById("sprtable").style.display = '';
	document.getElementById("showadvancedboxspr").innerHTML='';
}

function typesel_change() {
	switch (document.iform.srctype.selectedIndex) {
		case 1:	/* single */
			document.iform.src.disabled = 0;
			document.iform.srcmask.value = "";
			document.iform.srcmask.disabled = 1;
			break;
		case 2:	/* network */
			document.iform.src.disabled = 0;
			document.iform.srcmask.disabled = 0;
			break;
		default:
			document.iform.src.value = "";
			document.iform.src.disabled = 1;
			document.iform.srcmask.value = "";
			document.iform.srcmask.disabled = 1;
			break;
	}
	switch (document.iform.dsttype.selectedIndex) {
		case 1:	/* single */
			document.iform.dst.disabled = 0;
			document.iform.dstmask.value = "";
			document.iform.dstmask.disabled = 1;
			break;
		case 2:	/* network */
			document.iform.dst.disabled = 0;
			document.iform.dstmask.disabled = 0;
			break;
		default:
			document.iform.dst.value = "";
			document.iform.dst.disabled = 1;
			document.iform.dstmask.value = "";
			document.iform.dstmask.disabled = 1;
			break;
	}
}

function proto_change() {
	if (document.iform.proto.selectedIndex < 3) {
		portsenabled = 1;
	} else {
		portsenabled = 0;
	}

	/* Disable OS knob if the proto is not TCP. */
	if (document.iform.proto.selectedIndex < 1) {
		document.forms[0].os.disabled = 0;
	} else {
		document.forms[0].os.disabled = 1;
	}

	if (document.iform.proto.selectedIndex == 3) {
		document.iform.icmptype.disabled = 0;
	} else {
		document.iform.icmptype.disabled = 1;
	}

	ext_change();

	if(document.iform.proto.selectedIndex == 3 || document.iform.proto.selectedIndex == 4) {
		document.getElementById("icmpbox").style.display = '';
	} else {
		document.getElementById("icmpbox").style.display = 'none';
	}

	if(document.iform.proto.selectedIndex >= 0 && document.iform.proto.selectedIndex <= 2) {
		document.getElementById("dprtr").style.display = '';
		document.getElementById("showadvancedboxspr").innerHTML='<p><input type="button" onClick="show_source_port_range()" value="Advanced"></input> - Show source port range</a>';
	} else {
		document.getElementById("sprtable").style.display = 'none';
		document.getElementById("dprtr").style.display = 'none';
	}
}

function show_aodiv() {
	document.getElementById("aoadv").innerHTML='';
	aodiv = document.getElementById('aodivmain');
	aodiv.style.display = "block";
}

function show_advanced_state() {
	document.getElementById("showadvstatebox").innerHTML='';
	aodiv = document.getElementById('showstateadv');
	aodiv.style.display = "block";
}

function src_rep_change() {
	document.iform.srcendport.selectedIndex = document.iform.srcbeginport.selectedIndex;
}
function dst_rep_change() {
	document.iform.dstendport.selectedIndex = document.iform.dstbeginport.selectedIndex;
}

window.onload = function () {
	var oTextbox1 = new AutoSuggestControl(document.getElementById("src"), new StateSuggestions(addressarray));
	var oTextbox2 = new AutoSuggestControl(document.getElementById("srcbeginport_cust"), new StateSuggestions(customarray));
	var oTextbox3 = new AutoSuggestControl(document.getElementById("srcendport_cust"), new StateSuggestions(customarray));
	var oTextbox4 = new AutoSuggestControl(document.getElementById("dst"), new StateSuggestions(addressarray));
	var oTextbox5 = new AutoSuggestControl(document.getElementById("dstbeginport_cust"), new StateSuggestions(customarray));
	var oTextbox6 = new AutoSuggestControl(document.getElementById("dstendport_cust"), new StateSuggestions(customarray));
}
//-->