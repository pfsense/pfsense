<?php
/*
	status_rrd_graph.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2007 Seth Mos <seth.mos@dds.nl>
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

##|+PRIV
##|*IDENT=page-status-rrdgraphs
##|*NAME=Status: RRD Graphs
##|*DESCR=Allow access to the 'Status: RRD Graphs' page.
##|*MATCH=status_rrd_graph.php*
##|*MATCH=status_rrd_graph_img.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");
require_once("rrd.inc");

unset($input_errors);
/* if the rrd graphs are not enabled redirect to settings page */
if (!isset($config['rrd']['enable'])) {
	header("Location: status_rrd_graph_settings.php");
}

$home = getcwd();
$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob("*.rrd");
chdir($home);

if ($_GET['cat']) {
	$curcat = htmlspecialchars($_GET['cat']);
} else {
	if (!empty($config['rrd']['category'])) {
		$curcat = $config['rrd']['category'];
	} else {
		$curcat = "system";
	}
}

if ($_POST['cat']) {
	$curcat = htmlspecialchars($_POST['cat']);
}

if ($_GET['zone']) {
	$curzone = $_GET['zone'];
} else {
	$curzone = '';
}

if ($_POST['period']) {
	$curperiod = $_POST['period'];
} else {
	if (!empty($config['rrd']['period'])) {
		$curperiod = $config['rrd']['period'];
	} else {
		$curperiod = "absolute";
	}
}

if ($_POST['style']) {
	$curstyle = $_POST['style'];
} else {
	if (!empty($config['rrd']['style'])) {
		$curstyle = $config['rrd']['style'];
	} else {
		$curstyle = "absolute";
	}
}

