<?php
/*

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
	pfSense_BUILDER_BINARIES:	/bin/ps	/usr/bin/grep	/usr/sbin/tcpdump
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-diagnostics-packetcapture
##|*NAME=Diagnostics: Packet Capture page
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
	if (is_ipaddr($host))
		return "{$andor}host {$not}" . $host;
	elseif (is_subnet($host))
		return "{$andor}net {$not}" . $host;
	else
		return "";
}

if ($_POST['downloadbtn'] == gettext("Download Capture"))
	$nocsrf = true;

$pgtitle = array(gettext("Diagnostics"), gettext("Packet Capture"));
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");

$fp = "/root/";
$fn = "packetcapture.cap";
$snaplen = 0;//default packet length
$count = 100;//default number of packets to capture

$fams = array('ip', 'ip6');
$protos = array('icmp', 'icmp6', 'tcp', 'udp', 'arp', 'carp', 'esp',
		'!icmp', '!icmp6', '!tcp', '!udp', '!arp', '!carp', '!esp');

$input_errors = array();

$interfaces = get_configured_interface_with_descr();
if (isset($config['ipsec']['enable']))
	$interfaces['ipsec'] = "IPsec";
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

		if ($_POST['startbtn'] != "" ) {
			$action = gettext("Start");

			//delete previous packet capture if it exists
			if (file_exists($fp.$fn))
				unlink ($fp.$fn);

		} elseif ($_POST['stopbtn']!= "") {
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

		} elseif ($_POST['downloadbtn']!= "") {
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

include("head.inc"); ?>

<body link="#000000" vlink="#0000CC" alink="#0000CC">

<?php
include("fbegin.inc");
?>

<?php if ($input_errors) print_input_errors($input_errors); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="diag packet capture">
	<tr><td>
	<form action="diag_packet_capture.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="table">
		<tr>
			<td colspan="3" valign="top" class="listtopic"><?=gettext("Packet capture");?></td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Interface");?></td>
			<td width="32%" class="vtable">
			<select name="interface">
			<?php
			?>
			<?php foreach ($interfaces as $iface => $ifacename): ?>
				<option value="<?=$iface;?>" <?php if ($selectedif == $iface) echo "selected=\"selected\""; ?>>
				<?php echo $ifacename;?>
				</option>
			<?php endforeach; ?>
			</select>
			<br /><?=gettext("Select the interface on which to capture traffic.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Promiscuous");?></td>
			<td width="51%" class="vtable">
			<input name="promiscuous" type="checkbox"<?php if($_POST['promiscuous']) echo " checked=\"checked\""; ?> />
			<br /><?=gettext("If checked, the");?> <a target="_blank" href="http://www.freebsd.org/cgi/man.cgi?query=tcpdump&amp;apropos=0&amp;sektion=0&amp;manpath=FreeBSD+8.3-stable&amp;arch=default&amp;format=html"><?= gettext("packet capture")?></a> <?= gettext("will be performed using promiscuous mode.");?>
			<br /><b><?=gettext("Note");?>: </b><?=gettext("Some network adapters do not support or work well in promiscuous mode.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Address Family");?></td>
			<td colspan="2" width="83%" class="vtable">
			<select name="fam">
				<option value="">Any</option>
				<option value="ip" <?php if ($fam == "ip") echo "selected=\"selected\""; ?>>IPv4 Only</option>
				<option value="ip6" <?php if ($fam == "ip6") echo "selected=\"selected\""; ?>>IPv6 Only</option>
			</select>
			<br /><?=gettext("Select the type of traffic to be captured, either Any, IPv4 only or IPv6 only.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Protocol");?></td>
			<td colspan="2" width="83%" class="vtable">
			<select name="proto">
				<option value="">Any</option>
				<option value="icmp" <?php if ($proto == "icmp") echo "selected=\"selected\""; ?>>ICMP</option>
				<option value="!icmp" <?php if ($proto == "!icmp") echo "selected=\"selected\""; ?>>Exclude ICMP</option>
				<option value="icmp6" <?php if ($proto == "icmp6") echo "selected=\"selected\""; ?>>ICMPv6</option>
				<option value="!icmp6" <?php if ($proto == "!icmp6") echo "selected=\"selected\""; ?>>Exclude ICMPv6</option>
				<option value="tcp" <?php if ($proto == "tcp") echo "selected=\"selected\""; ?>>TCP</option>
				<option value="!tcp" <?php if ($proto == "!tcp") echo "selected=\"selected\""; ?>>Exclude TCP</option>
				<option value="udp" <?php if ($proto == "udp") echo "selected=\"selected\""; ?>>UDP</option>
				<option value="!udp" <?php if ($proto == "!udp") echo "selected=\"selected\""; ?>>Exclude UDP</option>
				<option value="arp" <?php if ($proto == "arp") echo "selected=\"selected\""; ?>>ARP</option>
				<option value="!arp" <?php if ($proto == "!arp") echo "selected=\"selected\""; ?>>Exclude ARP</option>
				<option value="carp" <?php if ($proto == "carp") echo "selected=\"selected\""; ?>>CARP (VRRP)</option>
				<option value="!carp" <?php if ($proto == "!carp") echo "selected=\"selected\""; ?>>Exclude CARP (VRRP)</option>
				<option value="esp" <?php if ($proto == "esp") echo "selected=\"selected\""; ?>>ESP</option>
			</select>
			<br /><?=gettext("Select the protocol to capture, or Any.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Host Address");?></td>
			<td colspan="2" width="83%" class="vtable">
			<input name="host" class="formfld host" id="host" size="20" value="<?=htmlspecialchars($host);?>" />
			<br /><?=gettext("This value is either the Source or Destination IP address or subnet in CIDR notation. The packet capture will look for this address in either field.");?>
			<br /><?=gettext("Matching can be negated by preceding the value with \"!\". Multiple IP addresses or CIDR subnets may be specified. Comma (\",\") separated values perform a boolean \"and\". Separating with a pipe (\"|\") performs a boolean \"or\".");?>
			<br /><?=gettext("If you leave this field blank, all packets on the specified interface will be captured.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Port");?></td>
			<td colspan="2" width="83%" class="vtable">
			<input name="port" class="formfld unknown" id="port" size="5" value="<?=$port;?>" />
			<br /><?=gettext("The port can be either the source or destination port. The packet capture will look for this port in either field.");?>
			<br /><?=gettext("Leave blank if you do not want to filter by port.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Packet Length");?></td>
			<td colspan="2" width="83%" class="vtable">
			<input name="snaplen" class="formfld unknown" id="snaplen" size="5" value="<?=$snaplen;?>" />
			<br /><?=gettext("The Packet length is the number of bytes of each packet that will be captured. Default value is 0, which will capture the entire frame regardless of its size.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Count");?></td>
			<td colspan="2" width="83%" class="vtable">
			<input name="count" class="formfld unknown" id="count" size="5" value="<?=$count;?>" />
			<br /><?=gettext("This is the number of packets the packet capture will grab. Default value is 100.") . "<br />" . gettext("Enter 0 (zero) for no count limit.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Level of Detail");?></td>
			<td colspan="2" width="83%" class="vtable">
			<select name="detail" class="formselect" id="detail" size="1">
				<option value="normal" <?php if ($detail == "normal") echo "selected=\"selected\""; ?>><?=gettext("Normal");?></option>
				<option value="medium" <?php if ($detail == "medium") echo "selected=\"selected\""; ?>><?=gettext("Medium");?></option>
				<option value="high"   <?php if ($detail == "high")   echo "selected=\"selected\""; ?>><?=gettext("High");?></option>
				<option value="full"   <?php if ($detail == "full")   echo "selected=\"selected\""; ?>><?=gettext("Full");?></option>
			</select>
			<br /><?=gettext("This is the level of detail that will be displayed after hitting 'Stop' when the packets have been captured.") .  "<br /><b>" .
					gettext("Note:") . "</b> " .
					gettext("This option does not affect the level of detail when downloading the packet capture.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top" class="vncellreq"><?=gettext("Reverse DNS Lookup");?></td>
			<td colspan="2" width="83%" class="vtable">
			<input name="dnsquery" type="checkbox" <?php if($_POST['dnsquery']) echo " checked=\"checked\""; ?> />
			<br /><?=gettext("This check box will cause the packet capture to perform a reverse DNS lookup associated with all IP addresses.");?>
			<br /><b><?=gettext("Note");?>: </b><?=gettext("This option can cause delays for large packet captures.");?>
			</td>
		</tr>
		<tr>
			<td width="17%" valign="top">&nbsp;</td>
			<td colspan="2" width="83%">
<?php

			/* check to see if packet capture tcpdump is already running */
			$processcheck = (trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep tcpdump | /usr/bin/grep {$fn} | /usr/bin/egrep -v '(pflog|grep)'")));

			if ($processcheck != "")
				$processisrunning = true;
			else
				$processisrunning = false;

			if (($action == gettext("Stop") or $action == "") and $processisrunning != true)
				echo "<input type=\"submit\" name=\"startbtn\" value=\"" . gettext("Start") . "\" />&nbsp;";
			else {
				echo "<input type=\"submit\" name=\"stopbtn\" value=\"" . gettext("Stop") . "\" />&nbsp;";
			}
			if (file_exists($fp.$fn) and $processisrunning != true) {
				echo "<input type=\"submit\" name=\"viewbtn\" value=\"" . gettext("View Capture") . "\" />&nbsp;";
				echo "<input type=\"submit\" name=\"downloadbtn\" value=\"" . gettext("Download Capture") . "\" />";
				echo "<br />" . gettext("The packet capture file was last updated:") . " " . date("F jS, Y g:i:s a.", filemtime($fp.$fn));
			}
?>
			</td>
		</tr>
	</table>
	</form>
	<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="results">
		<tr>
		<td valign="top" colspan="2">
<?php
		echo "<font face=\"terminal\" size=\"2\">";
		if ($processisrunning == true)
			echo("<strong>" . gettext("Packet Capture is running.") . "</strong><br />");

		if ($do_tcpdump) {
			$matches = array();

			if (in_array($fam, $fams))
				$matches[] = $fam;

			if (in_array($proto, $protos)) {
				$matches[] = fixup_not($proto);
			}

			if ($port != "")
				$matches[] = "port ".fixup_not($port);

			if ($host != "") {
				$hostmatch = "";
				$hostcount = 0;
				foreach ($hosts as $h) {
					$h = fixup_host($h, $hostcount++);
					if (!empty($h))
						$hostmatch .= " " . $h;
				}
				if (!empty($hostmatch))
					$matches[] = "({$hostmatch})";
			}

			if ($count != "0" ) {
				$searchcount = "-c " . $count;
			} else {
				$searchcount = "";
			}

			$selectedif = convert_friendly_interface_to_real_interface_name($selectedif);

			if ($action == gettext("Start")) {
				$matchstr = implode($matches, " and ");
				echo("<strong>" . gettext("Packet Capture is running.") . "</strong><br />");
				$cmd = "/usr/sbin/tcpdump -i {$selectedif} {$disablepromiscuous} {$searchcount} -s {$snaplen} -w {$fp}{$fn} " . escapeshellarg($matchstr);
				// Debug
				//echo $cmd;
				mwexec_bg ($cmd);
			} else {
				//action = stop
				echo("<strong>" . gettext("Packet Capture stopped.") . "<br /><br />" . gettext("Packets Captured:") . "</strong><br />");
?>
				<script type="text/javascript">
				//<![CDATA[
				window.onload=function(){
					document.getElementById("packetsCaptured").wrap='off';
				}
				//]]>
				</script>
				<textarea id="packetsCaptured" style="width:98%" name="code" rows="15" cols="66" readonly="readonly">
<?php
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
				system("/usr/sbin/tcpdump {$disabledns} {$detail_args} -r {$fp}{$fn}");

				conf_mount_ro();
?>
				</textarea>
<?php
			}
		}
?>
		&nbsp;</font>
		</td>
		</tr>
	</table>
	</td></tr>
</table>

<?php
include("fend.inc");
?>
</body>
</html>
