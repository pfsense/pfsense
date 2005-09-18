
// Seconds * 1000 = value
var update_interval = 4000;


function updateMeters()
{
	x_get_stats(stats);

	window.setTimeout('updateMeters()', update_interval);
}

function stats(x) {

	var values = x.split("|");

	updateCPU(values[0]);
	updateMemory(values[1]);
	updateUptime(values[2]);
	updateState(values[3]);
	updateTemp(values[4]);
}

function updateMemory(x)
{
	document.getElementById("memusagemeter").value = x + '%';

	document.getElementById("memwidtha").style.width = x + 'px';
	document.getElementById("memwidthb").style.width = (100 - x) + 'px';
}

function updateCPU(x)
{
	document.getElementById("cpumeter").value = x + '%';
	
	document.getElementById("cpuwidtha").style.width = x + 'px';
	document.getElementById("cpuwidthb").style.width = (100 - x) + 'px';
}

function updateTemp(x)
{
	if(document.getElementById("tempmeter") != null) {
		document.getElementById("tempmeter").value = x + '%';
		document.getElementById("tempwidtha").style.width = x + 'px';
		document.getElementById("tempwidthb").style.width = (100 - x) + 'px';
	}
}

function updateUptime(x)
{
	document.getElementById("uptime").value = x;
}

function updateState(x)
{
	document.getElementById("pfstate").value = x;
}

window.setTimeout('updateMeters()', update_interval);

