<?php
/* $Id$ */
/*
	system_advanced_network.php
	part of pfSense
	Copyright (C) 2005-2007 Scott Ullrich

	Copyright (C) 2008 Shrew Soft Inc

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

##|+PRIV
##|*IDENT=page-system-advanced-network
##|*NAME=System: Advanced: Network page
##|*DESCR=Allow access to the 'System: Advanced: Networking' page.
##|*MATCH=system_advanced-network.php*
##|-PRIV


require("guiconfig.inc");

$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
$pconfig['ipv6nat_ipaddr'] = $config['diag']['ipv6nat']['ipaddr'];
$pconfig['polling_enable'] = isset($config['system']['polling']);
$pconfig['sharednet'] = $config['system']['sharednet'];
$pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);

if ($_POST) {

    unset($input_errors);
    $pconfig = $_POST;

	if ($_POST['ipv6nat_enable'] && !is_ipaddr($_POST['ipv6nat_ipaddr']))
		$input_errors[] = "You must specify an IP address to NAT IPv6 packets.";

    ob_flush();
    flush();
	if (!$input_errors) {

		if($_POST['ipv6nat_enable'] == "yes") {
			$config['diag']['ipv6nat']['enable'] = true;
			$config['diag']['ipv6nat']['ipaddr'] = $_POST['ipv6nat_ipaddr'];
		} else {
			if($config['diag']) {
				if($config['diag']['ipv6nat']) {
					unset($config['diag']['ipv6nat']['enable']);
					unset($config['diag']['ipv6nat']['ipaddr']);				
				}
			}
		}

		if($_POST['sharednet'] == "yes") {
			$config['system']['sharednet'] = true;
			system_disable_arp_wrong_if();
		} else {
			unset($config['system']['sharednet']);
			system_enable_arp_wrong_if();
		}

		if($_POST['polling_enable'] == "yes") {
			$config['system']['polling'] = true;
			setup_polling();
		} else {
			unset($config['system']['polling']);
			setup_polling();
		}

		if($_POST['disablechecksumoffloading'] == "yes") {
			$config['system']['disablechecksumoffloading'] = $_POST['disablechecksumoffloading'];
		} else {
			unset($config['system']['disablechecksumoffloading']);
		}
	
		write_config();

		config_lock();
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message($retval);
		else
		    $savemsg = $retval;
		config_unlock();
	}
}

$pgtitle = array("System","Advanced: Networking");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<script language="JavaScript">
<!--

function enable_change(enable_over) {
	if (document.iform.ipv6nat_enable.checked || enable_over)
		document.iform.ipv6nat_ipaddr.disabled = 0;
	else
		document.iform.ipv6nat_ipaddr.disabled = 1;
}

//-->
</script>


<?
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
	<form action="system_advanced_network.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td>
					<span class="vexpl">
	    	        	<span class="red">
							<strong>Note:</strong>
						</span>
						the options on this page are intended for use by advanced users only.
						<br/>
					</span>
					<br/>
				</td>
			</tr>
			<tr>
				<td>
					<?php
						$tab_array = array();
						$tab_array[] = array("Admin Access", false, "system_advanced_admin.php");
						$tab_array[] = array("Firewall / NAT", false, "system_advanced_firewall.php");
						$tab_array[] = array("Networking", true, "system_advanced_network.php");
						$tab_array[] = array("Miscellaneous", false, "system_advanced_misc.php");
						$tab_array[] = array("System Tunables", false, "system_advanced_sysctl.php");
						display_top_tabs($tab_array);
					?>
				</td>
			</tr>
			<tr>
				<td>
					<div id="mainarea">
						<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic">IPv6 Options</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">IPv6 over IPv4 Tunneling</td>
								<td width="78%" class="vtable">
									<input name="ipv6nat_enable" type="checkbox" id="ipv6nat_enable" value="yes" <?php if ($pconfig['ipv6nat_enable']) echo "checked"; ?> onclick="enable_change(false)" />
									<strong>Enable IPv4 NAT encapsulation of IPv6 packets</strong><br/>
									This provides an RFC 2893 compatibility mechanism
									that can be used to tunneling IPv6 packets over IPv4
									routing	infrastructures. If enabled, don't forget to
									add a firewall rule to permit IPv6 packets.<br/>
									<br/>
									IP address :&nbsp;
									<input name="ipv6nat_ipaddr" type="text" class="formfld unknown" id="ipv6nat_ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipv6nat_ipaddr']);?>" />
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic">Network Interfaces</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Device polling</td>
								<td width="78%" class="vtable">
									<input name="polling_enable" type="checkbox" id="polling_enable" value="yes" <?php if ($pconfig['polling_enable']) echo "checked"; ?>>
									<strong>Enable device polling</strong><br>
									Device polling is a technique that lets the system periodically poll network devices for new data instead of relying on interrupts. This prevents your webConfigurator, SSH, etc. from being inaccessible due to interrupt floods when under extreme load. Generally this is not recommended.
									Not all NICs support polling; see the <?= $g['product_name'] ?> homepage for a list of supported cards.
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Hardware Checksum Offloading</td>
								<td width="78%" class="vtable">
									<input name="disablechecksumoffloading" type="checkbox" id="disablechecksumoffloading" value="yes" <?php if (isset($config['system']['disablechecksumoffloading'])) echo "checked"; ?> />
									<strong>Disable hardware checksum offload.</strong><br>
									This option will hardware assisted checksum offloading. FreeBSD sometimes has difficulties with certain drivers.
								</td>
							</tr>		
							<tr>
								<td width="22%" valign="top" class="vncell">Arp Handling</td>
								<td width="78%" class="vtable">
									<input name="sharednet" type="checkbox" id="sharednet" value="yes" <?php if (isset($pconfig['sharednet'])) echo "checked"; ?> />
									<strong>Suppress ARP messages</strong><br>
									This option will suppress ARP messages when interfaces share the same physical network</strong>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%"><input name="Submit" type="submit" class="formbtn" value="Save" /></td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</form>
	<script language="JavaScript" type="text/javascript">
	<!--
		enable_change(false);
	//-->
	</script>

<?php include("fend.inc"); ?>
</body>
</html>
