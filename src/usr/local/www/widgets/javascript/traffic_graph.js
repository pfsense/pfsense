/*
 * traffic_graph.js
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

function trafficshowDiv(incDiv,ifDescription,refreshIntervalSec,swapButtons) {
	// put the graph object HTML in the element and make it appear
	selectedDiv = incDiv + "graphdiv";
	$('#' + selectedDiv).html(
		'<object data="graph.php?ifnum=' + incDiv + '&amp;ifname=' + ifDescription + '&amp;timeint=' + refreshIntervalSec + '&amp;initdelay=0" height="100%" width="100%">' +
		'<param name="id" value="graph" />' +
		'<param name="type" value="image/svg+xml" />' +
		'<param name="pluginspage" value="http://www.adobe.com/svg/viewer/install/auto" />' +
		'</object>');
	$('#' + selectedDiv).effect('blind',{mode:'show'},1000);
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
	$('#' + selectedDiv).html('');
	$('#' + selectedDiv).effect('blind',{mode:'hide'},1000);
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
