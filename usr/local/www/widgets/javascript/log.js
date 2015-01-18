function logWidgetUpdateFromServer(){
	$.ajax({
		type: 'GET',
		url: '/widgets/widgets/log.widget.php',
		data: 'lastsawtime='+logWidgetLastRefresh,
		dataType: 'html',
		success: function(data){
			$('#widget-log .panel-body').html(data);
		}
	});
}

$(document).ready(function(){
	setInterval('logWidgetUpdateFromServer()', 60*1000);
});