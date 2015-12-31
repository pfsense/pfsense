<?php
/*
	services_dhcpv6.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-services-dhcpv6server
##|*NAME=Services: DHCPv6 server
##|*DESCR=Allow access to the 'Services: DHCPv6 server' page.
##|*MATCH=services_dhcpv6.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

/*	Fix failover DHCP problem
 *	http://article.gmane.org/gmane.comp.security.firewalls.pfsense.support/18749
 */
ini_set("memory_limit", "64M");

$if = $_GET['if'];
if ($_POST['if']) {
	$if = $_POST['if'];
}

/* if OLSRD is enabled, allow WAN to house DHCP. */
if ($config['installedpackages']['olsrd']) {
	foreach ($config['installedpackages']['olsrd']['config'] as $olsrd) {
		if ($olsrd['enable']) {
			$is_olsr_enabled = true;
			break;
		}
	}
}

$iflist = get_configured_interface_with_descr();
$iflist = array_merge($iflist, get_configured_pppoe_server_interfaces());

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];

		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))) ||
		    (!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6']))))) {
			continue;
		}
		$if = $ifent;
		break;
	}
}

if (is_array($config['dhcpdv6'][$if])) {
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
	list($pconfig['wins1'], $pconfig['wins2']) = $config['dhcpdv6'][$if]['winsserver'];
	list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $config['dhcpdv6'][$if]['dnsserver'];
	$pconfig['enable'] = isset($config['dhcpdv6'][$if]['enable']);
	$pconfig['ddnsdomain'] = $config['dhcpdv6'][$if]['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $config['dhcpdv6'][$if]['ddnsdomainprimary'];
	$pconfig['ddnsdomainkeyname'] = $config['dhcpdv6'][$if]['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkey'] = $config['dhcpdv6'][$if]['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($config['dhcpdv6'][$if]['ddnsupdate']);
	list($pconfig['ntp1'], $pconfig['ntp2']) = $config['dhcpdv6'][$if]['ntpserver'];
	$pconfig['tftp'] = $config['dhcpdv6'][$if]['tftp'];
	$pconfig['ldap'] = $config['dhcpdv6'][$if]['ldap'];
	$pconfig['netboot'] = isset($config['dhcpdv6'][$if]['netboot']);
	$pconfig['bootfile_url'] = $config['dhcpdv6'][$if]['bootfile_url'];
	$pconfig['netmask'] = $config['dhcpdv6'][$if]['netmask'];
	$pconfig['numberoptions'] = $config['dhcpdv6'][$if]['numberoptions'];
	$pconfig['dhcpv6leaseinlocaltime'] = $config['dhcpdv6'][$if]['dhcpv6leaseinlocaltime'];
	if (!is_array($config['dhcpdv6'][$if]['staticmap'])) {
		$config['dhcpdv6'][$if]['staticmap'] = array();
	}
	$a_maps = &$config['dhcpdv6'][$if]['staticmap'];
}

$ifcfgip = get_interface_ipv6($if);
$ifcfgsn = get_interface_subnetv6($if);

/*	 set the enabled flag which will tell us if DHCP relay is enabled
 *	 on any interface. We will use this to disable DHCP server since
 *	 the two are not compatible with each other.
 */

$dhcrelay_enabled = false;
$dhcrelaycfg = $config['dhcrelay6'];

if (is_array($dhcrelaycfg)) {
	foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
		if (isset($dhcrelayifconf['enable']) && isset($iflist[$dhcrelayif]) &&
		    (!link_interface_to_bridge($dhcrelayif))) {
			$dhcrelay_enabled = true;
		}
	}
}

if ($_POST) {
	unset($input_errors);

	$old_dhcpdv6_enable = ($pconfig['enable'] == true);
	$new_dhcpdv6_enable = ($_POST['enable'] ? true : false);
	$dhcpdv6_enable_changed = ($old_dhcpdv6_enable != $new_dhcpdv6_enable);

	$pconfig = $_POST;

	$numberoptions = array();
	for ($x = 0; $x < 99; $x++) {
		if (isset($_POST["number{$x}"]) && ctype_digit($_POST["number{$x}"])) {
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
		$reqdfieldsn = array(gettext("Range begin"), gettext("Range end"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if (($_POST['prefixrange_from'] && !is_ipaddrv6($_POST['prefixrange_from']))) {
			$input_errors[] = gettext("A valid range must be specified.");
		}
		if (($_POST['prefixrange_to'] && !is_ipaddrv6($_POST['prefixrange_to']))) {
			$input_errors[] = gettext("A valid prefix range must be specified.");
		}
		if (($_POST['range_from'] && !is_ipaddrv6($_POST['range_from']))) {
			$input_errors[] = gettext("A valid range must be specified.");
		}
		if (($_POST['range_to'] && !is_ipaddrv6($_POST['range_to']))) {
			$input_errors[] = gettext("A valid range must be specified.");
		}
		if (($_POST['gateway'] && !is_ipaddrv6($_POST['gateway']))) {
			$input_errors[] = gettext("A valid IPv6 address must be specified for the gateway.");
		}
		if (($_POST['dns1'] && !is_ipaddrv6($_POST['dns1'])) ||
		    ($_POST['dns2'] && !is_ipaddrv6($_POST['dns2'])) ||
		    ($_POST['dns3'] && !is_ipaddrv6($_POST['dns3'])) ||
		    ($_POST['dns4'] && !is_ipaddrv6($_POST['dns4']))) {
			$input_errors[] = gettext("A valid IPv6 address must be specified for each of the DNS servers.");
		}

		if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60))) {
			$input_errors[] = gettext("The default lease time must be at least 60 seconds.");
		}
		if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime']))) {
			$input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
		}
		if (($_POST['ddnsdomain'] && !is_domain($_POST['ddnsdomain']))) {
			$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
		}
		if (($_POST['ddnsdomain'] && !is_ipaddrv4($_POST['ddnsdomainprimary']))) {
			$input_errors[] = gettext("A valid primary domain name server IPv4 address must be specified for the dynamic domain name.");
		}
		if (($_POST['ddnsdomainkey'] && !$_POST['ddnsdomainkeyname']) ||
		    ($_POST['ddnsdomainkeyname'] && !$_POST['ddnsdomainkey'])) {
			$input_errors[] = gettext("You must specify both a valid domain key and key name.");
		}
		if ($_POST['domainsearchlist']) {
			$domain_array=preg_split("/[ ;]+/", $_POST['domainsearchlist']);
			foreach ($domain_array as $curdomain) {
				if (!is_domain($curdomain)) {
					$input_errors[] = gettext("A valid domain search list must be specified.");
					break;
				}
			}
		}

		if (($_POST['ntp1'] && !is_ipaddrv6($_POST['ntp1'])) || ($_POST['ntp2'] && !is_ipaddrv6($_POST['ntp2']))) {
			$input_errors[] = gettext("A valid IPv6 address must be specified for the primary/secondary NTP servers.");
		}
		if (($_POST['domain'] && !is_domain($_POST['domain']))) {
			$input_errors[] = gettext("A valid domain name must be specified for the DNS domain.");
		}
		if ($_POST['tftp'] && !is_ipaddr($_POST['tftp']) && !is_domain($_POST['tftp']) && !is_URL($_POST['tftp'])) {
			$input_errors[] = gettext("A valid IPv6 address or hostname must be specified for the TFTP server.");
		}
		if (($_POST['bootfile_url'] && !is_URL($_POST['bootfile_url']))) {
			$input_errors[] = gettext("A valid URL must be specified for the network bootfile.");
		}

		// Disallow a range that includes the virtualip
		if (is_array($config['virtualip']['vip'])) {
			foreach ($config['virtualip']['vip'] as $vip) {
				if ($vip['interface'] == $if) {
					if ($vip['subnetv6'] && is_inrange_v6($vip['subnetv6'], $_POST['range_from'], $_POST['range_to'])) {
						$input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IPv6 address %s."), $vip['subnetv6']);
					}
				}
			}
		}

		$noip = false;
		if (is_array($a_maps)) {
			foreach ($a_maps as $map) {
				if (empty($map['ipaddrv6'])) {
					$noip = true;
				}
			}
		}
		if (!$input_errors) {
			/* make sure the range lies within the current subnet */
			$subnet_start = gen_subnetv6($ifcfgip, $ifcfgsn);
			$subnet_end = gen_subnetv6_max($ifcfgip, $ifcfgsn);

			if (is_ipaddrv6($ifcfgip)) {
				if ((!is_inrange_v6($_POST['range_from'], $subnet_start, $subnet_end)) ||
				    (!is_inrange_v6($_POST['range_to'], $subnet_start, $subnet_end))) {
					$input_errors[] = gettext("The specified range lies outside of the current subnet.");
				}
			}
			/* "from" cannot be higher than "to" */
			if (inet_pton($_POST['range_from']) > inet_pton($_POST['range_to'])) {
				$input_errors[] = gettext("The range is invalid (first element higher than second element).");
			}

			/* make sure that the DHCP Relay isn't enabled on this interface */
			if (isset($config['dhcrelay'][$if]['enable'])) {
				$input_errors[] = sprintf(gettext("You must disable the DHCP relay on the %s interface before enabling the DHCP server."), $iflist[$if]);
			}


			/* Verify static mappings do not overlap:
			   - available DHCP range
			   - prefix delegation range (FIXME: still need to be completed) */
			$dynsubnet_start = inet_pton($_POST['range_from']);
			$dynsubnet_end = inet_pton($_POST['range_to']);

			if (is_array($a_maps)) {
				foreach ($a_maps as $map) {
					if (empty($map['ipaddrv6'])) {
						continue;
					}
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
		if (!is_array($config['dhcpdv6'][$if])) {
			$config['dhcpdv6'][$if] = array();
		}
		if (!is_array($config['dhcpdv6'][$if]['range'])) {
			$config['dhcpdv6'][$if]['range'] = array();
		}
		if (!is_array($config['dhcpdv6'][$if]['prefixrange'])) {
			$config['dhcpdv6'][$if]['prefixrange'] = array();
		}

		$config['dhcpdv6'][$if]['range']['from'] = $_POST['range_from'];
		$config['dhcpdv6'][$if]['range']['to'] = $_POST['range_to'];
		$config['dhcpdv6'][$if]['prefixrange']['from'] = $_POST['prefixrange_from'];
		$config['dhcpdv6'][$if]['prefixrange']['to'] = $_POST['prefixrange_to'];
		$config['dhcpdv6'][$if]['prefixrange']['prefixlength'] = $_POST['prefixrange_length'];
		$config['dhcpdv6'][$if]['defaultleasetime'] = $_POST['deftime'];
		$config['dhcpdv6'][$if]['maxleasetime'] = $_POST['maxtime'];
		$config['dhcpdv6'][$if]['netmask'] = $_POST['netmask'];

		unset($config['dhcpdv6'][$if]['winsserver']);

		unset($config['dhcpdv6'][$if]['dnsserver']);
		if ($_POST['dns1']) {
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns1'];
		}
		if ($_POST['dns2']) {
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns2'];
		}
		if ($_POST['dns3']) {
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns3'];
		}
		if ($_POST['dns4']) {
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns4'];
		}

		$config['dhcpdv6'][$if]['domain'] = $_POST['domain'];
		$config['dhcpdv6'][$if]['domainsearchlist'] = $_POST['domainsearchlist'];
		$config['dhcpdv6'][$if]['enable'] = ($_POST['enable']) ? true : false;
		$config['dhcpdv6'][$if]['ddnsdomain'] = $_POST['ddnsdomain'];
		$config['dhcpdv6'][$if]['ddnsdomainprimary'] = $_POST['ddnsdomainprimary'];
		$config['dhcpdv6'][$if]['ddnsdomainkeyname'] = $_POST['ddnsdomainkeyname'];
		$config['dhcpdv6'][$if]['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$config['dhcpdv6'][$if]['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;

		unset($config['dhcpdv6'][$if]['ntpserver']);
		if ($_POST['ntp1']) {
			$config['dhcpdv6'][$if]['ntpserver'][] = $_POST['ntp1'];
		}
		if ($_POST['ntp2']) {
			$config['dhcpdv6'][$if]['ntpserver'][] = $_POST['ntp2'];
		}

		$config['dhcpdv6'][$if]['tftp'] = $_POST['tftp'];
		$config['dhcpdv6'][$if]['ldap'] = $_POST['ldap'];
		$config['dhcpdv6'][$if]['netboot'] = ($_POST['netboot']) ? true : false;
		$config['dhcpdv6'][$if]['bootfile_url'] = $_POST['bootfile_url'];
		$config['dhcpdv6'][$if]['dhcpv6leaseinlocaltime'] = $_POST['dhcpv6leaseinlocaltime'];

		// Handle the custom options rowhelper
		if (isset($config['dhcpdv6'][$if]['numberoptions']['item'])) {
			unset($config['dhcpdv6'][$if]['numberoptions']['item']);
		}

		$config['dhcpdv6'][$if]['numberoptions'] = $numberoptions;

		write_config();

		$retval = 0;
		$retvaldhcp = 0;
		$retvaldns = 0;
		/* Stop DHCPv6 so we can cleanup leases */
		killbypid("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpdv6.pid");
		// dhcp_clean_leases();
		/* dnsmasq_configure calls dhcpd_configure */
		/* no need to restart dhcpd twice */
		if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic']))	{
			$retvaldns = services_dnsmasq_configure();
			if ($retvaldns == 0) {
				clear_subsystem_dirty('hosts');
				clear_subsystem_dirty('staticmaps');
			}
		} else if (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcpstatic'])) {
			$retvaldns = services_unbound_configure();
			if ($retvaldns == 0) {
				clear_subsystem_dirty('unbound');
				clear_subsystem_dirty('staticmaps');
			}
		} else {
			$retvaldhcp = services_dhcpd_configure();
			if ($retvaldhcp == 0) {
				clear_subsystem_dirty('staticmaps');
			}
		}
		if ($dhcpdv6_enable_changed) {
			$retvalfc = filter_configure();
		}
		if ($retvaldhcp == 1 || $retvaldns == 1 || $retvalfc == 1) {
			$retval = 1;
		}
		$savemsg = get_std_save_message($retval);
	}
}

if ($_GET['act'] == "del") {
	if ($a_maps[$_GET['id']]) {
		unset($a_maps[$_GET['id']]);
		write_config();
		if (isset($config['dhcpdv6'][$if]['enable'])) {
			mark_subsystem_dirty('staticmapsv6');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstaticv6'])) {
				mark_subsystem_dirty('hosts');
			}
		}
		header("Location: services_dhcpv6.php?if={$if}");
		exit;
	}
}

// Delete a row in the options table
if ($_GET['act'] == "delopt") {
	$idx = $_GET['id'];

	if ($pconfig['numberoptions'] && is_array($pconfig['numberoptions']['item'][$idx])) {
	   unset($pconfig['numberoptions']['item'][$idx]);
	}
}

// Add an option row
if ($_GET['act'] == "addopt") {
	if (!is_array($pconfig['numberoptions']['item'])) {
		$pconfig['numberoptions']['item'] = array();
	}

	array_push($pconfig['numberoptions']['item'], array('number' => null, 'value' => null));
}

$pgtitle = array(gettext("Services"), gettext("DHCPv6 Server"));
$shortcut_section = "dhcp6";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if ($dhcrelay_enabled) {
	print_info_box(gettext("DHCP Relay is currently enabled. Cannot enable the DHCP Server service while the DHCP Relay is enabled on any interface."), 'danger');
	include("foot.inc");
	exit;
}

if (is_subsystem_dirty('staticmaps')) {
	print_info_box_np(gettext('The static mapping configuration has been changed') . '.<br />' . gettext('You must apply the changes in order for them to take effect.'));
}

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;

foreach ($iflist as $ifent => $ifname) {
	$oc = $config['interfaces'][$ifent];


	if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))) ||
	    (!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6']))))) {
		continue;
	}

	if ($ifent == $if) {
		$active = true;
	} else {
		$active = false;
	}

	$tab_array[] = array($ifname, $active, "services_dhcpv6.php?if={$ifent}");
	$tabscounter++;
}

