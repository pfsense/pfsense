
// Seconds * 1000 = value
var update_interval = 7000;


function updateMeters()
{
    try {
    	x_get_stats(stats);
    	window.setTimeout('updateMeters()', update_interval);
    }catch(e){}
}

function stats(x) {
    try {
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
    }catch(e){}
}

function updateMemory(x)
{
    try {
    	document.getElementById("memusagemeter").value = x + '%';
	    document.getElementById("memwidtha").style.width = x + 'px';
    	document.getElementById("memwidthb").style.width = (100 - x) + 'px';
    }catch(e){}
}

function updateCPU(x)
{
    try {
	    document.getElementById("cpumeter").value = x + '%';
	    document.getElementById("cpuwidtha").style.width = x + 'px';
	    document.getElementById("cpuwidthb").style.width = (100 - x) + 'px';
    }catch(e){}
}

function updateTemp(x)
{
    try {
	    if(document.getElementById("tempmeter") != null) {
		    document.getElementById("tempmeter").value = x + 'C';
		    document.getElementById("tempwidtha").style.width = x + 'px';
		    document.getElementById("tempwidthb").style.width = (100 - x) + 'px';
	    }
    }catch(e){}
}

function updateUptime(x)
{
    try {
	    document.getElementById("uptime").value = x;
	}catch(e){}
}

function updateState(x)
{
    try {
    	document.getElementById("pfstate").value = x;
    }catch(e){}
}


/* start ajax helper "thread" if not started */
if(!ajaxStarted) {
    try {
	    window.setTimeout('updateMeters()', update_interval);
    	var ajaxStarted = true;
    }catch(e){}
}

