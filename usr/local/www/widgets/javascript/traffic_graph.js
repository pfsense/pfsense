function trafficshowDiv(incDiv,swapButtons){
		//appear element
	selectedDiv = incDiv + "graphdiv";
	jQuery('#' + selectedDiv).effect('blind',{mode:'show'},1000);      
	showSave();    
	d = document;	
	if (swapButtons){
		selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";	    
		
		selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
	}
	selectIntLink = incDiv + "_graph-config";
	textlink = d.getElementById(selectIntLink);
	textlink.value = "show";	
	updateGraphDisplays(); 
}
	
function  trafficminimizeDiv(incDiv,swapButtons){
	//fade element
	selectedDiv = incDiv + "graphdiv";
	jQuery('#' + selectedDiv).effect('blind',{mode:'hide'},1000);
	showSave();
	d = document;	
	if (swapButtons){
		selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";	    
		
		selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
	} 
	selectIntLink = incDiv + "_graph-config";
	textlink = d.getElementById(selectIntLink);
	textlink.value = "hide";	
	updateGraphDisplays();    
}

function updateGraphDisplays(){
	var graphs = document.getElementsByClassName('graphsettings');
	var graphsdisplayed = "";
	var firstprint = false;	
	d = document;
	for (i=0; i<graphs.length; i++){
		if (firstprint)
			graphsdisplayed += ",";
		var graph = graphs[i].id;
		graphsdisplayed += graph + ":";
		textlink = d.getElementById(graph).value;
		graphsdisplayed += textlink;
		firstprint = true;
	}
	selectIntLink = "refreshInterval";
	graphsdisplayed += ",refreshInterval=";
	graphsdisplayed += d.getElementById(selectIntLink).value;
	
	selectIntLink = "traffic_graphs-config";
	textlink = d.getElementById(selectIntLink);
	textlink.value = graphsdisplayed;
}
