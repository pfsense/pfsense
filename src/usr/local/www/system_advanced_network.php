<?php
/*
	system_advanced_network.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2008 Shrew Soft Inc
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-system-advanced-network
##|*NAME=System: Advanced: Networking
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

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if ($_POST['ipv6nat_enable'] && !is_ipaddr($_POST['ipv6nat_ipaddr'])) {
		$input_errors[] = gettext("You must specify an IP address to NAT IPv6 packets.");
	}

	ob_flush();
	flush();
	if (!$input_errors) {

		if ($_POST['ipv6nat_enable'] == "yes") {
			$config['diag']['ipv6nat']['enable'] = true;
			$config['diag']['ipv6nat']['ipaddr'] = $_POST['ip-address'];
		} else {
			if ($config['diag']) {
				if ($config['diag']['ipv6nat']) {
					unset($config['diag']['ipv6nat']['enable']);
					unset($config['diag']['ipv6nat']['ipaddr']);
				}
			}
		}

		if ($_POST['ipv6allow'] == "yes") {
			$config['system']['ipv6allow'] = true;
		} else {
			unset($config['system']['ipv6allow']);
		}

		if ($_POST['prefer_ipv4'] == "yes") {
			$config['system']['prefer_ipv4'] = true;
		} else {
			unset($config['system']['prefer_ipv4']);
		}

		if ($_POST['sharednet'] == "yes") {
			$config['system']['sharednet'] = true;
			system_disable_arp_wrong_if();
		} else {
			unset($config['system']['sharednet']);
			system_enable_arp_wrong_if();
		}

		if ($_POST['polling_enable'] == "yes") {
			$config['system']['polling'] = true;
			setup_polling();
		} else {
			unset($config['system']['polling']);
			setup_polling();
		}

		if ($_POST['disablechecksumoffloading'] == "yes") {
			$config['system']['disablechecksumoffloading'] = true;
		} else {
			unset($config['system']['disablechecksumoffloading']);
		}

		if ($_POST['disablesegmentationoffloading'] == "yes") {
			$config['system']['disablesegmentationoffloading'] = true;
		} else {
			unset($config['system']['disablesegmentationoffloading']);
		}

		if ($_POST['disablelargereceiveoffloading'] == "yes") {
			$config['system']['disablelargereceiveoffloading'] = true;
		} else {
			unset($config['system']['disablelargereceiveoffloading']);
		}

		setup_microcode();

		// Write out configuration (config.xml)
		write_config();

		// Set preferred protocol
		prefer_ipv4_or_ipv6();

		$retval = filter_configure();
		if (stristr($retval, "error") <> true) {
			$savemsg = get_std_save_message(gettext($retval));
		} else {
			$savemsg = gettext($retval);
		}
	}
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("Networking"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), true, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

$form = new Form;
$section = new Form_Section('IPv6 Options');

$section->addInput(new Form_Checkbox(
	'ipv6allow',
	'Allow IPv6',
	'All IPv6 traffic will be blocked by the firewall unless this box is checked',
	$pconfig['ipv6allow']
))->setHelp('NOTE: This does not disable any IPv6 features on the firewall, it only '.
	'blocks traffic.');

$group = new Form_Group('IPv6 over IPv4 Tunneling');
$group->add(new Form_Checkbox(
	'ipv6nat_enable',
	'IPv6 over IPv4 Tunneling',
	'Enable IPv4 NAT encapsulation of IPv6 packets',
	$pconfig['ipv6nat_enable']
))->setHelp('NOTE: This does not disable any IPv6 features on the firewall, it only '.
	'blocks traffic.');

$group->add(new Form_Input(
	'ipv6nat_ipaddr',
	'IP address',
	'text',
	$pconfig['ipv6nat_ipaddr']
))->setHelp('Enable IPv4 NAT encapsulation of IPv6 packets. <br/>This provides an '.
	'RFC 2893 compatibility mechanism that can be used to tunneling IPv6 packets over '.
	'IPv4 routing infrastructures. If enabled, don"t forget to add a firewall rule to '.
	'permit IPv6 packets.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'prefer_ipv4',
	'Prefer IPv4 over IPv6',
	'Prefer to use IPv4 even if IPv6 is available',
	$pconfig['prefer_ipv4']
))->setHelp('By default, if a hostname resolves IPv6 and IPv4 addresses IPv6 will '.
	'be used, if you check this option, IPv4 will be used instead of IPv6.');

$form->add($section);
$section = new Form_Section('Network Interfaces');

$section->addInput(new Form_Checkbox(
	'polling_enable',
	'Device polling',
	'Enable device polling',
	$pconfig['polling_enable']
))->setHelp('Device polling is a technique that lets the system periodically poll '.
	'network devices for new data instead of relying on interrupts. This prevents '.
	'your webConfigurator, SSH, etc. from being inaccessible due to interrupt floods '.
	'when under extreme load. Generally this is not recommended. Not all NICs support '.
	'polling; see the %s homepage for a list of supported cards', [$g["product_name"]]);


$section->addInput(new Form_Checkbox(
	'disablechecksumoffloading',
	'Hardware Checksum Offloading',
	'Disable hardware checksum offload',
	isset($config['system']['disablechecksumoffloading'])
))->setHelp('Checking this option will disable hardware checksum offloading.<br/>'.
	'Checksum offloading is broken in some hardware, particularly some Realtek cards. '.
	'Rarely, drivers may have problems with checksum offloading and some specific '.
	'NICs.This will take effect after you reboot the machine or re-configure each '.
	'interface.');

$section->addInput(new Form_Checkbox(
	'disablesegmentationoffloading',
	'Hardware TCP Segmentation Offloading',
	'Disable hardware TCP segmentation offload',
	isset($config['system']['disablesegmentationoffloading'])
))->setHelp('Checking this option will disable hardware TCP segmentation '.
	'offloading (TSO, TSO4, TSO6). This offloading is broken in some hardware '.
	'drivers, and may impact performance with some specific NICs.This will take '.
	'effect after you reboot the machine or re-configure each interface.');

$section->addInput(new Form_Checkbox(
	'disablelargereceiveoffloading',
	'Hardware Large Receive Offloading',
	'Disable hardware large receive offload',
	isset($config['system']['disablelargereceiveoffloading'])
))->setHelp('Checking this option will disable hardware large receive offloading '.
	'(LRO). This offloading is broken in some hardware drivers, and may impact '.
	'performance with some specific NICs.This will take effect after you reboot the '.
	'machine or re-configure each interface.');

$section->addInput(new Form_Checkbox(
	'sharednet',
	'ARP Handling',
	'Suppress ARP messages',
	isset($pconfig['sharednet'])
))->setHelp('This option will suppress ARP log messages when multiple interfaces '.
	'reside on the same broadcast domain');

if (get_freebsd_version() == 8) {
	$section->addInput(new Form_Checkbox(
		'flowtable',
		'Enable flowtable support',
		$pconfig['flowtable']
	))->setHelp('Enables infrastructure for caching flows as a means of accelerating '.
		'L3 and L2 lookups as well as providing stateful load balancing when used with '.
		'RADIX_MPATH.');
}

$form->add($section);
print $form;

include("foot.inc");
