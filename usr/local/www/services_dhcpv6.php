<?php
/* $Id$ */
/*
	services_dhcpv6.php
	parts of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	part of pfSense (http://www.pfsense.org)
	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
	pfSense_BUILDER_BINARIES:	/bin/rm
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-services-dhcpv6server
##|*NAME=Services: DHCPv6 server page
##|*DESCR=Allow access to the 'Services: DHCPv6 server' page.
##|*MATCH=services_dhcpv6.php*
##|-PRIV

require("guiconfig.inc");

if(!$g['services_dhcp_server_enable']) {
	Header("Location: /");
	exit;
}

/*  Fix failover DHCP problem
 *  http://article.gmane.org/gmane.comp.security.firewalls.pfsense.support/18749
 */
ini_set("memory_limit","64M");

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];

/* if OLSRD is enabled, allow WAN to house DHCP. */
if($config['installedpackages']['olsrd']) {
	foreach($config['installedpackages']['olsrd']['config'] as $olsrd) {
			if($olsrd['enable']) {
				$is_olsr_enabled = true;
				break;
			}
	}
}

if (!$_GET['if'])
	$savemsg = "<p><b>" . gettext("The DHCPv6 Server can only be enabled on interfaces configured with static IP addresses") . ".</b></p>" .
		   "<p><b>" . gettext("Only interfaces configured with a static IP will be shown") . ".</b></p>";

$iflist = get_configured_interface_with_descr();
$iflist = array_merge($iflist, get_configured_pppoe_server_interfaces());

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!preg_match("/fe80::/", $oc['ipaddrv6'])))) ||
			(!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!preg_match("/fe80::/", $oc['ipaddrv6'])))))
			continue;
		$if = $ifent;
		break;
	}
}

if (is_array($config['dhcpdv6'][$if])){
	/* DHCPv6 */
	if (is_array($config['dhcpdv6'][$if]['range'])) {
		$pconfig['range_from'] = $config['dhcpdv6'][$if]['range']['from'];
		$pconfig['range_to'] = $config['dhcpdv6'][$if]['range']['to'];
	}
	if (is_array($config['dhcpdv6'][$if]['prefixrange'])) {
		$pconfig['prefixrange_from'] = $config['dhcpdv6'][$if]['prefixrange']['from'];
		$pconfig['prefixrange_to'] = $config['dhcpdv6'][$if]['prefixrange']['to'];
		$pconfig['prefixrange_length'] = $config['dhcpdv6'][$if]['prefixrange']['prefixlength'];
	}
	$pconfig['deftime'] = $config['dhcpdv6'][$if]['defaultleasetime'];
	$pconfig['maxtime'] = $config['dhcpdv6'][$if]['maxleasetime'];
	$pconfig['domain'] = $config['dhcpdv6'][$if]['domain'];
	$pconfig['domainsearchlist'] = $config['dhcpdv6'][$if]['domainsearchlist'];
	list($pconfig['wins1'],$pconfig['wins2']) = $config['dhcpdv6'][$if]['winsserver'];
	list($pconfig['dns1'],$pconfig['dns2']) = $config['dhcpdv6'][$if]['dnsserver'];
	$pconfig['enable'] = isset($config['dhcpdv6'][$if]['enable']);
	$pconfig['denyunknown'] = isset($config['dhcpdv6'][$if]['denyunknown']);
	$pconfig['ddnsdomain'] = $config['dhcpdv6'][$if]['ddnsdomain'];
	$pconfig['ddnsupdate'] = isset($config['dhcpdv6'][$if]['ddnsupdate']);
	list($pconfig['ntp1'],$pconfig['ntp2']) = $config['dhcpdv6'][$if]['ntpserver'];
	$pconfig['tftp'] = $config['dhcpdv6'][$if]['tftp'];
	$pconfig['ldap'] = $config['dhcpdv6'][$if]['ldap'];
	$pconfig['netboot'] = isset($config['dhcpdv6'][$if]['netboot']);
	$pconfig['nextserver'] = $config['dhcpdv6'][$if]['nextserver'];
	$pconfig['filename'] = $config['dhcpdv6'][$if]['filename'];
	$pconfig['rootpath'] = $config['dhcpdv6'][$if]['rootpath'];
	$pconfig['netmask'] = $config['dhcpdv6'][$if]['netmask'];
	$pconfig['numberoptions'] = $config['dhcpdv6'][$if]['numberoptions'];
	$pconfig['dhcpv6leaseinlocaltime'] = $config['dhcpdv6'][$if]['dhcpv6leaseinlocaltime'];
	if (!is_array($config['dhcpdv6'][$if]['staticmap']))
		$config['dhcpdv6'][$if]['staticmap'] = array();
	$a_maps = &$config['dhcpdv6'][$if]['staticmap'];
}

