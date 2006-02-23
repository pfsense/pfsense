function enable_change(enable_over) {
	if (document.iform.ipv6nat_enable.checked || enable_over) {
		document.iform.ipv6nat_ipaddr.disabled = 0;
	} else {
		document.iform.ipv6nat_ipaddr.disabled = 1;
	}
}

var descs=new Array(5);
descs[0]="as the name says, it's the normal optimization algorithm";
descs[1]="used for high latency links, such as satellite links.  Expires idle connections later than default";
descs[2]="expires idle connections quicker. More efficient use of CPU and memory but can drop legitimate connections";
descs[3]="tries to avoid dropping any legitimate connections at the expense of increased memory usage and CPU utilization.";

function update_description(itemnum) {
        document.forms[0].info.value=descs[itemnum];

}

function openwindow(url) {
        var oWin = window.open(url,"pfSensePop","width=620,height=400,top=150,left=150");
        if (oWin==null || typeof(oWin)=="undefined") {
                return false;
        } else {
                return true;
        }
}