if ($_POST['option']) {
	$curoption = $_POST['option'];
} else {
	switch ($curcat) {
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
			foreach ($databases as $database) {
				if (preg_match("/[-]quality\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the quality graph */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "wireless":
			foreach ($databases as $database) {
				if (preg_match("/[-]wireless\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the wireless graph */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "cellular":
			foreach ($databases as $database) {
				if (preg_match("/[-]cellular\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the celullar graph */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "vpnusers":
			foreach ($databases as $database) {
				if (preg_match("/[-]vpnusers\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the VPN graphs */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "dhcpd":
			foreach ($databases as $database) {
				if (preg_match("/[-]dhcpd\.rrd/i", $database)) {
					/* pick off the 1st database we find that matches the dhcpd graph */
					$name = explode("-", $database);
					$curoption = "$name[0]";
					continue 2;
				}
			}
		case "captiveportal":
			$curoption = "allgraphs";
			break;
		case "ntpd":
			if (isset($config['ntpd']['statsgraph'])) {
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
if ($curcat == "custom") {
	if (is_numeric($_GET['start'])) {
		if ($start < ($now - (3600 * 24 * 365 * 5))) {
			$start = $now - (8 * 3600);
		}

		$start = $_POST['start'];
	} else if ($_POST['start']) {
		$start = strtotime($_POST['start']);

		if ($start === FALSE || $start === -1) {
			$input_errors[] = gettext("Invalid start date/time:") . " '{$_POST['start']}'";

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
		$input_errors[] = gettext("Invalid end date/time:") . " '{$_POST['end']}'";

	$end = $now;
	}
} else {
	$end = $now;
}

/* this should never happen */
if ($end < $start) {
	log_error("start $start is smaller than end $end");
	$end = $now;
}

$seconds = $end - $start;

$styles = array('inverse' => gettext('Inverse'),
	'absolute' => gettext('Absolute'));

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

foreach ($databases as $database) {
	if (stristr($database, "-wireless")) {
		$wireless = true;
	}
	if (stristr($database, "-queues")) {
		$queues = true;
	}
	if (stristr($database, "-cellular") && !empty($config['ppps'])) {
		$cellular = true;
	}
	if (stristr($database, "-vpnusers")) {
		$vpnusers = true;
	}
	if (stristr($database, "captiveportal-") && is_array($config['captiveportal'])) {
		$captiveportal = true;
	}
	if (stristr($database, "ntpd") && isset($config['ntpd']['statsgraph'])) {
		$ntpd = true;
	}
	if (stristr($database, "-dhcpd") && is_array($config['dhcpd'])) {
		$dhcpd = true;
	}

}
/* append the existing array to the header */
$ui_databases = array_merge($dbheader, $databases);
$custom_databases = array_merge($dbheader_custom, $databases);

$graphs = array("eighthour", "day", "week", "month", "quarter", "year", "fouryear");
$periods = array("absolute" => gettext("Absolute Timespans"), "current" => gettext("Current Period"), "previous" => gettext("Previous Period"));
$graph_length = array(
	"eighthour" => 28800,
	"day" => 86400,
	"week" => 604800,
	"month" => 2678400,
	"quarter" => 7948800,
	"year" => 31622400,
	"fouryear" => 126230400);

$pgtitle = array(gettext("Status"), gettext("RRD Graphs"), gettext(ucfirst($curcat)." Graphs"));

/* Load all CP zones */
if ($captiveportal && is_array($config['captiveportal'])) {
	$cp_zones_tab_array = array();
	foreach ($config['captiveportal'] as $cpkey => $cp) {
		if (!isset($cp['enable'])) {
			continue;
		}

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

function get_dates($curperiod, $graph) {
	global $graph_length;
	$now = time();
	$end = $now;

	if ($curperiod == "absolute") {
		$start = $end - $graph_length[$graph];
	} else {
		$curyear = date('Y', $now);
		$curmonth = date('m', $now);
		$curweek = date('W', $now);
		$curweekday = date('N', $now) - 1; // We want to start on monday
		$curday = date('d', $now);
		$curhour = date('G', $now);

		switch ($curperiod) {
			case "previous":
				$offset = -1;
				break;
			default:
				$offset = 0;
		}
		switch ($graph) {
			case "eighthour":
				if ($curhour < 24) {
					$starthour = 16;
				}
				if ($curhour < 16) {
					$starthour = 8;
				}
				if ($curhour < 8) {
					$starthour = 0;
				}

				switch ($offset) {
					case 0:
						$houroffset = $starthour;
						break;
					default:
						$houroffset = $starthour + ($offset * 8);
						break;
				}
				$start = mktime($houroffset, 0, 0, $curmonth, $curday, $curyear);
				if ($offset != 0) {
					$end = mktime(($houroffset + 8), 0, 0, $curmonth, $curday, $curyear);
				}
				break;
			case "day":
				$start = mktime(0, 0, 0, $curmonth, ($curday + $offset), $curyear);
				if ($offset != 0) {
					$end = mktime(0, 0, 0, $curmonth, (($curday + $offset) + 1), $curyear);
				}
				break;
			case "week":
				switch ($offset) {
					case 0:
						$weekoffset = 0;
						break;
					default:
						$weekoffset = ($offset * 7) - 7;
						break;
				}
				$start = mktime(0, 0, 0, $curmonth, (($curday - $curweekday) + $weekoffset), $curyear);
				if ($offset != 0) {
					$end = mktime(0, 0, 0, $curmonth, (($curday - $curweekday) + $weekoffset + 7), $curyear);
				}
				break;
			case "month":
				$start = mktime(0, 0, 0, ($curmonth + $offset), 0, $curyear);
				if ($offset != 0) {
					$end = mktime(0, 0, 0, (($curmonth + $offset) + 1), 0, $curyear);
				}
				break;
			case "quarter":
				$start = mktime(0, 0, 0, (($curmonth - 2) + $offset), 0, $curyear);
				if ($offset != 0) {
					$end = mktime(0, 0, 0, (($curmonth + $offset) + 1), 0, $curyear);
				}
				break;
			case "year":
				$start = mktime(0, 0, 0, 1, 0, ($curyear + $offset));
				if ($offset != 0) {
					$end = mktime(0, 0, 0, 1, 0, (($curyear + $offset) +1));
				}
				break;
			case "fouryear":
				$start = mktime(0, 0, 0, 1, 0, (($curyear - 3) + $offset));
				if ($offset != 0) {
					$end = mktime(0, 0, 0, 1, 0, (($curyear + $offset) +1));
				}
				break;
		}
	}
	// echo "start $start ". date('l jS \of F Y h:i:s A', $start) .", end $end ". date('l jS \of F Y h:i:s A', $end) ."<br />";
	$dates = array();
	$dates['start'] = $start;
	$dates['end'] = $end;
	return $dates;
}

function make_tabs() {
	global $curcat, $queues, $wireless, $cellular, $vpnusers, $captiveportal, $dhcpd, $ntpd;

	$tab_array = array();
	$tab_array[] = array(gettext("System"), ($curcat == "system"), "status_rrd_graph.php?cat=system");
	$tab_array[] = array(gettext("Traffic"), ($curcat == "traffic"), "status_rrd_graph.php?cat=traffic");
	$tab_array[] = array(gettext("Packets"), ($curcat == "packets"), "status_rrd_graph.php?cat=packets");
	$tab_array[] = array(gettext("Quality"), ($curcat == "quality"), "status_rrd_graph.php?cat=quality");


	if ($queues) {
		$tab_array[] = array(gettext("Queues"), ($curcat == "queues"), "status_rrd_graph.php?cat=queues");
		$tab_array[] = array(gettext("QueueDrops"), ($curcat == "queuedrops"), "status_rrd_graph.php?cat=queuedrops");
	}

	if ($wireless) {
		$tab_array[] = array(gettext("Wireless"), ($curcat == "wireless"), "status_rrd_graph.php?cat=wireless");
	}

	if ($cellular) {
		$tab_array[] = array(gettext("Cellular"), ($curcat == "cellular"), "status_rrd_graph.php?cat=cellular");
	}

	if ($vpnusers) {
		$tab_array[] = array(gettext("VPN"), ($curcat == "vpnusers"), "status_rrd_graph.php?cat=vpnusers");
	}

	if ($captiveportal) {
		$tab_array[] = array(gettext("Captive Portal"), ($curcat == "captiveportal"), "status_rrd_graph.php?cat=captiveportal");
	}

	if ($ntpd) {
		$tab_array[] = array("NTPD", ($curcat == "ntpd"), "status_rrd_graph.php?cat=ntpd");
	}

	if ($dhcpd) {
		$tab_array[] = array(gettext("DHCP Server"), ($curcat == "dhcpd"), "status_rrd_graph.php?cat=dhcpd");
	}

	$tab_array[] = array(gettext("Custom"), ($curcat == "custom"), "status_rrd_graph.php?cat=custom");
	$tab_array[] = array(gettext("Settings"), ($curcat == "settings"), "status_rrd_graph_settings.php");

	return($tab_array);
}

// Create the selectable list of graphs
function build_options() {
	global $curcat, $custom_databases, $ui_databases;

	$optionslist = array();

	if ($curcat == "custom") {
		foreach ($custom_databases as $db => $database) {
			$optionc = explode("-", $database);
			$friendly = convert_friendly_interface_to_friendly_descr(strtolower($optionc[0]));
			if (empty($friendly)) {
				$friendly = $optionc[0];
			}

			$search = array("-", ".rrd", $optionc[0]);
			$replace = array(" :: ", "", $friendly);
			$prettyprint = ucwords(str_replace($search, $replace, $database));
			$optionslist[$database] = htmlspecialchars($prettyprint);
			}
		}

	foreach ($ui_databases as $db => $database) {
		if (!preg_match("/($curcat)/i", $database)) {
			continue;
		}

		if (($curcat == "captiveportal") && !empty($curzone) && !preg_match("/captiveportal-{$curzone}/i", $database)) {
			continue;
		}

		$optionc = explode("-", $database);
		$search = array("-", ".rrd", $optionc);
		$replace = array(" :: ", "", $friendly);

		switch ($curcat) {
			case "captiveportal":
				$optionc = str_replace($search, $replace, $optionc[2]);
				$prettyprint = ucwords(str_replace($search, $replace, $optionc));
				$optionslist[$optionc] = htmlspecialchars($prettyprint);
				break;
			case "system":
				$optionc = str_replace($search, $replace, $optionc[1]);
				$prettyprint = ucwords(str_replace($search, $replace, $optionc));
				$optionslist[$optionc] = htmlspecialchars($prettyprint);
				break;
			default:
				/* Deduce an interface if possible and use the description */
				$optionc = "$optionc[0]";
				$friendly = convert_friendly_interface_to_friendly_descr(strtolower($optionc));
				if (empty($friendly)) {
					$friendly = $optionc;
				}
				$search = array("-", ".rrd", $optionc);
				$replace = array(" :: ", "", $friendly);
				$prettyprint = ucwords(str_replace($search, $replace, $friendly));
				$optionslist[$optionc] = htmlspecialchars($prettyprint);
		}
	}

	return($optionslist);
}

include("head.inc");

display_top_tabs(make_tabs());

if ($input_errors && count($input_errors)) {
	print_input_errors($input_errors);
}

$form = new Form(false);

$section = new Form_Section('Graph settings');

$group = new Form_Group('Options');

$group->add(new Form_Select(
	'option',
	'Graphs',
	$curoption,
	build_options()
))->setHelp('Graph');

$group->add(new Form_Select(
	'style',
	'Style',
	$curstyle,
	$styles
))->setHelp('Style');

$group->add(new Form_Select(
	'period',
	'Period',
	$curperiod,
	$periods
))->setHelp('Period');

if ($curcat == 'custom') {
	$group->setHelp('Any changes to these option may not take affect until the next auto-refresh.');
}

$section->add($group);

if ($curcat == 'custom') {
	$section->addInput(new Form_Input(
		'cat',
		null,
		'hidden',
		'custom'
	 ));

	$tz = date_default_timezone_get();
	$tz_msg = gettext("Enter date and/or time. Current timezone:") . " $tz";
	$start_fmt = strftime("%m/%d/%Y %H:%M:%S", $start);
	$end_fmt   = strftime("%m/%d/%Y %H:%M:%S", $end);

	$group = new Form_Group('');

	$group->add(new Form_Input(
		'start',
		'Start',
		'datetime',
		$start_fmt
	))->setHelp('Start');

	$group->add(new Form_Input(
		'end',
		'End',
		'datetime',
		$end_fmt
	))->setHelp('End');

	if ($curcat != 'custom') {
		$group->setHelp('Any changes to these option may not take affect until the next auto-refresh');
	}

	$section->add($group);

	$form->add($section);
	print($form);

	$curdatabase = $curoption;
	$graph = "custom-$curdatabase";
	if (in_array($curdatabase, $custom_databases)) {
		$id = "{$graph}-{$curoption}-{$curdatabase}";
		$id = preg_replace('/\./', '_', $id);
?>
		<div class="panel panel-default">
			<img class="text-center" name="<?=$id?>" id="<?=$id?>" alt="<?=$prettydb?> Graph" src="status_rrd_graph_img.php?start=<?=$start?>&amp;end=<?=$end?>&amp;database=<?=$curdatabase?>&amp;style=<?=$curstyle?>&amp;graph=<?=$graph?>" />
		</div>
<?php

	}
} else {
	$form->add($section);
	print($form);

	foreach ($graphs as $graph) {
		/* check which databases are valid for our category */
		foreach ($ui_databases as $curdatabase) {
			if (!preg_match("/($curcat)/i", $curdatabase)) {
				continue;
			}

			if (($curcat == "captiveportal") && !empty($curzone) && !preg_match("/captiveportal-{$curzone}/i", $curdatabase)) {
				continue;
			}

			$optionc = explode("-", $curdatabase);
			$search = array("-", ".rrd", $optionc);
			$replace = array(" :: ", "", $friendly);

			switch ($curoption) {
				case "outbound":
					/* make sure we do not show the placeholder databases in the outbound view */
					if ((stristr($curdatabase, "outbound")) || (stristr($curdatabase, "allgraphs"))) {
						continue 2;
					}
					/* only show interfaces with a gateway */
					$optionc = "$optionc[0]";
					if (!interface_has_gateway($optionc)) {
						if (!isset($gateways_arr)) {
							if (preg_match("/quality/i", $curdatabase)) {
								$gateways_arr = return_gateways_array();
							} else {
								$gateways_arr = array();
							}
						}
						$found_gateway = false;
						foreach ($gateways_arr as $gw) {
							if ($gw['name'] == $optionc) {
								$found_gateway = true;
								break;
							}
						}
						if (!$found_gateway) {
							continue 2;
						}
					}

					if (!preg_match("/(^$optionc-|-$optionc\\.)/i", $curdatabase)) {
						continue 2;
					}
					break;
				case "allgraphs":
					/* make sure we do not show the placeholder databases in the all view */
					if ((stristr($curdatabase, "outbound")) || (stristr($curdatabase, "allgraphs"))) {
						continue 2;
					}
					break;
				default:
					/* just use the name here */
					if (!preg_match("/(^$curoption-|-$curoption\\.)/i", $curdatabase)) {
						continue 2;
					}
			}

			if (in_array($curdatabase, $ui_databases)) {
				$id = "{$graph}-{$curoption}-{$curdatabase}";
				$id = preg_replace('/\./', '_', $id);

				$dates = get_dates($curperiod, $graph);
				$start = $dates['start'];
				$end = $dates['end'];
?>
				<div class="panel panel-default text-center">
					<img name="<?=$id?>" id="<?=$id?>" alt="<?=$prettydb?> Graph" src="status_rrd_graph_img.php?start=<?=$start?>&amp;end=<?=$end?>&amp;database=<?=$curdatabase?>&amp;style=<?=$curstyle?>&amp;graph=<?=$graph?>" />
				</div>
<?php
			}
		}
	}
}

?>

<script type="text/javascript">
//<![CDATA[
	function update_graph_images() {
		//alert('updating');
		var randomid = Math.floor(Math.random()*11);
		<?php
		foreach ($graphs as $graph) {
			/* check which databases are valid for our category */
			foreach ($ui_databases as $curdatabase) {
				if (!stristr($curdatabase, $curcat)) {
					continue;
				}
				$optionc = explode("-", $curdatabase);
				$search = array("-", ".rrd", $optionc);
				$replace = array(" :: ", "", $friendly);
				switch ($curoption) {
					case "outbound":
						/* make sure we do not show the placeholder databases in the outbound view */
						if ((stristr($curdatabase, "outbound")) || (stristr($curdatabase, "allgraphs"))) {
							continue 2;
						}
						/* only show interfaces with a gateway */
						$optionc = "$optionc[0]";
						if (!interface_has_gateway($optionc)) {
							if (!isset($gateways_arr)) {
								if (preg_match("/quality/i", $curdatabase)) {
									$gateways_arr = return_gateways_array();
								} else {
									$gateways_arr = array();
								}
							}
							$found_gateway = false;
							foreach ($gateways_arr as $gw) {
								if ($gw['name'] == $optionc) {
									$found_gateway = true;
									break;
								}
							}
							if (!$found_gateway) {
								continue 2;
							}
						}
						if (!preg_match("/(^$optionc-|-$optionc\\.)/i", $curdatabase)) {
							continue 2;
						}
						break;
					case "allgraphs":
						/* make sure we do not show the placeholder databases in the all view */
						if ((stristr($curdatabase, "outbound")) || (stristr($curdatabase, "allgraphs"))) {
							continue 2;
						}
						break;
					default:
						/* just use the name here */
						if (!preg_match("/(^$curoption-|-$curoption\\.)/i", $curdatabase)) {
							continue 2;
						}
				}
				$dates = get_dates($curperiod, $graph);
				$start = $dates['start'];
				if ($curperiod == "current") {
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

<script>
//<![CDATA[
events.push(function() {
	$('#option, #style, #period').on('change', function() {
		$(this).parents('form').submit();
	});
});
//]]>
</script>

<?php include("foot.inc");
