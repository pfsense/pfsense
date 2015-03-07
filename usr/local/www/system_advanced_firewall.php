<?php
/* $Id$ */
/*
	system_advanced_firewall.php
	part of pfSense
	Copyright (C) 2005-2007 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

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
##|*IDENT=page-system-advanced-firewall
##|*NAME=System: Advanced: Firewall and NAT page
##|*DESCR=Allow access to the 'System: Advanced: Firewall and NAT' page.
##|*MATCH=system_advanced_firewall.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pconfig['disablefilter'] = $config['system']['disablefilter'];
$pconfig['scrubnodf'] = $config['system']['scrubnodf'];
$pconfig['scrubrnid'] = $config['system']['scrubrnid'];
$pconfig['optimization'] = $config['filter']['optimization'];
$pconfig['adaptivestart'] = $config['system']['adaptivestart'];
$pconfig['adaptiveend'] = $config['system']['adaptiveend'];
$pconfig['maximumstates'] = $config['system']['maximumstates'];
$pconfig['aliasesresolveinterval'] = $config['system']['aliasesresolveinterval'];
$old_aliasesresolveinterval = $config['system']['aliasesresolveinterval'];
$pconfig['checkaliasesurlcert'] = isset($config['system']['checkaliasesurlcert']);
$pconfig['maximumtableentries'] = $config['system']['maximumtableentries'];
$pconfig['disablereplyto'] = isset($config['system']['disablereplyto']);
$pconfig['disablenegate'] = isset($config['system']['disablenegate']);
$pconfig['bogonsinterval'] = $config['system']['bogons']['interval'];
$pconfig['disablenatreflection'] = $config['system']['disablenatreflection'];
$pconfig['enablebinatreflection'] = $config['system']['enablebinatreflection'];
$pconfig['reflectiontimeout'] = $config['system']['reflectiontimeout'];
$pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
$pconfig['disablescrub'] = isset($config['system']['disablescrub']);
$pconfig['tftpinterface'] = explode(",", $config['system']['tftpinterface']);
$pconfig['disablevpnrules'] = isset($config['system']['disablevpnrules']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ((empty($_POST['adaptive-start']) && !empty($_POST['adaptive-end'])) || (!empty($_POST['adaptive-start']) && empty($_POST['adaptive-end'])))
		$input_errors[] = gettext("The Firewall Adaptive values must be set together.");
	if (!empty($_POST['adaptive-start']) && !is_numericint($_POST['adaptive-start'])) {
		$input_errors[] = gettext("The Firewall Adaptive Start value must be an integer.");
	}
	if (!empty($_POST['adaptive-end']) && !is_numericint($_POST['adaptive-end'])) {
		$input_errors[] = gettext("The Firewall Adaptive End value must be an integer.");
	}
	if ($_POST['firewall-maximum-states'] && !is_numericint($_POST['firewall-maximum-states'])) {
		$input_errors[] = gettext("The Firewall Maximum States value must be an integer.");
	}
	if ($_POST['aliases-hostnames-resolve-interval'] && !is_numericint($_POST['aliases-hostnames-resolve-interval'])) {
		$input_errors[] = gettext("The Aliases Hostname Resolve Interval value must be an integer.");
	}
	if ($_POST['firewall-maximum-table-entries'] && !is_numericint($_POST['firewall-maximum-table-entries'])) {
		$input_errors[] = gettext("The Firewall Maximum Table Entries value must be an integer.");
	}
	if ($_POST['reflection-timeout'] && !is_numericint($_POST['reflection-timeout'])) {
		$input_errors[] = gettext("The Reflection timeout must be an integer.");
	}

	ob_flush();
	flush();

	if (!$input_errors) {

		if($_POST['disable-firewall'] == "yes")
			$config['system']['disablefilter'] = "enabled";
		else
			unset($config['system']['disablefilter']);

		if($_POST['disable-auto-added-vpn-rules'] == "yes")
			$config['system']['disablevpnrules'] = true;
		else
			unset($config['system']['disablevpnrules']);

		if($_POST['ip-do-not-fragment-compatibility'] == "yes")
			$config['system']['scrubnodf'] = "enabled";
		else
			unset($config['system']['scrubnodf']);

		if($_POST['ip-random-id-generation'] == "yes")
			$config['system']['scrubrnid'] = "enabled";
		else
			unset($config['system']['scrubrnid']);

		if (!empty($_POST['adaptive-end']))
			$config['system']['adaptiveend'] = $_POST['adaptive-end'];
		else
			unset($config['system']['adaptiveend']);
		if (!empty($_POST['adaptive-start']))
			$config['system']['adaptivestart'] = $_POST['adaptive-start'];
		else
			unset($config['system']['adaptive-start']);

		if ($_POST['check-certificate-of-aliases-urls'] == "yes")
			$config['system']['checkaliasesurlcert'] = true;
		else
			unset($config['system']['checkaliasesurlcert']);

		$config['system']['optimization'] = $_POST['firewall-optimization-options'];
		$config['system']['maximumstates'] = $_POST['firewall-maximum-states'];
		$config['system']['aliasesresolveinterval'] = $_POST['aliases-hostnames-resolve-interval'];
		$config['system']['maximumtableentries'] = $_POST['firewall-maximum-table-entries'];

		if($_POST['nat-reflection-mode-for-port-forwards'] == "proxy") {
			unset($config['system']['disablenatreflection']);
			unset($config['system']['enablenatreflectionpurenat']);
		} else if($_POST['nat-reflection-mode-for-port-forwards'] == "purenat") {
			unset($config['system']['disablenatreflection']);
			$config['system']['enablenatreflectionpurenat'] = "yes";
		} else {
			$config['system']['disablenatreflection'] = "yes";
			unset($config['system']['enablenatreflectionpurenat']);
		}

		if($_POST['enable-nat-reflection-for-1-1-nat'] == "yes")
			$config['system']['enablebinatreflection'] = "yes";
		else
			unset($config['system']['enablebinatreflection']);

		if($_POST['disable-reply-to'] == "yes")
			$config['system']['disablereplyto'] = $_POST['disable-reply-to'];
		else
			unset($config['system']['disablereplyto']);

		if($_POST['disable-negate-rules'] == "yes")
			$config['system']['disablenegate'] = $_POST['disable-negate-rules'];
		else
			unset($config['system']['disablenegate']);

		if($_POST['enable-automatic-outbound-nat-for-reflection'] == "yes")
			$config['system']['enablenatreflectionhelper'] = "yes";
		else
			unset($config['system']['enablenatreflectionhelper']);

		$config['system']['reflectiontimeout'] = $_POST['reflection-timeout'];

		if($_POST['static-route-filtering'] == "yes")
			$config['filter']['bypassstaticroutes'] = $_POST['static-route-filtering'];
		elseif(isset($config['filter']['bypassstaticroutes']))
			unset($config['filter']['bypassstaticroutes']);

		if($_POST['disable-firewall-scrub'] == "yes")
			$config['system']['disablescrub'] = $_POST['disable-firewall-scrub'];
		else
			unset($config['system']['disablescrub']);

		if ($_POST['tftp-proxy'])
			$config['system']['tftpinterface'] = implode(",", $_POST['tftp-proxy']);
		else
			unset($config['system']['tftpinterface']);

		if ($_POST['update-frequency'] != $config['system']['bogons']['interval']) {
			switch ($_POST['update-frequency']) {
				case 'daily':
					install_cron_job("/usr/bin/nice -n20 /etc/rc.update_bogons.sh", true, "1", "3", "*", "*", "*");
					break;
				case 'weekly':
					install_cron_job("/usr/bin/nice -n20 /etc/rc.update_bogons.sh", true, "1", "3", "*", "*", "0");
					break;
				case 'monthly':
					// fall through
				default:
					install_cron_job("/usr/bin/nice -n20 /etc/rc.update_bogons.sh", true, "1", "3", "1", "*", "*");
			}
			$config['system']['bogons']['interval'] = $_POST['update-frequency'];
		}

		write_config();

		// Kill filterdns when value changes, filter_configure() will restart it
		if (($old_aliasesresolveinterval != $config['system']['aliasesresolveinterval']) &&
			isvalidpid("{$g['varrun_path']}/filterdns.pid"))
			killbypid("{$g['varrun_path']}/filterdns.pid");

		$retval = 0;
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
			$savemsg = get_std_save_message($retval);
		else
			$savemsg = $retval;
	}
}

$pgtitle = array(gettext("System"),gettext("Advanced: Firewall and NAT"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(gettext("Firewall / NAT"), true, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

?><div id="container"><?php

require('classes/Form.class.php');
$form = new Form;
$section = new Form_Section('Firewall Advanced');

$section->addInput(new Form_Checkbox(
	'ip-do-not-fragment-compatibility',
	'IP Do-Not-Fragment compatibility',
	'Clear invalid DF bits instead of dropping the packets',
	isset($config['system']['scrubnodf'])
))->setHelp('This allows for communications with hosts that generate fragmented '.
	'packets with the don"t fragment (DF) bit set. Linux NFS is known to do this. '.
	'This will cause the filter to not drop such packets but instead clear the don"t '.
	'fragment bit.');

$section->addInput(new Form_Checkbox(
	'ip-random-id-generation',
	'IP Random id generation',
	'Insert a stronger id into IP header of packets passing through the filter.',
	isset($config['system']['scrubrnid'])
))->setHelp('Replaces the IP identification field of packets with random values to '.
	'compensate for operating systems that use predictable values. This option only '.
	'applies to packets that are not fragmented after the optional packet '.
	'reassembly.');

$section->addInput($input = new Form_Select(
	'firewall-optimization-options',
	'Firewall Optimization Options',
	$config['system']['optimization'],
	array(
		'normal' => 'normal: the default optimization algorithm',
		'high-latency' => 'high-latency: used for eg. satellite links. Expires idle connections later than default',
		'aggressive' => 'aggressive: expires idle connections quicker. More efficient use of CPU and memory but can drop legitimate idle connections',
		'conservative' => 'conservative: tries to avoid dropping any legitimate idle connections at the expense of increased memory usage and CPU utilization.',
	)
))->setHelp('Select the type of state table optimization to use');

$section->addInput(new Form_Checkbox(
	'disable-firewall',
	'Disable Firewall',
	'Disable all packet filtering.',
	isset($config['system']['disablefilter'])
))->setHelp('Note: This converts %s into a routing only platform!<br/>'.
	'Note: This will also turn off NAT! If you only want to disable NAT, '.
	'and not firewall rules, visit the <a href="firewall_nat_out.php">Outbound '.
	'NAT</a>page.', [$g["product_name"]]);

$section->addInput(new Form_Checkbox(
	'disable-firewall-scrub',
	'Disable Firewall Scrub',
	'Disables the PF scrubbing option which can sometimes interfere with NFS and PPTP traffic.',
	isset($config['system']['disablescrub'])
));

$group = new Form_Group('Firewall Adaptive Timeouts');

$group->add(new Form_Input(
	'adaptive-start',
	'Adaptive start',
	'number',
	$pconfig['adaptivestart'],
	['min' => 1]
))->setHelp('When the number of state entries exceeds this value, adaptive '.
	'scaling begins.  All timeout values are scaled linearly with factor '.
	'(adaptive.end - number of states) / (adaptive.end - adaptive.start).');

$group->add(new Form_Input(
	'adaptive-end',
	'Adaptive end',
	'number',
	$pconfig['adaptiveend'],
	['min' => 1]
))->setHelp('When reaching this number of state entries, all timeout values '.
	'become zero, effectively purging all state entries immediately.  This '.
	'value is used to define the scale factor, it should not actually be '.
	'reached (set a lower state limit, see below).');

$group->setHelp('Timeouts for states can be scaled adaptively as the number of '.
	'state table entries grows. Leave blank for the default (0)');

$section->add($group);

$section->addInput(new Form_Input(
	'firewall-maximum-states',
	'Firewall Maximum States',
	'number',
	$pconfig['maximumstates'],
	['min' => 1, 'placeholder' => pfsense_default_state_size()]
))->setHelp('Maximum number of connections to hold in the firewall state table.. '.
	'<br/>Note: Leave this blank for the default. On your system the default '.
	'size is: %d', [pfsense_default_state_size()]);

$section->addInput(new Form_Input(
	'firewall-maximum-table-entries',
	'Firewall Maximum Table Entries',
	'text',
	$pconfig['maximumtableentries'],
	['placeholder' => pfsense_default_table_entries_size()]
))->setHelp('Maximum number of table entries for systems such as aliases, '.
	'sshlockout, snort, etc, combined..<br/>Note: Leave this blank for the '.
	'default. On your system the default size is: %d',
	[pfsense_default_table_entries_size()]);

$section->addInput(new Form_Checkbox(
	'static-route-filtering',
	'Static route filtering',
	'Bypass firewall rules for traffic on the same interface',
	$pconfig['bypassstaticroutes']
))->setHelp('This option only applies if you have defined one or more static '.
	'routes. If it is enabled, traffic that enters and leaves through the same '.
	'interface will not be checked by the firewall. This may be desirable in some '.
	'situations where multiple subnets are connected to the same interface.');

$section->addInput(new Form_Checkbox(
	'disable-auto-added-vpn-rules',
	'Disable Auto-added VPN rules',
	'Disable all auto-added VPN rules.',
	isset($config['system']['disablevpnrules'])
))->setHelp('<span>Note: This disables automatically added rules for IPsec, '.
	'PPTP.</span>');

$section->addInput(new Form_Checkbox(
	'disable-reply-to',
	'Disable reply-to',
	'Disable reply-to on WAN rules',
	$pconfig['disablereplyto']
))->setHelp('With Multi-WAN you generally want to ensure traffic leaves the same '.
	'interface it arrives on, hence reply-to is added automatically by default. When '.
	'using bridging, you must disable this behavior if the WAN gateway IP is '.
	'different from the gateway IP of the hosts behind the bridged interface.');

$section->addInput(new Form_Checkbox(
	'disable-negate-rules',
	'Disable Negate rules',
	'Disable Negate rule on policy routing rules',
	$pconfig['disablenegate']
))->setHelp('With Multi-WAN you generally want to ensure traffic reaches directly '.
	'connected networks and VPN networks when using policy routing. You can disable '.
	'this for special purposes but it requires manually creating rules for these '.
	'networks');

$section->addInput(new Form_Input(
	'aliases-hostnames-resolve-interval',
	'Aliases Hostnames Resolve Interval',
	'text',
	$pconfig['aliasesresolveinterval'],
	['placeholder' => '300']
))->setHelp('Interval, in seconds, that will be used to resolve hostnames '.
	'configured on aliases.. <br/>Note:  Leave this blank for the default '.
	'(300s).');

$section->addInput(new Form_Checkbox(
	'check-certificate-of-aliases-urls',
	'Check certificate of aliases URLs',
	'Verify HTTPS certificates when downloading alias URLs',
	$pconfig['checkaliasesurlcert']
))->setHelp('Make sure the certificate is valid for all HTTPS addresses on '.
	'aliases. If it\'s not valid or is revoked, do not download it.');

$form->add($section);
$section = new Form_Section('Bogon Networks');

$section->addInput(new Form_Select(
	'update-frequency',
	'Update Frequency',
	empty($pconfig['bogonsinterval']) ? 'monthly' : $pconfig['bogonsinterval'],
	array(
		'monthly' => 'Monthly',
		'weekly' => 'Weekly',
		'daily' => 'Daily',
	)
))->setHelp('The frequency of updating the lists of IP addresses that are '.
	'reserved (but not RFC 1918) or not yet assigned by IANA.');

$form->add($section);

if (count($config['interfaces']) > 1)
{
	$section = new Form_Section('Network Address Translation');

	if (isset($config['system']['disablenatreflection']))
		$value = 'disable';
	elseif (!isset($config['system']['enablenatreflectionpurenat']))
		$value = 'proxy';
	else
		$value = 'purenat';

	$section->addInput(new Form_Select(
		'nat-reflection-mode-for-port-forwards',
		'NAT Reflection mode for port forwards',
		$value,
		array(
			'disable' => 'disabled',
			'proxy' => 'NAT + proxy',
			'purenat' => 'Pure NAT',
		)
	))->setHelp('<ul><li>The pure NAT mode uses a set of NAT rules to direct '.
		'packets to the target of the port forward. It has better scalability, '.
		'but it must be possible to accurately determine the interface and '.
		'gateway IP used for communication with the target at the time the '.
		'rules are loaded. There are no inherent limits to the number of ports '.
		'other than the limits of the protocols.  All protocols available for '.
		'port forwards are supported.</li><li>The NAT + proxy mode uses a '.
		'helper program to send packets to the target of the port forward. '.
		'It is useful in setups where the interface and/or gateway IP used '.
		'for communication with the target cannot be accurately determined at '.
		'the time the rules are loaded. Reflection rules are not created for '.
		'ranges larger than 500 ports and will not be used for more than 1000 '.
		'ports total between all port forwards. Only TCP and UDP protocols are '.
		'supported.</li></ul>Individual rules may be configured to override '.
		'this system setting on a per-rule basis.');

	$section->addInput(new Form_Input(
		'reflection-timeout',
		'Reflection Timeout',
		'number',
		$config['system']['reflectiontimeout'],
		['min' => 1]
	))->setHelp('Enter value for Reflection timeout in seconds.<br/>Note: Only '.
		'applies to Reflection on port forwards in NAT + proxy mode.');

	$section->addInput(new Form_Checkbox(
		'enable-nat-reflection-for-1-1-nat',
		'Enable NAT Reflection for 1:1 NAT',
		'Automatic creation of additional NAT redirect rules from within your internal networks.',
		isset($config['system']['enablebinatreflection'])
	))->setHelp('Note: Reflection on 1:1 mappings is only for the inbound component of '.
		'the 1:1 mappings. This functions the same as the pure NAT mode for port '.
		'forwards. For more details, refer to the pure NAT mode description '.
		'above. Individual rules may be configured to override this system setting on a '.
		'per-rule basis.');

	$section->addInput(new Form_Checkbox(
		'enable-automatic-outbound-nat-for-reflection',
		'Enable automatic outbound NAT for Reflection',
		'Automatic create outbound NAT rules that direct traffic back out to the same subnet it originated from.',
		isset($config['system']['enablenatreflectionhelper'])
	))->setHelp('Required for full functionality of the pure NAT mode of NAT '.
		'Reflection for port forwards or NAT Reflection for 1:1 NAT.Note: This only works '.
		'for assigned interfaces.  Other interfaces require manually creating the '.
		'outbound NAT rules that direct the reply packets back through the router.');

	$section->addInput(new Form_Select(
		'tftp-proxy',
		'TFTP Proxy',
		$pconfig['tftpinterface'],
		get_configured_interface_with_descr(),
		true
	))->setHelp('Choose the interfaces where you want TFTP proxy helper to be enabled.');

	$form->add($section);
}

print $form;
include("foot.inc");