<?php
/*
	traffic_graphs.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2007 Scott Dale
 *	Copyright (c)  2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
 *	and Jonathan Watt <jwatt@jwatt.org>.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

/* TODOs */
//re-use on Status > traffic graphs
//figure out why there is a missing datapoint at the start
//name things/variables better
//apply css change to Status > Monitoring
//show interface name and latest in/out in upper left
//add stacked overall graph?
    //also show pie graph of lastest precentages of total? (split 50/50 on width)
    //make this an option?

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");
require_once("functions.inc");

$ifdescrs = get_configured_interface_with_descr();

if (ipsec_enabled()) {
	$ifdescrs['enc0'] = "IPsec";
}

//there are no traffic graph widget defaults in config yet. so set them, but don't write the config
if (!is_array($config["widgets"]["trafficgraphs"])) {

	$config["widgets"]["trafficgraphs"] = array();
	$config["widgets"]["trafficgraphs"]["refreshinterval"] = 1;
	$config["widgets"]["trafficgraphs"]["invert"] = "true";
	$config["widgets"]["trafficgraphs"]["size"] = 1;
	$config["widgets"]["trafficgraphs"]["shown"] = array();
	$config["widgets"]["trafficgraphs"]["shown"]["item"] = array();

	foreach($ifdescrs as $ifname => $ifdescr) {

		$ifinfo = get_interface_info($ifname);

		if ($ifinfo['status'] != "down") {
			$config["widgets"]["trafficgraphs"]["shown"]["item"][] = $ifname; 
		}

	}

	//TODO silently write to config? (use a config message about saving defaults)

}

if(!isset($config["widgets"]["trafficgraphs"]["size"])) {
	$config["widgets"]["trafficgraphs"]["size"] = 1;
}

if(!isset($config["widgets"]["trafficgraphs"]["invert"])) {
	$config["widgets"]["trafficgraphs"]["invert"] = "true";
}

$a_config = &$config["widgets"]["trafficgraphs"];

// save new default config options that have been submitted
if ($_POST) {

	//TODO validate data and throw error
	$a_config["shown"]["item"] = $_POST["traffic-graph-interfaces"];

	// TODO check if between 1 and 10
	if (isset($_POST["traffic-graph-interval"]) && is_numericint($_POST["traffic-graph-interval"])) {

		$a_config["refreshinterval"] = $_POST["traffic-graph-interval"];

	} else {

		die('{ "error" : "Refresh Interval is not a valid number between 1 and 10." }');

	}

	if($_POST["traffic-graph-invert"] === "true" || $_POST["traffic-graph-invert"] === "false") {

		$a_config["invert"] = $_POST["traffic-graph-invert"];

	} else {

		die('{ "error" : "Invert is not a boolean of true or false." }');

	}

	//TODO validate data and throw error
	$a_config["size"] = $_POST["traffic-graph-size"];

	write_config(gettext("Updated traffic graph settings via dashboard."));

	header('Content-Type: application/json');

	die('{ "success" : "The changes have been applied successfully." }');

}

$refreshinterval = $a_config["refreshinterval"];

$ifsarray = [];

foreach ($a_config["shown"]["item"] as $ifname) {

	$ifinfo = get_interface_info($ifname);

	if ($ifinfo['status'] != "down") {
		$ifsarray[] = $ifname; 
	} else {
		//TODO throw error?
	}

}

$allifs = implode("|", $ifsarray);

?>
	<script src="/vendor/d3/d3.min.js"></script>
	<script src="/vendor/nvd3/nv.d3.js"></script>
	<script src="/vendor/visibility/visibility-1.2.3.min.js"></script>

	<link href="/vendor/nvd3/nv.d3.css" media="screen, projection" rel="stylesheet" type="text/css">

	<div id="traffic-chart-error" class="alert alert-danger" style="display: none;"></div>

	<?php
	foreach($a_config["shown"]["item"] as $ifname) {
		echo '<div id="traffic-chart-' . $ifname . '" class="d3-chart traffic-widget-chart">';
		echo '	<svg></svg>';
		echo '</div>';
	}
	?>

	<script type="text/javascript">

