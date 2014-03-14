<?php
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
/*
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-advanced-network
##|*NAME=System: Advanced: Network page
##|*DESCR=Allow access to the 'System: Advanced: Networking' page.
##|*MATCH=system_advanced_network.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");


$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
$pconfig['ipv6nat_ipaddr'] = $config['diag']['ipv6nat']['ipaddr'];
$pconfig['ipv6allow'] = isset($config['system']['ipv6allow']);
$pconfig['prefer_ipv4'] = isset($config['system']['prefer_ipv4']);
$pconfig['polling_enable'] = isset($config['system']['polling']);
$pconfig['sharednet'] = $config['system']['sharednet'];
$pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
$pconfig['disablesegmentationoffloading'] = isset($config['system']['disablesegmentationoffloading']);
$pconfig['disablelargereceiveoffloading'] = isset($config['system']['disablelargereceiveoffloading']);
$pconfig['flowtable'] = isset($config['system']['flowtable']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if ($_POST['ipv6nat_enable'] && !is_ipaddr($_POST['ipv6nat_ipaddr']))
		$input_errors[] = gettext("You must specify an IP address to NAT IPv6 packets.");

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

		if($_POST['ipv6allow'] == "yes") {
			$config['system']['ipv6allow'] = true;
		} else {
			unset($config['system']['ipv6allow']);
		}

		if($_POST['prefer_ipv4'] == "yes") {
			$config['system']['prefer_ipv4'] = true;
		} else {
			unset($config['system']['prefer_ipv4']);
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

		if($_POST['flowtable'] == "yes") {
			$config['system']['flowtable'] = $_POST['flowtable'];
		} else {
			unset($config['system']['flowtable']);
		}

		if($_POST['disablechecksumoffloading'] == "yes") {
			$config['system']['disablechecksumoffloading'] = true;
		} else {
			unset($config['system']['disablechecksumoffloading']);
		}

		if($_POST['disablesegmentationoffloading'] == "yes") {
			$config['system']['disablesegmentationoffloading'] = true;
		} else {
			unset($config['system']['disablesegmentationoffloading']);
		}

		if($_POST['disablelargereceiveoffloading'] == "yes") {
			$config['system']['disablelargereceiveoffloading'] = true;
		} else {
			unset($config['system']['disablelargereceiveoffloading']);
		}

		setup_microcode();

		// Write out configuration (config.xml)
		write_config();

		// Configure flowtable support from filter.inc
		flowtable_configure();

		// Set preferred protocol
		prefer_ipv4_or_ipv6();

		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
			$savemsg = get_std_save_message(gettext($retval));
		else
			$savemsg = gettext($retval);
	}
}

$pgtitle = array(gettext("System"),gettext("Advanced: Networking"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[

function enable_change(enable_over) {
	if (document.iform.ipv6nat_enable.checked || enable_over)
		document.iform.ipv6nat_ipaddr.disabled = 0;
	else
		document.iform.ipv6nat_ipaddr.disabled = 1;
}

//]]>
</script>


<?
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
	<form action="system_advanced_network.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="system advanced newtwork">
			<tr>
				<td>
					<?php
						$tab_array = array();
						$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
						$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
						$tab_array[] = array(gettext("Networking"), true, "system_advanced_network.php");
						$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
						$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
						$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
						display_top_tabs($tab_array);
					?>
				</td>
			</tr>
			<tr>
				<td id="mainarea">
					<div class="tabcont">
						<span class="vexpl">
						<span class="red">
								<strong><?=gettext("NOTE:"); ?>&nbsp;</strong>
							</span>
							<?=gettext("The options on this page are intended for use by advanced users only."); ?>
							<br />
						</span>
						<br />
						<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("IPv6 Options"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Allow IPv6"); ?></td>
								<td width="78%" class="vtable">
									<input name="ipv6allow" type="checkbox" id="ipv6allow" value="yes" <?php if ($pconfig['ipv6allow']) echo "checked=\"checked\""; ?> onclick="enable_change(false)" />
									<strong><?=gettext("Allow IPv6"); ?></strong><br />
									<?=gettext("All IPv6 traffic will be blocked by the firewall unless this box is checked."); ?><br />
									<?=gettext("NOTE: This does not disable any IPv6 features on the firewall, it only blocks traffic."); ?><br />
									<br />
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 over IPv4 Tunneling"); ?></td>
								<td width="78%" class="vtable">
									<input name="ipv6nat_enable" type="checkbox" id="ipv6nat_enable" value="yes" <?php if ($pconfig['ipv6nat_enable']) echo "checked=\"checked\""; ?> onclick="enable_change(false)" />
									<strong><?=gettext("Enable IPv4 NAT encapsulation of IPv6 packets"); ?></strong><br />
									<?=gettext("This provides an RFC 2893 compatibility mechanism ".
									"that can be used to tunneling IPv6 packets over IPv4 ".
									"routing infrastructures. If enabled, don't forget to ".
									"add a firewall rule to permit IPv6 packets."); ?><br />
									<br />
									<?=gettext("IP address"); ?>&nbsp;:&nbsp;
									<input name="ipv6nat_ipaddr" type="text" class="formfld unknown" id="ipv6nat_ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipv6nat_ipaddr']);?>" />
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Prefer IPv4 over IPv6"); ?></td>
								<td width="78%" class="vtable">
									<input name="prefer_ipv4" type="checkbox" id="prefer_ipv4" value="yes" <?php if ($pconfig['prefer_ipv4']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Prefer to use IPv4 even if IPv6 is available"); ?></strong><br />
									<?=gettext("By default, if a hostname resolves IPv6 and IPv4 addresses ".
									"IPv6 will be used, if you check this option, IPv4 will be " .
									"used instead of IPv6."); ?><br />
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Network Interfaces"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Device polling"); ?></td>
								<td width="78%" class="vtable">
									<input name="polling_enable" type="checkbox" id="polling_enable" value="yes" <?php if ($pconfig['polling_enable']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Enable device polling"); ?></strong><br />
									<?php printf(gettext("Device polling is a technique that lets the system periodically poll network devices for new data instead of relying on interrupts. This prevents your webConfigurator, SSH, etc. from being inaccessible due to interrupt floods when under extreme load. Generally this is not recommended. Not all NICs support polling; see the %s homepage for a list of supported cards."), $g['product_name']); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Hardware Checksum Offloading"); ?></td>
								<td width="78%" class="vtable">
									<input name="disablechecksumoffloading" type="checkbox" id="disablechecksumoffloading" value="yes" <?php if (isset($config['system']['disablechecksumoffloading'])) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable hardware checksum offload"); ?></strong><br />
									<?=gettext("Checking this option will disable hardware checksum offloading. Checksum offloading is broken in some hardware, particularly some Realtek cards. Rarely, drivers may have problems with checksum offloading and some specific NICs."); ?>
									<br />
									<span class="red"><strong><?=gettext("Note:");?>&nbsp;</strong></span>
									<?=gettext("This will take effect after you reboot the machine or re-configure each interface.");?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Hardware TCP Segmentation Offloading"); ?></td>
								<td width="78%" class="vtable">
									<input name="disablesegmentationoffloading" type="checkbox" id="disablesegmentationoffloading" value="yes" <?php if (isset($config['system']['disablesegmentationoffloading'])) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable hardware TCP segmentation offload"); ?></strong><br />
									<?=gettext("Checking this option will disable hardware TCP segmentation offloading (TSO, TSO4, TSO6). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
									<br />
									<span class="red"><strong><?=gettext("Note:");?>&nbsp;</strong></span>
									<?=gettext("This will take effect after you reboot the machine or re-configure each interface.");?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Hardware Large Receive Offloading"); ?></td>
								<td width="78%" class="vtable">
									<input name="disablelargereceiveoffloading" type="checkbox" id="disablelargereceiveoffloading" value="yes" <?php if (isset($config['system']['disablelargereceiveoffloading'])) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable hardware large receive offload"); ?></strong><br />
									<?=gettext("Checking this option will disable hardware large receive offloading (LRO). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
									<br />
									<span class="red"><strong><?=gettext("Note:");?>&nbsp;</strong></span>
									<?=gettext("This will take effect after you reboot the machine or re-configure each interface.");?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("ARP Handling"); ?></td>
								<td width="78%" class="vtable">
									<input name="sharednet" type="checkbox" id="sharednet" value="yes" <?php if (isset($pconfig['sharednet'])) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Suppress ARP messages"); ?></strong><br />
									<?=gettext("This option will suppress ARP log messages when multiple interfaces reside on the same broadcast domain"); ?>
								</td>
							</tr>
<?php
/*
	$version = get_freebsd_version();
	if($version == "8"):

							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic">Flowtable support</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Enable Flowtable</td>
								<td width="78%" class="vtable">
									<input name="flowtable" type="checkbox" id="polling_enable" value="yes" <?php if ($pconfig['flowtable']) echo "checked=\"checked\""; ?> />
									<strong>Enable flowtable support</strong><br />
									Enables infrastructure for caching flows as a means of accelerating L3 and L2 lookups
									as well as providing stateful load balancing when used with RADIX_MPATH.<br />
								</td>
							</tr>
<?php endif; ?>
*/
?>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%"><input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" /></td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</form>
	<script type="text/javascript">
	//<![CDATA[
		enable_change(false);
	//]]>
	</script>

<?php include("fend.inc"); ?>
</body>
</html>
