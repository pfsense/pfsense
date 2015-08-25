//<![CDATA[
var portsenabled = 1;
var dstenabled = 1;
var showsource = 0;

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
	if ((document.iform.dstbeginport.selectedIndex == 0) && portsenabled && dstenabled) {
		document.iform.dstbeginport_cust.disabled = 0;
	} else {
		document.iform.dstbeginport_cust.value = "";
		document.iform.dstbeginport_cust.disabled = 1;
	}
	if ((document.iform.dstendport.selectedIndex == 0) && portsenabled && dstenabled) {
		document.iform.dstendport_cust.disabled = 0;
	} else {
		document.iform.dstendport_cust.value = "";
		document.iform.dstendport_cust.disabled = 1;
	}

	if ((document.iform.localbeginport.selectedIndex == 0) && portsenabled) {
		document.iform.localbeginport_cust.disabled = 0;
	} else {
		document.iform.localbeginport_cust.value = "";
		document.iform.localbeginport_cust.disabled = 1;
	}

	if (!portsenabled) {
		document.iform.srcbeginport.disabled = 1;
		document.iform.srcendport.disabled = 1;
		document.iform.dstbeginport.disabled = 1;
		document.iform.dstendport.disabled = 1;
		document.iform.localbeginport_cust.disabled = 1;
	} else {
		document.iform.srcbeginport.disabled = 0;
		document.iform.srcendport.disabled = 0;
		document.iform.localbeginport_cust.disabled = 0;
		if( dstenabled ) {
			document.iform.dstbeginport.disabled = 0;
			document.iform.dstendport.disabled = 0;
		}
	}
}

function nordr_change() {
	if (document.iform.nordr.checked) {
		document.getElementById("localiptable").style.display = 'none';
		document.getElementById("lprtr").style.display = 'none';
		document.getElementById("assoctable").style.display = 'none';
	} else {
		document.getElementById("localiptable").style.display = '';
		document.getElementById("lprtr").style.display = portsenabled ? '' : 'none';
		document.getElementById("assoctable").style.display = '';
	}
}

function show_source() {
	if(portsenabled)
		document.getElementById("sprtable").style.display = '';

	document.getElementById("srctable").style.display = '';
	document.getElementById("showadvancedboxsrc").style.display = 'none';
	showsource = 1;
}

function check_for_aliases() {
	/*  if External port range is an alias, then disallow
	 *  entry of Local port
	 */
	for(i=0; i<customarray.length; i++) {
		if(document.iform.dstbeginport_cust.value == customarray[i]) {
			document.iform.dstendport_cust.value = customarray[i];
			document.iform.localbeginport_cust.value = customarray[i];
			document.iform.dstendport_cust.disabled = 1;
			document.iform.localbeginport.disabled = 1;
			document.iform.localbeginport_cust.disabled = 1;
			document.iform.dstendport_cust.disabled = 0;
			document.iform.localbeginport.disabled = 0;
			document.iform.localbeginport_cust.disabled = 0;
		}
		if(document.iform.dstbeginport.value == customarray[i]) {
			document.iform.dstendport_cust.value = customarray[i];
			document.iform.localbeginport_cust.value = customarray[i];
			document.iform.dstendport_cust.disabled = 1;
			document.iform.localbeginport.disabled = 1;
			document.iform.localbeginport_cust.disabled = 1;
			document.iform.dstendport_cust.disabled = 0;
			document.iform.localbeginport.disabled = 0;
			document.iform.localbeginport_cust.disabled = 0;
		}
		if(document.iform.dstendport_cust.value == customarray[i]) {
			document.iform.dstendport_cust.value = customarray[i];
			document.iform.localbeginport_cust.value = customarray[i];
			document.iform.dstendport_cust.disabled = 1;
			document.iform.localbeginport.disabled = 1;
			document.iform.localbeginport_cust.disabled = 1;
			document.iform.dstendport_cust.disabled = 0;
			document.iform.localbeginport.disabled = 0;
			document.iform.localbeginport_cust.disabled = 0;
		}
		if(document.iform.dstendport.value == customarray[i]) {
			document.iform.dstendport_cust.value = customarray[i];
			document.iform.localbeginport_cust.value = customarray[i];
			document.iform.dstendport_cust.disabled = 1;
			document.iform.localbeginport.disabled = 1;
			document.iform.localbeginport_cust.disabled = 1;
			document.iform.dstendport_cust.disabled = 0;
			document.iform.localbeginport.disabled = 0;
			document.iform.localbeginport_cust.disabled = 0;
		}

	}
}

function proto_change() {
	if (document.iform.proto.selectedIndex >= 0 && document.iform.proto.selectedIndex <= 2) {
		portsenabled = 1;
	} else {
		portsenabled = 0;
	}

	if (portsenabled) {
		document.getElementById("sprtable").style.display = showsource == 1 ? '':'none';
		document.getElementById("dprtr").style.display = '';
		document.getElementById("lprtr").style.display = document.iform.nordr.checked ? 'none' : '';
	} else {
		document.getElementById("sprtable").style.display = 'none';
		document.getElementById("dprtr").style.display = 'none';
		document.getElementById("lprtr").style.display = 'none';
		document.getElementById("dstbeginport").selectedIndex = 0;
		document.getElementById("dstbeginport_cust").value = "";
		document.getElementById("dstendport").selectedIndex = 0;
		document.getElementById("dstendport_cust").value = "";
		document.getElementById("localbeginport").selectedIndex = 0;
		document.getElementById("localbeginport_cust").value = "";
	}
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
	if( dstenabled )
	{
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

function src_rep_change() {
	document.iform.srcendport.selectedIndex = document.iform.srcbeginport.selectedIndex;
}

function dst_rep_change() {
	document.iform.dstendport.selectedIndex = document.iform.dstbeginport.selectedIndex;
}

function dst_change( iface, old_iface, old_dst ) {
	if ( ( old_dst == "" ) || ( old_iface.concat("ip") == old_dst ) ) {
		document.iform.dsttype.value = iface.concat("ip");
	}
}
//]]>