//<![CDATA[
events.push(function() {

	var InterfaceString = "<?=$allifs?>";

	//store saved settings in a fresh localstorage
	localStorage.clear();
	localStorage.setItem('interfaces', JSON.stringify(InterfaceString.split("|"))); //TODO see if can be switched to interfaces
	localStorage.setItem('interval', <?=$refreshinterval?>);
	localStorage.setItem('invert', <?=$a_config["invert"]?>);
	localStorage.setItem('size', <?=$a_config["size"]?>);

	window.charts = {};
    window.myData = {};
    window.updateIds = 0;
    window.latest = [];
    var refreshInterval = localStorage.getItem('interval');

    //TODO make it fall on a second value so it increments better
    var now = then = new Date(Date.now());

    var nowTime = now.getTime();

	$.each( JSON.parse(localStorage.getItem('interfaces')), function( key, value ) {

		myData[value] = [];
		updateIds = 0;
		
		var itemIn = new Object();
		var itemOut = new Object();

		itemIn.key = value + " (in)";
		if(localStorage.getItem('invert') === "true") { itemIn.area = true; }
		itemIn.first = true;
		itemIn.values = [{x: nowTime, y: 0}];
		myData[value].push(itemIn);

		itemOut.key = value + " (out)";
		if(localStorage.getItem('invert') === "true") { itemOut.area = true; }
		itemOut.first = true;
		itemOut.values = [{x: nowTime, y: 0}];
		myData[value].push(itemOut);

	});

	draw_graph(refreshInterval, then);

	//re-draw graph when the page goes from inactive (in it's window) to active
	Visibility.change(function (e, state) {
		if(state === "visible") {

			now = then = new Date(Date.now());

			var nowTime = now.getTime();

			$.each( JSON.parse(localStorage.getItem('interfaces')), function( key, value ) {

				Visibility.stop(updateIds);

				myData[value] = [];
				
				var itemIn = new Object();
				var itemOut = new Object();

				itemIn.key = value + " (in)";
				if(localStorage.getItem('invert') === "true") { itemIn.area = true; }
				itemIn.first = true;
				itemIn.values = [{x: nowTime, y: 0}];
				myData[value].push(itemIn);

				itemOut.key = value + " (out)";
				if(localStorage.getItem('invert') === "true") { itemOut.area = true; }
				itemOut.first = true;
				itemOut.values = [{x: nowTime, y: 0}];
				myData[value].push(itemOut);

			});

			draw_graph(refreshInterval, then);

		}
	});

	// save new config defaults
    $( '#traffic-graph-form' ).submit(function(event) {

    	var error = false;
    	$("#traffic-chart-error").hide();

    	var interfaces = $( "#traffic-graph-interfaces" ).val(); 
    	refreshInterval = parseInt($( "#traffic-graph-interval" ).val());
    	var invert = $( "#traffic-graph-invert" ).val();
    	var size = $( "#traffic-graph-size" ).val();

    	//TODO validate interfaces data and throw error

    	if(!Number.isInteger(refreshInterval) || refreshInterval < 1 || refreshInterval > 10) {
    		error = 'Refresh Interval is not a valid number between 1 and 10.';
    	}

    	if(invert != "true" && invert != "false") {

    		error = 'Invert is not a boolean of true or false.';

    	} 

    	if(!error) {

			var formData = {
				'traffic-graph-interfaces' : interfaces,
				'traffic-graph-interval'   : refreshInterval,
				'traffic-graph-invert'     : invert,
				'traffic-graph-size'       : size
			};

			$.ajax({
				type        : 'POST',
				url         : '/widgets/widgets/traffic_graphs.widget.php',
				data        : formData,
				dataType    : 'json',
				encode      : true
			})
			.done(function(message) {

				if(message.success) {

					Visibility.stop(updateIds);

					//remove all old graphs (divs/svgs)
					$( ".traffic-widget-chart" ).remove();

					localStorage.setItem('interfaces', JSON.stringify(interfaces));
					localStorage.setItem('interval', refreshInterval);
					localStorage.setItem('invert', invert);
					localStorage.setItem('size', size);

					//redraw graph with new settings
					now = then = new Date(Date.now());

					var freshData = [];

					var nowTime = now.getTime();

					$.each( interfaces, function( key, value ) {

						//create new graphs (divs/svgs)
						$("#widget-traffic_graphs_panel-body").append('<div id="traffic-chart-' + value + '" class="d3-chart traffic-widget-chart"><svg></svg></div>');

						myData[value] = [];
						
						var itemIn = new Object();
						var itemOut = new Object();

						itemIn.key = value + " (in)";
						if(localStorage.getItem('invert') === "true") { itemIn.area = true; }
						itemIn.first = true;
						itemIn.values = [{x: nowTime, y: 0}];
						myData[value].push(itemIn);

						itemOut.key = value + " (out)";
						if(localStorage.getItem('invert') === "true") { itemOut.area = true; }
						itemOut.first = true;
						itemOut.values = [{x: nowTime, y: 0}];
						myData[value].push(itemOut);

					});

					draw_graph(refreshInterval, then);

					$( "#traffic-graph-message" ).removeClass("text-danger").addClass("text-success");
					$( "#traffic-graph-message" ).text(message.success);

					setTimeout(function() {
						$( "#traffic-graph-message" ).empty();
						$( "#traffic-graph-message" ).removeClass("text-success");
					}, 5000);

	        	} else {

					$( "#traffic-graph-message" ).addClass("text-danger");
					$( "#traffic-graph-message" ).text(message.error);

	        		console.warn(message.error);

	        	}

	        })
	        .fail(function() {

			    console.warn( "The Traffic Graphs widget AJAX request failed." );

			});

	    } else {

	    	$( "#traffic-graph-message" ).addClass("text-danger");
			$( "#traffic-graph-message" ).text(error);

    		console.warn(error);

	    }

        event.preventDefault();
    });

});
//]]>
</script>