/* tack on PPPoE or PPtP servers here */
/* pppoe server */
if (is_array($config['pppoes']['pppoe'])) {
	foreach ($config['pppoes']['pppoe'] as $pppoe) {
		if ($pppoe['mode'] == "server") {
			$ifent = "poes". $pppoe['pppoeid'];
			$ifname = strtoupper($ifent);

			if ($ifent == $if) {
				$active = true;
			} else {
				$active = false;
			}

			$tab_array[] = array($ifname, $active, "services_dhcpv6.php?if={$ifent}");
			$tabscounter++;
		}
	}
}

if ($tabscounter == 0) {
	print_info_box(gettext("The DHCPv6 Server can only be enabled on interfaces configured with a static IPv6 address. This system has none."), 'danger');
	include("foot.inc");
	exit;
}

display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("DHCPv6 Server"),		 true,	"services_dhcpv6.php?if={$if}");
$tab_array[] = array(gettext("Router Advertisements"), false, "services_router_advertisements.php?if={$if}");
display_top_tabs($tab_array, false, 'nav nav-tabs');

$form = new Form(new Form_Button(
	'Submit',
	'Save'
));

$section = new Form_Section('DHCPv6 Options');

$section->addInput(new Form_Checkbox(
	'enable',
	'DHCPv6 Server',
	'Enable DHCPv6 server on interface ' . $iflist[$if],
	$pconfig['enable']
))->toggles('.form-group:not(:first-child)');

