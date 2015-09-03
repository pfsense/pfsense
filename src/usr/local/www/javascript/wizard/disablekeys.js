function kH(e) {
	var pK = document.all? window.event.keyCode:e.which;
	return pK != 13;
}
document.onkeypress = kH;
if (document.layers) document.captureEvents(Event.KEYPRESS);