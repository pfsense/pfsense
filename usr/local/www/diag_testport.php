<?php
/*
	diag_testport.php

	Copyright (C) 2013 Jim P (jimp@pfsense.org)
	All rights reserved.

	Portions based on diag_ping.php
	part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2005 Bob Zoller (bob@kludgebox.com) and Manuel Kasper <mk@neon1.net>.
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
	pfSense_BUILDER_BINARIES:	/usr/bin/nc
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-diagnostics-testport
##|*NAME=Diagnostics: Test Port
##|*DESCR=Allow access to the 'Diagnostics: Test Port' page.
##|*MATCH=diag_testport.php*
##|-PRIV

$allowautocomplete = true;

$pgtitle = array(gettext("Diagnostics"), gettext("Test Port"));
require("guiconfig.inc");

define('NC_TIMEOUT', 10);

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);
	unset($do_testport);

	/* input validation */
	$reqdfields = explode(" ", "host port");
	$reqdfieldsn = array(gettext("Host"),gettext("Port"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_ipaddr($_REQUEST['host']) && !is_hostname($_REQUEST['host'])) {
		$input_errors[] = gettext("Please enter a valid IP or hostname.");
	}

	if (!is_port($_REQUEST['port'])) {
		$input_errors[] = gettext("Please enter a valid port number.");
	}

	if (is_numeric($_REQUEST['srcport']) && !is_port($_REQUEST['srcport'])) {
		$input_errors[] = gettext("Please enter a valid source port number, or leave the field blank.");
	}

	if (is_ipaddrv4($_REQUEST['host']) && ($_REQUEST['ipprotocol'] == "ipv6")) {
		$input_errors[] = gettext("You cannot connect to an IPv4 address using IPv6.");
	}
	if (is_ipaddrv6($_REQUEST['host']) && ($_REQUEST['ipprotocol'] == "ipv4")) {
		$input_errors[] = gettext("You cannot connect to an IPv6 address using IPv4.");
	}

	if (!$input_errors) {
		$do_testport = true;
		$host = $_REQUEST['host'];
		$sourceip = $_REQUEST['sourceip'];
		$port = $_REQUEST['port'];
		$srcport = $_REQUEST['srcport'];
		$showtext = isset($_REQUEST['showtext']);
		$ipprotocol = $_REQUEST['ipprotocol'];
		$timeout = NC_TIMEOUT;
	}
}
if (!isset($do_testport)) {
	$do_testport = false;
	$host = '';
	$port = '';
	$srcport = '';
	unset($showtext);
}

