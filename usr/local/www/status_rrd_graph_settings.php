<?php
/* $Id$ */
/*
	status_rrd_graph.php
	Part of pfSense
	Copyright (C) 2007 Seth Mos <seth.mos@dds.nl>
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
	pfSense_BUILDER_BINARIES:	/usr/bin/find
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-status-rrdgraph-settings
##|*NAME=Status: RRD Graphs settings page
##|*DESCR=Allow access to the 'Status: RRD Graphs: settings' page.
##|*MATCH=status_rrd_graph_settings.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");
require_once("rrd.inc");

$pconfig['enable'] = isset($config['rrd']['enable']);
$pconfig['category'] = $config['rrd']['category'];
$pconfig['style'] = $config['rrd']['style'];
$pconfig['period'] = $config['rrd']['period'];

$curcat = "settings";
$categories = array('system' => gettext("System"),
		'traffic' => gettext("Traffic"),
		'packets' => gettext("Packets"),
		'quality' => gettext("Quality"),
		'queues' => gettext("Queues"),
		'captiveportal' => gettext("Captive Portal"));
$styles = array('inverse' => gettext("Inverse"),
		'absolute' => gettext("Absolute"));
$periods = array("absolute" => gettext("Absolute Timespans"),
		"current" => gettext("Current Period"),
		"previous" => gettext("Previous Period"));

if ($_POST['ResetRRD']) {
	mwexec('/bin/rm /var/db/rrd/*');
	enable_rrd_graphing();
	setup_gateways_monitor();
	$savemsg = "RRD data has been cleared. New RRD files have been generated.";
} elseif ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	/* none */

        if (!$input_errors) {
                $config['rrd']['enable'] = $_POST['enable'] ? true : false;
                $config['rrd']['category'] = $_POST['category'];
                $config['rrd']['style'] = $_POST['style'];
                $config['rrd']['period'] = $_POST['period'];
                write_config();

                $retval = 0;
                $retval = enable_rrd_graphing();
                $savemsg = get_std_save_message($retval);
	}
}



$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob("*.rrd");

foreach($databases as $database) {
	if(stristr($database, "wireless")) {
		$wireless = true;
	}
	if(stristr($database, "queues")) {
		$queues = true;
	}
	if(stristr($database, "-cellular") && !empty($config['ppps'])) {
		$cellular = true;
	}
	if(stristr($database, "-vpnusers")) {
		$vpnusers = true;
	}
	if(stristr($database, "captiveportal-") && is_array($config['captiveportal'])) {
		$captiveportal = true;
	}
}

