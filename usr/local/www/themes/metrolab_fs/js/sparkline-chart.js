var Script = function () {

//sparkline chart

    $("table.chart-line").each(function () {
        var colors = [];
        $("table.chart-line thead th:not(:first)").each(function () {
            colors.push($(this).css("color"));
        });
        $(this).graphTable({
            series:'columns',
            position:'replace',
            width:'100%',
            height:'205px',
            colors:colors,
            legend:false,
            marginLeft:"40px"
        }, {
            legend:{
                position:"ne"
            },
            series:{
                lines:{ show:true },
                points:{ show:true }
            }
        })
    });

    $("table.chart-bar").each(function () {
        var colors = [];
        $("table.chart-line thead th:not(:first)").each(function () {
            colors.push($(this).css("color"));
        });
        $(this).graphTable(
            {
                series:'columns',
                position:'replace',
                width:'100%',
                height:'205px',
                colors:colors,
                legend:false,
                marginLeft:"40px"
            }, {
                series:{
                    lines: {
                        show: false,
                        lineWidth: 2
                    },
                    points: {show: false},
                    shadowSize: 2,
                    bars:{
                        show:true,
                        lineWidth:1,
                        barWidth:0.8,
                        fill:true,
                        align:"left",
                        multiplebars:false
                    }
                },
                grid: {
                    hoverable: false,
                    show: true,
                    borderWidth: 0,
                    labelMargin: 12
                },

                legend: {
                    show: false,
                    position:"ne"
                }
            });
    });

    /* spark line start */
    $("#metro-sparkline-type1").sparkline(
        [5, 6, 7, 9, 9, 5, 3, 2, 2, 4, 6, 7, 5, 6, 7, 9, 9, 5, 3, 2, 2, 4, 6, 7, 5, 6, 7, 9, 9, 5, 3, 2, 2, 4, 6, 7],
        {
            type:"line",
            height:60,
            lineColor: '#457bb2',
            fillColor: '#dff1ff',
            spotColor: '#de577b',
            minSpotColor: '#de577b',
            highlightLineColor: '#de577b'
        });
    $("#metro-sparkline-type2").sparkline(
        [5, 6, 7, 2, 0, -4, -2, 4, 5, 6, 7, 2, 0, -4, -2, 4 ],
        {
            type:"bar",
            height:60,
            barColor: '#4f8ac7',
            negBarColor: '#da587c'
        });
    $("#metro-sparkline-type3").sparkline(
        [5, 6, 7, 9, 9, 5, 3, 2, 2, 4, 6, 7, 5, 6, 7, 9, 5, 3, 2, 2, 4, 6, 7, 5, 6, 7, 9, 9, 5, 3],
        {
            type:"discrete",
            height:60,
            lineColor: '#7e9d43'
        });
    /* spark line end */


}();