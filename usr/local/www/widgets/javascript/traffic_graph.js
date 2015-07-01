function trafficshowDiv(incDiv,ifDescription,refreshIntervalSec,swapButtons) {
	// put the graph object HTML in the element and make it appear
	selectedDiv = incDiv + "graphdiv";
	jQuery('#' + selectedDiv).html(
		'<object data="graph.php?ifnum=' + incDiv + '&amp;ifname=' + ifDescription + '&amp;timeint=' + refreshIntervalSec + '&amp;initdelay=0" height="100%" width="100%">' +
		'<param name="id" value="graph" />' +
		'<param name="type" value="image/svg+xml" />' +
		'<param name="pluginspage" value="http://www.adobe.com/svg/viewer/install/auto" />' +
		'</object>');
	jQuery('#' + selectedDiv).effect('blind',{mode:'show'},1000);
	d = document;
	if (swapButtons) {
		selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";

		selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
	}
	document.traffic_graphs_widget_iform["shown[" + incDiv + "]"].value = "show";
}

function  trafficminimizeDiv(incDiv,swapButtons) {
	// remove the graph object HTML from the element (so it does not keep using CPU) and fade
	selectedDiv = incDiv + "graphdiv";
	jQuery('#' + selectedDiv).html('');
	jQuery('#' + selectedDiv).effect('blind',{mode:'hide'},1000);
	d = document;
	if (swapButtons) {
		selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";

		selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
	}
	document.traffic_graphs_widget_iform["shown[" + incDiv + "]"].value = "hide";
}