if (is_ipaddrv6($ifcfgip)) {

	$section->addInput(new Form_StaticText(
		'Subnet',
		gen_subnetv6($ifcfgip, $ifcfgsn)
		));

	$section->addInput(new Form_StaticText(
		'Subnet Mask',
		$ifcfgsn . ' bits'
		));

	$section->addInput(new Form_StaticText(
		'Available Range',
		$range_from = gen_subnetv6($ifcfgip, $ifcfgsn) . ' to ' . gen_subnetv6_max($ifcfgip, $ifcfgsn)
		));
}

if ($is_olsr_enabled) {
	$section->addInput(new Form_Select(
	'netmask',
	'Subnet Mask',
	$pconfig['netmask'],
	array_combine(range(128, 1, -1), range(128, 1, -1))
	));
}

$f1 = new Form_Input(
	'range_from',
	null,
	'text',
	$pconfig['range_from']
);

$f1->setHelp('To');

$f2 = new Form_Input(
	'range_to',
	null,
	'text',
	$pconfig['range_to']
);

$f2->setHelp('From');

$group = new Form_Group('Range');

$group->add($f1);
$group->add($f2);

$section->add($group);

$f1 = new Form_Input(
	'prefixrange_from',
	null,
	'text',
	$pconfig['prefixrange_from']
);

