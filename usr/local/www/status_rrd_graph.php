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

unset($input_errors);

/* if the rrd graphs are not enabled redirect to settings page */
if(! isset($config['rrd']['enable'])) {
	header("Location: status_rrd_graph_settings.php");
}

$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob("*.rrd");


if ($_GET['cat']) {
	$curcat = htmlspecialchars($_GET['cat']);
} else {
	if(! empty($config['rrd']['category'])) {
		$curcat = $config['rrd']['category'];
	} else {
		$curcat = "system";
	}
}

if ($_GET['zone'])
	$curzone = $_GET['zone'];
else
	$curzone = '';

if ($_GET['period']) {
	$curperiod = $_GET['period'];
} else {
	if(! empty($config['rrd']['period'])) {
		$curperiod = $config['rrd']['period'];
	} else {
		$curperiod = "absolute";
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
			break;
		case "quality":
			foreach($databases as $database) {
				if(preg_match("/[-]quality\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the quality graph */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "wireless":
			foreach($databases as $database) {
				if(preg_match("/[-]wireless\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the wireless graph */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "cellular":
			foreach($databases as $database) {
				if(preg_match("/[-]cellular\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the celullar graph */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "vpnusers":
			foreach($databases as $database) {
				if(preg_match("/[-]vpnusers\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the VPN graphs */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "captiveportal":
			$curoption = "allgraphs";
			break;
		case "ntpd":
			if(isset($config['ntpd']['statsgraph'])) {
				$curoption = "allgraphs";
			} else {
				$curoption = "processor";
				$curcat = "system";
			}
			break;
		default:
			$curoption = "wan";
			break;
	}
}

$now = time();
if($curcat == "custom") {
	if (is_numeric($_GET['start'])) {
		if($start < ($now - (3600 * 24 * 365 * 5))) {
			$start = $now - (8 * 3600);
		}
		$start = $_GET['start'];
	} else if ($_GET['start']) {
		$start = strtotime($_GET['start']);
		if ($start === FALSE || $start === -1) {
			$input_errors[] = gettext("Invalid start date/time:") . " '{$_GET['start']}'";
			$start = $now - (8 * 3600);
		}
	} else {
		$start = $now - (8 * 3600);
	}
}

if (is_numeric($_GET['end'])) {
        $end = $_GET['end'];
} else if ($_GET['end']) {
	$end = strtotime($_GET['end']);
	if ($end === FALSE || $end === -1) {
		$input_errors[] = gettext("Invalid end date/time:") . " '{$_GET['end']}'";
		$end = $now;
	}
} else {
        $end = $now;
}

/* this should never happen */
if($end < $start) {
	log_error("start $start is smaller than end $end");
        $end = $now;
}

$seconds = $end - $start;

$styles = array('inverse' => gettext('Inverse'),
		'absolute' => gettext('Absolute'));

// Set default and override later
$curstyle = "inverse";

if ($_GET['style']) {
	foreach($styles as $style) 
		if(strtoupper($style) == strtoupper($_GET['style'])) 
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
		"allgraphs-cellular.rrd",
		"allgraphs-vpnusers.rrd",
		"allgraphs-packets.rrd",
		"system-allgraphs.rrd",
		"system-throughput.rrd",
		"outbound-quality.rrd",
		"outbound-packets.rrd",
		"outbound-traffic.rrd");

/* additional menu choices for the custom tab */
$dbheader_custom = array("system-throughput.rrd");

foreach($databases as $database) {
	if(stristr($database, "-wireless")) {
		$wireless = true;
	}
	if(stristr($database, "-queues")) {
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
	if(stristr($database, "ntpd") && isset($config['ntpd']['statsgraph'])) {
		$ntpd = true;
	}
}
/* append the existing array to the header */
$ui_databases = array_merge($dbheader, $databases);
$custom_databases = array_merge($dbheader_custom, $databases);

$graphs = array("8hour", "day", "week", "month", "quarter", "year", "4year");
$periods = array("absolute" => gettext("Absolute Timespans"), "current" => gettext("Current Period"), "previous" => gettext("Previous Period"));
$graph_length = array(
	"8hour" => 28800,
	"day" => 86400,
	"week" => 604800,
	"month" => 2678400,
	"quarter" => 7948800,
	"year" => 31622400,
	"4year" => 126230400);

$pgtitle = array(gettext("Status"),gettext("RRD Graphs"));

$closehead = false;

/* Load all CP zones */
if ($captiveportal && is_array($config['captiveportal'])) {
	$cp_zones_tab_array = array();
	foreach($config['captiveportal'] as $cpkey => $cp) {
		if (!isset($cp['enable']))
			continue;

		if ($curzone == '') {
			$tabactive = true;
			$curzone = $cpkey;
		} elseif ($curzone == $cpkey) {
			$tabactive = true;
		} else {
			$tabactive = false;
		}

		$cp_zones_tab_array[] = array($cp['zone'], $tabactive, "status_rrd_graph.php?cat=captiveportal&zone=$cpkey");
	}
}

include("head.inc");
?>

<?php if ($curcat === "custom") { ?>
	<link rel="stylesheet" type="text/css" href="/javascript/jquery-ui-timepicker-addon/css/jquery-ui-timepicker-addon.css" />
	<?php if (file_exists("{$g['www_path']}/themes/{$g['theme']}/jquery-ui.custom.css")) { ?>
		<link rel="stylesheet" type="text/css" href="/themes/<?= $g['theme'] ?>/jquery-ui.custom.css" />
	<?php } else { ?>
		<link rel="stylesheet" type="text/css" href="/javascript/jquery/jquery-ui.custom.css" />
	<?php } ?>
	<script type="text/javascript" src="/javascript/jquery-ui-timepicker-addon/js/jquery-ui-timepicker-addon.js"></script>
	<script type="text/javascript">
	//<![CDATA[
		jQuery(function ($) {
			var options = {
				dateFormat: 'mm/dd/yy',
				timeFormat: 'hh:mm:ss',
				showSecond: true
			};
			$("#startDateTime").datetimepicker(options);
			$("#endDateTime").datetimepicker(options);
		});
	//]]>
	</script>
<?php } ?>

<?php

function get_dates($curperiod, $graph) {
	global $graph_length;
	$now = time();
	$end = $now;

	if($curperiod == "absolute") {
		$start = $end - $graph_length[$graph];
	} else {
		$curyear = date('Y', $now);
		$curmonth = date('m', $now);
		$curweek = date('W', $now);
		$curweekday = date('N', $now) - 1; // We want to start on monday
		$curday = date('d', $now);
		$curhour = date('G', $now);

		switch($curperiod) {
			case "previous":
				$offset = -1;
				break;
			default:
				$offset = 0;
		}
		switch($graph) {
			case "8hour":
				if($curhour < 24)
					$starthour = 16;
				if($curhour < 16)
					$starthour = 8;
				if($curhour < 8)
					$starthour = 0;

				switch($offset) {
					case 0:
						$houroffset = $starthour;
						break;
					default:
						$houroffset = $starthour + ($offset * 8);
						break;
				}
				$start = mktime($houroffset, 0, 0, $curmonth, $curday, $curyear);
				if($offset != 0) {
					$end = mktime(($houroffset + 8), 0, 0, $curmonth, $curday, $curyear);
				}
				break;
			case "day":
				$start = mktime(0, 0, 0, $curmonth, ($curday + $offset), $curyear);
				if($offset != 0)
					$end = mktime(0, 0, 0, $curmonth, (($curday + $offset) + 1), $curyear);
				break;
			case "week":
				switch($offset) {
					case 0:
						$weekoffset = 0;
						break;
					default:
						$weekoffset = ($offset * 7) - 7;
						break;
				}
				$start = mktime(0, 0, 0, $curmonth, (($curday - $curweekday) + $weekoffset), $curyear);
				if($offset != 0)
					$end = mktime(0, 0, 0, $curmonth, (($curday - $curweekday) + $weekoffset + 7), $curyear);
				break;
			case "month":
				$start = mktime(0, 0, 0, ($curmonth + $offset), 0, $curyear);
				if($offset != 0)
					$end = mktime(0, 0, 0, (($curmonth + $offset) + 1), 0, $curyear);
				break;
			case "quarter":
				$start = mktime(0, 0, 0, (($curmonth - 2) + $offset), 0, $curyear);
				if($offset != 0)
					$end = mktime(0, 0, 0, (($curmonth + $offset) + 1), 0, $curyear);
				break;
			case "year":
				$start = mktime(0, 0, 0, 1, 0, ($curyear + $offset));
				if($offset != 0)
					$end = mktime(0, 0, 0, 1, 0, (($curyear + $offset) +1));
				break;
			case "4year":
				$start = mktime(0, 0, 0, 1, 0, (($curyear - 3) + $offset));
				if($offset != 0)
					$end = mktime(0, 0, 0, 1, 0, (($curyear + $offset) +1));
				break;
		}
	}
	// echo "start $start ". date('l jS \of F Y h:i:s A', $start) .", end $end ". date('l jS \of F Y h:i:s A', $end) ."<br />";
	$dates = array();
	$dates['start'] = $start;
	$dates['end'] = $end;
	return $dates;
}

?>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors && count($input_errors)) { print_input_errors($input_errors); } ?>
<form name="form1" action="status_rrd_graph.php" method="get">
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="rrd graphs">
        <tr>
                <td>
			<input type="hidden" name="cat" value="<?php echo "$curcat"; ?>" />
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
				        $tab_array[] = array("VPN", $tabactive, "status_rrd_graph.php?cat=vpnusers");
				}
				if($captiveportal) {
					if($curcat == "captiveportal") { $tabactive = True; } else { $tabactive = False; }
				        $tab_array[] = array("Captive Portal", $tabactive, "status_rrd_graph.php?cat=captiveportal");
				}
				if($ntpd) {
					if($curcat == "ntpd") { $tabactive = True; } else { $tabactive = False; }
				        $tab_array[] = array("NTP", $tabactive, "status_rrd_graph.php?cat=ntpd");
				}
				if($curcat == "custom") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array(gettext("Custom"), $tabactive, "status_rrd_graph.php?cat=custom");
				if($curcat == "settings") { $tabactive = True; } else { $tabactive = False; }
			        $tab_array[] = array(gettext("Settings"), $tabactive, "status_rrd_graph_settings.php");
			        display_top_tabs($tab_array);
			?>
                </td>
        </tr>
	<?php if ($curcat == "captiveportal") : ?>
	<tr>
		<td class="tabnavtbl">
			<?php display_top_tabs($cp_zones_tab_array); ?>
		</td>
	</tr>
	<?php endif; ?>
        <tr>
                <td>
                        <div id="mainarea">
                        <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0" summary="main area">
                                <tr>
                                        <td colspan="2" class="list"><p><b><?=gettext("Note: Change of color and/or style may not take effect until the next refresh");?></b></p></td>
				</tr>
				<tr>
                                        <td colspan="2" class="list">
					<?=gettext("Graphs:");?>
					<?php if (!empty($curzone)): ?>
					<input type="hidden" name="zone" value="<?= htmlspecialchars($curzone) ?>" />
					<?php endif; ?>
					<select name="option" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
					<?php

					if($curcat == "custom") {
						foreach ($custom_databases as $db => $database) {
							$optionc = explode("-", $database);
							$search = array("-", ".rrd", $optionc);
							$replace = array(" :: ", "", $friendly);
							echo "<option value=\"{$database}\"";
							$prettyprint = ucwords(str_replace($search, $replace, $database));
							if($curoption == $database) {
								echo " selected=\"selected\"";
							}
							echo ">" . htmlspecialchars($prettyprint) . "</option>\n";
						}
					}
					foreach ($ui_databases as $db => $database) {
						if(! preg_match("/($curcat)/i", $database))
							continue;

						if (($curcat == "captiveportal") && !empty($curzone) && !preg_match("/captiveportal-{$curzone}/i", $database))
							continue;

						$optionc = explode("-", $database);
						$search = array("-", ".rrd", $optionc);
						$replace = array(" :: ", "", $friendly);

						switch($curcat) {
							case "captiveportal":
								$optionc = str_replace($search, $replace, $optionc[2]);
								echo "<option value=\"$optionc\"";
								$prettyprint = ucwords(str_replace($search, $replace, $optionc));
								break;
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
							echo " selected=\"selected\"";
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
						if ($style == $curstyle) echo " selected=\"selected\"";
						echo ">" . htmlspecialchars($styled) . "</option>\n";
					}
					?>
					</select>
					
					<?php
					if($curcat <> "custom") {
					?>
						<?=gettext("Period:");?>
						<select name="period" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
						<?php
						foreach ($periods as $period => $value) {
							echo "<option value=\"$period\"";
							if ($period == $curperiod) echo " selected=\"selected\"";
							echo ">" . htmlspecialchars($value) . "</option>\n";
						}
						echo "</select>\n";
						echo "</td></tr>\n";
					}
					?>
					<?php

					if($curcat == "custom") {
						$tz = date_default_timezone_get();
						$tz_msg = gettext("Enter date and/or time. Current timezone:") . " $tz";
						$start_fmt = strftime("%m/%d/%Y %H:%M:%S", $start);
						$end_fmt   = strftime("%m/%d/%Y %H:%M:%S", $end);
						?>
						<?=gettext("Start:");?>
						<input id="startDateTime" title="<?= htmlentities($tz_msg); ?>." type="text" name="start" class="formfldunknown" size="24" value="<?= htmlentities($start_fmt); ?>" />
						<?=gettext("End:");?>
						<input id="endDateTime" title="<?= htmlentities($tz_msg); ?>." type="text" name="end" class="formfldunknown" size="24" value="<?= htmlentities($end_fmt); ?>" />
						<input type="submit" name="Submit" value="<?=gettext("Go"); ?>" />
						</td></tr>
						<?php
						$curdatabase = $curoption;
						$graph = "custom-$curdatabase";
						if(in_array($curdatabase, $custom_databases)) {
							$id = "{$graph}-{$curoption}-{$curdatabase}";
							$id = preg_replace('/\./', '_', $id);

							echo "<tr><td colspan=\"2\" class=\"list\">\n";
							echo "<img border=\"0\" name=\"{$id}\" ";
							echo "id=\"{$id}\" alt=\"$prettydb Graph\" ";
							echo "src=\"status_rrd_graph_img.php?start={$start}&amp;end={$end}&amp;database={$curdatabase}&amp;style={$curstyle}&amp;graph={$graph}\" />\n";
							echo "<br /><hr /><br />\n";
							echo "</td></tr>\n";
						}
					} else {
						foreach($graphs as $graph) {
							/* check which databases are valid for our category */
							foreach($ui_databases as $curdatabase) {
								if(! preg_match("/($curcat)/i", $curdatabase))
									continue;

								if (($curcat == "captiveportal") && !empty($curzone) && !preg_match("/captiveportal-{$curzone}/i", $curdatabase))
									continue;

								$optionc = explode("-", $curdatabase);
								$search = array("-", ".rrd", $optionc);
								$replace = array(" :: ", "", $friendly);
								switch($curoption) {
									case "outbound":
										/* make sure we do not show the placeholder databases in the outbound view */
										if((stristr($curdatabase, "outbound")) || (stristr($curdatabase, "allgraphs"))) {
											continue 2;
										}
										/* only show interfaces with a gateway */
										$optionc = "$optionc[0]";
										if(!interface_has_gateway($optionc)) {
											if(!isset($gateways_arr)) {
												if(preg_match("/quality/i", $curdatabase))
													$gateways_arr = return_gateways_array();
												else
													$gateways_arr = array();
											}
											$found_gateway = false;
											foreach ($gateways_arr as $gw) {
												if ($gw['name'] == $optionc) {
													$found_gateway = true;
													break;
												}
											}
											if(!$found_gateway) {
												continue 2;
											}
										}
										if(! preg_match("/(^$optionc-|-$optionc\\.)/i", $curdatabase)) {
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
										if(! preg_match("/(^$curoption-|-$curoption\\.)/i", $curdatabase)) {
											continue 2;
										}
								}
								if(in_array($curdatabase, $ui_databases)) {
									$id = "{$graph}-{$curoption}-{$curdatabase}";
									$id = preg_replace('/\./', '_', $id);

									$dates = get_dates($curperiod, $graph);
									$start = $dates['start'];
									$end = $dates['end'];
									echo "<tr><td colspan=\"2\" class=\"list\">\n";
									echo "<img border=\"0\" name=\"{$id}\" ";
									echo "id=\"{$id}\" alt=\"$prettydb Graph\" ";
									echo "src=\"status_rrd_graph_img.php?start={$start}&amp;end={$end}&amp;database={$curdatabase}&amp;style={$curstyle}&amp;graph={$graph}\" />\n";
									echo "<br /><hr /><br />\n";
									echo "</td></tr>\n";
								}
							}
						}
					}
					?>
				<tr>
					<td colspan="2" class="list">
					<script type="text/javascript">
					//<![CDATA[
						function update_graph_images() {
							//alert('updating');
							var randomid = Math.floor(Math.random()*11);
							<?php
							foreach($graphs as $graph) {
								/* check which databases are valid for our category */
								foreach($ui_databases as $curdatabase) {
									if(! stristr($curdatabase, $curcat)) {
										continue;
									}
									$optionc = explode("-", $curdatabase);
									$search = array("-", ".rrd", $optionc);
									$replace = array(" :: ", "", $friendly);
									switch($curoption) {
										case "outbound":
											/* make sure we do not show the placeholder databases in the outbound view */
											if((stristr($curdatabase, "outbound")) || (stristr($curdatabase, "allgraphs"))) {
												continue 2;
											}
											/* only show interfaces with a gateway */
											$optionc = "$optionc[0]";
											if(!interface_has_gateway($optionc)) {
												if(!isset($gateways_arr))
													if(preg_match("/quality/i", $curdatabase))
														$gateways_arr = return_gateways_array();
													else
														$gateways_arr = array();
												$found_gateway = false;
												foreach ($gateways_arr as $gw) {
													if ($gw['name'] == $optionc) {
														$found_gateway = true;
														break;
													}
												}
												if(!$found_gateway) {
													continue 2;
												}
											}
											if(! preg_match("/(^$optionc-|-$optionc\\.)/i", $curdatabase)) {
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
											if(! preg_match("/(^$curoption-|-$curoption\\.)/i", $curdatabase)) {
												continue 2;
											}
									}
									$dates = get_dates($curperiod, $graph);
									$start = $dates['start'];
									if($curperiod == "current") {
										$end = $dates['end'];
									}
									/* generate update events utilizing jQuery('') feature */
									$id = "{$graph}-{$curoption}-{$curdatabase}";
									$id = preg_replace('/\./', '_', $id);

									echo "\n";
									echo "\t\tjQuery('#{$id}').attr('src','status_rrd_graph_img.php?start={$start}&graph={$graph}&database={$curdatabase}&style={$curstyle}&tmp=' + randomid);\n";
									}
								}
							?>
							window.setTimeout('update_graph_images()', 355000);
						}
						window.setTimeout('update_graph_images()', 355000);
					//]]>
					</script>
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
