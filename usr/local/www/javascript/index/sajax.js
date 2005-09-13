function updateMeters()
{
	x_cpu_usage(updateCPU);
	x_mem_usage(updateMemory);
	x_get_uptime(updateUptime);
	x_get_pfstate(updateState);

	window.setTimeout('updateMeters()', 5000);
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

function updateUptime(x)
{
	document.getElementById("uptime").value = x;
}

function updateState(x)
{
	document.getElementById("pfstate").value = x;
}

window.setTimeout('updateMeters()', 5000);