<script src="/js/traffic-graphs.js"></script>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>

<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

	<form id="traffic-graph-form" action="/widgets/widgets/traffic_graphs.widget.php" method="post" class="form-horizontal">
		<div class="form-group">
			<label for="traffic-graph-interfaces" class="col-sm-3 control-label"><?=gettext('Show graphs')?></label>
			<div class="col-sm-9">
				<select name="traffic-graph-interfaces[]" id="traffic-graph-interfaces" multiple>
				<?php
				foreach ($ifdescrs as $ifname => $ifdescr) {

					$if_shown = "";
					if (in_array($ifname, $a_config["shown"]["item"])) { $if_shown = " selected"; };
					echo '<option value="' . $ifname . '"' . $if_shown . '>' . $ifdescr . "</option>\n";

				}
				?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="traffic-graph-interval" class="col-sm-3 control-label"><?=gettext('Refresh Interval')?></label>
			<div class="col-sm-9">
				<input type="number" id="traffic-graph-interval" name="traffic-graph-interval" value="<?=$refreshinterval?>" min="1" max="10" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label for="traffic-graph-invert" class="col-sm-3 control-label"><?=gettext('Inverse')?></label>
			<div class="col-sm-9">
				<select class="form-control" id="traffic-graph-invert" name="traffic-graph-invert">
				<?php
					if($a_config["invert"] === "true") {
						echo '<option value="true" selected>On</option>';
						echo '<option value="false">Off</option>';
					} else {
						echo '<option value="true">On</option>';
						echo '<option value="false" selected>Off</option>';
					}
				?>	
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="traffic-graph-size" class="col-sm-3 control-label"><?=gettext('Unit Size')?></label>
			<div class="col-sm-9">
				<select class="form-control" id="traffic-graph-size" name="traffic-graph-size">
				<?php
					if($a_config["size"] === "8") {
						echo '<option value="8" selected>Bits</option>';
						echo '<option value="1">Bytes</option>';
					} else {
						echo '<option value="8">Bits</option>';
						echo '<option value="1" selected>Bytes</option>';
					}
				?>	
				</select>
			</div>
		</div>

		<div class="form-group">
			<div class="col-sm-3 text-right">
				<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			</div>
			<div class="col-sm-9">
				<div id="traffic-graph-message"></div>
			</div>
		</div>
	</form>