$ifcfgip = get_interface_ipv6($if);
$ifcfgsn = get_interface_subnetv6($if);

/*   set the enabled flag which will tell us if DHCP relay is enabled
 *   on any interface. We will use this to disable DHCP server since
 *   the two are not compatible with each other.
 */

$dhcrelay_enabled = false;
$dhcrelaycfg = $config['dhcrelay6'];

if(is_array($dhcrelaycfg)) {
	foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
		if (isset($dhcrelayifconf['enable']) && isset($iflist[$dhcrelayif]) &&
			(!link_interface_to_bridge($dhcrelayif)))
			$dhcrelay_enabled = true;
	}
}

function is_inrange($test, $start, $end) {
	if ( (inet_pton($test) < inet_pton($end)) && (inet_pton($test) > inet_pton($start)) )
		return true;
	else
		return false;
}

if ($_POST) {

	unset($input_errors);

	$pconfig = $_POST;

	$numberoptions = array();
	for($x=0; $x<99; $x++) {
		if(isset($_POST["number{$x}"]) && ctype_digit($_POST["number{$x}"])) {
			$numbervalue = array();
			$numbervalue['number'] = htmlspecialchars($_POST["number{$x}"]);
			$numbervalue['value'] = htmlspecialchars($_POST["value{$x}"]);
			$numberoptions['item'][] = $numbervalue;
		}
	}
	// Reload the new pconfig variable that the forum uses.
	$pconfig['numberoptions'] = $numberoptions;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "range_from range_to");
		$reqdfieldsn = array(gettext("Range begin"),gettext("Range end"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if (($_POST['prefixrange_from'] && !is_ipaddrv6($_POST['prefixrange_from'])))
			$input_errors[] = gettext("A valid range must be specified.");
		if (($_POST['prefixrange_to'] && !is_ipaddrv6($_POST['prefixrange_to'])))
			$input_errors[] = gettext("A valid prefix range must be specified.");
		if (($_POST['range_from'] && !is_ipaddrv6($_POST['range_from'])))
			$input_errors[] = gettext("A valid range must be specified.");
		if (($_POST['range_to'] && !is_ipaddrv6($_POST['range_to'])))
			$input_errors[] = gettext("A valid range must be specified.");
		if (($_POST['gateway'] && !is_ipaddrv6($_POST['gateway'])))
			$input_errors[] = gettext("A valid IPv6 address must be specified for the gateway.");
		if (($_POST['dns1'] && !is_ipaddrv6($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddrv6($_POST['dns2'])))
			$input_errors[] = gettext("A valid IPv6 address must be specified for the primary/secondary DNS servers.");

		if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60)))
			$input_errors[] = gettext("The default lease time must be at least 60 seconds.");
		if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime'])))
			$input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
		if (($_POST['ddnsdomain'] && !is_domain($_POST['ddnsdomain'])))
			$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
		if ($_POST['domainsearchlist']) {
			$domain_array=preg_split("/[ ;]+/",$_POST['domainsearchlist']);
			foreach ($domain_array as $curdomain) {
				if (!is_domain($curdomain)) {
					$input_errors[] = gettext("A valid domain search list must be specified.");
					break;
				}
			}
		}

		if (($_POST['ntp1'] && !is_ipaddrv6($_POST['ntp1'])) || ($_POST['ntp2'] && !is_ipaddrv6($_POST['ntp2'])))
			$input_errors[] = gettext("A valid IPv6 address must be specified for the primary/secondary NTP servers.");
		if (($_POST['domain'] && !is_domain($_POST['domain'])))
			$input_errors[] = gettext("A valid domain name must be specified for the DNS domain.");
		if ($_POST['tftp'] && !is_ipaddr($_POST['tftp']) && !is_domain($_POST['tftp']) && !is_URL($_POST['tftp']))
			$input_errors[] = gettext("A valid IPv6 address or hostname must be specified for the TFTP server.");
		if (($_POST['nextserver'] && !is_ipaddrv6($_POST['nextserver'])))
			$input_errors[] = gettext("A valid IPv6 address must be specified for the network boot server.");

		// Disallow a range that includes the virtualip
		if (is_array($config['virtualip']['vip'])) {
			foreach($config['virtualip']['vip'] as $vip) {
				if($vip['interface'] == $if)
					if($vip['subnetv6'] && is_inrange($vip['subnetv6'], $_POST['range_from'], $_POST['range_to']))
						$input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IPv6 address %s."),$vip['subnetv6']);
			}
		}

		$noip = false;
		if(is_array($a_maps))
			foreach ($a_maps as $map)
				if (empty($map['ipaddrv6']))
					$noip = true;
		if (!$input_errors) {
			/* make sure the range lies within the current subnet */
			$subnet_start = gen_subnetv6($ifcfgip, $ifcfgsn);
			$subnet_end = gen_subnetv6_max($ifcfgip, $ifcfgsn);

			if (is_ipaddrv6($ifcfgip)) {
				if ((! is_inrange($_POST['range_from'], $subnet_start, $subnet_end)) ||
			   	 (! is_inrange($_POST['range_to'], $subnet_start, $subnet_end))) {
					$input_errors[] = gettext("The specified range lies outside of the current subnet.");
				}
			}
			/* "from" cannot be higher than "to" */
			if (inet_pton($_POST['range_from']) > inet_pton($_POST['range_to']))
				$input_errors[] = gettext("The range is invalid (first element higher than second element).");

			/* make sure that the DHCP Relay isn't enabled on this interface */
			if (isset($config['dhcrelay'][$if]['enable']))
				$input_errors[] = sprintf(gettext("You must disable the DHCP relay on the %s interface before enabling the DHCP server."),$iflist[$if]);


			/* Verify static mappings do not overlap:
			   - available DHCP range
			   - prefix delegation range (FIXME: still need to be completed) */
			$dynsubnet_start = inet_pton($_POST['range_from']);
			$dynsubnet_end = inet_pton($_POST['range_to']);

			if(is_array($a_maps)) {
				foreach ($a_maps as $map) {
					if (empty($map['ipaddrv6']))
						continue;
					if ((inet_pton($map['ipaddrv6']) > $dynsubnet_start) &&
						(inet_pton($map['ipaddrv6']) < $dynsubnet_end)) {
						$input_errors[] = sprintf(gettext("The DHCP range cannot overlap any static DHCP mappings."));
						break;
					}
				}
			}
		}
	}

	if (!$input_errors) {
		if (!is_array($config['dhcpdv6'][$if]))
			$config['dhcpdv6'][$if] = array();
		if (!is_array($config['dhcpdv6'][$if]['range']))
			$config['dhcpdv6'][$if]['range'] = array();
		if (!is_array($config['dhcpdv6'][$if]['prefixrange']))
			$config['dhcpdv6'][$if]['prefixrange'] = array();

		$config['dhcpdv6'][$if]['range']['from'] = $_POST['range_from'];
		$config['dhcpdv6'][$if]['range']['to'] = $_POST['range_to'];
		$config['dhcpdv6'][$if]['prefixrange']['from'] = $_POST['prefixrange_from'];
		$config['dhcpdv6'][$if]['prefixrange']['to'] = $_POST['prefixrange_to'];
		$config['dhcpdv6'][$if]['prefixrange']['prefixlength'] = $_POST['prefixrange_length'];
		$config['dhcpdv6'][$if]['defaultleasetime'] = $_POST['deftime'];
		$config['dhcpdv6'][$if]['maxleasetime'] = $_POST['maxtime'];
		$config['dhcpdv6'][$if]['netmask'] = $_POST['netmask'];
		$previous = $config['dhcpdv6'][$if]['failover_peerip'];
		if($previous <> $_POST['failover_peerip'])
			mwexec("/bin/rm -rf /var/dhcpd/var/db/*");

		$config['dhcpdv6'][$if]['failover_peerip'] = $_POST['failover_peerip'];

		unset($config['dhcpdv6'][$if]['winsserver']);

		unset($config['dhcpdv6'][$if]['dnsserver']);
		if ($_POST['dns1'])
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns1'];
		if ($_POST['dns2'])
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns2'];

		$config['dhcpdv6'][$if]['domain'] = $_POST['domain'];
		$config['dhcpdv6'][$if]['domainsearchlist'] = $_POST['domainsearchlist'];
		$config['dhcpdv6'][$if]['denyunknown'] = ($_POST['denyunknown']) ? true : false;
		$config['dhcpdv6'][$if]['enable'] = ($_POST['enable']) ? true : false;
		$config['dhcpdv6'][$if]['ddnsdomain'] = $_POST['ddnsdomain'];
		$config['dhcpdv6'][$if]['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;

		unset($config['dhcpdv6'][$if]['ntpserver']);
		if ($_POST['ntp1'])
			$config['dhcpdv6'][$if]['ntpserver'][] = $_POST['ntp1'];
		if ($_POST['ntp2'])
			$config['dhcpdv6'][$if]['ntpserver'][] = $_POST['ntp2'];

		$config['dhcpdv6'][$if]['tftp'] = $_POST['tftp'];
		$config['dhcpdv6'][$if]['ldap'] = $_POST['ldap'];
		$config['dhcpdv6'][$if]['netboot'] = ($_POST['netboot']) ? true : false;
		$config['dhcpdv6'][$if]['nextserver'] = $_POST['nextserver'];
		$config['dhcpdv6'][$if]['filename'] = $_POST['filename'];
		$config['dhcpdv6'][$if]['rootpath'] = $_POST['rootpath'];
		$config['dhcpdv6'][$if]['dhcpv6leaseinlocaltime'] = $_POST['dhcpv6leaseinlocaltime'];

		// Handle the custom options rowhelper
		if(isset($config['dhcpdv6'][$if]['numberoptions']['item']))
			unset($config['dhcpdv6'][$if]['numberoptions']['item']);

		$config['dhcpdv6'][$if]['numberoptions'] = $numberoptions;

		write_config();

		$retval = 0;
		$retvaldhcp = 0;
		$retvaldns = 0;
		/* Stop DHCPv6 so we can cleanup leases */
		killbyname("dhcpd -6");
		// dhcp_clean_leases();
		/* dnsmasq_configure calls dhcpd_configure */
		/* no need to restart dhcpd twice */
		if (isset($config['dnsmasq']['regdhcpstatic']))	{
			$retvaldns = services_dnsmasq_configure();
			if ($retvaldns == 0) {
				clear_subsystem_dirty('hosts');
				clear_subsystem_dirty('staticmaps');
			}
		} else {
			$retvaldhcp = services_dhcpd_configure();
			if ($retvaldhcp == 0)
				clear_subsystem_dirty('staticmaps');
		}
		if($retvaldhcp == 1 || $retvaldns == 1)
			$retval = 1;
		$savemsg = get_std_save_message($retval);
	}
}

