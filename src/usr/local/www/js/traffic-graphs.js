/*
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
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

function draw_graph(refreshInterval, then, backgroundupdate) {

	d3.select("div[id^=nvtooltip-]").remove();
	d3.select(".interface-label").remove();

	var invert = localStorage.getItem('invert');
	var size = localStorage.getItem('size');
	var lasttime = 0;

	startTime = 120 * refreshInterval;
	then.setSeconds(then.getSeconds() - startTime);
	var thenTime = then.getTime();

	$.each( window.interfaces, function( key, value ) {
		myData[value]['interfacename'] = graph_interfacenames[value];
		latest[value + 'in'] = 0;
		latest[value + 'out'] = 0;

		var stepTime = thenTime;

		//initialize first 120 graph points to zero
		for (i = 1; i < 120; i++) {

			stepTime = stepTime + (1000 * refreshInterval);

			myData[value].forEach(function(entry) {
				entry.values.push({
					x: stepTime,
					y: 0
				});
			});

		}

		nv.addGraph(function() {

			charts[value] = nv.models.lineChart()
						.useInteractiveGuideline(true)
						.color(d3.scale.category20().range())
						.rightAlignYAxis(true)
						.margin({top: 0, left:25, bottom: 30, right: 45});

			charts[value]
				.x(function(d,i) { return d.x });

			charts[value].xAxis
				.tickFormat(function (d) {
					return d3.time.format('%M:%S')(new Date(d));
				});

			var sizeLabel = $( "#traffic-graph-size option:selected" ).text();

			d3.select('#traffic-chart-' + value + ' svg')
				.append("text")
				.attr('class', 'interface-label')
				.attr("x", 20)             
				.attr("y", 20)
				.attr("font-size", 18)
				.text(myData[value]['interfacename']);

			charts[value].yAxis
		    	.tickFormat(d3.format('.2s'))
		    	.showMaxMin(false);

			d3.select('#traffic-chart-' + value + ' svg')
				.datum(myData[value])
		    	.transition().duration(500)
		    	.call(charts[value]);

			nv.utils.windowResize(charts[value].update);

			//custom tooltip contents
			charts[value].interactiveLayer.tooltip.contentGenerator(function(data) {

				var units = 'b/s';
				if(localStorage.getItem('size') === "1") {
					units = 'B/s'
				}

				var content = '<h3>' + d3.time.format('%Y-%m-%d %H:%M:%S')(new Date(data.value)) + '</h3><table><tbody>';

				for ( var v = 0; v < data.series.length; v++ ){

					var rawValue = data.series[v].value;

					if((invert === "true") && data.series[v].key.includes("(out)")) {
						rawValue = 0 - rawValue;
					}

					var sValue = d3.formatPrefix(rawValue);

					//TODO change unit based on unit size
					var formattedVal = sValue.scale(rawValue).toFixed(2) + ' ' + sValue.symbol + units;

					content += '<tr><td class="legend-color-guide"><div style="background-color: ' + data.series[v].color + '"></div></td><td>' + data.series[v].key + '</td><td class="value"><strong>' + formattedVal + '</strong></td></tr>';

				}

				content += '</tbody></table>';

				return content;

			});

			return charts[value];
		});

	});

	var refreshGraphFunction = function(){
		d3.json("ifstats.php")
		.header("Content-Type", "application/x-www-form-urlencoded")
		.post('if='+window.interfaces.join('|'), function(error, json) { //TODO all ifs again

			if (error) {

				Visibility.stop(updateIds);
				clearInterval(updateTimerIds);
				$(".traffic-widget-chart").remove();
				$("#traffic-chart-error").show().html('<strong>Error</strong>: ' + error);
				return console.warn(error);

			}

			if (json.error) {

				Visibility.stop(updateIds);
				clearInterval(updateTimerIds);
				$(".traffic-widget-chart").remove();
				$("#traffic-chart-error").show().html('<strong>Error</strong>: ' + json.error);
				return console.warn(json.error);

			}

			var setTime = true;
			var xtime = 0;
			var timeDiff = 0;
			$.each(json, function( key, ifVals ) {
				if (setTime == true) {
					var valueTime = ifVals[0].values[0];
					timeDiff = valueTime - lasttime;
					lasttime = valueTime;
					xtime = valueTime * 1000;
					setTime = false;
				}
				label = $('#traffic-chart-' + key + ' svg > .interface-label');
				$(label).text(ifVals.name);
				if(!myData[key][0].first) {
					var trafficIn = ((ifVals[0].values[1] * size) - latest[ifVals[0].key]) / timeDiff;
					var trafficOut = ((ifVals[1].values[1] * size) - latest[ifVals[1].key]) / timeDiff;

					if((localStorage.getItem('invert') === "true")) {
						trafficOut = 0 - trafficOut;
					}

					myData[key][0].values.push({
						x: xtime,
						y: trafficIn
					});

					myData[key][1].values.push({
						x: xtime,
						y: trafficOut
					});

				} else {
					myData[key][0].values.push({
						x: xtime,
						y: 0
					});

					myData[key][1].values.push({
						x: xtime,
						y: 0
					});
				}

				latest[ifVals[0].key] = ifVals[0].values[1] * size;
				latest[ifVals[1].key] = ifVals[1].values[1] * size;

				myData[key][0].first = false;
				myData[key][1].first = false;


				myData[key][0].values.shift();
				myData[key][1].values.shift();

				if (!Visibility.hidden()) {
					/*
					 * don't draw graph when tab is not
					 * visible. This also prevents lots of
					 * timers stacking up waiting for a
					 * frame update.
					 */
					charts[key].update();
				}

			});

		});

	}
	
	if(backgroundupdate) {
		updateTimerIds = setInterval(refreshGraphFunction, refreshInterval * 1000);
	} else {
		//only update the graphs when tab is active in window to save resources and prevent build up
		updateIds = Visibility.every(refreshInterval * 1000, refreshGraphFunction);
	}
}
