/*   Most widgets update their backend data every 10 seconds.  11 seconds
 *   will ensure that we update the GUI right after the stats are updated.
 *   Seconds * 1000 = value
 */
var Seconds = 11;
var update_interval = (Math.abs(Math.ceil(Seconds))-1)*1000 + 990;

function updateMeters() {
	url = '/getstats.php';

	jQuery.ajax(url, {
		type: 'get',
		success: function(data) {
			response = data || "";
			if (response != "")
				stats(data);
		}
	});
        setTimer();
}

function setTimer() { 
         timeout = window.setTimeout('updateMeters()', update_interval); 
}

function stats(x) {
	var values = x.split("|");
	if (jQuery.each(values,function(key,value){
		if (value == 'undefined' || value == null)
			return true;
		else
			return false;
	}))

        updateUptime(values[2]);
        updateDateTime(values[5]);
        updateCPU(values[0]);
        updateMemory(values[1]);
        updateState(values[3]);
        updateTemp(values[4]);
        updateInterfaceStats(values[6]);
        updateInterfaces(values[7]);
        updateGatewayStats(values[8]);
        updateCpuFreq(values[9]);
        updateLoadAverage(values[10]);
        updateMbuf(values[11]);
        updateMbufMeter(values[12]);
        updateStateMeter(values[13]);
}

function updateMemory(x) {
	if(jQuery('#memusagemeter'))
		jQuery("#memusagemeter").html(x + '%');
	if(jQuery('#memwidtha'))
		jQuery("#memwidtha").css('width',x + 'px');
	if(jQuery('#memwidthb'))
		jQuery("#memwidthb").css('width', (100 - x) + 'px');
}

function updateMbuf(x) {
	if(jQuery('#mbuf'))
		jQuery("#mbuf").html(x);
}

function updateMbufMeter(x) {
	if(jQuery('#mbufusagemeter'))
		jQuery("#mbufusagemeter").html(x + '%');
	if(jQuery('#mbufwidtha'))
		jQuery("#mbufwidtha").css('width',x + 'px');
	if(jQuery('#mbufwidthb'))
		jQuery("#mbufwidthb").css('width', (100 - x) + 'px');
}

function updateCPU(x) {
	if(jQuery('#cpumeter'))
		jQuery("#cpumeter").html(x + '%');
	if(jQuery('#cpuwidtha'))
		jQuery("#cpuwidtha").css('width',x + 'px');
	if(jQuery('#cpuwidthb'))
		jQuery("#cpuwidthb").css('width',(100 - x) + 'px');
	/* Load CPU Graph widget if enabled */
	if(widgetActive('cpu_graphs')) {
		GraphValue(graph[0], x);
	}
}

function updateTemp(x) {
	if(jQuery("#tempmeter"))
		jQuery("#tempmeter").html(x + '\u00B0' + 'C');
        if(jQuery('#tempwidtha'))
		jQuery("#tempwidtha").css('width',x + 'px');
        if(jQuery('#tempwidthb'))
		jQuery("#tempwidthb").css('width',(100 - x) + 'px');
}

function updateDateTime(x) {
	if(jQuery('#datetime'))
		jQuery("#datetime").html(x);
}

function updateUptime(x) {
	if(jQuery('#uptime'))
		jQuery("#uptime").html(x);
}

function updateState(x) {
	if(jQuery('#pfstate'))
		jQuery("#pfstate").html(x);
}

function updateStateMeter(x) {
	if(jQuery('#pfstateusagemeter'))
		jQuery("#pfstateusagemeter").html(x + '%');
	if(jQuery('#pfstatewidtha'))
		jQuery("#pfstatewidtha").css('width',x + 'px');
	if(jQuery('#pfstatewidthb'))
		jQuery("#pfstatewidthb").css('width',(100 - x) + 'px');
}

function updateGatewayStats(x){
	if (widgetActive("gateways")){
		gateways_split = x.split(",");
		for (var y=0; y<gateways_split.length; y++){
			if(jQuery('#gateway' + (y + 1))) {
				jQuery('#gateway' + (y + 1)).html(gateways_split[y]);
			}
		}
	}
}

function updateCpuFreq(x) {
	if(jQuery('#cpufreq'))
		jQuery("#cpufreq").html(x);
}

function updateLoadAverage(x) {
	if(jQuery('#load_average'))
		jQuery("#load_average").html(x);
}

function updateInterfaceStats(x){
	if (widgetActive("interface_statistics")){
		statistics_split = x.split(",");
		var counter = 1;
		for (var y=0; y<statistics_split.length-1; y++){
			if(jQuery('#stat' + counter)) {
				jQuery('#stat' + counter).html(statistics_split[y]);
				counter++;	
			}
		}
	}
}

function updateInterfaces(x){
	if (widgetActive("interfaces")){
		interfaces_split = x.split("~");
		interfaces_split.each(function(iface){
			details = iface.split(",");
			switch(details[1]) {
				case "up":
					jQuery('#' + details[0] + '-up').css("display","inline");
					jQuery('#' + details[0] + '-down').css("display","none");
					jQuery('#' + details[0] + '-block').css("display","none");
					jQuery('#' + details[0] + '-ip').html(details[2]);
					jQuery('#' + details[0] + '-media').html(details[3]);
					break;
				case "down":
					jQuery('#' + details[0] + '-down').css("display","inline");
					jQuery('#' + details[0] + '-up').css("display","none");
					jQuery('#' + details[0] + '-block').css("display","none");
					jQuery('#' + details[0] + '-ip').html(details[2]);
					jQuery('#' + details[0] + '-media').html(details[3]);
					break;
				case "block":
						jQuery('#' + details[0] + '-block').css("display","inline");
						jQuery('#' + details[0] + '-down').css("display","none");
						jQuery('#' + details[0] + '-up').css("display","none");
					break;
			}
		});
	}
}

function widgetActive(x) {
	var widget = jQuery('#' + x + '-container');
	if ((widget != null) && (widget.css('display') != null) && (widget.css('display') != "none"))
		return true;
	else
		return false;
}

/* start updater */
jQuery(document).ready(function(){
	setTimer();
});
