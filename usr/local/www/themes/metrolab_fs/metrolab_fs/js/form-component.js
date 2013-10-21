var Script = function () {

    //chosen select
    $(".chzn-select").chosen(); $(".chzn-select-deselect").chosen({allow_single_deselect:true});


    //tag input

    function onAddTag(tag) {
        alert("Added a tag: " + tag);
    }
    function onRemoveTag(tag) {
        alert("Removed a tag: " + tag);
    }

    function onChangeTag(input,tag) {
        alert("Changed a tag: " + tag);
    }

    $(function() {

        $('#tags_1').tagsInput({width:'auto'});
        $('#tags_2').tagsInput({
            width: '250',
            onChange: function(elem, elem_tags)
            {
                var languages = ['php','ruby','javascript'];
                $('.tag', elem_tags).each(function()
                {
                    if($(this).text().search(new RegExp('\\b(' + languages.join('|') + ')\\b')) >= 0)
                        $(this).css('background-color', 'yellow');
                });
            }
        });

        // Uncomment this line to see the callback functions in action
        //			$('input.tags').tagsInput({onAddTag:onAddTag,onRemoveTag:onRemoveTag,onChange: onChangeTag});

        // Uncomment this line to see an input with no interface for adding new tags.
        //			$('input.tags').tagsInput({interactive:false});
    });



    //color picker

    $('.cp1').colorpicker({
        format: 'hex'
    });
    $('.cp2').colorpicker();



    //time picker

    $('#timepicker1, #timepicker3').timepicker();

    $('#timepicker2, #timepicker4').timepicker({
        minuteStep: 1,
        template: 'modal',
        showSeconds: true,
        showMeridian: false
    });


    //clock face time picker

    $('#clockface_1').clockface();

    $('#clockface_2').clockface({
        format: 'HH:mm',
        trigger: 'manual'
    });

    $('#clockface_2_toggle-btn').click(function (e) {
        e.stopPropagation();
        $('#clockface_2').clockface('toggle');
    });


    //date picker

    if (top.location != location) {
        top.location.href = document.location.href ;
    }
    $(function(){
        window.prettyPrint && prettyPrint();
        $('#dp1').datepicker({
            format: 'mm-dd-yyyy'
        });
        $('#dp2').datepicker();
        $('#dp3').datepicker();
        $('#dp3').datepicker();
        $('#dpYears').datepicker();
        $('#dpMonths').datepicker();


        var startDate = new Date(2012,1,20);
        var endDate = new Date(2012,1,25);
        $('#dp4').datepicker()
            .on('changeDate', function(ev){
                if (ev.date.valueOf() > endDate.valueOf()){
                    $('#alert').show().find('strong').text('The start date can not be greater then the end date');
                } else {
                    $('#alert').hide();
                    startDate = new Date(ev.date);
                    $('#startDate').text($('#dp4').data('date'));
                }
                $('#dp4').datepicker('hide');
            });
        $('#dp5').datepicker()
            .on('changeDate', function(ev){
                if (ev.date.valueOf() < startDate.valueOf()){
                    $('#alert').show().find('strong').text('The end date can not be less then the start date');
                } else {
                    $('#alert').hide();
                    endDate = new Date(ev.date);
                    $('#endDate').text($('#dp5').data('date'));
                }
                $('#dp5').datepicker('hide');
            });

        // disabling dates
        var nowTemp = new Date();
        var now = new Date(nowTemp.getFullYear(), nowTemp.getMonth(), nowTemp.getDate(), 0, 0, 0, 0);

        var checkin = $('#dpd1').datepicker({
            onRender: function(date) {
                return date.valueOf() < now.valueOf() ? 'disabled' : '';
            }
        }).on('changeDate', function(ev) {
                if (ev.date.valueOf() > checkout.date.valueOf()) {
                    var newDate = new Date(ev.date)
                    newDate.setDate(newDate.getDate() + 1);
                    checkout.setValue(newDate);
                }
                checkin.hide();
                $('#dpd2')[0].focus();
            }).data('datepicker');
        var checkout = $('#dpd2').datepicker({
            onRender: function(date) {
                return date.valueOf() <= checkin.date.valueOf() ? 'disabled' : '';
            }
        }).on('changeDate', function(ev) {
                checkout.hide();
            }).data('datepicker');
    });



    //daterange picker

    $('#reservation').daterangepicker();

    $('#reportrange').daterangepicker(
        {
            ranges: {
                'Today': ['today', 'today'],
                'Yesterday': ['yesterday', 'yesterday'],
                'Last 7 Days': [Date.today().add({ days: -6 }), 'today'],
                'Last 30 Days': [Date.today().add({ days: -29 }), 'today'],
                'This Month': [Date.today().moveToFirstDayOfMonth(), Date.today().moveToLastDayOfMonth()],
                'Last Month': [Date.today().moveToFirstDayOfMonth().add({ months: -1 }), Date.today().moveToFirstDayOfMonth().add({ days: -1 })]
            },
            opens: 'left',
            format: 'MM/dd/yyyy',
            separator: ' to ',
            startDate: Date.today().add({ days: -29 }),
            endDate: Date.today(),
            minDate: '01/01/2012',
            maxDate: '12/31/2013',
            locale: {
                applyLabel: 'Submit',
                fromLabel: 'From',
                toLabel: 'To',
                customRangeLabel: 'Custom Range',
                daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr','Sa'],
                monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                firstDay: 1
            },
            showWeekNumbers: true,
            buttonClasses: ['btn-danger']
        },
        function(start, end) {
            $('#reportrange span').html(start.toString('MMMM d, yyyy') + ' - ' + end.toString('MMMM d, yyyy'));
        }
    );

    //Set the initial state of the picker label
    $('#reportrange span').html(Date.today().add({ days: -29 }).toString('MMMM d, yyyy') + ' - ' + Date.today().toString('MMMM d, yyyy'));


    //toggle button

    window.prettyPrint && prettyPrint();

    $('#normal-toggle-button').toggleButtons();

    $('#text-toggle-button').toggleButtons({
        width: 220,
        label: {
            enabled: "Lorem Ipsum",
            disabled: "Dolor Sit"
        }
    });

    $('#not-animated-toggle-button').toggleButtons({
        animated: false
    });

    $('#transition-percent-toggle-button').toggleButtons({
        transitionspeed: "500%"
    });

    $('#transition-value-toggle-button').toggleButtons({
        transitionspeed: 1 // default value: 0.05
    });

    $('#danger-toggle-button').toggleButtons({
        style: {
            // Accepted values ["primary", "danger", "info", "success", "warning"] or nothing
            enabled: "danger",
            disabled: "info"
        }
    });
    $('#info-toggle-button').toggleButtons({
        style: {
            // Accepted values ["primary", "danger", "info", "success", "warning"] or nothing
            enabled: "info",
            disabled: "info"
        }
    });
    $('#success-toggle-button').toggleButtons({
        style: {
            // Accepted values ["primary", "danger", "info", "success", "warning"] or nothing
            enabled: "success",
            disabled: "info"
        }
    });
    $('#warning-toggle-button').toggleButtons({
        style: {
            // Accepted values ["primary", "danger", "info", "success", "warning"] or nothing
            enabled: "warning",
            disabled: "info"
        }
    });

    $('#height-text-style-toggle-button').toggleButtons({
        height: 100,
        font: {
            'line-height': '100px',
            'font-size': '18px',
            'font-style': 'regular'
        }
    });


    //WYSIWYG Editor

    $('.wysihtmleditor5').wysihtml5();

}();