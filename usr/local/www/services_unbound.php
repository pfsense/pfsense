<?php
/* $Id$ */
/*
	services_unbound.php
	part of the pfSense project (http://www.pfsense.com)
	Copyright (C) 2011	Warren Baker (warren@pfsense.org)
	All rights reserved.

	Copyright (C) 2003-2004 Bob Zoller <bob@kludgebox.com> and Manuel Kasper <mk@neon1.net>.
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
	pfSense_MODULE:	dnscache
*/

##|+PRIV
##|*IDENT=page-services-unbound
##|*NAME=Services: DNS Resolver page
##|*DESCR=Allow access to the 'Services: DNS Resolver' page.
##|*MATCH=services_unbound.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("unbound.inc");

$pconfig['enable'] = isset($config['unbound']['enable']);
$pconfig['active_interface'] = $config['unbound']['active_interface'];
$pconfig['outgoing_interface'] = $config['unbound']['outgoing_interface'];
$pconfig['dnssec'] = isset($config['unbound']['dnssec']);
$pconfig['forwarding'] = isset($config['unbound']['forwarding']);
$pconfig['regdhcp'] = isset($config['unbound']['regdhcp']);
$pconfig['regdhcpstatic'] = isset($config['unbound']['regdhcpstatic']);
$pconfig['dhcpfirst'] = isset($config['unbound']['dhcpfirst']);
$pconfig['port'] = isset($config['unbound']['port']);

if(!is_array($config['unbound']))
	$config['unbound'] = array();
$a_unboundcfg =& $config['unbound'];

if (!is_array($config['unbound']['hosts']))
	$config['unbound']['hosts'] = array();
$a_hosts =& $config['unbound']['hosts'];

if (!is_array($config['unbound']['domainoverrides']))
	$config['unbound']['domainoverrides'] = array();
$a_domainOverrides = &$config['unbound']['domainoverrides'];

