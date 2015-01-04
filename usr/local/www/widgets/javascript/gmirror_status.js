function gmirrorStatusUpdateFromServer(){
	$.ajax({
		type: 'GET',
		url: '/widgets/widgets/gmirror_status.widget.php',
		dataType: 'html',
		success: function(data){
			$('#gmirror_status').html(data);
		}
	});
}

$(document).ready(function(){
	setInterval('gmirrorStatusUpdateFromServer()', 60*1000);
});