include("head.inc"); ?>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php echo gettext("This page allows you to perform a simple TCP connection test to determine if a host is up and accepting connections on a given port. This test does not function for UDP since there is no way to reliably determine if a UDP port accepts connections in this manner."); ?>
<br /><br />
<?php echo gettext("No data is transmitted to the remote host during this test, it will only attempt to open a connection and optionally display the data sent back from the server."); ?>
<br /><br /><br />
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="diag_testport.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Test Port"); ?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Host"); ?></td>
			<td width="78%" class="vtable">
			<?=$mandfldhtml;?>
			<input name="host" type="text" class="formfld" id="host" size="20" value="<?=htmlspecialchars($host);?>"></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?= gettext("Port"); ?></td>
			<td width="78%" class="vtable">
				<input name="port" type="text" class="formfld" id="port" size="10" value="<?=htmlspecialchars($port);?>">
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?= gettext("Source Port"); ?></td>
			<td width="78%" class="vtable">
				<input name="srcport" type="text" class="formfld" id="srcport" size="10" value="<?=htmlspecialchars($srcport);?>">
				<br /><br /><?php echo gettext("This should typically be left blank."); ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?= gettext("Show Remote Text"); ?></td>
			<td width="78%" class="vtable">
				<input name="showtext" type="checkbox" id="showtext" <?php if ($showtext) echo "checked" ?>>
				<br /><br /><?php echo gettext("Shows the text given by the server when connecting to the port. Will take 10+ seconds to display if checked."); ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Source Address"); ?></td>
			<td width="78%" class="vtable">
				<select name="sourceip" class="formselect">
					<option value="">Any</option>
				<?php   $sourceips = get_possible_traffic_source_addresses(true);
					foreach ($sourceips as $sip):
						$selected = "";
						if (!link_interface_to_bridge($sip['value']) && ($sip['value'] == $sourceip))
							$selected = 'selected="selected"';
				?>
					<option value="<?=$sip['value'];?>" <?=$selected;?>>
						<?=htmlspecialchars($sip['name']);?>
					</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("IP Protocol"); ?></td>
			<td width="78%" class="vtable">
			<select name="ipprotocol" class="formfld">
				<option value="any" <?php if ("any" == $ipprotocol) echo "selected"; ?>>
					Any
				</option>
				<option value="ipv4" <?php if ($ipprotocol == "ipv4") echo "selected"; ?>>
					<?=gettext("IPv4");?>
				</option>
				<option value="ipv6" <?php if ($ipprotocol == "ipv6") echo "selected"; ?>>
					<?=gettext("IPv6");?>
				</option>
			</select>
			<br /><br />
			<?php echo gettext("If you force IPv4 or IPv6 and use a hostname that does not contain a result using that protocol, it will result in an error. For example if you force IPv4 and use a hostname that only returns an AAAA IPv6 IP address, it will not work."); ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Test"); ?>">
			</td>
		</tr>
		<tr>
		<td valign="top" colspan="2">
		<?php if ($do_testport) {
			echo "<font face='terminal' size='2'>";
			echo "<strong>" . gettext("Port Test Results") . ":</strong><br />";
			echo '<pre>';
			$result = "";
			$nc_base_cmd = "/usr/bin/nc";
			$nc_args = "-w {$timeout}";
			if (!$showtext)
				$nc_args .= " -z ";
			if (!empty($srcport))
				$nc_args .= " -p {$srcport} ";

			/* Attempt to determine the interface address, if possible. Else try both. */
			if (is_ipaddrv4($host)) {
				$ifaddr = ($sourceip == "any") ? "" : get_interface_ip($sourceip);
				$nc_args .= " -4";
			} elseif (is_ipaddrv6($host)) {
				if ($sourceip == "any")
					$ifaddr = "";
				else if (is_linklocal($sourceip))
					$ifaddr = $sourceip;
				else
					$ifaddr = get_interface_ipv6($sourceip);
				$nc_args .= " -6";
			} else {
				switch ($ipprotocol) {
					case "ipv4":
						$ifaddr = get_interface_ip($sourceip);
						$nc_ipproto = " -4";
						break;
					case "ipv6":
						$ifaddr = (is_linklocal($sourceip) ? $sourceip : get_interface_ipv6($sourceip));
						$nc_ipproto = " -6";
						break;
					case "any":
						$ifaddr = get_interface_ip($sourceip);
						$nc_ipproto = (!empty($ifaddr)) ? " -4" : "";
						if (empty($ifaddr)) {
							$ifaddr = (is_linklocal($sourceip) ? $sourceip : get_interface_ipv6($sourceip));
							$nc_ipproto = (!empty($ifaddr)) ? " -6" : "";
						}
						break;
				}
				/* Netcat doesn't like it if we try to connect using a certain type of IP without specifying the family. */
				if (!empty($ifaddr)) {
					$nc_args .= $nc_ipproto;
				} elseif ($sourceip == "any") {
					switch ($ipprotocol) {
						case "ipv4":
							$nc_ipproto = " -4";
							break;
						case "ipv6":
							$nc_ipproto = " -6";
							break;
					}
					$nc_args .= $nc_ipproto;
				}
			}
			/* Only add on the interface IP if we managed to find one. */
			if (!empty($ifaddr)) {
				$nc_args .= " -s " . escapeshellarg($ifaddr) . " ";
				$scope = get_ll_scope($ifaddr);
				if (!empty($scope) && !strstr($host, "%"))
					$host .= "%{$scope}";
			}

			$nc_cmd = "{$nc_base_cmd} {$nc_args} " . escapeshellarg($host) . " " . escapeshellarg($port) . " 2>&1";
			exec($nc_cmd, $result, $retval);
			//echo "NC CMD: {$nc_cmd}\n\n";
			if (empty($result)) {
				if ($showtext)
					echo gettext("No output received, or connection failed. Try with \"Show Remote Text\" unchecked first.");
				else
					echo gettext("Connection failed (Refused/Timeout)");
			} else {
				if (is_array($result)) {
					foreach ($result as $resline) {
						echo htmlspecialchars($resline) . "\n";
					}
				} else {
					echo htmlspecialchars($result);
				}
			}
			echo '</pre>' ;
		}
		?>
		</td>
		</tr>
	</table>
</form>
</td></tr></table>
<?php include("fend.inc"); ?>
