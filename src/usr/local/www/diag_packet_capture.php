<?php
/*
 * diag_packet_capture.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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
	} elseif (is_macaddr($host, false)) {
		return "{$andor}ether host {$not}" . $host;
	} elseif (is_macaddr($host, true)) {
		/* Try to match a partial MAC address. tcpdump only allows
		 * matching 1, 2, or 4 byte chunks so enforce that limit
		 */
		$searchmac = "0x";
		$partcount = 0;
		/* is_macaddr will fail a partial match that has empty sections
		 * but sections may only have one digit (leading 0) so add a
		 * left 0 pad.
		 */
		foreach (explode(':', $host) as $mp) {
			$searchmac .= str_pad($mp, 2, "0", STR_PAD_LEFT);
			$partcount++;
		}
		if (!in_array($partcount, array(1, 2, 4))) {
			return "";
		}
		$eq = has_not($value) ? "!=" : "==";
		// ether[0:2] == 0x0090 or ether[6:2] == 0x0090
		return "{$andor} ( ether[0:{$partcount}] {$eq} {$searchmac} or ether[6:{$partcount}] {$eq} {$searchmac} )";
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
$max_display_size = 50*1024*1024; // 50MB limit on GUI capture display. See https://redmine.pfsense.org/issues/9239

$fams = array('ip', 'ip6');
$protos = array('icmp', 'icmp6', 'tcp', 'udp', 'arp', 'carp', 'esp', 'pfsync',
		        '!icmp', '!icmp6', '!tcp', '!udp', '!arp', '!carp', '!esp', '!pfsync');

$input_errors = array();

$interfaces = get_configured_interface_with_descr();
if (ipsec_enabled()) {
	$interfaces['enc0'] = "IPsec";
}
$interfaces['lo0'] = "Localhost";

foreach (array('server' => gettext('OpenVPN Server'), 'client' => gettext('OpenVPN Client')) as $mode => $mode_descr) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$interfaces['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = $mode_descr . ": ".htmlspecialchars($setting['description']);
			}
		}
	}
}

$interfaces = array_merge($interfaces, interface_ipsec_vti_list_all());

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
			$h = strip_host_logic($h);
			if (!is_subnet($h) && !is_ipaddr($h) && !is_macaddr($h, true)) {
				$input_errors[] = sprintf(gettext("A valid IP address, CIDR block, or MAC address must be specified. [%s]"), $h);
			}
			/* Check length of partial MAC */
			if (!is_macaddr($h, false) && is_macaddr($h, true)) {
				$mac_parts = explode(':', $h);
				if (!in_array(count($mac_parts), array(1, 2, 4))) {
					$input_errors[] = gettext("Partial MAC addresses can only be matched using 1, 2, or 4 MAC segments (bytes).");
				}
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
			header("Content-Disposition: attachment; filename={$fn}");
			header("Content-Length: {$fs}");
			/* Ensure output buffering is off so PHP does not consume
			 * memory in readfile(). https://redmine.pfsense.org/issues/9239 */
			while (ob_get_level()) {
				@ob_end_clean();
			}
			readfile($fp.$fn);
			@ob_end_flush();
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
	'*Interface',
	$selectedif,
	$interfaces
))->setHelp('Select the interface on which to capture traffic. ');

$section->addInput(new Form_Checkbox(
	'promiscuous',
	'Promiscuous',
	'Enable promiscuous mode',
	$promiscuous
))->setHelp('%1$sNon-promiscuous mode captures only traffic that is directly relevant to the host (sent by it, sent or broadcast to it, or routed through it) and ' .
	'does not show packets that are ignored at network adapter level.%2$s%3$sPromiscuous mode%4$s ("sniffing") captures all data seen by the adapter, whether ' .
	'or not it is valid or related to the host, but in some cases may have undesirable side effects and not all adapters support this option. Click Info for details %5$s' .
	'Promiscuous mode requires more kernel processing of packets. This puts a slightly higher demand on system resources, especially ' .
	'on very busy networks or low power processors. The change in packet processing may allow a hostile host to detect that an adapter is in promiscuous mode ' .
	'or to \'fingerprint\' the kernel (see %6$s). Some network adapters may not support or work well in promiscuous mode (see %7$s).%8$s',

	'<p style="margin-bottom:2px;padding-bottom:0px">',
	'</p><p style="margin:0px;padding:0px">',
	'<a href="https://en.wikipedia.org/wiki/Promiscuous_mode">',
	'</a>',
	'<span class="infoblock" style="font-size:90%"><br />',
	'&nbsp;<a target="_blank" href="https://security.stackexchange.com/questions/3630/how-to-find-out-that-a-nic-is-in-promiscuous-mode-on-a-lan">[1]</a>' .
		'&nbsp;<a href="https://nmap.org/nsedoc/scripts/sniffer-detect.html">[2]</a>',
	'&nbsp;<a target="_blank" href="http://www.freebsd.org/cgi/man.cgi?query=tcpdump&amp;apropos=0&amp;sektion=0&amp;manpath=FreeBSD+11.0-stable&amp;arch=default&amp;format=html">[3]</a>',
	'</span></p>'
);

