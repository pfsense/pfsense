<?php
/* $Id$ */
/*
	status_rrd_graph.php
	Part of pfSense
	Copyright (C) 2007 Seth Mos <seth.mos@xs4all.nl>
	All rights reserved.

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
/*
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-status-rrdgraphs
##|*NAME=Status: RRD Graphs page
##|*DESCR=Allow access to the 'Status: RRD Graphs' page.
##|*MATCH=status_rrd_graph_settings.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['gateways']['settings']))
	$config['gateways']['settings'] = array();

$a_settings = &$config['gateways']['settings'];

$changedesc = gettext("Gateways") . ": ";
$input_errors = array();

if (empty($a_settings)) {
	$pconfig['latencylow'] = "100";
	$pconfig['latencyhigh'] = "500";
	$pconfig['losslow'] = "10";
	$pconfig['losshigh'] = "20";
} else {
	$pconfig['latencylow'] = $a_settings['latencylow'];
	$pconfig['latencyhigh'] = $a_settings['latencyhigh'];
	$pconfig['losslow'] = $a_settings['losslow'];
	$pconfig['losshigh'] = $a_settings['losshigh'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if($_POST['latencylow']) {
		if (! is_numeric($_POST['latencylow'])) {
			$input_errors[] = gettext("The low latency watermark needs to be a numeric value.");
		}
	}

	if($_POST['latencyhigh']) {
		if (! is_numeric($_POST['latencyhigh'])) {
			$input_errors[] = gettext("The high latency watermark needs to be a numeric value.");
		}
	}
	if($_POST['losslow']) {
		if (! is_numeric($_POST['losslow'])) {
			$input_errors[] = gettext("The low loss watermark needs to be a numeric value.");
		}
	}
	if($_POST['losshigh']) {
		if (! is_numeric($_POST['losshigh'])) {
			$input_errors[] = gettext("The high loss watermark needs to be a numeric value.");
		}
	}

	if(($_POST['latencylow']) && ($_POST['latencyhigh'])){
		if(($_POST['latencylow'] > $_POST['latencyhigh'])) {
			$input_errors[] = gettext("The High latency watermark needs to be higher then the low latency watermark");
		}
	}

	if(($_POST['losslow']) && ($_POST['losshigh'])){
		if($_POST['losslow'] > $_POST['losshigh']) {
			$input_errors[] = gettext("The High packet loss watermark needs to be higher then the low packet loss watermark");
		}
	}



        if (!$input_errors) {
		$a_settings['latencylow'] = $_POST['latencylow'];
		$a_settings['latencyhigh'] = $_POST['latencyhigh'];
		$a_settings['losslow'] = $_POST['losslow'];
		$a_settings['losshigh'] = $_POST['losshigh'];
		

		$config['gateways']['settings'] = $a_settings;

                $retval = 0;
                $retval = setup_gateways_monitor();
		write_config();

                $savemsg = get_std_save_message($retval);
	}
}

$pgtitle = array(gettext("Gateways"),gettext("Settings"));
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="system_gateways_settings.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
        <tr>
                <td>
			<?php
				$tab_array = array();
				$tab_array[0] = array(gettext("Gateways"), false, "system_gateways.php");
				$tab_array[1] = array(gettext("Routes"), false, "system_routes.php");
				$tab_array[2] = array(gettext("Groups"), false, "system_gateway_groups.php");
				$tab_array[3] = array(gettext("Settings"), true, "system_gateways_settings.php");
				display_top_tabs($tab_array);
			?>
                </td>
        </tr>
        <tr>
                <td>
                        <div id="mainarea">
                        <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
			<tr>
                        	<td width="22%" valign="top" class="vncellreq"><?=gettext("Latency thresholds");?></td>
	                        <td width="78%" class="vtable">
				<?=gettext("From");?> 
				    <input name="latencylow" type="text" class="formfld unknown" id="latencylow" size="2" 
					value="<?=htmlspecialchars($pconfig['latencylow']);?>">
				<?=gettext("To");?>
				    <input name="latencyhigh" type="text" class="formfld unknown" id="latencyhigh" size="2" 
					value="<?=htmlspecialchars($pconfig['latencyhigh']);?>">
				    <br> <span class="vexpl"><?=gettext("These define the low and high water marks for latency in milliseconds.");?></span></td>
				</td>
			</tr>
			<tr>
                        	<td width="22%" valign="top" class="vncellreq"><?=gettext("Packet Loss thresholds");?></td>
	                        <td width="78%" class="vtable">
				<?=gettext("From");?> 
				    <input name="losslow" type="text" class="formfld unknown" id="losslow" size="2" 
					value="<?=htmlspecialchars($pconfig['losslow']);?>">
				<?=gettext("To");?>
				    <input name="losshigh" type="text" class="formfld unknown" id="losshigh" size="2" 
					value="<?=htmlspecialchars($pconfig['losshigh']);?>">
				    <br> <span class="vexpl"><?=gettext("These define the low and high water marks for packet loss in %.");?></span></td>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%">
					<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onclick="enable_change(true)">
				</td>
			</tr>
			</table>
		</div>
		</td>
	</tr>
</table>

</form>
<?php include("fend.inc"); ?>
</body>
</html>
