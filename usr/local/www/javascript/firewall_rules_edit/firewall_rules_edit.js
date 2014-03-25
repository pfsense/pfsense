//<![CDATA[
var portsenabled = 1;
var editenabled = 1;

function ext_change() {
	if ((document.iform.srcbeginport.selectedIndex == 0) && portsenabled && editenabled) {
		document.iform.srcbeginport_cust.disabled = 0;
	} else {
		document.iform.srcbeginport_cust.value = "";
		document.iform.srcbeginport_cust.disabled = 1;
	}
	if ((document.iform.srcendport.selectedIndex == 0) && portsenabled && editenabled) {
		document.iform.srcendport_cust.disabled = 0;
	} else {
		document.iform.srcendport_cust.value = "";
		document.iform.srcendport_cust.disabled = 1;
	}
	if ((document.iform.dstbeginport.selectedIndex == 0) && portsenabled && editenabled) {
		document.iform.dstbeginport_cust.disabled = 0;
	} else {
		document.iform.dstbeginport_cust.value = "";
		document.iform.dstbeginport_cust.disabled = 1;
	}
	if ((document.iform.dstendport.selectedIndex == 0) && portsenabled && editenabled) {
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
		if( editenabled ) {
			document.iform.srcbeginport.disabled = 0;
			document.iform.srcendport.disabled = 0;
			document.iform.dstbeginport.disabled = 0;
			document.iform.dstendport.disabled = 0;
		}
	}
}

function show_source_port_range() {
	if (portsenabled) {
		document.getElementById("sprtable").style.display = '';
		document.getElementById("showadvancedboxspr").style.display = 'none';
	}
}

function typesel_change() {
	if( editenabled ) {
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
}

function proto_change() {
	if (document.iform.proto.selectedIndex < 3) {
		portsenabled = 1;
		document.getElementById("tcpflags").style.display = '';
	} else {
		portsenabled = 0;
		document.getElementById("tcpflags").style.display = 'none';
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
		if (editenabled) {
			document.getElementById("showadvancedboxspr").style.display = 'table-row';
		}
	} else {
		document.getElementById("sprtable").style.display = 'none';
		document.getElementById("dprtr").style.display = 'none';
		document.getElementById("showadvancedboxspr").style.display = 'none';
	}
}

function show_aodiv() {
	document.getElementById("aoadv").innerHTML='';
	aodiv = document.getElementById('aodivmain');
	aodiv.style.display = "block";
}

function show_dsdiv() {
	document.getElementById("dsadv").innerHTML='';
	dsdiv = document.getElementById('dsdivmain');
	dsdiv.style.display = "block";
}

function show_advanced_noxmlrpc() {
	document.getElementById("showadvnoxmlrpcsyncbox").innerHTML='';
	aodiv = document.getElementById('shownoxmlrpcadv');
	aodiv.style.display = "block";	
}

function show_advanced_vlanprio() {
	document.getElementById("showadvvlanpriobox").innerHTML='';
	aodiv = document.getElementById('showvlanprioadv');
	aodiv.style.display = "block";
}

function show_advanced_schedule() {
	document.getElementById("showadvschedulebox").innerHTML='';
	aodiv = document.getElementById('showscheduleadv');
	aodiv.style.display = "block";
}

function show_advanced_gateway() {
	document.getElementById("showadvgatewaybox").innerHTML='';
	aodiv = document.getElementById('showgatewayadv');
	aodiv.style.display = "block";
}

function show_advanced_sourceos() {
	document.getElementById("showadvsourceosbox").innerHTML='';
	aodiv = document.getElementById('showsourceosadv');
	aodiv.style.display = "block";
}

function show_advanced_ackqueue() {
	document.getElementById("showadvackqueuebox").innerHTML='';
	aodiv = document.getElementById('showackqueueadv');
	aodiv.style.display = "block";
}

function show_advanced_inout() {
	document.getElementById("showadvinoutbox").innerHTML='';
	aodiv = document.getElementById('showinoutadv');
	aodiv.style.display = "block";
}

function show_advanced_state() {
	document.getElementById("showadvstatebox").innerHTML='';
	aodiv = document.getElementById('showstateadv');
	aodiv.style.display = "block";
}

function show_advanced_tcpflags() {
        document.getElementById("showtcpflagsbox").innerHTML='';
        aodiv = document.getElementById('showtcpflagsadv');
        aodiv.style.display = "block";
}

function show_advanced_layer7() {
	document.getElementById("showadvlayer7box").innerHTML='';
	aodiv = document.getElementById('showlayer7adv');
	aodiv.style.display = "block";
}

function src_rep_change() {
	document.iform.srcendport.selectedIndex = document.iform.srcbeginport.selectedIndex;
}

function dst_rep_change() {
	document.iform.dstendport.selectedIndex = document.iform.dstbeginport.selectedIndex;
}

function tcpflags_anyclick(obj) {
	if (obj.checked) {
		document.getElementById('tcpheader').style.display= 'none';
	} else {
		document.getElementById('tcpheader').style.display= "";
	}
}
//]]>