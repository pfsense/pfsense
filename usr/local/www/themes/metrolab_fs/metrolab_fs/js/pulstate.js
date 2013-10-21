var Pulstate = function () {

//    pulstate

    jQuery('#pulsate-regular').pulsate({
        color: "#E74955"
    });

    jQuery('#pulsate-once').click(function () {
        $(this).pulsate({
            color: "#A5D16C",
            repeat: false
        });
    });

    jQuery('#pulsate-hover').pulsate({
        color: "#4A8BC2",
        repeat: false,
        onHover: true
    });

    jQuery('#pulsate-crazy').click(function () {
        $(this).pulsate({
            color: "#FCB322",
            reach: 50,
            repeat: 10,
            speed: 100,
            glow: true
        });
    });


}();