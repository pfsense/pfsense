function systemStatusGetUpdateStatus() {
	jQuery.ajax(
		'/widgets/widgets/system_information.widget.php',
		{
			type: 'get',
			data: 'getupdatestatus=yes',
			complete: function(transport) {
				jQuery('#updatestatus').html(transport.responseText);
			}
		}
	);
}

setTimeout('systemStatusGetUpdateStatus()', 4000);