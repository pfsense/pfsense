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
	pfSense_BUILDER_BINARIES:	/usr/bin/find
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-status-rrdgraphs
##|*NAME=Status: RRD Graphs page
##|*DESCR=Allow access to the 'Status: RRD Graphs' page.
##|*MATCH=status_rrd_graph.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");
require_once("rrd.inc");

/* if the rrd graphs are not enabled redirect to settings page */
if(! isset($config['rrd']['enable'])) {
	header("Location: status_rrd_graph_settings.php");
}

$rrddbpath = "/var/db/rrd/";
/* XXX: (billm) do we have an exec() type function that does this type of thing? */
exec("cd $rrddbpath;/usr/bin/find -name *.rrd", $databases);

if ($_GET['cat']) {
	$curcat = $_GET['cat'];
} else {
	if(! empty($config['rrd']['category'])) {
		$curcat = $config['rrd']['category'];
	} else {
		$curcat = "system";
	}
}

if ($_GET['option']) {
	$curoption = $_GET['option'];
} else {
	switch($curcat) {
		case "system":
			$curoption = "processor";
			break;
		case "queues":
			$curoption = "queues";
			break;
		case "queuedrops":
			$curoption = "queuedrops";
			case "quality":
		foreach($databases as $database) {
			if(preg_match("/[-]quality\.rrd/i", $database)) {
				/* pick off the 1st database we find that matches the quality graph */
				$name = explode("-", $database);
				$curoption = "$name[0]";
				continue 2;
			}
		}
		default:
			$curoption = "wan";
			break;
	}
}

if ($_GET['style']) {
	$curstyle = $_GET['style'];
} else {
	if(! empty($config['rrd']['style'])) {
		$curstyle = $config['rrd']['style'];
	} else {
		$curstyle = "inverse";
	}
}

/* sort names reverse so WAN comes first */
rsort($databases);

/* these boilerplate databases are required for the other menu choices */
$dbheader = array("allgraphs-traffic.rrd",
		"allgraphs-quality.rrd",
		"allgraphs-wireless.rrd",
		"allgraphs-packets.rrd",
		"system-allgraphs.rrd",
		"system-throughput.rrd",
		"outbound-quality.rrd",
		"outbound-packets.rrd",
		"outbound-traffic.rrd");

/* append the existing array to the header */
$ui_databases = array_merge($dbheader, $databases);

$styles = array('inverse' => 'Inverse',
		'absolute' => 'Absolute');
$periods = array("4h", "16h", "48h", "32d", "6m", "1y", "4y");