if ($_POST) {

	unset($input_errors);

	if ($_POST['enable'] == "yes" && isset($config['dnsmasq']['enable']))
		$input_errors[] = "The system dns-forwarder is still active. Disable it before enabling the DNS Resolver.";

	if (empty($_POST['active_interface']))
		$input_errors[] = "A single network interface needs to be selected for the DNS Resolver to bind to.";

	if ($_POST['port'])
		if (is_port($_POST['port']))
			$a_unboundcfg['port'] = $_POST['port'];
		else
			$input_errors[] = gettext("You must specify a valid port number.");
	else if (isset($config['unbound']['port']))
		unset($config['unbound']['port']);

	$a_unboundcfg['enable'] = ($_POST['enable']) ? true : false;
	$a_unboundcfg['dnssec'] = ($_POST['dnssec']) ? true : false;
	$a_unboundcfg['forwarding'] = ($_POST['forwarding']) ? true : false;
	$a_unboundcfg['regdhcp'] = ($_POST['regdhcp']) ? true : false;
	$a_unboundcfg['regdhcpstatic'] = ($_POST['regdhcpstatic']) ? true : false;
	$a_unboundcfg['dhcpfirst'] = ($_POST['dhcpfirst']) ? true : false;
	if (is_array($_POST['active_interface']))
		$a_unboundcfg['active_interface'] = implode(",", $_POST['active_interface']);
	else
		$a_unboundcfg['active_interface'] = $_POST['active_interface'];
	if (is_array($_POST['outgoing_interface']))
		$a_unboundcfg['outgoing_interface'] = implode(",", $_POST['outgoing_interface']);
	else
		$a_unboundcfg['outgoing_interface'] = $_POST['outgoing_interface'];

	if (!$input_errors) {
		write_config("DNS Resolver configured.");
		$retval = 0;
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = array(gettext("Services"),gettext("DNS Resolver"));
include_once("head.inc");

?>

<script language="JavaScript">
<!--
function enable_change(enable_over) {
	var endis;
	endis = !(jQuery('#enable').is(":checked") || enable_over);
	jQuery("#active_interface,#outgoing_interface,#dnssec,#forwarding,#regdhcp,#regdhcpstatic,#dhcpfirst,#port").prop('disabled', endis);
}
function show_advanced_dns() {
	jQuery("#showadv").show();
	jQuery("#showadvbox").hide();
}
//-->
</script>
	
<body>
<?php include("fbegin.inc"); ?>
<form action="services_unbound.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('hosts')): ?><p>
<?php print_info_box_np(gettext("The configuration for the DNS Resolver, has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="services unbound">
	<tbody>
		<tr>
			<td class="tabnavtbl">
				<?php
					$tab_array = array();
					$tab_array[] = array(gettext("General settings"), true, "services_unbound.php");
					$tab_array[] = array(gettext("Advanced settings"), false, "services_unbound_advanced.php");
					$tab_array[] = array(gettext("Access Lists"), false, "/services_unbound_acls.php");
					display_top_tabs($tab_array, true);
				?>
			</td>
		</tr>
		<tr>
			<td id="mainarea">
				<div class="tabcont">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tbody>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("General DNS Resolver Options");?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Enable");?></td>
								<td width="78%" class="vtable"><p>
									<input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable'] == "yes") echo "checked";?> onClick="enable_change(false)">
									<strong><?=gettext("Enable DNS Resolver");?><br />
									</strong></p></td>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Network Interfaces"); ?></td>
								<td width="78%" class="vtable">
									<?php
										$interface_addresses = get_possible_listen_ips(true);
										$size=count($interface_addresses)+1;
									?>
									<?=gettext("Interface IPs used by the DNS Resolver for responding to queries from clients. If an interface has both IPv4 and IPv6 IPs, both are used. Queries to other interface IPs not selected below are discarded. The default behavior is to respond to queries on every available IPv4 and IPv6 address.");?>
									<br /><br />
									<select id="active_interface" name="active_interface[]" multiple="true" size="3">
										<option value="" <?php if (empty($pconfig['interface'])) echo 'selected="selected"'; ?>>All</option>
										<?php  foreach ($interface_addresses as $laddr):
												$selected = "";
												if (in_array($laddr['value'], $pconfig['interface']))
													$selected = 'selected="selected"';
										?>
										<option value="<?=$laddr['value'];?>" <?=$selected;?>>
											<?=htmlspecialchars($laddr['name']);?>
										</option>
										<?php endforeach; ?>
									</select>
									<br /><br />
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Outgoing Network Interfaces"); ?></td>
								<td width="78%" class="vtable">
									<?php
										$interface_addresses = get_possible_listen_ips(true);
										$size=count($interface_addresses)+1;
									?>
									<?=gettext("Utilize different network interface(s) that the DNS Resolver will use to send queries to authoritative servers and receive their replies. By default all interfaces are used.");?>
									<br /><br />
									<select id="outgoing_interface" name="outgoing_interface[]" multiple="true" size="3">
										<option value="" <?php if (empty($pconfig['interface'])) echo 'selected="selected"'; ?>>All</option>
										<?php  foreach ($interface_addresses as $laddr):
												$selected = "";
												if (in_array($laddr['value'], $pconfig['interface']))
												$selected = 'selected="selected"';
										?>
										<option value="<?=$laddr['value'];?>" <?=$selected;?>>
											<?=htmlspecialchars($laddr['name']);?>
										</option>
										<?php endforeach; ?>
									</select>
									<br /><br />
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("DNSSEC");?></td>
								<td width="78%" class="vtable"><p>
									<input name="dnssec" type="checkbox" id="dnssec" value="yes" <?php if ($pconfig['dnssec'] === true) echo "checked";?>/>
									<strong><?=gettext("Enable DNSSEC Support");?><br />
									</strong></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("DNS Query Forwarding");?></td>
								<td width="78%" class="vtable"><p>
									<input name="forwarding" type="checkbox" id="forwarding" value="yes" <?php if ($pconfig['forwarding'] == "yes") echo "checked";?>>
									<strong><?=gettext("Enable Forwarding Mode");?></strong><br /></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("DHCP Registration");?></td>
								<td width="78%" class="vtable"><p>
									<input name="regdhcp" type="checkbox" id="regdhcp" value="yes" <?php if ($pconfig['regdhcp'] === true) echo "checked";?>>
									<strong><?=gettext("Register DHCP leases in the DNS Resolver");?><br />
									</strong><?php printf(gettext("If this option is set, then machines that specify".
									" their hostname when requesting a DHCP lease will be registered".
									" in the DNS Resolver, so that their name can be resolved.".
									" You should also set the domain in %sSystem:".
									" General setup%s to the proper value."),'<a href="system.php">','</a>')?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Static DHCP");?></td>
								<td width="78%" class="vtable"><p>
									<input name="regdhcpstatic" type="checkbox" id="regdhcpstatic" value="yes" <?php if ($pconfig['regdhcpstatic'] === true) echo "checked";?>>
									<strong><?=gettext("Register DHCP static mappings in the DNS Resolver");?><br />
									</strong><?php printf(gettext("If this option is set, then DHCP static mappings will ".
											"be registered in the DNS Resolver, so that their name can be ".
											"resolved. You should also set the domain in %s".
											"System: General setup%s to the proper value."),'<a href="system.php">','</a>');?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Prefer DHCP");?></td>
								<td width="78%" class="vtable"><p>
									<input name="dhcpfirst" type="checkbox" id="dhcpfirst" value="yes" <?php if ($pconfig['dhcpfirst'] === true) echo "checked";?>>
									<strong><?=gettext("Resolve DHCP mappings first");?><br />
									</strong><?php printf(gettext("If this option is set, then DHCP mappings will ".
											"be resolved before the manual list of names below. This only ".
											"affects the name given for a reverse lookup (PTR)."));?></p>
								</td>
							</tr>	
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Listen Port");?></td>
								<td width="78%" class="vtable"><p>
									<input name="port" type="text" id="port" size="6" <?php if ($pconfig['port']) echo "value=\"{$pconfig['port']}\"";?>>
									<br /><br />
									<?=gettext("The port used for responding to DNS queries. It should normally be left blank unless another service needs to bind to TCP/UDP port 53.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Advanced");?></td>
								<td width="78%" class="vtable"><p>
									<div id="showadvbox" <?php if ($pconfig['custom_options']) echo "style='display:none'"; ?>>
										<input type="button" onClick="show_advanced_dns()" value="<?=gettext("Advanced"); ?>"></input> - <?=gettext("Show advanced option");?></a>
									</div>
									<div id="showadv" <?php if (empty($pconfig['custom_options'])) echo "style='display:none'"; ?>>
										<strong><?=gettext("Advanced");?><br /></strong>
										<textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=htmlspecialchars($pconfig['custom_options']);?></textarea><br />
										<?=gettext("Enter any additional options you would like to add to the DNS Resolver configuration here, separated by a space or newline"); ?><br />
									</div>
									</p>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<input name="submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onclick="enable_change(true)">
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</td>
		</tr>
	</tbody>
</table>


<p><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br />
</strong></span><?php printf(gettext("If the DNS Resolver is enabled, the DHCP".
" service (if enabled) will automatically serve the LAN IP".
" address as a DNS server to DHCP clients so they will use".
" the DNS Resolver. If Forwarding, is enabled, the DNS Resolver will use the DNS servers".
" entered in %sSystem: General setup%s".
" or those obtained via DHCP or PPP on WAN if the &quot;Allow".
" DNS server list to be overridden by DHCP/PPP on WAN&quot;".
" is checked."),'<a href="system.php">','</a>');?><br />
</span></p>

&nbsp;<br />
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont">
<tr>
	<td colspan="5" valign="top" class="listtopic"><?=gettext("Host Overrides");?></td>
</tr>
<tr>
	<td><br />
	<?=gettext("Entries in this section override individual results from the forwarders.");?>
	<?=gettext("Use these for changing DNS results or for adding custom DNS records.");?>
	</td>
</tr>
</table>
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont sortable">
	<thead>
	<tr>
		<td width="20%" class="listhdrr"><?=gettext("Host");?></td>
		<td width="25%" class="listhdrr"><?=gettext("Domain");?></td>
		<td width="20%" class="listhdrr"><?=gettext("IP");?></td>
		<td width="25%" class="listhdr"><?=gettext("Description");?></td>
		<td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td width="17"></td>
					<td valign="middle"><a href="services_unbound_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
			</table>
		</td>
	</tr>
	</thead>
	<tbody>
	<?php $i = 0; foreach ($a_hosts as $hostent): ?>
	<tr>
		<td class="listlr" ondblclick="document.location='services_unbound_edit.php?id=<?=$i;?>';">
			<?=strtolower($hostent['host']);?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='services_unbound_edit.php?id=<?=$i;?>';">
			<?=strtolower($hostent['domain']);?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='services_unbound_edit.php?id=<?=$i;?>';">
			<?=$hostent['ip'];?>&nbsp;
		</td>
		<td class="listbg" ondblclick="document.location='services_unbound_edit.php?id=<?=$i;?>';">
			<?=htmlspecialchars($hostent['descr']);?>&nbsp;
		</td>
		<td valign="middle" nowrap class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td valign="middle"><a href="services_unbound_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
					<td><a href="services_unbound.php?type=host&act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this host?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			</table>
	</tr>
	<?php $i++; endforeach; ?>
	</tbody>
	<tfoot>
	<tr>
		<td class="list" colspan="4"></td>
		<td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td width="17"></td>
					<td valign="middle"><a href="services_unbound_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
			</table>
		</td>
	</tr>
	</tfoot>
</table>
<br />
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont">
<tr>
	<td colspan="5" valign="top" class="listtopic"><?=gettext("Domain Overrides");?></td>
</tr>
<tr>
	<tr>
		<td><p><?=gettext("Entries in this area override an entire domain by specifying an".
		" authoritative DNS server to be queried for that domain.");?></p></td>
	</tr>
</tr>
</table>
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont sortable">
	<thead>
	<tr>
		<td width="35%" class="listhdrr"><?=gettext("Domain");?></td>
		<td width="20%" class="listhdrr"><?=gettext("IP");?></td>
		<td width="35%" class="listhdr"><?=gettext("Description");?></td>
		<td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td width="17" heigth="17"></td>
					<td><a href="services_unbound_domainoverride_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
			</table>
		</td>
	</tr>
	</thead>
	<tbody>
	<?php $i = 0; foreach ($a_domainOverrides as $doment): ?>
	<tr>
		<td class="listlr">
			<?=strtolower($doment['domain']);?>&nbsp;
		</td>
		<td class="listr">
			<?=$doment['ip'];?>&nbsp;
		</td>
		<td class="listbg">
			<?=htmlspecialchars($doment['descr']);?>&nbsp;
		</td>
		<td valign="middle" nowrap class="list"> <a href="services_unbound_domainoverride_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
			&nbsp;<a href="services_unbound.php?act=del&type=doverride&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this domain override?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
	</tr>
	<?php $i++; endforeach; ?>
	</tbody>
	<tfoot>
	<tr>
		<td class="list" colspan="3"></td>
		<td class="list">
		<table border="0" cellspacing="0" cellpadding="1">
			<tr>
				<td width="17" heigth="17"></td>
				<td><a href="services_unbound_domainoverride_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
			</tr>
		</table>
		</td>
	</tr>
	</tfoot>
</table>
</form>
<script language="JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
