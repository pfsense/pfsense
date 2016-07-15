<?php
/*
 * diag_packet_capture.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-diagnostics-packetcapture
##|*NAME=Diagnostics: Packet Capture
##|*DESCR=Allow access to the 'Diagnostics: Packet Capture' page.
##|*MATCH=diag_packet_capture.php*
##|-PRIV

$allowautocomplete = true;

function fixup_host_logic($value) {
	return str_replace(array(" ", ",", "+", "|", "!"), array("", "and ", "and ", "or ", "not "), $value);
}

function strip_host_logic($value) {
	return str_replace(array(" ", ",", "+", "|", "!"), array("", "", "", "", ""), $value);
}

function get_host_boolean($value, $host) {
	$value = str_replace(array("!", $host), array("", ""), $value);
	$andor = "";
	switch (trim($value)) {
		case "|":
			$andor = "or ";
			break;
		case ",":
		case "+":
			$andor = "and ";
			break;
	}

	return $andor;
}

function has_not($value) {
	return strpos($value, '!') !== false;
}

function fixup_not($value) {
	return str_replace("!", "not ", $value);
}

function strip_not($value) {
	return ltrim(trim($value), '!');
}

function fixup_host($value, $position) {
	$host = strip_host_logic($value);
	$not = has_not($value) ? "not " : "";
	$andor = ($position > 0) ? get_host_boolean($value, $host) : "";
	if (is_ipaddr($host)) {
		return "{$andor}host {$not}" . $host;
	} elseif (is_subnet($host)) {
		return "{$andor}net {$not}" . $host;
	} else {
		return "";
	}
}

if ($_POST['downloadbtn'] == gettext("Download Capture")) {
	$nocsrf = true;
}

$pgtitle = array(gettext("Diagnostics"), gettext("Packet Capture"));
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");

$fp = "/root/";
$fn = "packetcapture.cap";
$snaplen = 0;//default packet length
$count = 100;//default number of packets to capture

$fams = array('ip', 'ip6');
$protos = array('icmp', 'icmp6', 'tcp', 'udp', 'arp', 'carp', 'esp', 'pfsync',
		        '!icmp', '!icmp6', '!tcp', '!udp', '!arp', '!carp', '!esp', '!pfsync');

$input_errors = array();

$interfaces = get_configured_interface_with_descr();
if (ipsec_enabled()) {
	$interfaces['enc0'] = "IPsec";
}

foreach (array('server', 'client') as $mode) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$interfaces['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
			}
		}
	}
}

if ($_POST) {
	$host = $_POST['host'];
	$selectedif = $_POST['interface'];
	$promiscuous = isset($_POST['promiscuous']);
	$count = $_POST['count'];
	$snaplen = $_POST['snaplen'];
	$port = $_POST['port'];
	$detail = $_POST['detail'];
	$fam = $_POST['fam'];
	$proto = $_POST['proto'];

	if (!array_key_exists($selectedif, $interfaces)) {
		$input_errors[] = gettext("Invalid interface.");
	}

	if ($fam !== "" && $fam !== "ip" && $fam !== "ip6") {
		$input_errors[] = gettext("Invalid address family.");
	}

	if ($fam !== "" && $proto !== "") {
		if ($fam == "ip" && $proto == "icmp6") {
			$input_errors[] = gettext("IPv4 with ICMPv6 is not valid.");
		}
		if ($fam == "ip6" && $proto == "icmp") {
			$input_errors[] = gettext("IPv6 with ICMP is not valid.");
		}
		if ($fam == "ip6" && $proto =="arp") {
			$input_errors[] = gettext("IPv6 with ARP is not valid.");
		}
	}

	if ($proto !== "" && !in_array(strip_not($proto), $protos)) {
		$input_errors[] = gettext("Invalid protocol.");
	}

	if ($host != "") {
		$host_string = str_replace(array(" ", "|", ","), array("", "#|", "#+"), $host);

		if (strpos($host_string, '#') === false) {
			$hosts = array($host);
		} else {
			$hosts = explode('#', $host_string);
		}

		foreach ($hosts as $h) {
			if (!is_subnet(strip_host_logic($h)) && !is_ipaddr(strip_host_logic($h))) {
				$input_errors[] = sprintf(gettext("A valid IP address or CIDR block must be specified. [%s]"), $h);
			}
		}
	}

	if ($port != "") {
		if (!is_port(strip_not($port))) {
			$input_errors[] = gettext("Invalid value specified for port.");
		}
	}

	if ($snaplen == "") {
		$snaplen = 0;
	} else {
		if (!is_numeric($snaplen) || $snaplen < 0) {
			$input_errors[] = gettext("Invalid value specified for packet length.");
		}
	}

	if ($count == "") {
		$count = 0;
	} else {
		if (!is_numeric($count) || $count < 0) {
			$input_errors[] = gettext("Invalid value specified for packet count.");
		}
	}

	if (!count($input_errors)) {
		$do_tcpdump = true;

		conf_mount_rw();

		if ($_POST['promiscuous']) {
			//if promiscuous mode is checked
			$disablepromiscuous = "";
		} else {
			//if promiscuous mode is unchecked
			$disablepromiscuous = "-p";
		}

		if ($_POST['dnsquery']) {
			//if dns lookup is checked
			$disabledns = "";
		} else {
			//if dns lookup is unchecked
			$disabledns = "-n";
		}

		if ($_POST['startbtn'] != "") {
			$action = gettext("Start");

			//delete previous packet capture if it exists
			if (file_exists($fp.$fn)) {
				unlink ($fp.$fn);
			}

		} elseif ($_POST['stopbtn'] != "") {
			$action = gettext("Stop");
			$processes_running = trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep tcpdump | /usr/bin/grep {$fn} | /usr/bin/egrep -v '(pflog|grep)'"));

			//explode processes into an array, (delimiter is new line)
			$processes_running_array = explode("\n", $processes_running);

			//kill each of the packetcapture processes
			foreach ($processes_running_array as $process) {
				$process_id_pos = strpos($process, ' ');
				$process_id = substr($process, 0, $process_id_pos);
				exec("kill $process_id");
			}

		} elseif ($_POST['downloadbtn'] != "") {
			//download file
			$fs = filesize($fp.$fn);
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=$fn");
			header("Content-Length: $fs");
			readfile($fp.$fn);
			exit;
		}
	}
} else {
	$do_tcpdump = false;
}

$excl = gettext("Exclude");

$protocollist = array(
	'' => 'Any',
	'icmp' => 'ICMP',
	'!icmp' => $excl . ' ICMP',
	'icmp6' => 'ICMPv6',
	'!icmp6' => $excl . ' ICMPv6',
	'tcp' => 'TCP',
	'!tcp' => $excl . ' TCP',
	'udp' => 'UDP',
	'!udp' => $excl . ' UDP',
	'arp' => 'ARP',
	'!arp' => $excl . ' ARP',
	'carp' => 'CARP',
	'!carp' => $excl . ' CARP',
	'pfsync' => 'pfsync',
	'!pfsync' => $excl . ' pfsync',
	'esp' => 'ESP',
	'!esp' => $excl . ' ESP'
);

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(false); // No button yet. We add those later depending on the required action

$section = new Form_Section('Packet Capture Options');

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$selectedif,
	$interfaces
))->setHelp('Select the interface on which to capture traffic. ');

$section->addInput(new Form_Checkbox(
	'promiscuous',
	'Promiscuous',
	'Enable promiscuous mode',
	$promiscuous
))->setHelp('The packet capture will be performed using promiscuous mode.<br />' .
			'Note: Some network adapters do not support or work well in promiscuous mode.'. '<br />' .
			'More: ' . '<a target="_blank" href="http://www.freebsd.org/cgi/man.cgi?query=tcpdump&amp;apropos=0&amp;sektion=0&amp;manpath=FreeBSD+8.3-stable&amp;arch=default&amp;format=html">' .
			'Packet capture' . '</a>');

$section->addInput(new Form_Select(
	'fam',
	'Address Family',
	$fam,
	array('' => 'Any',
		  'ip' => gettext('IPv4 Only'),
		  'ip6' => gettext('IPv6 Only')
	)
))->setHelp('Select the type of traffic to be captured.');

$section->addInput(new Form_Select(
	'proto',
	'Protocol',
	$proto,
	$protocollist
))->setHelp('Select the protocol to capture, or "Any". ');

$section->addInput(new Form_Input(
	'host',
	'Host Address',
	'text',
	$host
))->setHelp('This value is either the Source or Destination IP address or subnet in CIDR notation. The packet capture will look for this address in either field.' . '<br />' .
			'Matching can be negated by preceding the value with "!". Multiple IP addresses or CIDR subnets may be specified. Comma (",") separated values perform a boolean "AND". ' .
			'Separating with a pipe ("|") performs a boolean "OR".' . '<br />' .
			'If this field is left blank, all packets on the specified interface will be captured.');

$section->addInput(new Form_Input(
	'port',
	'Port',
	'text',
	$port
))->setHelp('The port can be either the source or destination port. The packet capture will look for this port in either field. ' .
			'Leave blank if not filtering by port.');

$section->addInput(new Form_Input(
	'snaplen',
	'Packet Length',
	'text',
	$snaplen
))->setHelp('The Packet length is the number of bytes of each packet that will be captured. Default value is 0, ' .
			'which will capture the entire frame regardless of its size.');

$section->addInput(new Form_Input(
	'count',
	'Count',
	'text',
	$count
))->setHelp('This is the number of packets the packet capture will grab. Default value is 100.' . '<br />' .
			'Enter 0 (zero) for no count limit.');

$section->addInput(new Form_Select(
	'detail',
	'Level of detail',
	$detail,
	array('normal' => gettext('Normal'),
		  'medium' => gettext('Medium'),
		  'high' => gettext('High'),
		  'full' => gettext('Full'),
	)
))->setHelp('This is the level of detail that will be displayed after hitting "Stop" when the packets have been captured.' . '<br />' .
			'This option does not affect the level of detail when downloading the packet capture. ');

$section->addInput(new Form_Checkbox(
	'dnsquery',
	'Reverse DNS Lookup',
	'Do reverse DNS lookup',
	$_POST['dnsquery']
))->setHelp('The packet capture will perform a reverse DNS lookup associated with all IP addresses.' . '<br />' .
			'This option can cause delays for large packet captures.');

$form->add($section);

/* check to see if packet capture tcpdump is already running */
$processcheck = (trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep tcpdump | /usr/bin/grep {$fn} | /usr/bin/egrep -v '(pflog|grep)'")));

$processisrunning = ($processcheck != "");

if (($action == gettext("Stop") or $action == "") and $processisrunning != true) {
	$form->addGlobal(new Form_Button(
		'startbtn',
		'Start',
		null,
		'fa-play-circle'
	))->addClass('btn-success');
} else {
	$form->addGlobal(new Form_Button(
		'stopbtn',
		'Stop',
		null,
		'fa-stop-circle'
	))->addClass('btn-warning');
}

if (file_exists($fp.$fn) and $processisrunning != true) {
	$form->addGlobal(new Form_Button(
		'viewbtn',
		'View Capture',
		null,
		'fa-file-text-o'
	))->addClass('btn-primary');

	$form->addGlobal(new Form_Button(
		'downloadbtn',
		'Download Capture',
		null,
		'fa-download'
	))->addClass('btn-primary');

	$section->addInput(new Form_StaticText(
		'Last capture',
		date("F jS, Y g:i:s a.", filemtime($fp.$fn))
	));
}

print($form);

if ($do_tcpdump) :
	$matches = array();

	if (in_array($fam, $fams)) {
		$matches[] = $fam;
	}

	if (in_array($proto, $protos)) {
		$matches[] = fixup_not($proto);
	}

	if ($port != "") {
		$matches[] = "port ".fixup_not($port);
	}

	if ($host != "") {
		$hostmatch = "";
		$hostcount = 0;

		foreach ($hosts as $h) {
			$h = fixup_host($h, $hostcount++);

			if (!empty($h)) {
				$hostmatch .= " " . $h;
			}
		}

		if (!empty($hostmatch)) {
			$matches[] = "({$hostmatch})";
		}
	}

	if ($count != "0") {
		$searchcount = "-c " . $count;
	} else {
		$searchcount = "";
	}

	$selectedif = convert_friendly_interface_to_real_interface_name($selectedif);

	if ($action == gettext("Start")) {
		$matchstr = implode($matches, " and ");

		print_info_box(gettext('Packet capture is running.'), 'info');

		$cmd = "/usr/sbin/tcpdump -i {$selectedif} {$disablepromiscuous} {$searchcount} -s {$snaplen} -w {$fp}{$fn} " . escapeshellarg($matchstr);
		// Debug
		//echo $cmd;
		mwexec_bg ($cmd);
	} else {
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Packets Captured')?></h2></div>
	<div class="panel-body">
		<div class="form-group">
<?php
		if ($proto == "carp") {
			$iscarp = "-T carp";
		} else {
			$iscarp = "";
		}
		$detail_args = "";
		switch ($detail) {
			case "full":
				$detail_args = "-vv -e";
				break;
			case "high":
				$detail_args = "-vv";
				break;
			case "medium":
				$detail_args = "-v";
				break;
			case "normal":
			default:
				$detail_args = "-q";
				break;
		}

		print('<textarea class="form-control" rows="20" style="font-size: 13px; font-family: consolas,monaco,roboto mono,liberation mono,courier;">');
		system("/usr/sbin/tcpdump {$disabledns} {$detail_args} {$iscarp} -r {$fp}{$fn}");
		print('</textarea>');

		conf_mount_ro();
?>
		</div>
	</div>
</div>
<?php
	}
endif;

include("foot.inc");
