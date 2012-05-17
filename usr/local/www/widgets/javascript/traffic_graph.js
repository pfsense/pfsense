function trafficshowDiv(incDiv,swapButtons){
	//appear element
	selectedDiv = incDiv + "graphdiv";
	jQuery('#' + selectedDiv).effect('blind',{mode:'show'},1000);      
	d = document;	
	if (swapButtons){
		selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";	    
		
		selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
	}
	document.iform["shown[" + incDiv + "]"].value = "show";
}
	
function  trafficminimizeDiv(incDiv,swapButtons){
	//fade element
	selectedDiv = incDiv + "graphdiv";
	jQuery('#' + selectedDiv).effect('blind',{mode:'hide'},1000);
	d = document;	
	if (swapButtons){
		selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";	    
		
		selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
	} 
	document.iform["shown[" + incDiv + "]"].value = "hide";
}