$f1->setHelp('To');

$f2 = new Form_Input(
	'prefixrange_to',
	null,
	'text',
	$pconfig['prefixrange_to']
);

$f2->setHelp('From');
$group = new Form_Group('Prefix Delegation Range');

$group->add($f1);
$group->add($f2);

$section->add($group);

$section->addInput(new Form_Select(
	'prefixrange_length',
	'Prefix Delegation Size',
	$pconfig['prefixrange_length'],
	array(
		'48' => '48',
		'52' => '52',
		'56' => '56',
		'60' => '60',
		'62' => '62',
		'63' => '63',
		'64' => '64'
		)
))->setHelp('You can define a Prefix range here for DHCP Prefix Delegation. This allows for assigning networks to subrouters. The start and end of the range must end on boundaries of the prefix delegation size.');

$group = new Form_Group('DNS Servers');

for ($i=1;$i<=4; $i++) {
	$group->add(new Form_input(
		'dns' . $i,
		null,
		'text',
		$pconfig['dns' . $i],
		['placeholder' => 'DNS ' . $i]
	));
}

$group->setHelp('Leave blank to use the system default DNS servers, this interface\'s IP if DNS forwarder is enabled, or the servers configured on the "General" page.');
$section->add($group);

$section->addInput(new Form_Input(
	'domain',
	'Domain Name',
	'text',
	$pconfig['domain']
))->setHelp('The default is to use the domain name of this system as the default domain name provided by DHCP. You may specify an alternate domain name here. ');