$section->addInput(new Form_Select(
	'fam',
	'*Address Family',
	$fam,
	array('' => 'Any',
		  'ip' => gettext('IPv4 Only'),
		  'ip6' => gettext('IPv6 Only')
	)
))->setHelp('Select the type of traffic to be captured.');

$section->addInput(new Form_Select(
	'proto',
	'*Protocol',
	$proto,
	$protocollist
))->setHelp('Select the protocol to capture, or "Any". ');

$section->addInput(new Form_Input(
	'host',
	'Host Address',
	'text',
	$host
))->setHelp('This value is either the Source or Destination IP address, subnet in CIDR notation, or MAC address.%1$s' .
			'Matching can be negated by preceding the value with "!". Multiple IP addresses or CIDR subnets may be specified. Comma (",") separated values perform a boolean "AND". ' .
			'Separating with a pipe ("|") performs a boolean "OR".%1$s' .
			'MAC addresses must be entered in colon-separated format, such as xx:xx:xx:xx:xx:xx or a partial address consisting of one (xx), two (xx:xx), or four (xx:xx:xx:xx) segments.%1$s' .
			'If this field is left blank, all packets on the specified interface will be captured.',
			'<br />');

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
))->setHelp('This is the number of packets the packet capture will grab. Default value is 100.%s' .
			'Enter 0 (zero) for no count limit.',
			'<br />');

$section->addInput(new Form_Select(
	'detail',
	'Level of detail',
	$detail,
	array('normal' => gettext('Normal'),
		  'medium' => gettext('Medium'),
		  'high' => gettext('High'),
		  'full' => gettext('Full'),
		  'none' => gettext('None'),
	)
))->setHelp('This is the level of detail that will be displayed after hitting "Stop" when the packets have been captured.%s' .
			'This option does not affect the level of detail when downloading the packet capture. ',
			'<br />');

$section->addInput(new Form_Checkbox(
	'dnsquery',
	'Reverse DNS Lookup',
	'Do reverse DNS lookup',
	$_POST['dnsquery']
))->setHelp('The packet capture will perform a reverse DNS lookup associated with all IP addresses.%s' .
			'This option can cause delays for large packet captures.',
			'<br />');

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
	$section->addInput(new Form_StaticText(
		'Last capture start',
		date("F jS, Y g:i:s a.", filemtime("/tmp/packetcapture.time"))
	));
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
		'Last capture start',
		date("F jS, Y g:i:s a.", filemtime("/tmp/packetcapture.time"))
	));
	$section->addInput(new Form_StaticText(
		'Last capture stop',
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

		print_info_box(gettext('Packet capture is running'), 'info');

		$cmd = "/usr/sbin/tcpdump -i {$selectedif} {$disablepromiscuous} {$searchcount} -s {$snaplen} -w {$fp}{$fn} " . escapeshellarg($matchstr);
		mwexec ("touch /tmp/packetcapture.time");
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
		if (filesize($fp.$fn) > $max_display_size)
			print(gettext("Packet capture file is too large to display in the GUI.") .
			    "\n" .
			    gettext("Download the file, or view it in the console or ssh shell."));
		elseif ($detail == 'none') {
			print(gettext("Select a detail level to view the contents of the packet capture."));
		} else {
			system("/usr/sbin/tcpdump {$disabledns} {$detail_args} {$iscarp} -r {$fp}{$fn}");
		}
		print('</textarea>');

?>
		</div>
	</div>
</div>
<?php
	}
endif;

include("foot.inc");
