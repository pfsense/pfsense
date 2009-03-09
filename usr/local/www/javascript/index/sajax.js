
// Seconds * 1000 = value
var update_interval = 10000;


function updateMeters()
{
	x_get_stats(stats);

	window.setTimeout('updateMeters()', update_interval);
}

function stats(x) {

	var values = x.split("|");

	for(var counter=0; counter<x.length; x++) {
		if(values[counter] == 'undefined' || values[counter] == null)
			return;
	}	

	updateCPU(values[0]);
	updateMemory(values[1]);
	updateUptime(values[2]);
	updateState(values[3]);
	updateTemp(values[4]);
	updateDateTime(values[5]);
	updateInterfaceStats(values[6]);
	updateInterfaces(values[7]);
	
}

function updateMemory(x)
{
	if($('memusagemeter'))
		document.getElementById("memusagemeter").value = x + '%';
	if($('memwidtha'))
		document.getElementById("memwidtha").style.width = x + 'px';
	if($('memwidthb'))
		document.getElementById("memwidthb").style.width = (100 - x) + 'px';
}

function updateCPU(x)
{
	if($('cpumeter'))
		document.getElementById("cpumeter").value = x + '%';
	if($('cpuwidtha'))
		document.getElementById("cpuwidtha").style.width = x + 'px';
	if($('cpuwidthb'))
		document.getElementById("cpuwidthb").style.width = (100 - x) + 'px';
        GraphValue(graph[0], x);

}

function updateTemp(x)
{
	if(document.getElementById("tempmeter") != null) {
		document.getElementById("tempmeter").value = x + 'C';
		document.getElementById("tempwidtha").style.width = x + 'px';
		document.getElementById("tempwidthb").style.width = (100 - x) + 'px';
	}
}

function updateDateTime(x) {
	if(!$('datetime')) 
		return;		
	if(document.getElementById("datetime") == null)
		return;		
	document.getElementById("datetime").firstChild.data = x;
}

function updateUptime(x)
{
	if($('uptime'))
		document.getElementById("uptime").value = x;
}

function updateState(x)
{
	if($('pfstate'))
		document.getElementById("pfstate").value = x;
}

function updateInterfaceStats(x){
	if (widgetActive("interface_statistics")){
		statistics_split = x.split(",");
		var counter = 1;
		for (var y=0; y<statistics_split.length-1; y++){
			document.getElementById('stat' + counter).innerHTML = statistics_split[y];
			counter++;	
		}
	}
}

function updateInterfaces(x){
	if (widgetActive("interfaces")){
		interfaces = x.split("~");
		for (var z=0; z<interfaces.length-1; z++){
			details = interfaces[z].split(",");	
			if (details[1] == "up"){
				document.getElementById(details[0] + '-up').style.display = "inline";	 
				document.getElementById(details[0] + '-down').style.display = "none";
				document.getElementById(details[0] + '-block').style.display = "none";
				document.getElementById(details[0] + '-ip').innerHTML = details[2]; 
				document.getElementById(details[0] + '-media').innerHTML = details[3];
			} else if (details[1] == "down"){
				document.getElementById(details[0] + '-down').style.display = "inline";	 
				document.getElementById(details[0] + '-up').style.display = "none";
				document.getElementById(details[0] + '-block').style.display = "none";
				document.getElementById(details[0] + '-ip').innerHTML = details[2]; 
				document.getElementById(details[0] + '-media').innerHTML = details[3];			
			} else if (details[1] == "block"){
				document.getElementById(details[0] + '-block').style.display = "inline";	 
				document.getElementById(details[0] + '-down').style.display = "none";
				document.getElementById(details[0] + '-up').style.display = "none";			
			}
			
		}
	}
}

function widgetActive(x){
	var widget = document.getElementById(x + '-container');
	if (widget.style.display != "none")
		return true;
	else
		return false;
}

/* start ajax helper "thread" if not started */
if(!ajaxStarted) {
	window.setTimeout('updateMeters()', update_interval);
	var ajaxStarted = true;
}