$pgtitle = array("Status","RRD Graphs");
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
        <tr>
                <td>
			<form name="form1" action="status_rrd_graph.php" method="get">
			<input type="hidden" name="cat" value="<?php echo "$curcat"; ?>">
			<?php
			        $tab_array = array();
				if($curcat == "system") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array("System", $tabactive, "status_rrd_graph.php?cat=system");
				if($curcat == "traffic") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array("Traffic", $tabactive, "status_rrd_graph.php?cat=traffic");
				if($curcat == "packets") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array("Packets", $tabactive, "status_rrd_graph.php?cat=packets");
				if($curcat == "quality") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array("Quality", $tabactive, "status_rrd_graph.php?cat=quality");
				if($curcat == "queues") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array("Queues", $tabactive, "status_rrd_graph.php?cat=queues");
				if($curcat == "queuedrops") { $tabactive = True; } else { $tabactive = False; }
                                $tab_array[] = array("QueueDrops", $tabactive, "status_rrd_graph.php?cat=queuedrops");
				if($curcat == "wireless") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array("Wireless", $tabactive, "status_rrd_graph.php?cat=wireless");
				if($curcat == "settings") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array("Settings", $tabactive, "status_rrd_graph_settings.php");
			        display_top_tabs($tab_array);
			?>
                </td>
        </tr>
        <tr>
                <td>
                        <div id="mainarea">
                        <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                        <td colspan="2" class="list"><p><b><?=gettext("Note: Change of color and/or style may not take effect until the next refresh");?></b></p></td>
				</tr>
				<tr>
                                        <td colspan="2" class="list">
					<?=gettext("Graphs:");?>
					<select name="option" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
					<?php

					foreach ($ui_databases as $db => $database) {
						if(! preg_match("/($curcat)/i", $database)) {
							continue;
						}
						$optionc = split("-", $database);
						$search = array("-", ".rrd", $optionc);
						$replace = array(" :: ", "", $friendly);
						switch($curcat) {
							case "system":
								$optionc = str_replace($search, $replace, $optionc[1]);
								echo "<option value=\"$optionc\"";
								$prettyprint = ucwords(str_replace($search, $replace, $optionc));
								break;
							default:
								/* Deduce a interface if possible and use the description */
								$optionc = "$optionc[0]";
								$friendly = convert_friendly_interface_to_friendly_descr(strtolower($optionc));
								if(empty($friendly)) {
									$friendly = $optionc;
								}
								$search = array("-", ".rrd", $optionc);
								$replace = array(" :: ", "", $friendly);
								echo "<option value=\"$optionc\"";
								$prettyprint = ucwords(str_replace($search, $replace, $friendly));
						}
						if($curoption == $optionc) {
							echo " selected ";
						}
						echo ">" . htmlspecialchars($prettyprint) . "</option>\n";
					}

					?>
					</select>

					<?=gettext("Style:");?>
					<select name="style" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
					<?php
					foreach ($styles as $style => $styled) {
						echo "<option value=\"$style\"";
						if ($style == $curstyle) echo " selected";
						echo ">" . htmlspecialchars($styled) . "</option>\n";
					}
					?>

					</select>

					<?php

					foreach($periods as $period => $interval) {
						/* check which databases are valid for our category */
						foreach($ui_databases as $curdatabase) {
							if(! preg_match("/($curcat)/i", $curdatabase)) {
								continue;
							}
							$optionc = split("-", $curdatabase);
							$search = array("-", ".rrd", $optionc);
							$replace = array(" :: ", "", $friendly);
							switch($curoption) {
								case "outbound":
									/* only show interfaces with a gateway */
									$optionc = "$optionc[0]";
									if(!interface_has_gateway($optionc)) {
										if(!preg_match("/($optionc)-(quality)/", $curdatabase)) {
											continue 2;
										}
									}
									if(! preg_match("/($optionc)[-.]/i", $curdatabase)) {
										continue 2;
									}
									break;
								case "allgraphs":
									/* make sure we do not show the placeholder databases in the all view */
									if((stristr($curdatabase, "outbound")) || (stristr($curdatabase, "allgraphs"))) {
										continue 2;
									}
									break;
								default:
									/* just use the name here */
									if(! preg_match("/($curoption)[-.]/i", $curdatabase)) {
										continue 2;
									}
							}
							if(in_array($curdatabase, $databases)) {
								echo "<tr><td colspan=2 class=\"list\">\n";
								echo "<IMG BORDER='0' name='{$interval}-{$curoption}-{$curdatabase}' ";
								echo "id='{$interval}-{$curoption}-{$curdatabase}' ALT=\"$prettydb Graph\" ";
								echo "SRC=\"status_rrd_graph_img.php?interval=$interval&amp;database={$curdatabase}&amp;style={$curstyle}\" />\n";
								echo "<br /><hr><br />\n";								
								echo "</td></tr>\n";
							}
						}
					}
					?>
					</td>
				</tr>
				<tr>
					<td colspan=2 class="list">
					<script language="javascript">
						function update_graph_images() {
							//alert('updating');
							var randomid = Math.floor(Math.random()*11);
							<?php
							foreach($periods as $period => $interval) {
								/* check which databases are valid for our category */
								foreach($databases as $curdatabase) {
									if(! stristr($curdatabase, $curcat)) {
										continue;
									}
									$optionc = split("-", $curdatabase);
									$search = array("-", ".rrd", $optionc);
									$replace = array(" :: ", "", $friendly);
									switch($curoption) {
										case "outbound":
											if(!interface_has_gateway($optionc)) {
												continue 2; 
											}
											if(! stristr($curdatabase, $optionc)) {
													continue 2;
											}
											break;
										case "allgraphs":
											/* make sure we do not show the placeholder databases in the all view */
											if((stristr($curdatabase, "outbound")) || (stristr($curdatabase, "allgraphs"))) {
												continue 2;
											}
											break;
										default:
											/* just use the name here */
											if(! stristr($curdatabase, $curoption)) {
												continue 2;
											}
									}
									/* generate update events utilizing prototype $('') feature */
									echo "\n";
									echo "\t\t\$('{$interval}-{$curoption}-{$curdatabase}').src='status_rrd_graph_img.php?interval={$interval}&database={$curdatabase}&style={$curstyle}&tmp=' + randomid;\n";
									}
								}
							?>
							window.setTimeout('update_graph_images()', 355000);
						}
						window.setTimeout('update_graph_images()', 355000);
					</script>
					</form>
					</td>
				</tr>
			</table>
		</div>
		</td>
	</tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