$pgtitle = array(gettext("Status"),gettext("RRD Graphs"));
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="status_rrd_graph_settings.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
        <tr>
                <td>
			<?php
				$tab_array = array();
				if($curcat == "system") { $tabactive = True; } else { $tabactive = False; }
				$tab_array[] = array(gettext("System"), $tabactive, "status_rrd_graph.php?cat=system");
				if($curcat == "traffic") { $tabactive = True; } else { $tabactive = False; }
				$tab_array[] = array(gettext("Traffic"), $tabactive, "status_rrd_graph.php?cat=traffic");
				if($curcat == "packets") { $tabactive = True; } else { $tabactive = False; }
				$tab_array[] = array(gettext("Packets"), $tabactive, "status_rrd_graph.php?cat=packets");
				if($curcat == "quality") { $tabactive = True; } else { $tabactive = False; }
				$tab_array[] = array(gettext("Quality"), $tabactive, "status_rrd_graph.php?cat=quality");
				if($queues) {
					if($curcat == "queues") { $tabactive = True; } else { $tabactive = False; }
						$tab_array[] = array(gettext("Queues"), $tabactive, "status_rrd_graph.php?cat=queues");
					if($curcat == "queuedrops") { $tabactive = True; } else { $tabactive = False; }
						$tab_array[] = array(gettext("QueueDrops"), $tabactive, "status_rrd_graph.php?cat=queuedrops");
				}
				if($wireless) {
					if($curcat == "wireless") { $tabactive = True; } else { $tabactive = False; }
						$tab_array[] = array(gettext("Wireless"), $tabactive, "status_rrd_graph.php?cat=wireless");
				}
				if($cellular) {
					if($curcat == "cellular") { $tabactive = True; } else { $tabactive = False; }
						$tab_array[] = array(gettext("Cellular"), $tabactive, "status_rrd_graph.php?cat=cellular");
				}
				if($vpnusers) {
					if($curcat == "vpnusers") { $tabactive = True; } else { $tabactive = False; }
						$tab_array[] = array(gettext("VPN"), $tabactive, "status_rrd_graph.php?cat=vpnusers");
				}
				if($captiveportal) {
					if($curcat == "captiveportal") { $tabactive = True; } else { $tabactive = False; }
						$tab_array[] = array(gettext("Captive Portal"), $tabactive, "status_rrd_graph.php?cat=captiveportal");
				}
				if($curcat == "custom") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array(gettext("Custom"), $tabactive, "status_rrd_graph.php?cat=custom");
				if($curcat == "settings") { $tabactive = True; } else { $tabactive = False; }
					$tab_array[] = array(gettext("Settings"), $tabactive, "status_rrd_graph_settings.php");
				
				display_top_tabs($tab_array);
                        ?>
                </td>
        </tr>
        <tr>
                <td>
                        <div id="mainarea">
                        <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
			<tr>
				<td width="22%" valign="top" class="vtable"><?=gettext("RRD Graphs");?></td>
				<td width="78%" class="vtable">
					<input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\"" ?> onclick="enable_change(false)"/>
					<b><?=gettext("Enables the RRD graphing backend.");?></b>
				</td>
			</tr>
			<tr>
                        	<td width="22%" valign="top" class="vtable"><?=gettext("Default category");?></td>
	                        <td width="78%" class="vtable">
					<select name="category" id="category" class="formselect" style="z-index: -10;" >
					<?php
					foreach ($categories as $category => $categoryd) {
						echo "<option value=\"$category\"";
						if ($category == $pconfig['category']) echo " selected=\"selected\"";
						echo ">" . htmlspecialchars($categoryd) . "</option>\n";
					}
					?>
					</select>
					<b><?=gettext("This selects default category.");?></b>
				</td>
			</tr>
			<tr>
                        	<td width="22%" valign="top" class="vtable"><?=gettext("Default style");?></td>
	                        <td width="78%" class="vtable">
					<select name="style" class="formselect" style="z-index: -10;" >
					<?php
					foreach ($styles as $style => $styled) {
						echo "<option value=\"$style\"";
						if ($style == $pconfig['style']) echo " selected=\"selected\"";
						echo ">" . htmlspecialchars($styled) . "</option>\n";
					}
					?>
					</select>
					<b><?=gettext("This selects the default style.");?></b>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vtable"><?=gettext("Default period");?></td>
				<td width="78%" class="vtable">
					<select name="period" class="formselect" style="z-index: -10;" >
					<?php
					foreach ($periods as $period => $periodd) {
						echo "<option value=\"$period\"";
						if ($period == $pconfig['period']) echo " selected=\"selected\"";
						echo ">" . htmlspecialchars($periodd) . "</option>\n";
					}
					?>
					</select>
					<b><?=gettext("This selects the default period.");?></b>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%">
					<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onclick="enable_change(true)"/>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%">
					<input name="ResetRRD" type="submit" class="formbtn" value="<?=gettext("Reset RRD Data");?>" onclick="return confirm('<?=gettext('Do you really want to reset the RRD graphs? This will erase all graph data.');?>')"/>
				</td>
			</tr>
			<tr>
				<td width="22%" height="53" valign="top">&nbsp;</td>
				<td width="78%"><strong><span class="red"><?=gettext("Note:");?></span></strong><br />
					<?=gettext("Graphs will not be allowed to be recreated within a 1 minute interval, please " .
					"take this into account after changing the style.");?>
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
