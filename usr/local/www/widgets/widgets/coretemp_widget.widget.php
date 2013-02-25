<?php
/*
  $Id: coretemp_widget.widget.php 
	Descr: 	
		Core Temperatures Widget.
	File location: 
		\usr\local\www\widgets\widgets\
	Depends on:
		\usr\local\www\widgets\javascript\coretemp_widget.js
		\usr\local\www\widgets\include\coretemp_widget.php
		\usr\local\www\widgets\include\coretemp_widget.inc

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

$nocsrf = true;

require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/coretemp_widget.inc");


$coretemp_widget_config_changed = false;
//save widget config settings if POSTed
if (isset($_POST['coretemp_widget_zoneswarningtempthreshold'])) {
	$config['widgets']['coretemp_widget_zoneswarningtempthreshold'] = $_POST['coretemp_widget_zoneswarningtempthreshold'];
	$coretemp_widget_config_changed = true;
}

if (isset($_POST['coretemp_widget_zonescriticaltempthreshold'])) {
	$config['widgets']['coretemp_widget_zonescriticaltempthreshold'] = $_POST['coretemp_widget_zonescriticaltempthreshold'];
	$coretemp_widget_config_changed = true;
}

if (isset($_POST['coretemp_widget_coreswarningtempthreshold'])) {
	$config['widgets']['coretemp_widget_coreswarningtempthreshold'] = $_POST['coretemp_widget_coreswarningtempthreshold'];
	$coretemp_widget_config_changed = true;
}

if (isset($_POST['coretemp_widget_corescriticaltempthreshold'])) {
	$config['widgets']['coretemp_widget_corescriticaltempthreshold'] = $_POST['coretemp_widget_corescriticaltempthreshold'];
	$coretemp_widget_config_changed = true;
}

if ($_POST) {//handle checkboxes separately
	$config['widgets']['coretemp_widget_showrawoutput'] = isset($_POST['coretemp_widget_showrawoutput']) ? 1 : 0;
	$config['widgets']['coretemp_widget_showfullsensorname'] = isset($_POST['coretemp_widget_showfullsensorname']) ? 1 : 0;
	
	$config['widgets']['coretemp_widget_pulsatewarning'] = isset($_POST['coretemp_widget_pulsatewarning']) ? 1 : 0;
	$config['widgets']['coretemp_widget_pulsatecritical'] = isset($_POST['coretemp_widget_pulsatecritical']) ? 1 : 0;
	
	$coretemp_widget_config_changed = true;
}

if ($coretemp_widget_config_changed) {
	write_config("Saved coretemp_widget settings via Dashboard.");
	header("Location: ../../index.php");
}

//set default Thresholds
$coretemp_widget_zoneWarningTempThreshold = 60;//default 60 C
$coretemp_widget_zoneCriticalTempThreshold = 70;//default 70 C
$coretemp_widget_coreWarningTempThreshold = 60;//default 60 C
$coretemp_widget_coreCriticalTempThreshold = 70;//default 70 C

$coretemp_widget_showRawOutput = false;
$coretemp_widget_showFullSensorName = false;
$coretemp_widget_pulsateWarning = true;
$coretemp_widget_pulsateCritical = true;

//get Threshold settings from config (apply defaults if missing)
if (isset($config['widgets']['coretemp_widget_zoneswarningtempthreshold'])){
	$coretemp_widget_zoneWarningTempThreshold = (int) $config['widgets']['coretemp_widget_zoneswarningtempthreshold'];
	if ($coretemp_widget_zoneWarningTempThreshold == 0){
		$coretemp_widget_zoneWarningTempThreshold = 60; //default 60 C
	}
}

if (isset($config['widgets']['coretemp_widget_zonescriticaltempthreshold'])){
	$coretemp_widget_zoneCriticalTempThreshold = (int) $config['widgets']['coretemp_widget_zonescriticaltempthreshold'];
	if ($coretemp_widget_zoneCriticalTempThreshold == 0){
		$coretemp_widget_zoneCriticalTempThreshold = 70; //default 70 C
	}
}

if (isset($config['widgets']['coretemp_widget_coreswarningtempthreshold'])){
	$coretemp_widget_coreWarningTempThreshold = (int) $config['widgets']['coretemp_widget_coreswarningtempthreshold'];
	if ($coretemp_widget_coreWarningTempThreshold == 0){
		$coretemp_widget_coreWarningTempThreshold = 60; //default 60 C
	}
}

if (isset($config['widgets']['coretemp_widget_corescriticaltempthreshold'])){
	$coretemp_widget_coreCriticalTempThreshold = (int) $config['widgets']['coretemp_widget_corescriticaltempthreshold'];
	if ($coretemp_widget_coreCriticalTempThreshold == 0){
		$coretemp_widget_coreCriticalTempThreshold = 70; //default 70 C
	}
}

if (isset($config['widgets']['coretemp_widget_showrawoutput'])){
	$coretemp_widget_showRawOutput = (bool) $config['widgets']['coretemp_widget_showrawoutput'];
}

if (isset($config['widgets']['coretemp_widget_showfullsensorname'])){
	$coretemp_widget_showFullSensorName = (bool) $config['widgets']['coretemp_widget_showfullsensorname'];
}

if (isset($config['widgets']['coretemp_widget_pulsatewarning'])){
	$coretemp_widget_pulsateWarning = (bool) $config['widgets']['coretemp_widget_pulsatewarning'];
}

if (isset($config['widgets']['coretemp_widget_pulsatecritical'])){
	$coretemp_widget_pulsateCritical = (bool) $config['widgets']['coretemp_widget_pulsatecritical'];
}

?>

<script language="javascript" type="text/javascript">
	//set Thresholds. to be used in coretemp_widget.js 
	var coretemp_widget_zoneWarningTempThreshold = <?= $coretemp_widget_zoneWarningTempThreshold; ?>;
	var coretemp_widget_zoneCriticalTempThreshold = <?= $coretemp_widget_zoneCriticalTempThreshold; ?>;
	var coretemp_widget_coreWarningTempThreshold = <?= $coretemp_widget_coreWarningTempThreshold; ?>;
	var coretemp_widget_coreCriticalTempThreshold = <?= $coretemp_widget_coreCriticalTempThreshold; ?>;
	
	var coretemp_widget_showRawOutput = <?= $coretemp_widget_showRawOutput ? "true" : "false"; ?>;
	var coretemp_widget_showFullSensorName = <?= $coretemp_widget_showFullSensorName ? "true" : "false"; ?>;
	
	var coretemp_widget_pulsateWarning = <?= $coretemp_widget_pulsateWarning ? "true" : "false"; ?>;
	var coretemp_widget_pulsateCritical = <?= $coretemp_widget_pulsateCritical ? "true" : "false"; ?>;
	
	//start showing temp data
	//NOTE: the refresh interval will be reset to a proper value in showCoreTempData() (coretemp_widget.js).

	//TODO: jQuery way for pfSense v2.1 and up
	//jQuery(document).ready(function(){
	//	showCoreTempData();
	//});
	
	//Prototype way for pfSense up to v2.0.2 (inclusive)
	document.observe('dom:loaded', function(){
		showCoreTempData();
	});

</script>

<style type="text/css">
/*coretemp widget styles*/
.coreTempRow		{ width: 100%; border: 0px solid #ddd; padding: 1px; border-radius: 3px; }
.coreTempBarShell	{ position: relative; width: 100%; height: 5px; border: 1px solid lightgray; border-radius: 3px; }
.coreTempBar		{ position: absolute; width:   0%; height: 1px; z-index: 1; border-style: solid; border-radius: 3px; 
						background-color: LimeGreen; 
						border-top-width: 2px; border-top-color: Lime; 
						border-left-width: 0px;   
						border-right-width: 0px; 
						border-bottom-width: 2px; border-bottom-color: Green; 
					}
.coreTempTextShell	{ height: 20px; width: 100%; top: 3px; }
.coreTempText		{ float: left; height: 20px; top: 3px; }
.coreTempValue		{ float: left; height: 20px; top: 3px; font-weight: bold; margin-left: 10px;}

.coreTempWarnThresh	{ position: absolute; background-color: orange; height: 16px; width: 2px; z-index: 2; margin-top: -8px; }
.coreTempCritThresh	{ position: absolute; background-color:    red; height: 16px; width: 2px; z-index: 2; margin-top: -8px; }

.coretemp_widget_scale000 { position: absolute; height: 5px; width: 1px; left: -1px; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale020 { position: absolute; height: 3px; width: 1px; left:  10%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale010 { position: absolute; height: 3px; width: 1px; left:  20%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale030 { position: absolute; height: 3px; width: 1px; left:  30%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale040 { position: absolute; height: 3px; width: 1px; left:  40%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale050 { position: absolute; height: 3px; width: 1px; left:  50%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale060 { position: absolute; height: 3px; width: 1px; left:  60%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale070 { position: absolute; height: 3px; width: 1px; left:  70%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale080 { position: absolute; height: 3px; width: 1px; left:  80%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale090 { position: absolute; height: 3px; width: 1px; left:  90%; margin-top: -4px; background-color: lightgray; z-index: 0; font-size: 0; }
.coretemp_widget_scale100 { position: absolute; height: 9px; width: 1px; left: 100%; margin-top: -6px; background-color: lightgray; z-index: 0; font-size: 0; }

.coretemp_widget_mark100 { position: absolute; width: 35px; left: 96%; margin-top: -12px; font: 6pt Arial, sans-serif; text-align: left; color: #575757; }

</style>


<input type="hidden" id="coretemp_widget-config" name="coretemp_widget-config" value="">
<div id="coretemp_widget-settings" name="coretemp_widget-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/coretemp_widget.widget.php" method="post" name="iform_coretemp_widget_settings">
		<table width="100%" border="0">
			<tr>
				<td align="right" colspan="1">
					<span style="font-weight: bold" >Thresholds in &deg;C: </span>
				</td>
				<td>
				</td>
				<td align="right" colspan="1">
					<span style="font-weight: bold" >Display settings:</span>
				</td>
				<td>
				</td>
			</tr>
			<tr>
				<td align="right">
					Zone Warning: 
				</td>
				<td>
					<input type="text" maxlength="3" size="3" class="formfld unknown" 
						name="coretemp_widget_zoneswarningtempthreshold" 
						id="coretemp_widget_zoneswarningtempthreshold" 
						value="<?= $coretemp_widget_zoneWarningTempThreshold; ?>" />
				</td>
				<td align="right">
					Show raw output (no graph): 
				</td>
				<td>
					<input type="checkbox" 
						id="coretemp_widget_showrawoutput" 
						name="coretemp_widget_showrawoutput" 
						value="<?= $coretemp_widget_showRawOutput; ?>" <?= ($coretemp_widget_showRawOutput) ? " checked='checked'" : ""; ?> />
				</td>
			</tr>
			<tr>
				<td align="right">
					Zone Critical: 
				</td>
				<td>
					<input type="text" maxlength="3" size="3" class="formfld unknown" 
						name="coretemp_widget_zonescriticaltempthreshold" 
						id="coretemp_widget_zonescriticaltempthreshold" 
						value="<?= $coretemp_widget_zoneCriticalTempThreshold; ?>" />
				</td>
				<td align="right">
					Show full sensor name: 
				</td>
				<td>
					<input type="checkbox" 
						id="coretemp_widget_showfullsensorname" 
						name="coretemp_widget_showfullsensorname" 
						value="<?= $coretemp_widget_showFullSensorName; ?>" <?= ($coretemp_widget_showFullSensorName) ? " checked='checked'" : ""; ?> />
				</td>
			</tr>
			<tr>
				<td align="right">
					Core Warning: 
				</td>
				<td>
					<input type="text" maxlength="3" size="3" class="formfld unknown" 
						name="coretemp_widget_coreswarningtempthreshold" 
						id="coretemp_widget_coreswarningtempthreshold" 
						value="<?= $coretemp_widget_coreWarningTempThreshold ?>" />
				</td>
				<td align="right">
					Pulsate Warning:
				</td>
				<td>
					<input type="checkbox" 
						id="coretemp_widget_pulsatewarning" 
						name="coretemp_widget_pulsatewarning" 
						value="<?= $coretemp_widget_pulsateWarning; ?>" <?= ($coretemp_widget_pulsateWarning) ? " checked='checked'" : ""; ?> />
				</td>
			</tr>
			<tr>
				<td align="right">
					Core Critical: 
				</td>
				<td>
					<input type="text" maxlength="3" size="3" class="formfld unknown" 
						name="coretemp_widget_corescriticaltempthreshold" 
						id="coretemp_widget_corescriticaltempthreshold" 
						value="<?= $coretemp_widget_coreCriticalTempThreshold ?>" />
				</td>
				<td align="right">
					Pulsate Critical:
				</td>
				<td>
					<input type="checkbox" 
						id="coretemp_widget_pulsatecritical" 
						name="coretemp_widget_pulsatecritical" 
						value="<?= $coretemp_widget_pulsateCritical; ?>" <?= ($coretemp_widget_pulsateCritical) ? " checked='checked'" : ""; ?> />
				</td>
			</tr>
			<tr>
				<td align="right" colspan="4">
					<input type="submit" id="submita" name="submita" class="formbtn" value="Save" />
				</td>
			</tr>
		</table>
    </form>
</div>
 
<div style="padding: 5px">
	<?php 
		$ctd = getCoreTempData(); 
		$ctd = str_replace("|", "<br />", $ctd);
	?>
	<div id="coretempContainer" class="listr">
		(Updating...)<br /><br />
		<?= $ctd; ?>
	</div>
</div>

<!-- needed to display the widget settings menu -->
<script language="javascript" type="text/javascript">

	//TODO: jQuery way for pfSense v2.1 and up
	//textlink = jQuery("coretemp_widget-configure");
	//textlink.css( { "display" : "inline" } );

	//Prototype way for pfSense up to v2.0.2 (inclusive)
	textlink = $("coretemp_widget-configure");
	textlink.setStyle( { display : "inline"} );

</script>