$section->addInput(new Form_Input(
	'domainsearchlist',
	'Domain search list',
	'text',
	$pconfig['domainsearchlist']
))->setHelp('The DHCP server can optionally provide a domain search list. Use the semicolon character as separator');

$section->addInput(new Form_Input(
	'deftime',
	'Default lease time',
	'text',
	$pconfig['deftime']
))->setHelp('Seconds . Used for clients that do not ask for a specific expiration time. ' . ' <br />' .
			'The default is 7200 seconds.');

$section->addInput(new Form_Input(
	'maxtime',
	'Max lease time',
	'text',
	$pconfig['maxtime']
))->setHelp('Maximum lease time for clients that ask for a specific expiration time.' . ' <br />' .
			'The default is 86400 seconds.');

$section->addInput(new Form_Checkbox(
	'dhcpv6leaseinlocaltime',
	'Time Format Change',
	'Change DHCPv6 display lease time from UTC to local time',
	$pconfig['dhcpv6leaseinlocaltime']
))->setHelp('By default DHCPv6 leases are displayed in UTC time. ' .
			'By checking this box DHCPv6 lease time will be displayed in local time and set to time zone selected. ' .
			'This will be used for all DHCPv6 interfaces lease time.');

$btndyndns = new Form_Button(
	'btndyndns',
	'Advanced'
);

