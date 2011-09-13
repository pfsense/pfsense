
/*   Most widgets update their backend data every 10 seconds.  11 seconds
 *   will ensure that we update the GUI right after the stats are updated.
 *   Seconds * 1000 = value
 */
var update_interval = 11000;

function updateMeters() {
	url = '/getstats.php'

	new Ajax.Request(url, {
		method: 'get',
		onSuccess: function(transport) {
			response = transport.responseText || "";
			if (response != "")
				stats(transport.responseText);
		}
	});
	setTimeout('updateMeters()', update_interval);
}

function stats(x) {
	var values = x.split("|");
	if (values.find(function(value){
		if (value == 'undefined' || value == null)
			return true;
		else
			return false;
	}))
		return;

	updateCPU(values[0]);
	updateMemory(values[1]);
	updateUptime(values[2]);
	updateState(values[3]);
	updateTemp(values[4]);
	updateDateTime(values[5]);
	updateInterfaceStats(values[6]);
	updateInterfaces(values[7]);
	updateGatewayStats(values[8]);
}

function updateMemory(x) {
	if($('memusagemeter'))
		$("memusagemeter").value = x + '%';
	if($('memwidtha'))
		$("memwidtha").style.width = x + 'px';
	if($('memwidthb'))
		$("memwidthb").style.width = (100 - x) + 'px';
}

function updateCPU(x) {
	if($('cpumeter'))
		$("cpumeter").value = x + '%';
	if($('cpuwidtha'))
		$("cpuwidtha").style.width = x + 'px';
	if($('cpuwidthb'))
		$("cpuwidthb").style.width = (100 - x) + 'px';
	/* Load CPU Graph widget if enabled */
	if(widgetActive('cpu_graphs')) {
		GraphValue(graph[0], x);
	}
}

function updateTemp(x) {
	if($("tempmeter")) {
		$("tempmeter").value = x + 'C';
		$("tempwidtha").style.width = x + 'px';
		$("tempwidthb").style.width = (100 - x) + 'px';
	}
}

function updateDateTime(x) {
	if($('datetime')) 
		$("datetime").firstChild.data = x;
}

function updateUptime(x) {
	if($('uptime'))
		$("uptime").value = x;
}

function updateState(x) {
	if($('pfstate'))
		$("pfstate").value = x;
}

function updateGatewayStats(x){
	if (widgetActive("gateways")){
		gateways_split = x.split(",");
		for (var y=0; y<gateways_split.length; y++){
			if($('gateway' + (y + 1))) {
				$('gateway' + (y + 1)).update(gateways_split[y]);
			}
		}
	}
}

function updateInterfaceStats(x){
	if (widgetActive("interface_statistics")){
		statistics_split = x.split(",");
		var counter = 1;
		for (var y=0; y<statistics_split.length-1; y++){
			if($('stat' + counter)) {
				$('stat' + counter).update(statistics_split[y]);
				counter++;	
			}
		}
	}
}

function updateInterfaces(x){
	if (widgetActive("interfaces")){
		interfaces = x.split("~");
		interfaces.each(function(iface){
			details = iface.split(",");
			switch(details[1]) {
				case "up":
					$(details[0] + '-up').style.display = "inline";
					$(details[0] + '-down').style.display = "none";
					$(details[0] + '-block').style.display = "none";
					$(details[0] + '-ip').update(details[2]);
					$(details[0] + '-media').update(details[3]);
					break;
				case "down":
					$(details[0] + '-down').style.display = "inline";
					$(details[0] + '-up').style.display = "none";
					$(details[0] + '-block').style.display = "none";
					$(details[0] + '-ip').update(details[2]);
					$(details[0] + '-media').update(details[3]);
					break;
				case "block":
						$(details[0] + '-block').style.display = "inline";
						$(details[0] + '-down').style.display = "none";
						$(details[0] + '-up').style.display = "none";
					break;
			}
		});
	}
}

function widgetActive(x) {
	var widget = $(x + '-container');
	if ((widget != null) && (widget.style.display != "none"))
		return true;
	else
		return false;
}

/* start updater */
document.observe('dom:loaded', function(){
	setTimeout('updateMeters()', update_interval);
});

