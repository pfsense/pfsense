
function check_upload_status(id) {

	if (document.getElementById("restore").value == 'Restore configuration') {
		window.open('progress.php?conffile=' + id,'UploadMeter','width=370,height=115', true);
		return true;
	} else {
		return false;
	}

}