$btndyndns->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Dynamic DNS',
	$btndyndns . '&nbsp;' . 'Show dynamic DNS settings'
));

$section->addInput(new Form_Checkbox(
	'ddnsupdate',
	'DHCP Registration',
	'Enable registration of DHCP client names in DNS.',
	$pconfig['ddnsupdate']
));

$section->addInput(new Form_Input(
	'ddnsdomain',
	'DDNS Domain',
	'text',
	$pconfig['ddnsdomain']
))->setHelp('Leave blank to disable dynamic DNS registration. Enter the dynamic DNS domain which will be used to register client names in the DNS server.');

$section->addInput(new Form_IpAddress(
	'ddnsdomainprimary',
	'DDNS Server IP',
	$pconfig['ddnsdomainprimary']
))->setHelp('Enter the primary domain name server IP address for the dynamic domain name.');

$section->addInput(new Form_Input(
	'ddnsdomainkeyname',
	'DDNS Domain Key name',
	'text',
	$pconfig['ddnsdomainkeyname']
))->setHelp('Enter the dynamic DNS domain key name which will be used to register client names in the DNS server.');

$section->addInput(new Form_Input(
	'ddnsdomainkey',
	'DDNS Domain Key secret',
	'text',
	$pconfig['ddnsdomainkey']
))->setHelp('Enter the dynamic DNS domain key secret which will be used to register client names in the DNS server.');

$btnntp = new Form_Button(
	'btnntp',
	'Advanced'
);

$btnntp->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'NTP servers',
	$btnntp . '&nbsp;' . 'Show NTP Configuration'
));

$group = new Form_Group('NTP Servers');

$group->add(new Form_Input(
	'ntp1',
	'NTP Server 1',
	'text',
	$pconfig['ntp1'],
	['placeholder' => 'NTP 1']
));

$group->add(new Form_Input(
	'ntp2',
	'NTP Server 2',
	'text',
	$pconfig['ntp2'],
	['placeholder' => 'NTP 2']
));

$group->addClass('ntpclass');

$section->add($group);

$btnldap = new Form_Button(
	'btnldap',
	'Advanced'
);

$btnldap->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'LDAP',
	$btnldap . '&nbsp;' . 'Show LDAP Configuration'
));

$section->addInput(new Form_Input(
	'ldap',
	'LDAP URI',
	'text',
	$pconfig['ldap']
));

$btnnetboot = new Form_Button(
	'btnnetboot',
	'Advanced'
);

$btnnetboot->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Network booting',
	$btnnetboot . '&nbsp;' . 'Show Network booting'
));

$section->addInput(new Form_Checkbox(
	'shownetboot',
	'Network booting',
	'Enable Network Booting',
	$pconfig['shownetboot']
));

$section->addInput(new Form_Input(
	'bootfile_url',
	'Bootfile URL',
	'text',
	$pconfig['bootfile_url']
));

$btnadnl = new Form_Button(
	'btnadnl',
	'Advanced'
);

$btnadnl->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Additional BOOTP/DHCP Options',
	$btnadnl . '&nbsp;' . 'Additional BOOTP/DHCP Options'
));

$form->add($section);

$title = 'Show Additional BOOTP/DHCP Options';

if ($pconfig['numberoptions']) {
	$counter = 0;
	$last = count($pconfig['numberoptions']['item']) - 1;

	foreach ($pconfig['numberoptions']['item'] as $item) {
		$group = new Form_Group(null);

		$group->add(new Form_Input(
			'number' . $counter,
			null,
			'text',
			$item['number']
		))->setHelp($counter == $last ? 'Number':null);

		$group->add(new Form_Input(
			'value' . $counter,
			null,
			'text',
			$item['value']
		))->setHelp($counter == $last ? 'Value':null);

		$btn = new Form_Button(
			'btn' . $counter,
			'Delete',
			'services_dhcpv6.php?if=' . $if . '&act=delopt' . '&id=' . $counter
		);

		$btn->removeClass('btn-primary')->addClass('btn-danger btn-xs adnlopt');
		$group->addClass('adnlopt');
		$group->add($btn);
		$section->add($group);
		$counter++;
	}
}

$btnaddopt = new Form_Button(
	'btnaddopt',
	'Add Option',
	'services_dhcpv6.php?if=' . $if . '&act=addopt'
);

