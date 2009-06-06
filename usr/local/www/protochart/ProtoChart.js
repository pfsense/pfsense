/**
 * Class: ProtoChart 
 * Version: v0.5 beta
 * 
 * ProtoChart is a charting lib on top of Prototype.
 * This library is heavily motivated by excellent work done by:
 * * Flot <http://code.google.com/p/flot/>
 * * Flotr <http://solutoire.com/flotr/>
 * 
 * Complete examples can be found at: <http://www.deensoft.com/lab/protochart>
 */

/**
 * Events:
 * ProtoChart:mousemove - Fired when mouse is moved over the chart
 * ProtoChart:plotclick - Fired when graph is clicked
 * ProtoChart:dataclick - Fired when graph is clicked AND the click is on a data point
 * ProtoChart:selected	- Fired when certain region on the graph is selected
 * ProtoChart:hit		- Fired when mouse is moved near or over certain data point on the graph
 */


if(!Proto) var Proto = {};

Proto.Chart = Class.create({
	/**
	 * Function: 
	 * {Object} elem
	 * {Object} data
	 * {Object} options
	 */
	initialize: function(elem, data, options)
	{
		options = options || {};
		this.graphData = [];
		/**
		 * Property: options
		 * 
		 * Description: Various options can be set. More details in description.
		 * 
		 * colors:
		 * {Array}		- pass in a array which contains strings of colors you want to use. Default has 6 color set.
		 * 
		 * legend:
		 * {BOOL} 		- show				- if you want to show the legend. Default is false
		 * {integer} 	- noColumns			- Number of columns for the legend. Default is 1
		 * {function} 	- labelFormatter	- A function that returns a string. The function is called with a string and is expected to return a string. Default = null
		 * {string}		- labelBoxBorderColor - border color for the little label boxes. Default #CCC
		 * {HTMLElem}	- container			- an HTML id or HTML element where the legend should be rendered. If left null means to put the legend on top of the Chart
		 * {string}		- position			- position for the legend on the Chart. Default value 'ne'
		 * {integer}	- margin			- default valud of 5
		 * {string} 	- backgroundColor	- default to null (which means auto-detect)
		 * {float} 		- backgroundOpacity - leave it 0 to avoid background
		 * 
		 * xaxis (yaxis) options:
		 * {string} 	- mode 		- default is null but you can pass a string "time" to indicate time series
		 * {integer}	- min
		 * {integer}	- max
		 * {float}		- autoscaleMargin - in % to add if auto-setting min/max
		 * {mixed}		- ticks - either [1, 3] or [[1, "a"], 3] or a function which gets axis info and returns ticks
		 * {function} 	- tickFormatter - A function that returns a string as a tick label. Default is null
		 * {float}		- tickDecimals
		 * {integer}	- tickSize
		 * {integer} 	- minTickSize
		 * {array}		- monthNames
		 * {string}		- timeformat
		 * 
		 * Points / Lines / Bars options:
		 * {bool}		- show, default is false
		 * {integer}	- radius: default is 3
		 * {integer}	- lineWidth : default is 2
		 * {bool}		- fill : default is true
		 * {string}		- fillColor: default is #ffffff
		 * 
		 * Grid options:
		 * {string}		- color
		 * {string}		- backgroundColor 	- defualt is *null*
		 * {string}		- tickColor			- default is *#dddddd*
		 * {integer}	- labelMargin		- should be in pixels default is 3
		 * {integer}	- borderWidth		- default *1*
		 * {bool}		- clickable 		- default *null* - pass in TRUE if you wish to monitor click events
		 * {mixed}		- coloredAreas 		- default *null* - pass in mixed object eg. {x1, x2}
		 * {string}		- coloredAreasColor	- default *#f4f4f4*
		 * {bool}		- drawXAxis			- default *true*
		 * {bool}		- drawYAxis			- default *true*
		 * 
		 * selection options:
		 * {string}		- mode : either "x", "y" or "xy"
		 * {string}		- color : string
		 */
		this.options = this.merge(options,{
			colors: ["#edc240", "#00A8F0", "#C0D800", "#cb4b4b", "#4da74d", "#9440ed"],
            legend: {
                show: false,
                noColumns: 1,
                labelFormatter: null,
                labelBoxBorderColor: "#ccc",
                container: null, 
                position: "ne",
                margin: 5,
                backgroundColor: null,
                backgroundOpacity: 0.85
            },
            xaxis: {
				mode: null, 
                min: null,
                max: null,
                autoscaleMargin: null,
                ticks: null,
                tickFormatter: null,
                tickDecimals: null,
                tickSize: null,
                minTickSize: null,
                monthNames: null,
                timeformat: null
            },
            yaxis: {
				mode: null,
				min: null,
				max: null,
				ticks: null,
				tickFormatter: null,
				tickDecimals: null,
				tickSize: null,
				minTickSize: null,
				monthNames: null,
				timeformat: null,				
                autoscaleMargin: 0.02
            },

            points: {
                show: false,
                radius: 3,
                lineWidth: 2,
                fill: true,
                fillColor: "#ffffff"
            },
            lines: {
                show: false,
                lineWidth: 2,
                fill: false,
                fillColor: null
            },
            bars: {
                show: false,
                lineWidth: 2,
                barWidth: 1,
                fill: true,
                fillColor: null,
				showShadow: false,
				fillOpacity: 0.4,
				autoScale: true
            },
			pies: {
				show: false,
				radius: 50,
				borderWidth: 1,
				fill: true,
				fillColor: null,
				fillOpacity: 0.90,
				labelWidth: 30,
				fontSize: 11,
				autoScale: true
			},
            grid: {
                color: "#545454",
                backgroundColor: null,
                tickColor: "#dddddd",
                labelMargin: 3,
                borderWidth: 1,
                clickable: null,
                coloredAreas: null,
                coloredAreasColor: "#f4f4f4",
				drawXAxis: true,
				drawYAxis: true
            },
			mouse: {
				track: false,
				position: 'se',
				fixedPosition: true,
				clsName: 'mouseValHolder',
				trackFormatter: this.defaultTrackFormatter,
				margin: 3,
				color: '#ff3f19',
				trackDecimals: 1,
				sensibility: 2,
				radius: 5,
				lineColor: '#cb4b4b'
			},
            selection: {
                mode: null,
                color: "#97CBFF"
            },
			allowDataClick: true,
			makeRandomColor: false,
            shadowSize: 4			
		});
		
		/*
		 * Local variables.
		 */
		this.canvas = null; 
		this.overlay = null;
		this.eventHolder = null;
		this.context = null;
		this.overlayContext = null;
		
		this.domObj = $(elem);

		this.xaxis = {};
		this.yaxis = {};
		this.chartOffset = {left: 0, right: 0, top: 0, bottom: 0};
		this.yLabelMaxWidth = 0;
		this.yLabelMaxHeight = 0;
		this.xLabelBoxWidth = 0;
		this.canvasWidth = 0;
		this.canvasHeight = 0;
		this.chartWidth = 0;
		this.chartHeight = 0;
		this.hozScale = 0;
		this.vertScale = 0;
		this.workarounds = {};
		
		this.domObj = $(elem);
		
		this.barDataRange = [];
		
        this.lastMousePos = { pageX: null, pageY: null };
        this.selection = { first: { x: -1, y: -1}, second: { x: -1, y: -1} };
        this.prevSelection = null;
        this.selectionInterval = null;
        this.ignoreClick = false;	
		this.prevHit = null;
			
		if(this.options.makeRandomColor)
			this.options.color = this.makeRandomColor(this.options.colors);
		
		this.setData(data);
		this.constructCanvas();
		this.setupGrid();
		this.draw();
	},
	/**
	 * Private function internally used.
	 */
	merge: function(src, dest)
	{
		var result = dest || {};
		for(var i in src){		  
			result[i] = (typeof(src[i]) == 'object' && !(src[i].constructor == Array || src[i].constructor == RegExp)) ? this.merge(src[i], dest[i]) : result[i] = src[i];		
		}
		return result;	
	},
	/**
	 * Function: setData
	 * {Object} data
	 * 
	 * Description:
	 * Sets datasoruces properly then sets the Bar Width accordingly, then copies the default data options and then processes the graph data
	 * 
	 * Returns: none
	 * 
	 */	
	setData: function(data) 
	{
        this.graphData = this.parseData(data);		
		this.setBarWidth();
        this.copyGraphDataOptions();
        this.processGraphData();
    },
	/**
	 * Function: parseData
	 * {Object} data
	 * 
	 * Return: 
	 * {Object} result
	 * 
	 * Description:
	 * Takes the provided data object and converts it into generic data that we can understand. User can pass in data in 3 different ways:
	 * - [d1, d2]
	 * - [{data: d1, label: "data1"}, {data: d2, label: "data2"}]
	 * - [d1, {data: d1, label: "data1"}]
	 * 
	 * This function parses these senarios and makes it readable
	 */
	parseData: function(data)
	{
		var res = [];
		data.each(function(d){
			var s;
			if(d.data) {
				s = {};
				for(var v in d) {
					s[v] = d[v];
				}
			}
			else {
				s = {data: d};
			}
			res.push(s);
		}.bind(this));
		return res;
	},
	/**
	 * function: makeRandomColor
	 * {Object} colorSet
	 * 
	 * Return: 
	 * {Array} result - array containing random colors
	 */
	makeRandomColor: function(colorSet)
	{
		var randNum = Math.floor(Math.random() * colorSet.length);
		var randArr = [];
		var newArr = [];
		randArr.push(randNum);
		
		while(randArr.length < colorSet.length)
		{
			var tempNum = Math.floor(Math.random() * colorSet.length);
	
			while(checkExisted(tempNum, randArr))
				tempNum = Math.floor(Math.random() * colorSet.length);
				
			randArr.push(tempNum);
		}
		
		randArr.each(function(ra){
			newArr.push(colorSet[ra]);
			
		}.bind(this));
		return newArr;		
	},
	/**
	 * function: checkExisted
	 * {Object} needle
	 * {Object} haystack
	 * 
	 * return: 
	 * {bool} existed - true if it finds needle in the haystack
	 */
	checkExisted: function(needle, haystack)
	{
		var existed = false;
		haystack.each(function(aNeedle){
			if(aNeedle == needle) {
				existed = true;
				throw $break;
			}
		}.bind(this));
		return existed;
	},
	/**
	 * function: setBarWidth
	 * 
	 * Description: sets the bar width for Bar Graph, you should enable *autoScale* property for bar graph
	 */
	setBarWidth: function()
	{
		if(this.options.bars.show && this.options.bars.autoScale)
		{
			this.options.bars.barWidth = 1 / this.graphData.length / 1.2;	
		}
	},
	/**
	 * Function: copyGraphDataOptions
	 * 
	 * Description: Private function that goes through each graph data (series) and assigned the graph
	 * properties to it.
	 */
	copyGraphDataOptions: function()
	{
		var i, neededColors = this.graphData.length, usedColors = [], assignedColors = [];
		
		this.graphData.each(function(gd){
			var sc = gd.color;
			if(sc) {
				--neededColors;
				if(Object.isNumber(sc)) {
					assignedColors.push(sc);
				}
				else {
					usedColors.push(this.parseColor(sc));
				}
			}
		}.bind(this));
		
		
		assignedColors.each(function(ac){
			neededColors = Math.max(neededColors, ac + 1);
		});

        var colors = [];
        var variation = 0;
        i = 0;
        while (colors.length < neededColors) {
            var c;
            if (this.options.colors.length == i) {
				c = new Proto.Color(100, 100, 100);
			}
			else {
				c = this.parseColor(this.options.colors[i]);
			}

            var sign = variation % 2 == 1 ? -1 : 1;
            var factor = 1 + sign * Math.ceil(variation / 2) * 0.2;
            c.scale(factor, factor, factor);

            colors.push(c);
            
            ++i;
            if (i >= this.options.colors.length) {
                i = 0;
                ++variation;
            }
        }

        var colorIndex = 0, s;
		
		this.graphData.each(function(gd){
			if(gd.color == null)
			{
				gd.color = colors[colorIndex].toString();
				++colorIndex;
			}
			else if(Object.isNumber(gd.color)) {
				gd.color = colors[gd.color].toString();
			}
			
            gd.lines = Object.extend(Object.clone(this.options.lines), gd.lines); 
            gd.points = Object.extend(Object.clone(this.options.points), gd.points); 
            gd.bars = Object.extend(Object.clone(this.options.bars), gd.bars); 
            gd.mouse = Object.extend(Object.clone(this.options.mouse), gd.mouse);
            if (gd.shadowSize == null) {
                gd.shadowSize = this.options.shadowSize;
			}
		}.bind(this));
			
	},
	/**
	 * Function: processGraphData
	 * 
	 * Description: processes graph data, setup xaxis and yaxis min and max points. 
	 */
	processGraphData: function() {
		
		this.xaxis.datamin = this.yaxis.datamin = Number.MAX_VALUE;
		this.xaxis.datamax = this.yaxis.datamax = Number.MIN_VALUE;
			
		this.graphData.each(function(gd) {
			var data = gd.data;
			data.each(function(d){
				if(d == null) {
					return;
				}
				
				var x = d[0], y = d[1];
				if(!x || !y || isNaN(x = +x) || isNaN(y = +y)) {
					d = null;
					return;
				}
				
				if (x < this.xaxis.datamin)
					this.xaxis.datamin = x;
				if (x > this.xaxis.datamax)
					this.xaxis.datamax = x;
				if (y < this.yaxis.datamin)
					this.yaxis.datamin = y;
				if (y > this.yaxis.datamax)
					this.yaxis.datamax = y;
			}.bind(this));
		}.bind(this));

		
		if (this.xaxis.datamin == Number.MAX_VALUE)
			this.xaxis.datamin = 0;
		if (this.yaxis.datamin == Number.MAX_VALUE)
			this.yaxis.datamin = 0;
		if (this.xaxis.datamax == Number.MIN_VALUE)
			this.xaxis.datamax = 1;
		if (this.yaxis.datamax == Number.MIN_VALUE)
			this.yaxis.datamax = 1;
	},
	/**
	 * Function: constructCanvas
	 * 
	 * Description: constructs the main canvas for drawing. It replicates the HTML elem (usually DIV) passed
	 * in via constructor. If there is no height/width assigned to the HTML elem then we take a default size
	 * of 400px (width) and 300px (height)
	 */
	constructCanvas: function() {

		this.canvasWidth = this.domObj.getWidth();
		this.canvasHeight = this.domObj.getHeight();
		this.domObj.update(""); // clear target
		this.domObj.setStyle({
			"position": "relative"
		}); 

		if (this.canvasWidth <= 0) {
			this.canvasWdith = 400;
		}
		if(this.canvasHeight <= 0) {
			this.canvasHeight = 300;
		}
		
		this.canvas = (Prototype.Browser.IE) ? document.createElement("canvas") : new Element("CANVAS", {'width': this.canvasWidth, 'height': this.canvasHeight});
		Element.extend(this.canvas);
		this.canvas.style.width = this.canvasWidth + "px";
		this.canvas.style.height = this.canvasHeight + "px";
		
		this.domObj.appendChild(this.canvas);
		
		if (Prototype.Browser.IE) // excanvas hack
		{
			this.canvas = $(window.G_vmlCanvasManager.initElement(this.canvas));
		}
		this.canvas = $(this.canvas);
		
		this.context = this.canvas.getContext("2d");

		this.overlay = (Prototype.Browser.IE) ? document.createElement("canvas") :  new Element("CANVAS", {'width': this.canvasWidth, 'height': this.canvasHeight});
		Element.extend(this.overlay);
		this.overlay.style.width = this.canvasWidth + "px";
		this.overlay.style.height = this.canvasHeight + "px";
		this.overlay.style.position = "absolute";
		this.overlay.style.left = "0px";
		this.overlay.style.right = "0px";
		
		this.overlay.setStyle({
			'position': 'absolute',
			'left': '0px',
			'right': '0px'
		});
		this.domObj.appendChild(this.overlay);
		
		if (Prototype.Browser.IE) {
			this.overlay = $(window.G_vmlCanvasManager.initElement(this.overlay));
		}
		
		this.overlay = $(this.overlay);
		this.overlayContext = this.overlay.getContext("2d");

		if(this.options.selection.mode)
		{
			this.overlay.observe('mousedown', this.onMouseDown.bind(this));
			this.overlay.observe('mousemove', this.onMouseMove.bind(this));
		}
		if(this.options.grid.clickable) {
			this.overlay.observe('click', this.onClick.bind(this));
		}
		if(this.options.mouse.track)
		{
			this.overlay.observe('mousemove', this.onMouseMove.bind(this));
		}
	},
	/**
	 * function: setupGrid
	 * 
	 * Description: a container function that does a few interesting things.
	 * 
	 * 1. calls <extendXRangeIfNeededByBar> function which makes sure that our axis are expanded if needed
	 * 
	 * 2. calls <setRange> function providing xaxis options which fixes the ranges according to data points
	 * 
	 * 3. calls <prepareTickGeneration> function for xaxis which generates ticks according to options provided by user
	 * 
	 * 4. calls <setTicks> function for xaxis that sets the ticks
	 * 
	 * similar sequence is called for y-axis. 
	 * 
	 * At the end if this is a pie chart than we insert Labels (around the pie chart) via <insertLabels> and we also call <insertLegend>
	 */
	setupGrid: function()
	{
		if(this.options.bars.show)
		{
			this.xaxis.max += 0.5;
			this.xaxis.min -= 0.5;
		}
		//x-axis
		this.extendXRangeIfNeededByBar();
		this.setRange(this.xaxis, this.options.xaxis);
		this.prepareTickGeneration(this.xaxis, this.options.xaxis);
		this.setTicks(this.xaxis, this.options.xaxis);
		
		
		//y-axis
		this.setRange(this.yaxis, this.options.yaxis);
		this.prepareTickGeneration(this.yaxis, this.options.yaxis);
		this.setTicks(this.yaxis, this.options.yaxis);
		this.setSpacing();
		
		if(!this.options.pies.show)
		{
			this.insertLabels();
		}
		this.insertLegend();
	},
	/**
	 * function: setRange
	 * 
	 * parameters:
	 * {Object} axis
	 * {Object} axisOptions
	 */
	setRange: function(axis, axisOptions) {
		var min = axisOptions.min != null ? axisOptions.min : axis.datamin;
		var max = axisOptions.max != null ? axisOptions.max : axis.datamax;

		if (max - min == 0.0) {
			// degenerate case
			var widen;
			if (max == 0.0)
				widen = 1.0;
			else
				widen = 0.01;

			min -= widen;
			max += widen;
		}
		else {
			// consider autoscaling
			var margin = axisOptions.autoscaleMargin;
			if (margin != null) {
				if (axisOptions.min == null) {
					min -= (max - min) * margin;
					// make sure we don't go below zero if all values
					// are positive
					if (min < 0 && axis.datamin >= 0)
						min = 0;
				}
				if (axisOptions.max == null) {
					max += (max - min) * margin;
					if (max > 0 && axis.datamax <= 0)
						max = 0;
				}
			}
		}
		axis.min = min;
		axis.max = max;
	},
	/**
	 * function: prepareTickGeneration
	 * 
	 * Parameters:
	 * {Object} axis
	 * {Object} axisOptions
	 */
	prepareTickGeneration: function(axis, axisOptions) {
		// estimate number of ticks
		var noTicks;
		if (Object.isNumber(axisOptions.ticks) && axisOptions.ticks > 0)
			noTicks = axisOptions.ticks;
		else if (axis == this.xaxis)
			noTicks = this.canvasWidth / 100;
		else
			noTicks = this.canvasHeight / 60;
		
		var delta = (axis.max - axis.min) / noTicks;
		var size, generator, unit, formatter, i, magn, norm;

		if (axisOptions.mode == "time") {
			function formatDate(d, fmt, monthNames) {
				var leftPad = function(n) {
					n = "" + n;
					return n.length == 1 ? "0" + n : n;
				};
				
				var r = [];
				var escape = false;
				if (monthNames == null)
					monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
				for (var i = 0; i < fmt.length; ++i) {
					var c = fmt.charAt(i);
					
					if (escape) {
						switch (c) {
						case 'h': c = "" + d.getHours(); break;
						case 'H': c = leftPad(d.getHours()); break;
						case 'M': c = leftPad(d.getMinutes()); break;
						case 'S': c = leftPad(d.getSeconds()); break;
						case 'd': c = "" + d.getDate(); break;
						case 'm': c = "" + (d.getMonth() + 1); break;
						case 'y': c = "" + d.getFullYear(); break;
						case 'b': c = "" + monthNames[d.getMonth()]; break;
						}
						r.push(c);
						escape = false;
					}
					else {
						if (c == "%")
							escape = true;
						else
							r.push(c);
					}
				}
				return r.join("");
			}
			
				
			// map of app. size of time units in milliseconds
			var timeUnitSize = {
				"second": 1000,
				"minute": 60 * 1000,
				"hour": 60 * 60 * 1000,
				"day": 24 * 60 * 60 * 1000,
				"month": 30 * 24 * 60 * 60 * 1000,
				"year": 365.2425 * 24 * 60 * 60 * 1000
			};


			// the allowed tick sizes, after 1 year we use
			// an integer algorithm
			var spec = [
				[1, "second"], [2, "second"], [5, "second"], [10, "second"],
				[30, "second"], 
				[1, "minute"], [2, "minute"], [5, "minute"], [10, "minute"],
				[30, "minute"], 
				[1, "hour"], [2, "hour"], [4, "hour"],
				[8, "hour"], [12, "hour"],
				[1, "day"], [2, "day"], [3, "day"],
				[0.25, "month"], [0.5, "month"], [1, "month"],
				[2, "month"], [3, "month"], [6, "month"],
				[1, "year"]
			];

			var minSize = 0;
			if (axisOptions.minTickSize != null) {
				if (typeof axisOptions.tickSize == "number")
					minSize = axisOptions.tickSize;
				else
					minSize = axisOptions.minTickSize[0] * timeUnitSize[axisOptions.minTickSize[1]];
			}
			
			for (i = 0; i < spec.length - 1; ++i) {
				if (delta < (spec[i][0] * timeUnitSize[spec[i][1]] + spec[i + 1][0] * timeUnitSize[spec[i + 1][1]]) / 2 && spec[i][0] * timeUnitSize[spec[i][1]] >= minSize) {
					break;
				}
			}
			
			size = spec[i][0];
			unit = spec[i][1];
			
			// special-case the possibility of several years
			if (unit == "year") {
				magn = Math.pow(10, Math.floor(Math.log(delta / timeUnitSize.year) / Math.LN10));
				norm = (delta / timeUnitSize.year) / magn;
				if (norm < 1.5)
					size = 1;
				else if (norm < 3)
					size = 2;
				else if (norm < 7.5)
					size = 5;
				else
					size = 10;

				size *= magn;
			}

			if (axisOptions.tickSize) {
				size = axisOptions.tickSize[0];
				unit = axisOptions.tickSize[1];
			}
			
			var floorInBase = this.floorInBase; //gives us a reference to a global function.. 
			
			generator = function(axis) {
				var ticks = [],
					tickSize = axis.tickSize[0], unit = axis.tickSize[1],
					d = new Date(axis.min);
				
				var step = tickSize * timeUnitSize[unit];
				
				
				
				if (unit == "second")
					d.setSeconds(floorInBase(d.getSeconds(), tickSize));
				if (unit == "minute")
					d.setMinutes(floorInBase(d.getMinutes(), tickSize));
				if (unit == "hour")
					d.setHours(floorInBase(d.getHours(), tickSize));
				if (unit == "month")
					d.setMonth(floorInBase(d.getMonth(), tickSize));
				if (unit == "year")
					d.setFullYear(floorInBase(d.getFullYear(), tickSize));
				
				// reset smaller components
				d.setMilliseconds(0);
				if (step >= timeUnitSize.minute)
					d.setSeconds(0);
				if (step >= timeUnitSize.hour)
					d.setMinutes(0);
				if (step >= timeUnitSize.day)
					d.setHours(0);
				if (step >= timeUnitSize.day * 4)
					d.setDate(1);
				if (step >= timeUnitSize.year)
					d.setMonth(0);


				var carry = 0, v;
				do {
					v = d.getTime();
					ticks.push({ v: v, label: axis.tickFormatter(v, axis) });
					if (unit == "month") {
						if (tickSize < 1) {
							d.setDate(1);
							var start = d.getTime();
							d.setMonth(d.getMonth() + 1);
							var end = d.getTime();
							d.setTime(v + carry * timeUnitSize.hour + (end - start) * tickSize);
							carry = d.getHours();
							d.setHours(0);
						}
						else
							d.setMonth(d.getMonth() + tickSize);
					}
					else if (unit == "year") {
						d.setFullYear(d.getFullYear() + tickSize);
					}
					else
						d.setTime(v + step);
				} while (v < axis.max);

				return ticks;
			};

			formatter = function (v, axis) {
				var d = new Date(v);

				// first check global format
				if (axisOptions.timeformat != null)
					return formatDate(d, axisOptions.timeformat, axisOptions.monthNames);
				
				var t = axis.tickSize[0] * timeUnitSize[axis.tickSize[1]];
				var span = axis.max - axis.min;
				
				if (t < timeUnitSize.minute)
					fmt = "%h:%M:%S";
				else if (t < timeUnitSize.day) {
					if (span < 2 * timeUnitSize.day)
						fmt = "%h:%M";
					else
						fmt = "%b %d %h:%M";
				}
				else if (t < timeUnitSize.month)
					fmt = "%b %d";
				else if (t < timeUnitSize.year) {
					if (span < timeUnitSize.year)
						fmt = "%b";
					else
						fmt = "%b %y";
				}
				else
					fmt = "%y";
				
				return formatDate(d, fmt, axisOptions.monthNames);
			};
		}
		else {
			// pretty rounding of base-10 numbers
			var maxDec = axisOptions.tickDecimals;
			var dec = -Math.floor(Math.log(delta) / Math.LN10);
			if (maxDec != null && dec > maxDec)
				dec = maxDec;
			
			magn = Math.pow(10, -dec);
			norm = delta / magn; // norm is between 1.0 and 10.0
			
			if (norm < 1.5)
				size = 1;
			else if (norm < 3) {
				size = 2;
				// special case for 2.5, requires an extra decimal
				if (norm > 2.25 && (maxDec == null || dec + 1 <= maxDec)) {
					size = 2.5;
					++dec;
				}
			}
			else if (norm < 7.5)
				size = 5;
			else
				size = 10;

			size *= magn;
			
			if (axisOptions.minTickSize != null && size < axisOptions.minTickSize)
				size = axisOptions.minTickSize;

			if (axisOptions.tickSize != null)
				size = axisOptions.tickSize;
			
			axis.tickDecimals = Math.max(0, (maxDec != null) ? maxDec : dec);
			
			var floorInBase = this.floorInBase;
			
			generator = function (axis) {
				var ticks = [];
				var start = floorInBase(axis.min, axis.tickSize);
				// then spew out all possible ticks
				var i = 0, v;
				do {
					v = start + i * axis.tickSize;
					ticks.push({ v: v, label: axis.tickFormatter(v, axis) });
					++i;
				} while (v < axis.max);
				return ticks;
			};

			formatter = function (v, axis) {
				if(v) {
				return v.toFixed(axis.tickDecimals);
				}
				return 0;
			};
		}

		axis.tickSize = unit ? [size, unit] : size;
		axis.tickGenerator = generator;
		if (Object.isFunction(axisOptions.tickFormatter))
			axis.tickFormatter = function (v, axis) { return "" + axisOptions.tickFormatter(v, axis); };
		else
			axis.tickFormatter = formatter;
	},
	/**
	 * function: extendXRangeIfNeededByBar
	 */
	extendXRangeIfNeededByBar: function() {

		if (this.options.xaxis.max == null) {
			// great, we're autoscaling, check if we might need a bump
			var newmax = this.xaxis.max;
			this.graphData.each(function(gd){
				if(gd.bars.show && gd.bars.barWidth + this.xaxis.datamax > newmax)
				{
					newmax = this.xaxis.datamax + gd.bars.barWidth;
				}
			}.bind(this));
			this.xaxis.nax = newmax;
			
		}
	},
	/**
	 * function: setTicks
	 * 
	 * parameters:
	 * {Object} axis
	 * {Object} axisOptions
	 */
	setTicks: function(axis, axisOptions) {
		axis.ticks = [];
		
		if (axisOptions.ticks == null)
			axis.ticks = axis.tickGenerator(axis);
		else if (typeof axisOptions.ticks == "number") {
			if (axisOptions.ticks > 0)
				axis.ticks = axis.tickGenerator(axis);
		}
		else if (axisOptions.ticks) {
			var ticks = axisOptions.ticks;

			if (Object.isFunction(ticks))
				// generate the ticks
				ticks = ticks({ min: axis.min, max: axis.max });
			
			// clean up the user-supplied ticks, copy them over
			//var i, v;
			ticks.each(function(t, i){
				var v = null;
				var label = null;
				if(typeof t == 'object') {
					v = t[0];
					if(t.length > 1) { label = t[1]; }
				}
				else {
					v = t;
				}
				if(!label) {
					label = axis.tickFormatter(v, axis);
				}
				axis.ticks[i] = {v: v, label: label}
			}.bind(this));

		}

		if (axisOptions.autoscaleMargin != null && axis.ticks.length > 0) {
			if (axisOptions.min == null)
				axis.min = Math.min(axis.min, axis.ticks[0].v);
			if (axisOptions.max == null && axis.ticks.length > 1)
				axis.max = Math.min(axis.max, axis.ticks[axis.ticks.length - 1].v);
		}
	},
	/**
	 * Function: setSpacing
	 * 
	 * Parameters: none
	 */
	setSpacing: function() {
		// calculate y label dimensions
		var i, labels = [], l;
		for (i = 0; i < this.yaxis.ticks.length; ++i) {
			l = this.yaxis.ticks[i].label;

			if (l)
				labels.push('<div class="tickLabel">' + l + '</div>');
		}

		if (labels.length > 0) {
			var dummyDiv = new Element('div', {'style': 'position:absolute;top:-10000px;font-size:smaller'});
			dummyDiv.update(labels.join(""));
			this.domObj.insert(dummyDiv);
			this.yLabelMaxWidth = dummyDiv.getWidth();
			this.yLabelMaxHeight = dummyDiv.select('div')[0].getHeight();
			dummyDiv.remove();
		}

		var maxOutset = this.options.grid.borderWidth;
		if (this.options.points.show)
			maxOutset = Math.max(maxOutset, this.options.points.radius + this.options.points.lineWidth/2);
		for (i = 0; i < this.graphData.length; ++i) {
			if (this.graphData[i].points.show)
				maxOutset = Math.max(maxOutset, this.graphData[i].points.radius + this.graphData[i].points.lineWidth/2);
		}

		this.chartOffset.left = this.chartOffset.right = this.chartOffset.top = this.chartOffset.bottom = maxOutset;
		
		this.chartOffset.left += this.yLabelMaxWidth + this.options.grid.labelMargin;
		this.chartWidth = this.canvasWidth - this.chartOffset.left - this.chartOffset.right;

		this.xLabelBoxWidth = this.chartWidth / 6;
		labels = [];

		for (i = 0; i < this.xaxis.ticks.length; ++i) {
			l = this.xaxis.ticks[i].label;
			if (l) {
				labels.push('<span class="tickLabel" width="' + this.xLabelBoxWidth + '">' + l + '</span>');
			}
		}

		var xLabelMaxHeight = 0;
		if (labels.length > 0) {
			var dummyDiv = new Element('div', {'style': 'position:absolute;top:-10000px;font-size:smaller'});
			dummyDiv.update(labels.join(""));
			this.domObj.appendChild(dummyDiv);	
			xLabelMaxHeight = dummyDiv.getHeight();
			dummyDiv.remove();
		}

		this.chartOffset.bottom += xLabelMaxHeight + this.options.grid.labelMargin;
		this.chartHeight = this.canvasHeight - this.chartOffset.bottom - this.chartOffset.top;
		this.hozScale = this.chartWidth / (this.xaxis.max - this.xaxis.min);
		this.vertScale = this.chartHeight / (this.yaxis.max - this.yaxis.min);
	},
	/**
	 * function: draw
	 */
	draw: function() {
		if(this.options.bars.show)
		{
			this.extendXRangeIfNeededByBar();
			this.setSpacing();
			this.drawGrid();
			this.drawBarGraph(this.graphData, this.barDataRange);
		}
		else if(this.options.pies.show)
		{
			this.preparePieData(this.graphData);
			this.drawPieGraph(this.graphData);
		}
		else
		{
			this.drawGrid();
			for (var i = 0; i < this.graphData.length; i++) {
				this.drawGraph(this.graphData[i]);
			}
		}
	},
    /**
     * function: translateHoz
     * 
     * Paramters:
     * {Object} x
     * 
     * Description: Given a value this function translate it to relative x coord on canvas
     */
	translateHoz: function(x) {
        return (x - this.xaxis.min) * this.hozScale;
    },
	/**
	 * function: translateVert
	 * 
	 * parameters:
	 * {Object} y
	 * 
	 * Description: Given a value this function translate it to relative y coord on canvas
	 */
    translateVert: function(y) {
        return this.chartHeight - (y - this.yaxis.min) * this.vertScale;
    },	
	/**
	 * function: drawGrid
	 * 
	 * parameters: none
	 * 
	 * description: draws the actual grid on the canvas
	 */
	drawGrid: function() {
		var i;
		
		this.context.save();
		this.context.clearRect(0, 0, this.canvasWidth, this.canvasHeight);
		this.context.translate(this.chartOffset.left, this.chartOffset.top);

		// draw background, if any
		if (this.options.grid.backgroundColor != null) {
			this.context.fillStyle = this.options.grid.backgroundColor;
			this.context.fillRect(0, 0, this.chartWidth, this.chartHeight);
		}

		// draw colored areas
		if (this.options.grid.coloredAreas) {
			var areas = this.options.grid.coloredAreas;
			if (Object.isFunction(areas)) {
				areas = areas({ xmin: this.xaxis.min, xmax: this.xaxis.max, ymin: this.yaxis.min, ymax: this.yaxis.max });
			}
			
			areas.each(function(a){
				// clip
				if (a.x1 == null || a.x1 < this.xaxis.min)
					a.x1 = this.xaxis.min;
				if (a.x2 == null || a.x2 > this.xaxis.max)
					a.x2 = this.xaxis.max;
				if (a.y1 == null || a.y1 < this.yaxis.min)
					a.y1 = this.yaxis.min;
				if (a.y2 == null || a.y2 > this.yaxis.max)
					a.y2 = this.yaxis.max;

				var tmp;
				if (a.x1 > a.x2) {
					tmp = a.x1;
					a.x1 = a.x2;
					a.x2 = tmp;
				}
				if (a.y1 > a.y2) {
					tmp = a.y1;
					a.y1 = a.y2;
					a.y2 = tmp;
				}

				if (a.x1 >= this.xaxis.max || a.x2 <= this.xaxis.min || a.x1 == a.x2
					|| a.y1 >= this.yaxis.max || a.y2 <= this.yaxis.min || a.y1 == a.y2)
					return;

				this.context.fillStyle = a.color || this.options.grid.coloredAreasColor;
				this.context.fillRect(Math.floor(this.translateHoz(a.x1)), Math.floor(this.translateVert(a.y2)),
							 Math.floor(this.translateHoz(a.x2) - this.translateHoz(a.x1)), Math.floor(this.translateVert(a.y1) - this.translateVert(a.y2)));				
			}.bind(this));

			
		}
		
		// draw the inner grid
		this.context.lineWidth = 1;
		this.context.strokeStyle = this.options.grid.tickColor;
		this.context.beginPath();
		var v;
		if (this.options.grid.drawXAxis) {
			this.xaxis.ticks.each(function(aTick){
				v = aTick.v;
				if(v <= this.xaxis.min || v >= this.xaxis.max) {
					return;
				}
				this.context.moveTo(Math.floor(this.translateHoz(v)) + this.context.lineWidth / 2, 0);
				this.context.lineTo(Math.floor(this.translateHoz(v)) + this.context.lineWidth / 2, this.chartHeight);
			}.bind(this));

		}
		
		if (this.options.grid.drawYAxis) {
			this.yaxis.ticks.each(function(aTick){
				v = aTick.v;
				if(v <= this.yaxis.min || v >= this.yaxis.max) {
					return;
				}
				this.context.moveTo(0, Math.floor(this.translateVert(v)) + this.context.lineWidth / 2);
				this.context.lineTo(this.chartWidth, Math.floor(this.translateVert(v)) + this.context.lineWidth / 2);
			}.bind(this));
			
		}
		this.context.stroke();
		
		if (this.options.grid.borderWidth) {
			// draw border
			this.context.lineWidth = this.options.grid.borderWidth;
			this.context.strokeStyle = this.options.grid.color;
			this.context.lineJoin = "round";
			this.context.strokeRect(0, 0, this.chartWidth, this.chartHeight);
			this.context.restore();
		}
	},
	/**
	 * function: insertLabels
	 * 
	 * parameters: none
	 * 
	 * description: inserts the label with proper spacing. Both on X and Y axis
	 */
	insertLabels: function() {
		this.domObj.select(".tickLabels").invoke('remove');
		
		var i, tick;
		var html = '<div class="tickLabels" style="font-size:smaller;color:' + this.options.grid.color + '">';
		
		// do the x-axis
		this.xaxis.ticks.each(function(tick){
			if (!tick.label || tick.v < this.xaxis.min || tick.v > this.xaxis.max)
				return;
			html += '<div style="position:absolute;top:' + (this.chartOffset.top + this.chartHeight + this.options.grid.labelMargin) + 'px;left:' + (this.chartOffset.left + this.translateHoz(tick.v) - this.xLabelBoxWidth/2) + 'px;width:' + this.xLabelBoxWidth + 'px;text-align:center" class="tickLabel">' + tick.label + "</div>";
			
		}.bind(this));
		
		// do the y-axis
		this.yaxis.ticks.each(function(tick){
			if (!tick.label || tick.v < this.yaxis.min || tick.v > this.yaxis.max)
				return;
			html += '<div id="ylabels" style="position:absolute;top:' + (this.chartOffset.top + this.translateVert(tick.v) - this.yLabelMaxHeight/2) + 'px;left:0;width:' + this.yLabelMaxWidth + 'px;text-align:right" class="tickLabel">' + tick.label + "</div>";
		}.bind(this));

		html += '</div>';
		
		this.domObj.insert(html);
	},
	/**
	 * function: drawGraph
	 * 
	 * Paramters:
	 * {Object} graphData
	 * 
	 * Description: given a graphData (series) this function calls a proper lower level method to draw it.
	 */
	drawGraph: function(graphData) {
		if (graphData.lines.show || (!graphData.bars.show && !graphData.points.show))
			this.drawGraphLines(graphData);
		if (graphData.bars.show)
			this.drawGraphBar(graphData);
		if (graphData.points.show)
			this.drawGraphPoints(graphData);
	},
	/**
	 * function: plotLine
	 * 
	 * parameters:
	 * {Object} data
	 * {Object} offset
	 * 
	 * description: 
	 * Helper function that plots a line based on the data provided
	 */
	plotLine: function(data, offset) {
        var prev, cur = null, drawx = null, drawy = null;
        
        this.context.beginPath();
        for (var i = 0; i < data.length; ++i) {
            prev = cur;
            cur = data[i];

            if (prev == null || cur == null)
                continue;
            
            var x1 = prev[0], y1 = prev[1],
                x2 = cur[0], y2 = cur[1];

            // clip with ymin
            if (y1 <= y2 && y1 < this.yaxis.min) {
                if (y2 < this.yaxis.min)
                    continue;   // line segment is outside
                // compute new intersection point
                x1 = (this.yaxis.min - y1) / (y2 - y1) * (x2 - x1) + x1;
                y1 = this.yaxis.min;
            }
            else if (y2 <= y1 && y2 < this.yaxis.min) {
                if (y1 < this.yaxis.min)
                    continue;
                x2 = (this.yaxis.min - y1) / (y2 - y1) * (x2 - x1) + x1;
                y2 = this.yaxis.min;
            }

            // clip with ymax
            if (y1 >= y2 && y1 > this.yaxis.max) {
                if (y2 > this.yaxis.max)
                    continue;
                x1 = (this.yaxis.max - y1) / (y2 - y1) * (x2 - x1) + x1;
                y1 = this.yaxis.max;
            }
            else if (y2 >= y1 && y2 > this.yaxis.max) {
                if (y1 > this.yaxis.max)
                    continue;
                x2 = (this.yaxis.max - y1) / (y2 - y1) * (x2 - x1) + x1;
                y2 = this.yaxis.max;
            }

            // clip with xmin
            if (x1 <= x2 && x1 < this.xaxis.min) {
                if (x2 < this.xaxis.min)
                    continue;
                y1 = (this.xaxis.min - x1) / (x2 - x1) * (y2 - y1) + y1;
                x1 = this.xaxis.min;
            }
            else if (x2 <= x1 && x2 < this.xaxis.min) {
                if (x1 < this.xaxis.min)
                    continue;
                y2 = (this.xaxis.min - x1) / (x2 - x1) * (y2 - y1) + y1;
                x2 = this.xaxis.min;
            }

            // clip with xmax
            if (x1 >= x2 && x1 > this.xaxis.max) {
                if (x2 > this.xaxis.max)
                    continue;
                y1 = (this.xaxis.max - x1) / (x2 - x1) * (y2 - y1) + y1;
                x1 = this.xaxis.max;
            }
            else if (x2 >= x1 && x2 > this.xaxis.max) {
                if (x1 > this.xaxis.max)
                    continue;
                y2 = (this.xaxis.max - x1) / (x2 - x1) * (y2 - y1) + y1;
                x2 = this.xaxis.max;
            }

            if (drawx != this.translateHoz(x1) || drawy != this.translateVert(y1) + offset)
                this.context.moveTo(this.translateHoz(x1), this.translateVert(y1) + offset);
            
            drawx = this.translateHoz(x2);
            drawy = this.translateVert(y2) + offset;
            this.context.lineTo(drawx, drawy);
        }
        this.context.stroke();
    },
	/**
	 * function: plotLineArea
	 * 
	 * parameters:
	 * {Object} data
	 * 
	 * description:
	 * Helper functoin that plots a colored line graph. This function
	 * takes the data nad then fill in the area on the graph properly
	 */
	plotLineArea: function(data) {
        var prev, cur = null;
        
        var bottom = Math.min(Math.max(0, this.yaxis.min), this.yaxis.max);
        var top, lastX = 0;

        var areaOpen = false;
        
        for (var i = 0; i < data.length; ++i) {
            prev = cur;
            cur = data[i];

            if (areaOpen && prev != null && cur == null) {
                // close area
                this.context.lineTo(this.translateHoz(lastX), this.translateVert(bottom));
                this.context.fill();
                areaOpen = false;
                continue;
            }

            if (prev == null || cur == null)
                continue;
                
            var x1 = prev[0], y1 = prev[1],
                x2 = cur[0], y2 = cur[1];

            // clip x values
            
            // clip with xmin
            if (x1 <= x2 && x1 < this.xaxis.min) {
                if (x2 < this.xaxis.min)
                    continue;
                y1 = (this.xaxis.min - x1) / (x2 - x1) * (y2 - y1) + y1;
                x1 = this.xaxis.min;
            }
            else if (x2 <= x1 && x2 < this.xaxis.min) {
                if (x1 < this.xaxis.min)
                    continue;
                y2 = (this.xaxis.min - x1) / (x2 - x1) * (y2 - y1) + y1;
                x2 = this.xaxis.min;
            }

            // clip with xmax
            if (x1 >= x2 && x1 > this.xaxis.max) {
                if (x2 > this.xaxis.max)
                    continue;
                y1 = (this.xaxis.max - x1) / (x2 - x1) * (y2 - y1) + y1;
                x1 = this.xaxis.max;
            }
            else if (x2 >= x1 && x2 > this.xaxis.max) {
                if (x1 > this.xaxis.max)
                    continue;
                y2 = (this.xaxis.max - x1) / (x2 - x1) * (y2 - y1) + y1;
                x2 = this.xaxis.max;
            }

            if (!areaOpen) {
                // open area
                this.context.beginPath();
                this.context.moveTo(this.translateHoz(x1), this.translateVert(bottom));
                areaOpen = true;
            }
            
            // now first check the case where both is outside
            if (y1 >= this.yaxis.max && y2 >= this.yaxis.max) {
                this.context.lineTo(this.translateHoz(x1), this.translateVert(this.yaxis.max));
                this.context.lineTo(this.translateHoz(x2), this.translateVert(this.yaxis.max));
                continue;
            }
            else if (y1 <= this.yaxis.min && y2 <= this.yaxis.min) {
                this.context.lineTo(this.translateHoz(x1), this.translateVert(this.yaxis.min));
                this.context.lineTo(this.translateHoz(x2), this.translateVert(this.yaxis.min));
                continue;
            }
            
            var x1old = x1, x2old = x2;

            // clip with ymin
            if (y1 <= y2 && y1 < this.yaxis.min && y2 >= this.yaxis.min) {
                x1 = (this.yaxis.min - y1) / (y2 - y1) * (x2 - x1) + x1;
                y1 = this.yaxis.min;
            }
            else if (y2 <= y1 && y2 < this.yaxis.min && y1 >= this.yaxis.min) {
                x2 = (this.yaxis.min - y1) / (y2 - y1) * (x2 - x1) + x1;
                y2 = this.yaxis.min;
            }

            // clip with ymax
            if (y1 >= y2 && y1 > this.yaxis.max && y2 <= this.yaxis.max) {
                x1 = (this.yaxis.max - y1) / (y2 - y1) * (x2 - x1) + x1;
                y1 = this.yaxis.max;
            }
            else if (y2 >= y1 && y2 > this.yaxis.max && y1 <= this.yaxis.max) {
                x2 = (this.yaxis.max - y1) / (y2 - y1) * (x2 - x1) + x1;
                y2 = this.yaxis.max;
            }


            // if the x value was changed we got a rectangle
            // to fill
            if (x1 != x1old) {
                if (y1 <= this.yaxis.min)
                    top = this.yaxis.min;
                else
                    top = this.yaxis.max;
                
                this.context.lineTo(this.translateHoz(x1old), this.translateVert(top));
                this.context.lineTo(this.translateHoz(x1), this.translateVert(top));
            }
            
            // fill the triangles
            this.context.lineTo(this.translateHoz(x1), this.translateVert(y1));
            this.context.lineTo(this.translateHoz(x2), this.translateVert(y2));

            // fill the other rectangle if it's there
            if (x2 != x2old) {
                if (y2 <= this.yaxis.min)
                    top = this.yaxis.min;
                else
                    top = this.yaxis.max;
                
                this.context.lineTo(this.translateHoz(x2old), this.translateVert(top));
                this.context.lineTo(this.translateHoz(x2), this.translateVert(top));
            }

            lastX = Math.max(x2, x2old);
        }

        if (areaOpen) {
            this.context.lineTo(this.translateHoz(lastX), this.translateVert(bottom));
            this.context.fill();
        }
    },		
	/**
	 * function: drawGraphLines
	 * 
	 * parameters:
	 * {Object} graphData
	 * 
	 * description:
	 * Main function that daws the line graph. This function is called 
	 * if <options> lines property is set to show or no other type of 
	 * graph is specified. This function depends on <plotLineArea> and 
	 * <plotLine> functions.
	 */
	drawGraphLines: function(graphData) {
        this.context.save();
        this.context.translate(this.chartOffset.left, this.chartOffset.top);
        this.context.lineJoin = "round";

        var lw = graphData.lines.lineWidth;
        var sw = graphData.shadowSize;
        // FIXME: consider another form of shadow when filling is turned on
        if (sw > 0) {
            // draw shadow in two steps
            this.context.lineWidth = sw / 2;
            this.context.strokeStyle = "rgba(0,0,0,0.1)";
            this.plotLine(graphData.data, lw/2 + sw/2 + this.context.lineWidth/2);

            this.context.lineWidth = sw / 2;
            this.context.strokeStyle = "rgba(0,0,0,0.2)";
            this.plotLine(graphData.data, lw/2 + this.context.lineWidth/2);
        }

        this.context.lineWidth = lw;
        this.context.strokeStyle = graphData.color;
        if (graphData.lines.fill) {
            this.context.fillStyle = graphData.lines.fillColor != null ? graphData.lines.fillColor : this.parseColor(graphData.color).scale(null, null, null, 0.4).toString();
            this.plotLineArea(graphData.data, 0);
        }

        this.plotLine(graphData.data, 0);
        this.context.restore();
    },
	/**
	 * function: plotPoints
	 * 
	 * parameters:
	 * {Object} data
	 * {Object} radius
	 * {Object} fill
	 * 
	 * description:
	 * Helper function that draws the point graph according to the data provided. Size of each 
	 * point is provided by radius variable and fill specifies if points 
	 * are filled
	 */
	plotPoints: function(data, radius, fill) {
        for (var i = 0; i < data.length; ++i) {
            if (data[i] == null)
                continue;
            
            var x = data[i][0], y = data[i][1];
            if (x < this.xaxis.min || x > this.xaxis.max || y < this.yaxis.min || y > this.yaxis.max)
                continue;
            
            this.context.beginPath();
            this.context.arc(this.translateHoz(x), this.translateVert(y), radius, 0, 2 * Math.PI, true);
            if (fill)
                this.context.fill();
            this.context.stroke();
        }
    },
	/**
	 * function: plotPointShadows
	 * 
	 * parameters:
	 * {Object} data
	 * {Object} offset
	 * {Object} radius
	 * 
	 * description: 
	 * Helper function that draws the shadows for the points.
	 */
    plotPointShadows: function(data, offset, radius) {
        for (var i = 0; i < data.length; ++i) {
            if (data[i] == null)
                continue;
            
            var x = data[i][0], y = data[i][1];
            if (x < this.xaxis.min || x > this.xaxis.max || y < this.yaxis.min || y > this.yaxis.max)
                continue;
            this.context.beginPath();
            this.context.arc(this.translateHoz(x), this.translateVert(y) + offset, radius, 0, Math.PI, false);
            this.context.stroke();
        }
    },
	/**
	 * function: drawGraphPoints
	 * 
	 * paramters:
	 * {Object} graphData
	 * 
	 * description:
	 * Draws the point graph onto the canvas. This function depends on helper 
	 * functions <plotPointShadows> and <plotPoints>
	 */
    drawGraphPoints: function(graphData) {
       	this.context.save();
        this.context.translate(this.chartOffset.left, this.chartOffset.top);

        var lw = graphData.lines.lineWidth;
        var sw = graphData.shadowSize;
        if (sw > 0) {
            // draw shadow in two steps
            this.context.lineWidth = sw / 2;
            this.context.strokeStyle = "rgba(0,0,0,0.1)";
            this.plotPointShadows(graphData.data, sw/2 + this.context.lineWidth/2, graphData.points.radius);

            this.context.lineWidth = sw / 2;
            this.context.strokeStyle = "rgba(0,0,0,0.2)";
            this.plotPointShadows(graphData.data, this.context.lineWidth/2, graphData.points.radius);
        }

        this.context.lineWidth = graphData.points.lineWidth;
        this.context.strokeStyle = graphData.color;
        this.context.fillStyle = graphData.points.fillColor != null ? graphData.points.fillColor : graphData.color;
        this.plotPoints(graphData.data, graphData.points.radius, graphData.points.fill);
        this.context.restore();
    },
	/**
	 * function: preparePieData
	 * 
	 * parameters:
	 * {Object} graphData
	 * 
	 * Description: 
	 * Helper function that manipulates the given data stream so that it can 
	 * be plotted as a Pie Chart
	 */
	preparePieData: function(graphData)
	{
		for(i = 0; i < graphData.length; i++)
		{
			var data = 0;
			for(j = 0; j < graphData[i].data.length; j++){
				data += parseInt(graphData[i].data[j][1]);
			}
			graphData[i].data = data;
		}
	},
	/**
	 * function: drawPieShadow
	 * 
	 * {Object} anchorX
	 * {Object} anchorY
	 * {Object} radius
	 * 
	 * description:
	 * Helper function that draws a shadow for the Pie Chart. This just draws 
	 * a circle with offset that simulates shadow. We do not give each piece 
	 * of the pie an individual shadow.
	 */
	drawPieShadow: function(anchorX, anchorY, radius)
	{
		this.context.beginPath();
		this.context.moveTo(anchorX, anchorY);
		this.context.fillStyle = 'rgba(0,0,0,' + 0.1 + ')';
		startAngle = 0;
		endAngle = (Math.PI/180)*360;	
		this.context.arc(anchorX + 2, anchorY +2, radius + (this.options.shadowSize/2), startAngle, endAngle, false);
		this.context.fill();
		this.context.closePath();
	},
	/**
	 * function: drawPieGraph
	 * 
	 * parameters:
	 * {Object} graphData
	 * 
	 * description: 
	 * Draws the actual pie chart. This function depends on helper function 
	 * <drawPieShadow> to draw the actual shadow
	 */
	drawPieGraph: function(graphData)
	{
		var sumData = 0;
		var radius = 0;
		var centerX = this.chartWidth/2;
		var centerY = this.chartHeight/2;
		var startAngle = 0;
		var endAngle = 0;
		var fontSize = this.options.pies.fontSize;
		var labelWidth = this.options.pies.labelWidth;

		//determine Pie Radius
		if(!this.options.pies.autoScale)
			radius = this.options.pies.radius;
		else
			radius = (this.chartHeight * 0.85)/2;

		var labelRadius = radius * 1.05;

		for(i = 0; i < graphData.length; i++)
			sumData += graphData[i].data;

		// used to adjust labels so that everything adds up to 100%
		totalPct = 0;
		
		//lets draw the shadow first.. we don't need an individual shadow to every pie rather we just
		//draw a circle underneath to simulate the shadow...
		this.drawPieShadow(centerX, centerY, radius, 0, 0); 
		
		//lets draw the actual pie chart now.
		graphData.each(function(gd, j){
			var pct = gd.data / sumData;
			startAngle = endAngle;
			endAngle += pct * (2 * Math.PI);
			var sliceMiddle = (endAngle - startAngle) / 2 + startAngle;
			var labelX = centerX + Math.cos(sliceMiddle) * labelRadius;
			var labelY = centerY + Math.sin(sliceMiddle) * labelRadius;
			var anchorX = centerX;
			var anchorY = centerY;
			var textAlign = null;
			var verticalAlign = null;
			var left = 0;
			var top = 0;
			
			//draw pie:
			//drawing pie	
			this.context.beginPath();
			this.context.moveTo(anchorX, anchorY);				
			this.context.arc(anchorX, anchorY, radius, startAngle, endAngle, false);
			this.context.closePath();
			this.context.fillStyle = this.parseColor(gd.color).scale(null, null, null, this.options.pies.fillOpacity).toString();

			if(this.options.pies.fill)	{ this.context.fill(); }

			// drawing labels
			if (sliceMiddle <= 0.25 * (2 * Math.PI)) 
			{
				// text on top and align left
				textAlign = "left";
				verticalAlign = "top";
				left = labelX;
				top = labelY + fontSize;
			}
			else if (sliceMiddle > 0.25 * (2 * Math.PI) && sliceMiddle <= 0.5 * (2 * Math.PI)) 
			{
				// text on bottom and align left
				textAlign = "left";
				verticalAlign = "bottom";
				left = labelX - labelWidth;
				top = labelY;
			}
			else if (sliceMiddle > 0.5 * (2 * Math.PI) && sliceMiddle <= 0.75 * (2 * Math.PI)) 
			{
				// text on bottom and align right
				textAlign = "right";
				verticalAlign = "bottom";
				left = labelX - labelWidth;
				top = labelY - fontSize;
			}
			else 
			{
				// text on top and align right
				textAlign = "right";
				verticalAlign = "bottom";
				left = labelX;
				top = labelY - fontSize;
			}

			left = left + "px";
			top = top + "px";
			var textVal = Math.round(pct * 100);

			if (j == graphData.length - 1) {
				if (textVal + totalPct < 100) {
					textVal = textVal + 1;
				} else if (textVal + totalPct > 100) {
					textVal = textVal - 1;
				};
			}

			var html = "<div style=\"position: absolute;zindex:11; width:" + labelWidth + "px;fontSize:" + fontSize + "px;overflow:hidden;top:"+ top + ";left:"+ left + ";textAlign:" + textAlign + ";verticalAlign:" + verticalAlign +"\">" +  textVal + "%</div>";
			//$(html).appendTo(target);
			this.domObj.insert(html);

			totalPct = totalPct + textVal;			
		}.bind(this));
		
	},
	/**
	 * function: drawBarGraph
	 * 
	 * parameters:
	 * {Object} graphData
	 * {Object} barDataRange
	 * 
	 * description: 
	 * Goes through each series in graphdata and passes it onto <drawBarGraphs> function
	 */
	drawBarGraph: function(graphData, barDataRange)
	{
		graphData.each(function(gd, i){
			this.drawGraphBars(gd, i, graphData.size(), barDataRange);
		}.bind(this));
	},
	/**
	 * function: drawGraphBar
	 * 
	 * parameters:
	 * {Object} graphData
	 * 
	 * description:
	 * This function is called when an individual series in GraphData is bar graph and plots it
	 */
	drawGraphBar: function(graphData)
	{
		this.drawGraphBars(graphData, 0, this.graphData.length, this.barDataRange);			
	},	
	/**
	 * function: plotBars
	 * 
	 * parameters:
	 * {Object} graphData
	 * {Object} data
	 * {Object} barWidth
	 * {Object} offset
	 * {Object} fill
	 * {Object} counter
	 * {Object} total
	 * {Object} barDataRange
	 * 
	 * description: 
	 * Helper function that draws the bar graph based on data.
	 */
	plotBars: function(graphData, data, barWidth, offset, fill,counter, total, barDataRange) {
		var shift = 0;
		
		if(total % 2 == 0)
		{
			shift = (1 + ((counter  - total /2 ) - 1)) * barWidth;
		}
		else
		{
			var interval = 0.5;			
			if(counter == (total/2 - interval )) {
				shift = - barWidth * interval;
			}
			else {
				shift = (interval + (counter  - Math.round(total/2))) * barWidth;
			}
		}

		var rangeData = [];
		data.each(function(d){
			if(!d) return;
			
			var x = d[0], y = d[1];
			var drawLeft = true, drawTop = true, drawRight = true;
			var left = x + shift, right = x + barWidth + shift, bottom = 0, top = y;
			var rangeDataPoint = {};
			rangeDataPoint.left = left;
			rangeDataPoint.right = right;
			rangeDataPoint.value = top;
			rangeData.push(rangeDataPoint);

			if (right < this.xaxis.min || left > this.xaxis.max || top < this.yaxis.min || bottom > this.yaxis.max)
				return;

			// clip
			if (left < this.xaxis.min) {
				left = this.xaxis.min;
				drawLeft = false;
			}

			if (right > this.xaxis.max) {
				right = this.xaxis.max;
				drawRight = false;
			}

			if (bottom < this.yaxis.min)
				bottom = this.yaxis.min;

			if (top > this.yaxis.max) {
				top = this.yaxis.max;
				drawTop = false;
			}
			
			if(graphData.bars.showShadow && graphData.shadowSize > 0)
				this.plotShadowOutline(graphData, this.context.strokeStyle, left, bottom, top, right, drawLeft, drawRight, drawTop);
				
			// fill the bar
			if (fill) {
				this.context.beginPath();
				this.context.moveTo(this.translateHoz(left), this.translateVert(bottom) + offset);
				this.context.lineTo(this.translateHoz(left), this.translateVert(top) + offset);
				this.context.lineTo(this.translateHoz(right), this.translateVert(top) + offset);
				this.context.lineTo(this.translateHoz(right), this.translateVert(bottom) + offset);
				this.context.fill();
			}

			// draw outline
			if (drawLeft || drawRight || drawTop) {
				this.context.beginPath();
				this.context.moveTo(this.translateHoz(left), this.translateVert(bottom) + offset);
				if (drawLeft)
					this.context.lineTo(this.translateHoz(left), this.translateVert(top) + offset);
				else
					this.context.moveTo(this.translateHoz(left), this.translateVert(top) + offset);

				if (drawTop)
					this.context.lineTo(this.translateHoz(right), this.translateVert(top) + offset);
				else
					this.context.moveTo(this.translateHoz(right), this.translateVert(top) + offset);
				if (drawRight)
					this.context.lineTo(this.translateHoz(right), this.translateVert(bottom) + offset);
				else
					this.context.moveTo(this.translateHoz(right), this.translateVert(bottom) + offset);
				this.context.stroke();
			}
		}.bind(this));
		
		barDataRange.push(rangeData);
	},
	/**
	 * function: plotShadowOutline
	 * 
	 * parameters:
	 * {Object} graphData
	 * {Object} orgStrokeStyle
	 * {Object} left
	 * {Object} bottom
	 * {Object} top
	 * {Object} right
	 * {Object} drawLeft
	 * {Object} drawRight
	 * {Object} drawTop
	 * 
	 * description:
	 * Helper function that draws a outline simulating shadow for bar chart
	 */
	plotShadowOutline: function(graphData, orgStrokeStyle, left, bottom, top, right, drawLeft, drawRight, drawTop)
	{
		var orgOpac = 0.3;
		
		for(var n = 1; n <= this.options.shadowSize/2; n++)
		{
			var opac = orgOpac * n;
			this.context.beginPath();
			this.context.strokeStyle = "rgba(0,0,0," + opac + ")";

			this.context.moveTo(this.translateHoz(left) + n, this.translateVert(bottom));

			if(drawLeft)
				this.context.lineTo(this.translateHoz(left) + n, this.translateVert(top) - n);
			else
				this.context.moveTo(this.translateHoz(left) + n, this.translateVert(top) - n);

			if(drawTop)	
				this.context.lineTo(this.translateHoz(right) + n, this.translateVert(top) - n);
			else
				this.context.moveTo(this.translateHoz(right) + n, this.translateVert(top) - n);

			if(drawRight)
				this.context.lineTo(this.translateHoz(right) + n, this.translateVert(bottom));
			else
				this.context.lineTo(this.translateHoz(right) + n, this.translateVert(bottom));

			this.context.stroke();
			this.context.closePath();
		}

		this.context.strokeStyle = orgStrokeStyle;
	},
	/**
	 * function: drawGraphBars
	 * 
	 * parameters:
	 * {Object} graphData 
	 * {Object} counter
	 * {Object} total
	 * {Object} barDataRange
	 * 
	 * description:
	 * Draws the actual bar graphs. Calls <plotBars> to draw the individual bar
	 */
	drawGraphBars: function(graphData, counter, total, barDataRange){
		this.context.save();
		this.context.translate(this.chartOffset.left, this.chartOffset.top);
		this.context.lineJoin = "round";

		var bw = graphData.bars.barWidth;
		var lw = Math.min(graphData.bars.lineWidth, bw);


		this.context.lineWidth = lw;
		this.context.strokeStyle = graphData.color;
		if (graphData.bars.fill) {
			this.context.fillStyle = graphData.bars.fillColor != null ? graphData.bars.fillColor : this.parseColor(graphData.color).scale(null, null, null, this.options.bars.fillOpacity).toString();
		}
		this.plotBars(graphData, graphData.data, bw, 0, graphData.bars.fill, counter, total, barDataRange);
		this.context.restore();
	},
	/**
	 * function: insertLegend
	 * 
	 * description:
	 * inserts legend onto the graph. *legend: {show: true}* must be set in <options> 
	 * for for this to work.
	 */
	insertLegend: function() {
		this.domObj.select(".legend").invoke('remove');

		if (!this.options.legend.show)
			return;
		
		var fragments = [];
		var rowStarted = false;
		this.graphData.each(function(gd, index){
			if(!gd.label) {
				return;
			}
			if(index % this.options.legend.noColumns == 0) {
				if(rowStarted) {
					fragments.push('</tr>');
				}
				fragments.push('<tr>');
				rowStarted = true;
			}
			var label = gd.label;
			if(this.options.legend.labelFormatter != null) {
				label = this.options.legend.labelFormatter(label);
			}
			
			fragments.push(
				'<td class="legendColorBox"><div style="border:1px solid ' + this.options.legend.labelBoxBorderColor + ';padding:1px"><div style="width:14px;height:10px;background-color:' + gd.color + ';overflow:hidden"></div></div></td>' +
				'<td class="legendLabel">' + label + '</td>');
			
		}.bind(this));

		if (rowStarted)
			fragments.push('</tr>');
		
		if(fragments.length > 0){
			var table = '<table style="font-size:smaller;color:' + this.options.grid.color + '">' + fragments.join("") + '</table>';
			if($(this.options.legend.container) != null){
				$(this.options.legend.container).insert(table);
			}else{
				var pos = '';
				var p = this.options.legend.position, m = this.options.legend.margin;
				
				if(p.charAt(0) == 'n') pos += 'top:' + (m + this.chartOffset.top) + 'px;';
				else if(p.charAt(0) == 's') pos += 'bottom:' + (m + this.chartOffset.bottom) + 'px;';					
				if(p.charAt(1) == 'e') pos += 'right:' + (m + this.chartOffset.right) + 'px;';
				else if(p.charAt(1) == 'w') pos += 'left:' + (m + this.chartOffset.bottom) + 'px;';
				var div = this.domObj.insert('<div class="ProtoChart-legend" style="border: 1px solid '+this.options.legend.borderColor+'; position:absolute;z-index:2;' + pos +'">' + table + '</div>').getElementsBySelector('div.ProtoChart-legend').first();
				
				if(this.options.legend.backgroundOpacity != 0.0){
					var c = this.options.legend.backgroundColor;
					if(c == null){
						var tmp = (this.options.grid.backgroundColor != null) ? this.options.grid.backgroundColor : this.extractColor(div);
						c = this.parseColor(tmp).adjust(null, null, null, 1).toString();
					}
					this.domObj.insert('<div class="ProtoChart-legend-bg" style="position:absolute;width:' + div.getWidth() + 'px;height:' + div.getHeight() + 'px;' + pos +'background-color:' + c + ';"> </div>').select('div.ProtoChart-legend-bg').first().setStyle({
						'opacity': this.options.legend.backgroundOpacity
					});						
				}
			}
		}
	},
	/**
	 * Function: onMouseMove
	 * 
	 * parameters:
	 * event: {Object} ev
	 * 
	 * Description:
	 * Called whenever the mouse is moved on the graph. This takes care of the mousetracking.
	 * This event also fires <ProtoChart:mousemove> event, which gets current position of the 
	 * mouse as a parameters. 
	 */
	onMouseMove: function(ev) {
		var e = ev || window.event;
		if (e.pageX == null && e.clientX != null) {
			var de = document.documentElement, b = $(document.body);
			this.lastMousePos.pageX = e.clientX + (de && de.scrollLeft || b.scrollLeft || 0);
			this.lastMousePos.pageY = e.clientY + (de && de.scrollTop || b.scrollTop || 0);
		}
		else {
			this.lastMousePos.pageX = e.pageX;
			this.lastMousePos.pageY = e.pageY;
		}
		
		var offset = this.overlay.cumulativeOffset();
		var pos = {
			x: this.xaxis.min + (e.pageX - offset.left - this.chartOffset.left) / this.hozScale,
			y: this.yaxis.max - (e.pageY - offset.top - this.chartOffset.top) / this.vertScale
		};
		
		if(this.options.mouse.track && this.selectionInterval == null) {
			this.hit(ev, pos);
		}
		this.domObj.fire("ProtoChart:mousemove", [ pos ]);
	},
	/**
	 * Function: onMouseDown
	 * 
	 * Parameters:
	 * Event - {Object} e
	 * 
	 * Description:
	 * Called whenever the mouse is clicked.
	 */
	onMouseDown: function(e) {
		if (e.which != 1)  // only accept left-click
			return;
		
		document.body.focus();

		if (document.onselectstart !== undefined && this.workarounds.onselectstart == null) {
			this.workarounds.onselectstart = document.onselectstart;
			document.onselectstart = function () { return false; };
		}
		if (document.ondrag !== undefined && this.workarounds.ondrag == null) {
			this.workarounds.ondrag = document.ondrag;
			document.ondrag = function () { return false; };
		}
		
		this.setSelectionPos(this.selection.first, e);
			
		if (this.selectionInterval != null)
			clearInterval(this.selectionInterval);
		this.lastMousePos.pageX = null;
		this.selectionInterval = setInterval(this.updateSelectionOnMouseMove.bind(this), 200);

		this.overlay.observe("mouseup", this.onSelectionMouseUp.bind(this));
	},
	/**
	 * Function: onClick
	 * parameters:
	 * Event - {Object} e
	 * Description: 
	 * Handles the "click" event on the chart. This function fires <ProtoChart:plotclick> event. If
	 * <options.allowDataClick> is enabled then it also fires <ProtoChart:dataclick> event which gives
	 * you access to exact data point where user clicked.
	 */
	onClick: function(e) {
		if (this.ignoreClick) {
			this.ignoreClick = false;
			return;
		}
		var offset = this.overlay.cumulativeOffset(); 
		var pos ={
			x: this.xaxis.min + (e.pageX - offset.left - this.chartOffset.left) / this.hozScale,
			y: this.yaxis.max - (e.pageY - offset.top - this.chartOffset.top) / this.vertScale
		};
		this.domObj.fire("ProtoChart:plotclick", [ pos ]);

		if(this.options.allowDataClick)
		{
			var dataPoint = {};
			if(this.options.points.show)
			{
				dataPoint = this.getDataClickPoint(pos, this.options);
				this.domObj.fire("ProtoChart:dataclick", [dataPoint]);
			}
			else if(this.options.lines.show && this.options.points.show)
			{
				dataPoint = this.getDataClickPoint(pos, this.options);
				this.domObj.fire("ProtoChart:dataclick", [dataPoint]);
			}
			else if(this.options.bars.show)
			{
				if(this.barDataRange.length > 0)
				{
					dataPoint = this.getDataClickPoint(pos, this.options, this.barDataRange);
					this.domObj.fire("ProtoChart:dataclick", [dataPoint]);
				}
			}
		}
	},
	/**
	 * Internal function used by onClick method.
	 */
	getDataClickPoint: function(pos, options, barDataRange)
	{
		pos.x = parseInt(pos.x);
		pos.y = parseInt(pos.y);
		var yClick = pos.y.toFixed(0);
		var dataVal = {};

		dataVal.position = pos;
		dataVal.value = '';

		if(options.points.show)
		{
			this.graphData.each(function(gd){
				var temp = gd.data;
				var xClick = parseInt(pos.x.toFixed(0));
				if(xClick < 0) { xClick = 0; }
				if(temp[xClick] && yClick >= temp[xClick][1] - (this.options.points.radius * 10) && yClick <= temp[xClick][1] + (this.options.points.radius * 10)) {
					dataVal.value = temp[xClick][1];
					throw $break;
				}
				
			}.bind(this));
		}
		else if(options.bars.show)
		{
			xClick = pos.x;
			this.barDataRange.each(function(barData){
				barData.each(function(data){
					var temp = data;
					if(xClick > temp.left && xClick < temp.right) {
						dataVal.value = temp.value;
						throw $break;
					}
				}.bind(this));
			}.bind(this));

		}

		return dataVal;
	},
	/**
	 * Function: triggerSelectedEvent
	 * 
	 * Description:
	 * Internal function called when a selection on the graph is made. This function
	 * fires <ProtoChart:selected> event which has a parameter representing the selection
	 * {
	 * 	x1: {int}, y1: {int},
	 * 	x2: {int}, y2: {int}
	 * }
	 */
	triggerSelectedEvent: function() {
		var x1, x2, y1, y2;
		if (this.selection.first.x <= this.selection.second.x) {
			x1 = this.selection.first.x;
			x2 = this.selection.second.x;
		}
		else {
			x1 = this.selection.second.x;
			x2 = this.selection.first.x;
		}

		if (this.selection.first.y >= this.selection.second.y) {
			y1 = this.selection.first.y;
			y2 = this.selection.second.y;
		}
		else {
			y1 = this.selection.second.y;
			y2 = this.selection.first.y;
		}
		
		x1 = this.xaxis.min + x1 / this.hozScale;
		x2 = this.xaxis.min + x2 / this.hozScale;

		y1 = this.yaxis.max - y1 / this.vertScale;
		y2 = this.yaxis.max - y2 / this.vertScale;

		this.domObj.fire("ProtoChart:selected", [ { x1: x1, y1: y1, x2: x2, y2: y2 } ]);
	},
	/**
	 * Internal function
	 */
	onSelectionMouseUp: function(e) {
		if (document.onselectstart !== undefined)
			document.onselectstart = this.workarounds.onselectstart;
		if (document.ondrag !== undefined)
			document.ondrag = this.workarounds.ondrag;
		
		if (this.selectionInterval != null) {
			clearInterval(this.selectionInterval);
			this.selectionInterval = null;
		}

		this.setSelectionPos(this.selection.second, e);
		this.clearSelection();
		if (!this.selectionIsSane() || e.which != 1)
			return false;
		
		this.drawSelection();
		this.triggerSelectedEvent();
		this.ignoreClick = true;

		return false;
	},
	setSelectionPos: function(pos, e) {
		var offset = $(this.overlay).cumulativeOffset();
		if (this.options.selection.mode == "y") {
			if (pos == this.selection.first)
				pos.x = 0;
			else
				pos.x = this.chartWidth;
		}
		else {
			pos.x = e.pageX - offset.left - this.chartOffset.left;
			pos.x = Math.min(Math.max(0, pos.x), this.chartWidth);
		}

		if (this.options.selection.mode == "x") {
			if (pos == this.selection.first)
				pos.y = 0;
			else
				pos.y = this.chartHeight;
		}
		else {
			pos.y = e.pageY - offset.top - this.chartOffset.top;
			pos.y = Math.min(Math.max(0, pos.y), this.chartHeight);
		}
	},
	updateSelectionOnMouseMove: function() {
		if (this.lastMousePos.pageX == null)
			return;
		
		this.setSelectionPos(this.selection.second, this.lastMousePos);
		this.clearSelection();
		if (this.selectionIsSane())
			this.drawSelection();
	},
	clearSelection: function() {
		if (this.prevSelection == null)
			return;

		var x = Math.min(this.prevSelection.first.x, this.prevSelection.second.x),
			y = Math.min(this.prevSelection.first.y, this.prevSelection.second.y),
			w = Math.abs(this.prevSelection.second.x - this.prevSelection.first.x),
			h = Math.abs(this.prevSelection.second.y - this.prevSelection.first.y);
		
		this.overlayContext.clearRect(x + this.chartOffset.left - this.overlayContext.lineWidth,
					   		y + this.chartOffset.top - this.overlayContext.lineWidth,
					   		w + this.overlayContext.lineWidth*2,
					   		h + this.overlayContext.lineWidth*2);
		
		this.prevSelection = null;
	},
	/**
	 * Function: setSelection
	 * 
	 * Parameters:
	 * Area - {Object} area represented as a range like: {x1: 3, y1: 3, x2: 4, y2: 8}
	 * 
	 * Description: 
	 * Sets the current graph selection to the provided range. Calls <drawSelection> and 
	 * <triggerSelectedEvent> functions internally.
	 */
	setSelection: function(area) {
		this.clearSelection();
		
		if (this.options.selection.mode == "x") {
			this.selection.first.y = 0;
			this.selection.second.y = this.chartHeight;
		}
		else {
			this.selection.first.y = (this.yaxis.max - area.y1) * this.vertScale;
			this.selection.second.y = (this.yaxis.max - area.y2) * this.vertScale;
		}
		if (this.options.selection.mode == "y") {
			this.selection.first.x = 0;
			this.selection.second.x = this.chartWidth;
		}
		else {
			this.selection.first.x = (area.x1 - this.xaxis.min) * this.hozScale;
			this.selection.second.x = (area.x2 - this.xaxis.min) * this.hozScale;
		}

		this.drawSelection();
		this.triggerSelectedEvent();
	},
	/**
	 * Function: drawSelection
	 * Description: Internal function called to draw the selection made on the graph. 
	 */
	drawSelection: function() {
		if (this.prevSelection != null &&
			this.selection.first.x == this.prevSelection.first.x &&
			this.selection.first.y == this.prevSelection.first.y && 
			this.selection.second.x == this.prevSelection.second.x &&
			this.selection.second.y == this.prevSelection.second.y)
		{
			return;	
		}
		
		this.overlayContext.strokeStyle = this.parseColor(this.options.selection.color).scale(null, null, null, 0.8).toString();
		this.overlayContext.lineWidth = 1;
		this.context.lineJoin = "round";
		this.overlayContext.fillStyle = this.parseColor(this.options.selection.color).scale(null, null, null, 0.4).toString();

		this.prevSelection = { first:  { x: this.selection.first.x,
									y: this.selection.first.y },
						  second: { x: this.selection.second.x,
									y: this.selection.second.y } };

		var x = Math.min(this.selection.first.x, this.selection.second.x),
			y = Math.min(this.selection.first.y, this.selection.second.y),
			w = Math.abs(this.selection.second.x - this.selection.first.x),
			h = Math.abs(this.selection.second.y - this.selection.first.y);
		
		this.overlayContext.fillRect(x + this.chartOffset.left, y + this.chartOffset.top, w, h);
		this.overlayContext.strokeRect(x + this.chartOffset.left, y + this.chartOffset.top, w, h);
	},
	/**
	 * Internal function
	 */
	selectionIsSane: function() {
		var minSize = 5;
		return Math.abs(this.selection.second.x - this.selection.first.x) >= minSize &&
			Math.abs(this.selection.second.y - this.selection.first.y) >= minSize;
	},
	/**
	 * Internal function that formats the track. This is the format the text is shown when mouse
	 * tracking is enabled.
	 */
	defaultTrackFormatter: function(val)
	{
		return '['+val.x+', '+val.y+']';
	},
	/**
	 * Function: clearHit
	 */
	clearHit: function(){
		if(this.prevHit){
			this.overlayContext.clearRect(
				this.translateHoz(this.prevHit.x) + this.chartOffset.left - this.options.mouse.radius*2,
				this.translateVert(this.prevHit.y) + this.chartOffset.top - this.options.mouse.radius*2,
				this.options.mouse.radius*3 + this.options.points.lineWidth*3, 
				this.options.mouse.radius*3 + this.options.points.lineWidth*3
			);
			this.prevHit = null;
		}		
	},	
	/**
	 * Function: hit
	 * 
	 * Parameters: 
	 * 	event - {Object} event object
	 * 	mouse - {Object} mouse object that is used to keep track of mouse movement
	 * 
	 * Description:
	 * 	If hit occurs this function will fire a ProtoChart:hit event.
	 */
	hit: function(event, mouse){	
		/**
		 * Nearest data element.
		 */
		var n = {
			dist:Number.MAX_VALUE,
			x:null,
			y:null,
			mouse:null
		};
		
		
		for(var i = 0, data, xsens, ysens; i < this.graphData.length; i++){
			if(!this.graphData[i].mouse.track) continue;
			data = this.graphData[i].data;				
			xsens = (this.hozScale*this.graphData[i].mouse.sensibility);
			ysens = (this.vertScale*this.graphData[i].mouse.sensibility);
			for(var j = 0, xabs, yabs; j < data.length; j++){
				xabs = this.hozScale*Math.abs(data[j][0] - mouse.x);
				yabs = this.vertScale*Math.abs(data[j][1] - mouse.y);
				
				if(xabs < xsens && yabs < ysens && (xabs+yabs) < n.dist){
					n.dist = (xabs+yabs);
					n.x = data[j][0];
					n.y = data[j][1];
					n.mouse = this.graphData[i].mouse;
				}
			}
		}
		
		if(n.mouse && n.mouse.track && !this.prevHit || (this.prevHit && n.x != this.prevHit.x && n.y != this.prevHit.y)){
			var el = this.domObj.select('.'+this.options.mouse.clsName).first();
			if(!el){
				var pos = '', p = this.options.mouse.position, m = this.options.mouse.margin;					
				if(p.charAt(0) == 'n') pos += 'top:' + (m + this.chartOffset.top) + 'px;';
				else if(p.charAt(0) == 's') pos += 'bottom:' + (m + this.chartOffset.bottom) + 'px;';					
				if(p.charAt(1) == 'e') pos += 'right:' + (m + this.chartOffset.right) + 'px;';
				else if(p.charAt(1) == 'w') pos += 'left:' + (m + this.chartOffset.bottom) + 'px;';
				
				this.domObj.insert('<div class="'+this.options.mouse.clsName+'" style="display:none;position:absolute;'+pos+'"></div>');
				return;
			}
			if(n.x !== null && n.y !== null){
				el.setStyle({display:'block'});					
				
				this.clearHit();
				if(n.mouse.lineColor != null){
					this.overlayContext.save();
					this.overlayContext.translate(this.chartOffset.left, this.chartOffset.top);
					this.overlayContext.lineWidth = this.options.points.lineWidth;
					this.overlayContext.strokeStyle = n.mouse.lineColor;
					this.overlayContext.fillStyle = '#ffffff';
					this.overlayContext.beginPath();
					
					
					this.overlayContext.arc(this.translateHoz(n.x), this.translateVert(n.y), this.options.mouse.radius, 0, 2 * Math.PI, true);
					this.overlayContext.fill();
					this.overlayContext.stroke();
					this.overlayContext.restore();
				} 
				this.prevHit = n;
								
				var decimals = n.mouse.trackDecimals;
				if(decimals == null || decimals < 0) decimals = 0;
				if(!this.options.mouse.fixedPosition)
				{
					el.setStyle({
						left: (this.translateHoz(n.x) + this.options.mouse.radius + 10) + "px",
						top: (this.translateVert(n.y) + this.options.mouse.radius + 10) + "px"
					});
				}
				el.innerHTML = n.mouse.trackFormatter({x: n.x.toFixed(decimals), y: n.y.toFixed(decimals)});
				this.domObj.fire( 'ProtoChart:hit', [n] )					
			}else if(this.options.prevHit){
				el.setStyle({display:'none'});
				this.clearHit();
			}
		}
	},	
	/**
	 * Internal function
	 */
	floorInBase: function(n, base) {
        return base * Math.floor(n / base);
    },	
	/**
	 * Function: extractColor
	 * 
	 * Parameters:
	 * 		element - HTML element or ID of an HTML element
	 * 
	 * Returns: 
	 * 		color in string format
	 */
	extractColor: function(element)
	{
		var color;
		do
		{
			color = $(element).getStyle('background-color').toLowerCase();
			if(color  != '' && color != 'transparent')
			{
				break;
			}
			element = element.up(0); //or else just get the parent ....
		} while(element.nodeName.toLowerCase() != 'body');
		
		//safari fix
		if(color == 'rgba(0, 0, 0, 0)') 
			return 'transparent';
		return color;
	},
	/**
	 * Function: parseColor
	 * 
	 * Parameters: 
	 * 		str - color string in different formats
	 * 
	 * Returns:
	 * 		a Proto.Color Object - use toString() function to retreive the color in rgba/rgb format
	 */
	parseColor: function(str)
	{
		var result;
	
		/**
		 * rgb(num,num,num)
		 */
		if((result = /rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)/.exec(str)))
			return new Proto.Color(parseInt(result[1]), parseInt(result[2]), parseInt(result[3]));
	
		/**
		 * rgba(num,num,num,num)
		 */
		if((result = /rgba\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]+(?:\.[0-9]+)?)\s*\)/.exec(str)))
			return new Proto.Color(parseInt(result[1]), parseInt(result[2]), parseInt(result[3]), parseFloat(result[4]));
			
		/**
		 * rgb(num%,num%,num%)
		 */
		if((result = /rgb\(\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*\)/.exec(str)))
			return new Proto.Color(parseFloat(result[1])*2.55, parseFloat(result[2])*2.55, parseFloat(result[3])*2.55);
	
		/**
		 * rgba(num%,num%,num%,num)
		 */
		if((result = /rgba\(\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\s*\)/.exec(str)))
			return new Proto.Color(parseFloat(result[1])*2.55, parseFloat(result[2])*2.55, parseFloat(result[3])*2.55, parseFloat(result[4]));
			
		/**
		 * #a0b1c2
		 */
		if((result = /#([a-fA-F0-9]{2})([a-fA-F0-9]{2})([a-fA-F0-9]{2})/.exec(str)))
			return new Proto.Color(parseInt(result[1],16), parseInt(result[2],16), parseInt(result[3],16));
	
		/**
		 * #fff
		 */
		if((result = /#([a-fA-F0-9])([a-fA-F0-9])([a-fA-F0-9])/.exec(str)))
			return new Proto.Color(parseInt(result[1]+result[1],16), parseInt(result[2]+result[2],16), parseInt(result[3]+result[3],16));

		/**
		 * Otherwise, check if user wants transparent .. or we just return a standard color;
		 */
		var name = str.strip().toLowerCase();
		if(name == 'transparent'){
			return new Proto.Color(255, 255, 255, 0);
		}

		return new Proto.Color(100,100,100, 1);
					
	}		
});

if(!Proto) var Proto = {};

/**
 * Class: Proto.Color
 * 
 * Helper class that manipulates colors using RGBA values. 
 * 
 */

Proto.Color = Class.create({
	initialize: function(r, g, b, a) {
		this.rgba = ['r', 'g', 'b', 'a'];
		var x = 4;
		while(-1<--x) {
			this[this.rgba[x]] = arguments[x] || ((x==3) ? 1.0 : 0);
		}
	},
	toString: function()  {
		if(this.a >= 1.0) {
			return "rgb(" + [this.r, this.g, this.b].join(",") +")";
		}
		else {
			return "rgba("+[this.r, this.g, this.b, this.a].join(",")+")";
		}
	},
	scale: function(rf, gf, bf, af) {
		x = 4;
		while(-1<--x) {
			if(arguments[x] != null) {
				this[this.rgba[x]] *= arguments[x];
			}
		}
		return this.normalize();
	},
	adjust: function(rd, gd, bd, ad) {
        x = 4; //rgba.length
        while (-1<--x) {
            if (arguments[x] != null)
                this[this.rgba[x]] += arguments[x];
        }
        return this.normalize();		
	},
	clone: function() {
        return new Proto.Color(this.r, this.b, this.g, this.a);
    },
	limit: function(val,minVal,maxVal) {
        return Math.max(Math.min(val, maxVal), minVal);
    },
    normalize: function() {
        this.r = this.limit(parseInt(this.r), 0, 255);
        this.g = this.limit(parseInt(this.g), 0, 255);
        this.b = this.limit(parseInt(this.b), 0, 255);
        this.a = this.limit(this.a, 0, 1);
        return this;
    }
});