if ($_GET['act'] == "del") {
	if ($a_maps[$_GET['id']]) {
		unset($a_maps[$_GET['id']]);
		write_config();
		if(isset($config['dhcpdv6'][$if]['enable'])) {
			mark_subsystem_dirty('staticmapsv6');
			if (isset($config['dnsmasq']['regdhcpstaticv6']))
				mark_subsystem_dirty('hosts');
		}
		header("Location: services_dhcpv6.php?if={$if}");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("DHCPv6 server"));
$shortcut_section = "dhcp6";

include("head.inc");

?>

<script type="text/javascript" src="/javascript/row_helper.js">
</script>

<script type="text/javascript">
	rowname[0] = "number";
	rowtype[0] = "textbox";
	rowsize[0] = "10";
	rowname[1] = "value";
	rowtype[1] = "textbox";
	rowsize[1] = "55";
</script>

<script type="text/javascript" language="JavaScript">
	function enable_change(enable_over) {
		var endis;
		endis = !(document.iform.enable.checked || enable_over);
		document.iform.range_from.disabled = endis;
		document.iform.range_to.disabled = endis;
		document.iform.prefixrange_from.disabled = endis;
		document.iform.prefixrange_to.disabled = endis;
		document.iform.prefixrange_length.disabled = endis;
		document.iform.dns1.disabled = endis;
		document.iform.dns2.disabled = endis;
		document.iform.deftime.disabled = endis;
		document.iform.maxtime.disabled = endis;
		//document.iform.gateway.disabled = endis;
		document.iform.failover_peerip.disabled = endis;
		document.iform.dhcpv6leaseinlocaltime.disabled = endis;
		document.iform.domain.disabled = endis;
		document.iform.domainsearchlist.disabled = endis;
		document.iform.ddnsdomain.disabled = endis;
		document.iform.ddnsupdate.disabled = endis;
		document.iform.ntp1.disabled = endis;
		document.iform.ntp2.disabled = endis;
		//document.iform.tftp.disabled = endis;
		document.iform.ldap.disabled = endis;
		document.iform.netboot.disabled = endis;
		document.iform.nextserver.disabled = endis;
		document.iform.filename.disabled = endis;
		document.iform.rootpath.disabled = endis;
		document.iform.denyunknown.disabled = endis;
	}

	function show_shownumbervalue() {
		document.getElementById("shownumbervaluebox").innerHTML='';
		aodiv = document.getElementById('shownumbervalue');
		aodiv.style.display = "block";
	}

	function show_ddns_config() {
		document.getElementById("showddnsbox").innerHTML='';
		aodiv = document.getElementById('showddns');
		aodiv.style.display = "block";
	}
	function show_ntp_config() {
		document.getElementById("showntpbox").innerHTML='';
		aodiv = document.getElementById('showntp');
		aodiv.style.display = "block";
	}
	/*
	function show_tftp_config() {
		document.getElementById("showtftpbox").innerHTML='';
		aodiv = document.getElementById('showtftp');
		aodiv.style.display = "block";
	}
	*/
	function show_ldap_config() {
		document.getElementById("showldapbox").innerHTML='';
		aodiv = document.getElementById('showldap');
		aodiv.style.display = "block";
	}

	function show_netboot_config() {
		document.getElementById("shownetbootbox").innerHTML='';
		aodiv = document.getElementById('shownetboot');
		aodiv.style.display = "block";
	}
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_dhcpv6.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php
	if ($dhcrelay_enabled) {
		echo gettext("DHCP Relay is currently enabled. Cannot enable the DHCP Server service while the DHCP Relay is enabled on any interface.");
		include("fend.inc");
		echo "</body>";
		echo "</html>";
		exit;
	}
?>
<?php if (is_subsystem_dirty('staticmaps')): ?><p>
<?php print_info_box_np(gettext("The static mapping configuration has been changed") . ".<br>" . gettext("You must apply the changes in order for them to take effect."));?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
	/* active tabs */
	$tab_array = array();
	$tabscounter = 0;
	$i = 0;
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!preg_match("/fe80::/", $oc['ipaddrv6'])))) ||
			(!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!preg_match("/fe80::/", $oc['ipaddrv6'])))))
			continue;
		if ($ifent == $if)
			$active = true;
		else
			$active = false;
		$tab_array[] = array($ifname, $active, "services_dhcpv6.php?if={$ifent}");
		$tabscounter++;
	}
	/* tack on PPPoE or PPtP servers here */
	/* pppoe server */
	if (is_array($config['pppoes']['pppoe'])) {
		foreach($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				$ifent = "poes". $pppoe['pppoeid'];
				$ifname = strtoupper($ifent);
				if ($ifent == $if)
					$active = true;
				else
					$active = false;
				$tab_array[] = array($ifname, $active, "services_dhcpv6.php?if={$ifent}");
				$tabscounter++;
			}
		}
	}
	if ($tabscounter == 0) {
		echo "</td></tr></table></form>";
		include("fend.inc");
		echo "</body>";
		echo "</html>";
		exit;
	}
	display_top_tabs($tab_array);
