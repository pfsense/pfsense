var Script = function () {

//  tracking chart

    var plot;
    $(function () {
        var sin = [], cos = [];
        for (var i = 0; i < 14; i += 0.1) {
            sin.push([i, Math.sin(i)]);
            cos.push([i, Math.cos(i)]);
        }

        plot = $.plot($("#chart-1"),
            [ { data: sin, label: "sin(x) = -0.00"},
                { data: cos, label: "cos(x) = -0.00" } ], {
                series: {
                    lines: { show: true }
                },
                crosshair: { mode: "x" },
                grid: { hoverable: true, autoHighlight: false },
                yaxis: { min: -1.2, max: 1.2 }
            });
        var legends = $("#chart-1 .legendLabel");
        legends.each(function () {
            // fix the widths so they don't jump around
            $(this).css('width', $(this).width());
        });

        var updateLegendTimeout = null;
        var latestPosition = null;

        function updateLegend() {
            updateLegendTimeout = null;

            var pos = latestPosition;

            var axes = plot.getAxes();
            if (pos.x < axes.xaxis.min || pos.x > axes.xaxis.max ||
                pos.y < axes.yaxis.min || pos.y > axes.yaxis.max)
                return;

            var i, j, dataset = plot.getData();
            for (i = 0; i < dataset.length; ++i) {
                var series = dataset[i];

                // find the nearest points, x-wise
                for (j = 0; j < series.data.length; ++j)
                    if (series.data[j][0] > pos.x)
                        break;

                // now interpolate
                var y, p1 = series.data[j - 1], p2 = series.data[j];
                if (p1 == null)
                    y = p2[1];
                else if (p2 == null)
                    y = p1[1];
                else
                    y = p1[1] + (p2[1] - p1[1]) * (pos.x - p1[0]) / (p2[0] - p1[0]);

                legends.eq(i).text(series.label.replace(/=.*/, "= " + y.toFixed(2)));
            }
        }

        $("#chart-1").bind("plothover",  function (event, pos, item) {
            latestPosition = pos;
            if (!updateLegendTimeout)
                updateLegendTimeout = setTimeout(updateLegend, 50);
        });
    });

//    selection chart

    $(function () {
        var data = [
            {
                label: "United States",
                data: [[1990, 18.9], [1991, 18.7], [1992, 18.4], [1993, 19.3], [1994, 19.5], [1995, 19.3], [1996, 19.4], [1997, 20.2], [1998, 19.8], [1999, 19.9], [2000, 20.4], [2001, 20.1], [2002, 20.0], [2003, 19.8], [2004, 20.4]]
            },
            {
                label: "Russia",
                data: [[1992, 13.4], [1993, 12.2], [1994, 10.6], [1995, 10.2], [1996, 10.1], [1997, 9.7], [1998, 9.5], [1999, 9.7], [2000, 9.9], [2001, 9.9], [2002, 9.9], [2003, 10.3], [2004, 10.5]]
            },
            {
                label: "United Kingdom",
                data: [[1990, 10.0], [1991, 11.3], [1992, 9.9], [1993, 9.6], [1994, 9.5], [1995, 9.5], [1996, 9.9], [1997, 9.3], [1998, 9.2], [1999, 9.2], [2000, 9.5], [2001, 9.6], [2002, 9.3], [2003, 9.4], [2004, 9.79]]
            },
            {
                label: "Germany",
                data: [[1990, 12.4], [1991, 11.2], [1992, 10.8], [1993, 10.5], [1994, 10.4], [1995, 10.2], [1996, 10.5], [1997, 10.2], [1998, 10.1], [1999, 9.6], [2000, 9.7], [2001, 10.0], [2002, 9.7], [2003, 9.8], [2004, 9.79]]
            },
            {
                label: "Denmark",
                data: [[1990, 9.7], [1991, 12.1], [1992, 10.3], [1993, 11.3], [1994, 11.7], [1995, 10.6], [1996, 12.8], [1997, 10.8], [1998, 10.3], [1999, 9.4], [2000, 8.7], [2001, 9.0], [2002, 8.9], [2003, 10.1], [2004, 9.80]]
            },
            {
                label: "Sweden",
                data: [[1990, 5.8], [1991, 6.0], [1992, 5.9], [1993, 5.5], [1994, 5.7], [1995, 5.3], [1996, 6.1], [1997, 5.4], [1998, 5.4], [1999, 5.1], [2000, 5.2], [2001, 5.4], [2002, 6.2], [2003, 5.9], [2004, 5.89]]
            },
            {
                label: "Norway",
                data: [[1990, 8.3], [1991, 8.3], [1992, 7.8], [1993, 8.3], [1994, 8.4], [1995, 5.9], [1996, 6.4], [1997, 6.7], [1998, 6.9], [1999, 7.6], [2000, 7.4], [2001, 8.1], [2002, 12.5], [2003, 9.9], [2004, 19.0]]
            }
        ];

        var options = {
            series: {
                lines: { show: true },
                points: { show: true }
            },
            legend: { noColumns: 2 },
            xaxis: { tickDecimals: 0 },
            yaxis: { min: 0 },
            selection: { mode: "x" }
        };

        var placeholder = $("#chart-2");

        placeholder.bind("plotselected", function (event, ranges) {
            $("#selection").text(ranges.xaxis.from.toFixed(1) + " to " + ranges.xaxis.to.toFixed(1));

            var zoom = $("#zoom").attr("checked");
            if (zoom)
                plot = $.plot(placeholder, data,
                    $.extend(true, {}, options, {
                        xaxis: { min: ranges.xaxis.from, max: ranges.xaxis.to }
                    }));
        });

        placeholder.bind("plotunselected", function (event) {
            $("#selection").text("");
        });

        var plot = $.plot(placeholder, data, options);

        $("#clearSelection").click(function () {
            plot.clearSelection();
        });

        $("#setSelection").click(function () {
            plot.setSelection({ xaxis: { from: 1994, to: 1995 } });
        });
    });

//    live chart

    $(function () {
        // we use an inline data source in the example, usually data would
        // be fetched from a server
        var data = [], totalPoints = 300;
        function getRandomData() {
            if (data.length > 0)
                data = data.slice(1);

            // do a random walk
            while (data.length < totalPoints) {
                var prev = data.length > 0 ? data[data.length - 1] : 50;
                var y = prev + Math.random() * 10 - 5;
                if (y < 0)
                    y = 0;
                if (y > 100)
                    y = 100;
                data.push(y);
            }

            // zip the generated y values with the x values
            var res = [];
            for (var i = 0; i < data.length; ++i)
                res.push([i, data[i]])
            return res;
        }

        // setup control widget
        var updateInterval = 30;
        $("#updateInterval").val(updateInterval).change(function () {
            var v = $(this).val();
            if (v && !isNaN(+v)) {
                updateInterval = +v;
                if (updateInterval < 1)
                    updateInterval = 1;
                if (updateInterval > 2000)
                    updateInterval = 2000;
                $(this).val("" + updateInterval);
            }
        });

        // setup plot
        var options = {
            series: { shadowSize: 0 }, // drawing is faster without shadows
            yaxis: { min: 0, max: 100 },
            xaxis: { show: false }
        };
        var plot = $.plot($("#chart-3"), [ getRandomData() ], options);

        function update() {
            plot.setData([ getRandomData() ]);
            // since the axes don't change, we don't need to call plot.setupGrid()
            plot.draw();

            setTimeout(update, updateInterval);
        }

        update();
    });
    
//    support chart

    $(function () {
        var d1 = [];
        for (var i = 0; i < 14; i += 0.5)
            d1.push([i, Math.sin(i)]);

        var d2 = [[0, 3], [4, 8], [8, 5], [9, 13]];

        var d3 = [];
        for (var i = 0; i < 14; i += 0.5)
            d3.push([i, Math.cos(i)]);

        var d4 = [];
        for (var i = 0; i < 14; i += 0.1)
            d4.push([i, Math.sqrt(i * 10)]);

        var d5 = [];
        for (var i = 0; i < 14; i += 0.5)
            d5.push([i, Math.sqrt(i)]);

        var d6 = [];
        for (var i = 0; i < 14; i += 0.5 + Math.random())
            d6.push([i, Math.sqrt(2*i + Math.sin(i) + 5)]);

        $.plot($("#chart-4"), [
            {
                data: d1,
                lines: { show: true, fill: true }
            },
            {
                data: d2,
                bars: { show: true }
            },
            {
                data: d3,
                points: { show: true }
            },
            {
                data: d4,
                lines: { show: true }
            },
            {
                data: d5,
                lines: { show: true },
                points: { show: true }
            },
            {
                data: d6,
                lines: { show: true, steps: true }
            }
        ]);
    });

//    bar chart

    $(function () {
        var d1 = [];
        for (var i = 0; i <= 10; i += 1)
            d1.push([i, parseInt(Math.random() * 30)]);

        var d2 = [];
        for (var i = 0; i <= 10; i += 1)
            d2.push([i, parseInt(Math.random() * 30)]);

        var d3 = [];
        for (var i = 0; i <= 10; i += 1)
            d3.push([i, parseInt(Math.random() * 30)]);

        var stack = 0, bars = true, lines = false, steps = false;

        function plotWithOptions() {
            $.plot($("#chart-5"), [ d1, d2, d3 ], {
                series: {
                    stack: stack,
                    lines: { show: lines, fill: true, steps: steps },
                    bars: { show: bars, barWidth: 0.6 }
                }
            });
        }

        plotWithOptions();

        $(".stackControls input").click(function (e) {
            e.preventDefault();
            stack = $(this).val() == "With stacking" ? true : null;
            plotWithOptions();
        });
        $(".graphControls input").click(function (e) {
            e.preventDefault();
            bars = $(this).val().indexOf("Bars") != -1;
            lines = $(this).val().indexOf("Lines") != -1;
            steps = $(this).val().indexOf("steps") != -1;
            plotWithOptions();
        });
    });

//    graph chart


    $(function () {
        // data
        /*var data = [
         { label: "Series1",  data: 10},
         { label: "Series2",  data: 30},
         { label: "Series3",  data: 90},
         { label: "Series4",  data: 70},
         { label: "Series5",  data: 80},
         { label: "Series6",  data: 110}
         ];*/
        /*var data = [
         { label: "Series1",  data: [[1,10]]},
         { label: "Series2",  data: [[1,30]]},
         { label: "Series3",  data: [[1,90]]},
         { label: "Series4",  data: [[1,70]]},
         { label: "Series5",  data: [[1,80]]},
         { label: "Series6",  data: [[1,0]]}
         ];*/
        var data = [];
        var series = Math.floor(Math.random()*10)+1;
        for( var i = 0; i<series; i++)
        {
            data[i] = { label: "Series"+(i+1), data: Math.floor(Math.random()*100)+1 }
        }



        // GRAPH 1
        $.plot($("#graph1"), data,
            {
                series: {
                    pie: {
                        show: true
                    }
                },
                legend: {
                    show: false
                }
            });

        // GRAPH 2
        $.plot($("#graph2"), data,
            {
                series: {
                    pie: {
                        show: true,
                        radius: 1,
                        label: {
                            show: true,
                            radius: 1,
                            formatter: function(label, series){
                                return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'+label+'<br/>'+Math.round(series.percent)+'%</div>';
                            },
                            background: { opacity: 0.8 }
                        }
                    }
                },
                legend: {
                    show: false
                }
            });

        // GRAPH 3
        $.plot($("#graph3"), data,
            {
                series: {
                    pie: {
                        show: true,
                        radius: 1,
                        label: {
                            show: true,
                            radius: 3/4,
                            formatter: function(label, series){
                                return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'+label+'<br/>'+Math.round(series.percent)+'%</div>';
                            },
                            background: { opacity: 0.5 }
                        }
                    }
                },
                legend: {
                    show: false
                }
            });


        // DONUT
        $.plot($("#donut"), data,
            {
                series: {
                    pie: {
                        innerRadius: 0.5,
                        show: true
                    }
                }
            });



    });

    function pieHover(event, pos, obj)
    {
        if (!obj)
            return;
        percent = parseFloat(obj.series.percent).toFixed(2);
        $("#hover").html('<span style="font-weight: bold; color: '+obj.series.color+'">'+obj.series.label+' ('+percent+'%)</span>');
    }

    function pieClick(event, pos, obj)
    {
        if (!obj)
            return;
        percent = parseFloat(obj.series.percent).toFixed(2);
        alert(''+obj.series.label+': '+percent+'%');
    }

    
}();