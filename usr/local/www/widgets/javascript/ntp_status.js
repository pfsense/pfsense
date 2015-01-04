function ntpWidgetUpdateFromServer(){
	$.ajax({
		type: 'GET',
		url: '/widgets/widgets/ntp_status.widget.php',
		data: 'updateme=yes',
		dataType: 'html',
		success: function(data){
			$('#ntp_status_widget').html(data);
		}
	});
}

function ntpWidgetUpdateDisplay(){
	// Javascript handles overflowing
	ntpServerTime.setSeconds(ntpServerTime.getSeconds()+1);

	$('#ntpStatusClock').html(ntpServerTime.toString());
}

$(document).ready(function(){
	setInterval('ntpWidgetUpdateFromServer()', 60*1000);
	setInterval('ntpWidgetUpdateDisplay()', 1000);
});