?>
</td></tr>
<tr><td class="tabnavtbl">
<?php
$tab_array = array();
$tab_array[] = array(gettext("DHCPv6 Server"),         true,  "services_dhcpv6.php?if={$if}");
$tab_array[] = array(gettext("Router Advertisements"), false, "services_router_advertisements.php?if={$if}");
display_top_tabs($tab_array);
?>
</td></tr>
<tr>
<td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("DHCPv6 Server");?></td>
			<td width="78%" class="vtable">
				<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false);">
			<strong><?php printf(gettext("Enable DHCPv6 server on " .
			"%s " .
			"interface"),htmlspecialchars($iflist[$if]));?></strong></td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vtable">&nbsp;</td>
			<td width="78%" class="vtable">
				<input name="denyunknown" id="denyunknown" type="checkbox" value="yes" <?php if ($pconfig['denyunknown']) echo "checked"; ?>>
				<strong><?=gettext("Deny unknown clients");?></strong><br>
				<?=gettext("If this is checked, only the clients defined below will get DHCP leases from this server. ");?></td>
			</tr>
			<tr>
			<?php
			/* the PPPoE Server could well have no IPv6 address and operate fine with just link-local, just hide these */
			if(is_ipaddrv6($ifcfgip)) {
			?>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Subnet");?></td>
			<td width="78%" class="vtable">
				<?=gen_subnetv6($ifcfgip, $ifcfgsn);?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Subnet mask");?></td>
			<td width="78%" class="vtable">
				<?=$ifcfgsn;?> bits
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Available range");?></td>
			<td width="78%" class="vtable">
			<?php
				$range_from = gen_subnetv6($ifcfgip, $ifcfgsn);
				$range_from++;
				echo $range_from;

			?>
			-
			<?php
				$range_to = gen_subnetv6_max($ifcfgip, $ifcfgsn);
				echo $range_to;
			?>
			</td>
			</tr>
			<?php } ?>

			<?php if($is_olsr_enabled): ?>
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Subnet Mask");?></td>
			<td width="78%" class="vtable">
				<select name="netmask" class="formselect" id="netmask">
				<?php
				for ($i = 128; $i > 0; $i--) {
					if($i <> 127) {
						echo "<option value=\"{$i}\" ";
						if ($i == $pconfig['netmask']) echo "selected";
						echo ">" . $i . "</option>";
					}
				}
				?>
				</select>
			</td>
			</tr>
			<?php endif; ?>
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Range");?></td>
			<td width="78%" class="vtable">
				<input name="range_from" type="text" class="formfld unknown" id="range_from" size="28" value="<?=htmlspecialchars($pconfig['range_from']);?>">
				&nbsp;<?=gettext("to"); ?>&nbsp; <input name="range_to" type="text" class="formfld unknown" id="range_to" size="28" value="<?=htmlspecialchars($pconfig['range_to']);?>">
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Prefix Delegation Range");?></td>
			<td width="78%" class="vtable">
				<input name="prefixrange_from" type="text" class="formfld unknown" id="prefixrange_from" size="28" value="<?=htmlspecialchars($pconfig['prefixrange_from']);?>">
				&nbsp;<?=gettext("to"); ?>&nbsp; <input name="prefixrange_to" type="text" class="formfld unknown" id="prefixrange_to" size="28" value="<?=htmlspecialchars($pconfig['prefixrange_to']);?>">
				&nbsp;<?=gettext("prefix delegation size"); ?>&nbsp; <select name="prefixrange_length" class="formselect" id="prefixrange_length">
					<option value="48" <?php if($pconfig['prefixrange_length'] == 48) echo "selected"; ?>>48</option>
					<option value="52" <?php if($pconfig['prefixrange_length'] == 52) echo "selected"; ?>>52</option>
					<option value="56" <?php if($pconfig['prefixrange_length'] == 56) echo "selected"; ?>>56</option>
					<option value="60" <?php if($pconfig['prefixrange_length'] == 60) echo "selected"; ?>>60</option>
					<option value="62" <?php if($pconfig['prefixrange_length'] == 62) echo "selected"; ?>>62</option>
					<option value="63" <?php if($pconfig['prefixrange_length'] == 63) echo "selected"; ?>>63</option>
					<option value="64" <?php if($pconfig['prefixrange_length'] == 64) echo "selected"; ?>>64</option>
				</select> <br/>
				<?php echo gettext("You can define a Prefix range here for DHCP Prefix Delegation. This allows for 
					assigning networks to subrouters. The start and end of the range must end on boundaries of the prefix delegation size."); ?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("DNS servers");?></td>
			<td width="78%" class="vtable">
				<input name="dns1" type="text" class="formfld unknown" id="dns1" size="28" value="<?=htmlspecialchars($pconfig['dns1']);?>"><br>
				<input name="dns2" type="text" class="formfld unknown" id="dns2" size="28" value="<?=htmlspecialchars($pconfig['dns2']);?>"><br>
				<?=gettext("NOTE: leave blank to use the system default DNS servers - this interface's IP if DNS forwarder is enabled, otherwise the servers configured on the General page.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Domain name");?></td>
			<td width="78%" class="vtable">
				<input name="domain" type="text" class="formfld unknown" id="domain" size="28" value="<?=htmlspecialchars($pconfig['domain']);?>"><br>
				 <?=gettext("The default is to use the domain name of this system as the default domain name provided by DHCP. You may specify an alternate domain name here.");?>
			 </td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Domain search list");?></td>
			<td width="78%" class="vtable">
				<input name="domainsearchlist" type="text" class="formfld unknown" id="domainsearchlist" size="28" value="<?=htmlspecialchars($pconfig['domainsearchlist']);?>"><br>
				<?=gettext("The DHCP server can optionally provide a domain search list. Use the semicolon character as seperator");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Default lease time");?></td>
			<td width="78%" class="vtable">
				<input name="deftime" type="text" class="formfld unknown" id="deftime" size="10" value="<?=htmlspecialchars($pconfig['deftime']);?>">
				<?=gettext("seconds");?><br>
				<?=gettext("This is used for clients that do not ask for a specific " .
				"expiration time."); ?><br>
				<?=gettext("The default is 7200 seconds.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Maximum lease time");?></td>
			<td width="78%" class="vtable">
				<input name="maxtime" type="text" class="formfld unknown" id="maxtime" size="10" value="<?=htmlspecialchars($pconfig['maxtime']);?>">
				<?=gettext("seconds");?><br>
				<?=gettext("This is the maximum lease time for clients that ask".
				" for a specific expiration time."); ?><br>
				<?=gettext("The default is 86400 seconds.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Failover peer IP:");?></td>
			<td width="78%" class="vtable">
				<input name="failover_peerip" type="text" class="formfld host" id="failover_peerip" size="28" value="<?=htmlspecialchars($pconfig['failover_peerip']);?>"><br>
				<?=gettext("Leave blank to disable.  Enter the interface IP address of the other machine.  Machines must be using CARP.");?>
			</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?=gettext("Time format change"); ?></td>
				<td width="78%" class="vtable">
				<table>
					<tr>
					<td>
						<input name="dhcpv6leaseinlocaltime" type="checkbox" id="dhcpv6leaseinlocaltime" value="yes" <?php if ($pconfig['dhcpv6leaseinlocaltime']) echo "checked"; ?>>
					</td>
					<td>
						<strong>
							<?=gettext("Change DHCPv6 display lease time from UTC to local time."); ?>
						</strong>
					</td>
					</tr>
					<tr>
					<td>&nbsp;</td>
					<td>
						<span class="red"><strong><?=gettext("Note:");?></strong></span> <?=gettext("By default DHCPv6 leases are displayed in UTC time.  By checking this 
						box DHCPv6 lease time will be displayed in local time and set to time zone selected.  This will be used for all DHCPv6 interfaces lease time."); ?>
					
					</td>
					</tr>
				</table>
				</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Dynamic DNS");?></td>
			<td width="78%" class="vtable">
				<div id="showddnsbox">
					<input type="button" onClick="show_ddns_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show Dynamic DNS");?></a>
				</div>
				<div id="showddns" style="display:none">
					<input valign="middle" type="checkbox" value="yes" name="ddnsupdate" id="ddnsupdate" <?php if($pconfig['ddnsupdate']) echo " checked"; ?>>&nbsp;
					<b><?=gettext("Enable registration of DHCP client names in DNS.");?></b><br />
					<p>
					<input name="ddnsdomain" type="text" class="formfld unknown" id="ddnsdomain" size="28" value="<?=htmlspecialchars($pconfig['ddnsdomain']);?>"><br />
					<?=gettext("Note: Leave blank to disable dynamic DNS registration.");?><br />
					<?=gettext("Enter the dynamic DNS domain which will be used to register client names in the DNS server.");?>
				</div>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("NTP servers");?></td>
			<td width="78%" class="vtable">
				<div id="showntpbox">
					<input type="button" onClick="show_ntp_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show NTP configuration");?></a>
				</div>
				<div id="showntp" style="display:none">
					<input name="ntp1" type="text" class="formfld unknown" id="ntp1" size="28" value="<?=htmlspecialchars($pconfig['ntp1']);?>"><br>
					<input name="ntp2" type="text" class="formfld unknown" id="ntp2" size="28" value="<?=htmlspecialchars($pconfig['ntp2']);?>">
				</div>
			</td>
			</tr>
			<!-- ISC dhcpd does not support tftp for ipv6 yet. See redmine #2016
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("TFTP server");?></td>
			<td width="78%" class="vtable">
			<div id="showtftpbox">
				<input type="button" onClick="show_tftp_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show TFTP configuration");?></a>
			</div>
			<div id="showtftp" style="display:none">
				<input name="tftp" type="text" class="formfld unknown" id="tftp" size="50" value="<?=htmlspecialchars($pconfig['tftp']);?>"><br>
				<?=gettext("Leave blank to disable.  Enter a full hostname or IP for the TFTP server.");?>
			</div>
			</td>
			</tr>
			-->
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("LDAP URI");?></td>
			<td width="78%" class="vtable">
				<div id="showldapbox">
					<input type="button" onClick="show_ldap_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show LDAP configuration");?></a>
				</div>
				<div id="showldap" style="display:none">
					<input name="ldap" type="text" class="formfld unknown" id="ldap" size="80" value="<?=htmlspecialchars($pconfig['ldap']);?>"><br>
					<?=gettext("Leave blank to disable.  Enter a full URI for the LDAP server in the form ldap://ldap.example.com/dc=example,dc=com");?>
				</div>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Enable network booting");?></td>
			<td width="78%" class="vtable">
				<div id="shownetbootbox">
					<input type="button" onClick="show_netboot_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show Network booting");?></a>
				</div>
				<div id="shownetboot" style="display:none">
					<input valign="middle" type="checkbox" value="yes" name="netboot" id="netboot" <?php if($pconfig['netboot']) echo " checked"; ?>>&nbsp;
					<b><?=gettext("Enables network booting.");?></b>
					<p>
					<?=gettext("Enter the IP of the"); ?> <b><?=gettext("next-server"); ?></b>
					<input name="nextserver" type="text" class="formfld unknown" id="nextserver" size="28" value="<?=htmlspecialchars($pconfig['nextserver']);?>">
					<?=gettext("and the filename");?>
					<input name="filename" type="text" class="formfld unknown" id="filename" size="28" value="<?=htmlspecialchars($pconfig['filename']);?>"><br>
					<?=gettext("Note: You need both a filename and a boot server configured for this to work!");?>
					<p>
					<?=gettext("Enter the"); ?> <b><?=gettext("root-path"); ?></b>-<?=gettext("string");?>
					<input name="rootpath" type="text" class="formfld unknown" id="rootpath" size="90" value="<?=htmlspecialchars($pconfig['rootpath']);?>"><br>
					<?=gettext("Note: string-format: iscsi:(servername):(protocol):(port):(LUN):targetname");?>
				</div>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Additional BOOTP/DHCP Options");?></td>
			<td width="78%" class="vtable">
				<div id="shownumbervaluebox">
					<input type="button" onClick="show_shownumbervalue()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show Additional BOOTP/DHCP Options");?></a>
				</div>
				<div id="shownumbervalue" style="display:none">
				<table id="maintable">
				<tbody>
				<tr>
				<td colspan="3">
					<div style="padding:5px; margin-top: 16px; margin-bottom: 16px; border:1px dashed #000066; background-color: #ffffff; color: #000000; font-size: 8pt;" id="itemhelp">
					<?=gettext("Enter the DHCP option number and the value for each item you would like to include in the DHCP lease information.  For a list of available options please visit this"); ?> <a href="http://www.iana.org/assignments/bootp-dhcp-parameters/" target="_new"><?=gettext("URL"); ?></a>
					</div>
				</td>
				</tr>
				<tr>
				<td><div id="onecolumn"><?=gettext("Number");?></div></td>
				<td><div id="twocolumn"><?=gettext("Value");?></div></td>
				</tr>
				<?php $counter = 0; ?>
				<?php
					if($pconfig['numberoptions'])
						foreach($pconfig['numberoptions']['item'] as $item):
				?>
					<?php
						$number = $item['number'];
						$value = $item['value'];
					?>
				<tr>
				<td>
					<input autocomplete="off" name="number<?php echo $counter; ?>" type="text" class="formfld" id="number<?php echo $counter; ?>" size="10" value="<?=htmlspecialchars($number);?>" />
				</td>
				<td>
					<input autocomplete="off" name="value<?php echo $counter; ?>" type="text" class="formfld" id="value<?php echo $counter; ?>" size="55" value="<?=htmlspecialchars($value);?>" />
				</td>
				<td>
					<input type="image" src="/themes/<?echo $g['theme'];?>/images/icons/icon_x.gif" onclick="removeRow(this); return false;" value="<?=gettext("Delete");?>" />
				</td>
				</tr>
				<?php $counter++; ?>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
				</tfoot>
				</table>
				<a onclick="javascript:addRowTo('maintable', 'formfldalias'); return false;" href="#">
					<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="<?=gettext("add another entry");?>" />
				</a>
				<script type="text/javascript">
					field_counter_js = 2;
					rows = 1;
					totalrows = <?php echo $counter; ?>;
					loaded = <?php echo $counter; ?>;
				</script>
				</div>

				</td>
			</tr>
			<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="if" type="hidden" value="<?=$if;?>">
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onclick="enable_change(true)">
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%"> <p><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br>
				</strong></span><?=gettext("The DNS servers entered in"); ?> <a href="system.php"><?=gettext("System: " .
				"General setup"); ?></a> <?=gettext("(or the"); ?> <a href="services_dnsmasq.php"><?=gettext("DNS " .
				"forwarder"); ?></a>, <?=gettext("if enabled)"); ?> </span><span class="vexpl"><?=gettext("will " .
				"be assigned to clients by the DHCP server."); ?><br>
				<br>
				<?=gettext("The DHCP lease table can be viewed on the"); ?> <a href="status_dhcpv6_leases.php"><?=gettext("Status: " .
				"DHCPv6 leases"); ?></a> <?=gettext("page."); ?><br>
				</span></p>
			</td>
			</tr>
		</table>
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td width="25%" class="listhdrr"><?=gettext("DUID");?></td>
			<td width="15%" class="listhdrr"><?=gettext("IPv6 address");?></td>
			<td width="20%" class="listhdrr"><?=gettext("Hostname");?></td>
			<td width="30%" class="listhdr"><?=gettext("Description");?></td>
			<td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			<tr>
			<td valign="middle" width="17"></td>
			<td valign="middle"><a href="services_dhcpv6_edit.php?if=<?=$if;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
			</tr>
			</table>
			</td>
		</tr>
			<?php if(is_array($a_maps)): ?>
			<?php $i = 0; foreach ($a_maps as $mapent): ?>
			<?php if($mapent['duid'] <> "" or $mapent['ipaddrv6'] <> ""): ?>
		<tr>
		<td class="listlr" ondblclick="document.location='services_dhcpv6_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
			<?=htmlspecialchars($mapent['duid']);?>
		</td>
		<td class="listr" ondblclick="document.location='services_dhcpv6_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
			<?=htmlspecialchars($mapent['ipaddrv6']);?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='services_dhcpv6_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
			<?=htmlspecialchars($mapent['hostname']);?>&nbsp;
		</td>
		<td class="listbg" ondblclick="document.location='services_dhcpv6_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
			<?=htmlspecialchars($mapent['descr']);?>&nbsp;
		</td>
		<td valign="middle" nowrap="nowrap" class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			<tr>
			<td valign="middle"><a href="services_dhcpv6_edit.php?if=<?=$if;?>&id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
			<td valign="middle"><a href="services_dhcpv6.php?if=<?=$if;?>&act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this mapping?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
			</tr>
			</table>
		</td>
		</tr>
		<?php endif; ?>
		<?php $i++; endforeach; ?>
		<?php endif; ?>
		<tr>
		<td class="list" colspan="4"></td>
		<td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			<tr>
			<td valign="middle" width="17"></td>
			<td valign="middle"><a href="services_dhcpv6_edit.php?if=<?=$if;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
			</tr>
			</table>
		</td>
		</tr>
		</table>
	</div>
</td>
</tr>
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