$btnaddopt->removeClass('btn-primary')->addClass('btn-success btn-sm');

$section->addInput($btnaddopt);

$section->addInput(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));

print($form);

print_info_box(gettext('The DNS servers entered in ') . '<a href="system.php">' . gettext(' System: General setup') . '</a>' .
			   gettext(' (or the ') . '<a href="services_dnsmasq.php"/>' . gettext('DNS forwarder') . '</a>, ' . gettext('if enabled) ') .
			   gettext('will be assigned to clients by the DHCP server.') . '<br />' .
			   gettext('The DHCP lease table can be viewed on the ') . '<a href="status_dhcpv6_leases.php">' .
			   gettext('Status: DHCPv6 leases') . '</a>' . gettext(' page.'));
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">DHCPv6 Static Mappings for this interface.</h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("DUID")?></th>
					<th><?=gettext("IPv6 address")?></th>
					<th><?=gettext("Hostname")?></th>
					<th><?=gettext("Description")?></th>
					<th><!-- Buttons --></th>
				</tr>
			</thead>
			<tbody>
<?php
if (is_array($a_maps)):
	$i = 0;
	foreach ($a_maps as $mapent):
		if ($mapent['duid'] != "" or $mapent['ipaddrv6'] != ""):
?>
				<tr>
					<td>
						<?=htmlspecialchars($mapent['duid'])?>
					</td>
					<td>
						<?=htmlspecialchars($mapent['ipaddrv6'])?>
					</td>
					<td>
						<?=htmlspecialchars($mapent['hostname'])?>
					</td>
					<td>
						<?=htmlspecialchars($mapent['descr'])?>
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit static mapping')?>" href="services_dhcpv6_edit.php?if=<?=$if?>&amp;id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete static mapping')?>" href="services_dhcpv6.php?if=<?=$if?>&amp;act=del&amp;id=<?=$i?>"></a>
					</td>
				</tr>
<?php
		endif;
	$i++;
	endforeach;
endif;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_dhcpv6_edit.php?if=<?=$if?>" class="btn btn-sm btn-success"/>
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function hideDDNS(hide) {
		hideCheckBox('ddnsupdate', hide);
		hideInput('ddnsdomain', hide);
		hideInput('ddnsdomainprimary', hide);
		hideInput('ddnsdomainkeyname', hide);
		hideInput('ddnsdomainkey', hide);
	}

	// Make the 'Copy My MAC' button a plain button, not a submit button
	$("#btnmymac").prop('type','button');

	// On click, copy the hidden 'mymac' text to the 'mac' input
	$("#btnmymac").click(function() {
		$('#mac').val('<?=$mymac?>');
	});

	// Make the 'tftp' button a plain button, not a submit button
	$("#btntftp").prop('type','button');

	// Show tftp controls
	$("#btntftp").click(function() {
		hideInput('tftp', false);
	});

	// Make the 'ntp' button a plain button, not a submit button
	$("#btnntp").prop('type','button');

	// Show ntp controls
	$("#btnntp").click(function() {
		hideClass('ntpclass', false);
	});

	// Make the 'ddns' button a plain button, not a submit button
	$("#btndyndns").prop('type','button');

	// Show ddns controls
	$("#btndyndns").click(function() {
		hideDDNS(false);
	});

	// Make the 'ldap' button a plain button, not a submit button
	$("#btnldap").prop('type','button');

	// Show ldap controls
	$("#btnldap").click(function() {
		hideInput('ldap', false);
	});

	// Make the 'netboot' button a plain button, not a submit button
	$("#btnnetboot").prop('type','button');

	// Show netboot controls
	$("#btnnetboot").click(function() {
		hideInput('bootfile_url', false);
		hideCheckBox('shownetboot', false);
	});

	// Make the 'additional options' button a plain button, not a submit button
	$("#btnadnl").prop('type','button');

	// Show additional  controls
	$("#btnadnl").click(function() {
		hideClass('adnlopt', false);
		hideInput('btnaddopt', false);
	});

	// On initial load
	hideDDNS(true);
	hideClass('ntpclass', true);
	hideInput('tftp', true);
	hideInput('ldap', true);
	hideInput('bootfile_url', true);
	hideCheckBox('shownetboot', true);
	hideClass('adnlopt', true);
	hideInput('btnaddopt', true);
});
//]]>
</script>

<?php include('foot.inc');
