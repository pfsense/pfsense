/******************************************************************************
  $Id: graphlink.js,v 1.1 2006/12/21 17:10:25 dberlin Exp $

  This file is part of the GraphLink software.
  GraphLink is distributed under the MIT License.
  Copyright (c) 2005-2006 Max Khitrov <max@mxsoft.org>
 ******************************************************************************/

/***** Global data ************************************************************/

var gl_graphCount = 0;				// Number of graphs on the current page

/***** Constants **************************************************************/

var GL_START   = 0;
var GL_END     = 1;
var GL_STATIC  = 0;
var GL_DYNAMIC = 1;

/***** Public functions *******************************************************/

/**
 * Creates a graph and returns the graph data structure which can later be
 * manipulated using the other graph functions.
 *
 * element_id - DOM element id (should be a DIV) that will contain the graph.
 * width      - The width of the graph in pixels.
 * height     - Height of the graph in pixels.
 * bar_width  - Width of each bar on the graph. This number should divide width
 *              evenly, or else width will be adjusted to meet this requirement.
 *              General formula to keep in mind:
 *              Smaller bar width = more bars = higher CPU usage on client-side.
 *
 * Returns graph data structure on success, false on error.
 */
function GraphInitialize(element_id, width, height, bar_width) {
	// Find the page element which will contain the graph
	var owner;
	if((owner = $('#' + element_id)) == null) {
		alert("GraphLink Error: Element ID '" + element_id + "' not found.");
		return false;
	}

	// Make sure width is divisible by bar_width
	if(width / bar_width != Math.floor(width / bar_width))
		width = Math.floor(width / bar_width) * bar_width;

	var bar_count = width / bar_width;

	// Create the graph data structure
	var graph           = new Array();
	graph['id']         = gl_graphCount;        // ID used to separate elements of one graph from those of another
	graph['width']      = width;                // Graph width
	graph['height']     = height;               // Graph height
	graph['bar_count']  = bar_count;            // Number of bars on the graph
	graph['scale_type'] = GL_STATIC;            // How the graph is scaled
	graph['scale']      = 1;                    // Multiplier for the bar height
	graph['max']        = 0;                    // Largest value currently on the graph
	graph['vmax']       = height;               // Virtual graph maximum
	graph['spans']      = new Array(bar_count); // References to all the spans for each graph
	graph['vals']       = new Array(bar_count); // The height of each bar on the graph, actually it's (graph height - bar height)
	gl_graphCount++;

	// Build the graph (x)html
	var graph_html = '';
	graph_html += '<div id="GraphLinkData' + graph['id'] + '" class="GraphLinkData">';

	for(var i = 0; i < bar_count; i++) {
		graph['vals'][i] = height;
		graph_html += '<span id="GraphLinkBar' + graph['id'] + '_' + i + '" class="GraphLinkBar"></span>';
	}

	graph_html += '</div>';
	owner.html(graph_html);
	graph['element_id'] = $('#GraphLinkData' + graph['id']);

	for(i = 0; i < bar_count; i++) {
		graph['spans'][i] = $('#GraphLinkBar' + graph['id'] + '_' + i);
		graph['spans'][i].css('width',bar_width + 'px');
		graph['spans'][i].css('margin-top',height + 'px');
	}

	return graph;
}

/**
 * Adds a new value to a graph.
 *
 * graph - Graph object to which to add the new value.
 * value - Value to add.
 * where - (optional) GL_START (0) or GL_END (1), depending on where you want
 *         the new value to appear. GL_START will add the value on the left
 *         of the graph, GL_END will add it on the right (default).
 */
function GraphValue(graph, value, where) {
	if(typeof(where) == 'undefined')
		where = GL_END;

	var rescale = false;
	var lost    = 0;

	if(value < 0)
		value = 0;

	if(graph['scale_type'] == GL_DYNAMIC && value > graph['max'])
		rescale = true;

	if(graph['scale_type'] == GL_STATIC) {
		if(value > graph['vmax'])
			value = graph['vmax'];
		value = Math.round(value * graph['scale']);
	}

	if(where == GL_START) {
		graph['vals'].unshift(graph['height'] - value);
		lost = graph['vals'].pop();
	}
	else {
		graph['vals'].push(graph['height'] - value);
		lost = graph['vals'].shift();
	}

	if(graph['scale_type'] == GL_DYNAMIC && (graph['height'] - lost) == graph['max'])
		rescale = true;

	if(rescale)
		GraphAdjustScale(graph)

	GraphDraw(graph);
}

/**
 * Sets a virtual maximum for the graph allowing you to have non-scaled graphs
 * that can show a value greater then the graph height. This function will
 * automatically set the graph to a static scale mode, meaning that no values
 * above the maximum will be permitted. If you need to have a graph with no
 * pre-defined maximum, make it dynamic. Also note that if you set a vmax on a
 * graph that has data larger than vmax, that data will be reduced.
 *
 * graph - Graph object for which to set virtual max.
 * vmax  - The virtual maximum value for the graph.
 */
function GraphSetVMax(graph, vmax) {
	graph['scale_type'] = GL_STATIC;
	graph['vmax']       = vmax;

	GraphAdjustScale(graph);
	GraphDraw(graph);
}

/**
 * This function instructs the graph to be scaled according to what the maximum
 * value is. That value is used as the graph maximum and is reevaluated whenever
 * a new value is added, or the current maximum is removed. Dynamic scaling is a
 * good way of showing data for which you don't know what the maximum will be,
 * but it also is a bit more resource-intensive then statically scaled graphs.
 *
 * graph - Graph object for which to enable dynamic scaling.
 */
function GraphDynamicScale(graph) {
	graph['scale_type'] = GL_DYNAMIC;

	GraphAdjustScale(graph);
	GraphDraw(graph);
}

/***** Private functions ******************************************************/

/**
 * Checks if the current scale of the graph is still valid, or needs to be
 * adjusted.
 *
 * graph - Graph object for which to check the scale.
 */
function GraphAdjustScale(graph) {
	var limit = graph['bar_count'];
	var new_max = 0;
	var new_scale = 0;
	var val = 0;

	if(graph['scale_type'] == GL_STATIC) {
		new_max   = graph['vmax'];
		new_scale = graph['height'] / new_max;

		if(new_scale == graph['scale'])
			return;
	}

	for(var i = 0; i < limit; i++) {
		if(graph['scale_type'] == GL_STATIC) {
			val = (graph['height'] - graph['vals'][i]) * graph['scale'];
			val = val * new_scale;

			if(val > new_max)
				val = new_max;

			graph['vals'][i] = graph['height'] - Math.round(val * new_scale);

		}
		else if((graph['height'] - graph['vals'][i]) > new_max) {
			new_max = graph['height'] - graph['vals'][i];
		}
	}


	if(graph['scale_type'] == GL_STATIC) {
		graph['scale'] = new_scale;
	}
	else {
		if(new_max == 0)
			graph['scale'] = 1;
		else
			graph['scale'] = graph['height'] / new_max;

		graph['max'] = new_max;
	}
}

/**
 * Redraws the graph on the screen.
 *
 * graph - Graph object which needs to be re-drawn.
 */
function GraphDraw(graph) {
	var count = graph['bar_count'];

	if(graph['scale_type'] == GL_STATIC)
		var getMargin = function(i) {
			return graph['vals'][i] + 'px';
		};
	else
		var getMargin = function(i) {
			var h = graph['height'];
			var s = graph['scale'];
			var v = graph['vals'][i];
			return (h - Math.round((h - v) * s)) + 'px';
		};

	graph['spans'][count - 1].css("display", "none");

	for(var i = 0; i < count; i++)
		graph['spans'][i].css("marginTop", getMargin(i));

//	$('#' + graph['spans'][count - 1]).fadeIn(500);
}
