<?php
/*
	interfaces_ppps_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2003-2004 Manuel Kasper <mk@neon1.net>
 *	Copyright (c)  2010 Gabriel B. <gnoahb@gmail.com>
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
##|*IDENT=page-interfaces-ppps-edit
##|*NAME=Interfaces: PPPs: Edit
##|*DESCR=Allow access to the 'Interfaces: PPPs: Edit' page.
##|*MATCH=interfaces_ppps_edit.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");

define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");

if (!is_array($config['ppps']['ppp'])) {
	$config['ppps']['ppp'] = array();
}

$a_ppps = &$config['ppps']['ppp'];

$iflist = get_configured_interface_with_descr();
$portlist = get_interface_list();
$portlist = array_merge($portlist, $iflist);

if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
	foreach ($config['vlans']['vlan'] as $vlan) {
		$portlist[$vlan['vlanif']] = $vlan;
	}
}

if ($_GET && $_GET['type']) {
	$pconfig['type'] = $_GET['type'];
}

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}

if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_ppps[$id]) {
	$pconfig['ptpid'] = $a_ppps[$id]['ptpid'];
	$pconfig['type'] = $a_ppps[$id]['type'];
	//$pconfig['if'] = $a_ppps[$id]['if'];
	$pconfig['interfaces'] = $a_ppps[$id]['ports'];
	$pconfig['username'] = $a_ppps[$id]['username'];
	$pconfig['password'] = base64_decode($a_ppps[$id]['password']);
	if (isset($a_ppps[$id]['ondemand'])) {
		$pconfig['ondemand'] = true;
	}
	$pconfig['idletimeout'] = $a_ppps[$id]['idletimeout'];
	$pconfig['uptime'] = $a_ppps[$id]['uptime'];
	$pconfig['descr'] = $a_ppps[$id]['descr'];
	$pconfig['bandwidth'] = explode(",", $a_ppps[$id]['bandwidth']);
	$pconfig['mtu'] = explode(",", $a_ppps[$id]['mtu']);
	$pconfig['mru'] = explode(",", $a_ppps[$id]['mru']);
	$pconfig['mrru'] = explode(",", $a_ppps[$id]['mrru']);

	if (isset($a_ppps[$id]['shortseq'])) {
		$pconfig['shortseq'] = true;
	}

	if (isset($a_ppps[$id]['acfcomp'])) {
		$pconfig['acfcomp'] = true;
	}
	if (isset($a_ppps[$id]['protocomp'])) {
		$pconfig['protocomp'] = true;
	}
	if (isset($a_ppps[$id]['vjcomp'])) {
		$pconfig['vjcomp'] = true;
	}
	if (isset($a_ppps[$id]['tcpmssfix'])) {
		$pconfig['tcpmssfix'] = true;
	}
	switch ($a_ppps[$id]['type']) {
		case "ppp":
			$pconfig['initstr'] = base64_decode($a_ppps[$id]['initstr']);
			$pconfig['simpin'] = $a_ppps[$id]['simpin'];
			$pconfig['pin-wait'] = $a_ppps[$id]['pin-wait'];
			$pconfig['apn'] = $a_ppps[$id]['apn'];
			$pconfig['apnum'] = $a_ppps[$id]['apnum'];
			$pconfig['phone'] = $a_ppps[$id]['phone'];
			$pconfig['connect-timeout'] = $a_ppps[$id]['connect-timeout'];
			$pconfig['localip'] = explode(",", $a_ppps[$id]['localip']);
			$pconfig['gateway'] = explode(",", $a_ppps[$id]['gateway']);
			break;
		case "l2tp":
		case "pptp":
			$pconfig['localip'] = explode(",", $a_ppps[$id]['localip']);
			$pconfig['subnet'] = explode(",", $a_ppps[$id]['subnet']);
			$pconfig['gateway'] = explode(",", $a_ppps[$id]['gateway']);
		case "pppoe":
			$pconfig['provider'] = $a_ppps[$id]['provider'];
			if (isset($a_ppps[$id]['provider']) and empty($a_ppps[$id]['provider'])) {
				$pconfig['null_service'] = true;
			}
			/* ================================================ */
			/* = force a connection reset at a specific time? = */
			/* ================================================ */

			if (isset($a_ppps[$id]['pppoe-reset-type'])) {
				$pconfig['pppoe-reset-type'] = $a_ppps[$id]['pppoe-reset-type'];
				$itemhash = getMPDCRONSettings($a_ppps[$id]['if']);
				$cronitem = $itemhash['ITEM'];
				if (isset($cronitem)) {
					$resetTime = "{$cronitem['minute']} {$cronitem['hour']} {$cronitem['mday']} {$cronitem['month']} {$cronitem['wday']}";
				} else {
					$resetTime = NULL;
				}

				if ($a_ppps[$id]['pppoe-reset-type'] == "custom") {
					$resetTime_a = explode(" ", $resetTime);
					$pconfig['pppoe_pr_custom'] = true;
					$pconfig['pppoe_resetminute'] = $resetTime_a[0];
					$pconfig['pppoe_resethour'] = $resetTime_a[1];
					/*  just initialize $pconfig['pppoe_resetdate'] if the
					 *  corresponding item contains appropriate numeric values.
					 */
					if ($resetTime_a[2] <> "*" && $resetTime_a[3] <> "*") {
						$pconfig['pppoe_resetdate'] = "{$resetTime_a[3]}/{$resetTime_a[2]}/" . date("Y");
					}
				} else if ($a_ppps[$id]['pppoe-reset-type'] == "preset") {
					$pconfig['pppoe_pr_preset'] = true;

					switch ($resetTime) {
						case CRON_MONTHLY_PATTERN:
							$pconfig['pppoe_monthly'] = true;
							break;
						case CRON_WEEKLY_PATTERN:
							$pconfig['pppoe_weekly'] = true;
							break;
						case CRON_DAILY_PATTERN:
							$pconfig['pppoe_daily'] = true;
							break;
						case CRON_HOURLY_PATTERN:
							$pconfig['pppoe_hourly'] = true;
							break;
					}
				}
			}
			break;
	}

} else {
	$pconfig['ptpid'] = interfaces_ptpid_next();
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* okay first of all, cause we are just hiding the PPPoE HTML
	 * fields related to PPPoE resets, we are going to unset $_POST
	 * vars, if the reset feature should not be used. Otherwise the
	 * data validation procedure below, may trigger a false error
	 * message.
	 */
	if (empty($_POST['pppoe-reset-type'])) {
		unset($_POST['pppoe_resethour']);
		unset($_POST['pppoe_resetminute']);
		unset($_POST['pppoe_resetdate']);
		unset($_POST['pppoe_pr_preset_val']);
	}

	/* input validation */
	switch ($_POST['type']) {
		case "ppp":
			$reqdfields = explode(" ", "interfaces phone");
			$reqdfieldsn = array(gettext("Link Interface(s)"), gettext("Phone Number"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "pppoe":
			if ($_POST['ondemand']) {
				$reqdfields = explode(" ", "interfaces username passwordfld ondemand idletimeout");
				$reqdfieldsn = array(gettext("Link Interface(s)"), gettext("Username"), gettext("Password"), gettext("Dial on demand"), gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "interfaces username passwordfld");
				$reqdfieldsn = array(gettext("Link Interface(s)"), gettext("Username"), gettext("Password"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "l2tp":
		case "pptp":
			if ($_POST['ondemand']) {
				$reqdfields = explode(" ", "interfaces username passwordfld localip subnet gateway ondemand idletimeout");
				$reqdfieldsn = array(gettext("Link Interface(s)"), gettext("Username"), gettext("Password"), gettext("Local IP address"), gettext("Subnet"), gettext("Remote IP address"), gettext("Dial on demand"), gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "interfaces username passwordfld localip subnet gateway");
				$reqdfieldsn = array(gettext("Link Interface(s)"), gettext("Username"), gettext("Password"), gettext("Local IP address"), gettext("Subnet"), gettext("Remote IP address"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		default:
			$input_errors[] = gettext("Please choose a Link Type.");
			break;
	}
	if ($_POST['passwordfld'] != $_POST['passwordfld_confirm']) {
		$input_errors[] = gettext("Password and confirmed password must match.");
	}
	if ($_POST['type'] == "ppp" && count($_POST['interfaces']) > 1) {
		$input_errors[] = gettext("Multilink connections (MLPPP) using the PPP link type is not currently supported. Please select only one Link Interface.");
	}
	if ($_POST['provider'] && !is_domain($_POST['provider'])) {
		$input_errors[] = gettext("The Service name contains invalid characters.");
	}
	if ($_POST['provider'] && $_POST['null_service']) {
		$input_errors[] = gettext("Do not specify both a Service name and a NULL Service name.");
	}
	if (($_POST['idletimeout'] != "") && !is_numericint($_POST['idletimeout'])) {
		$input_errors[] = gettext("The idle timeout value must be an integer.");
	}
	if ($_POST['pppoe-reset-type'] == "custom" && $_POST['pppoe_resethour'] <> "" && !is_numericint($_POST['pppoe_resethour']) &&
	    $_POST['pppoe_resethour'] >= 0 && $_POST['pppoe_resethour'] <=23) {
		$input_errors[] = gettext("A valid PPPoE reset hour must be specified (0-23).");
	}
	if ($_POST['pppoe-reset-type'] == "custom" && $_POST['pppoe_resetminute'] <> "" && !is_numericint($_POST['pppoe_resetminute']) &&
	    $_POST['pppoe_resetminute'] >= 0 && $_POST['pppoe_resetminute'] <=59) {
		$input_errors[] = gettext("A valid PPPoE reset minute must be specified (0-59).");
	}
	if ($_POST['pppoe-reset-type'] == "custom" && $_POST['pppoe_resetdate'] <> "" && !is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))) {
		$input_errors[] = gettext("A valid PPPoE reset date must be specified (mm/dd/yyyy).");
	}
	if ($_POST['pppoe-reset-type'] == "custom" && $_POST['pppoe_resetdate'] <> "" && is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))) {
		$date_nums = explode("/", $_POST['pppoe_resetdate']);
		if ($date_nums[0] < 1 || $date_nums[0] > 12) {
			$input_errors[] = gettext("A valid PPPoE reset month must be specified (1-12) in the Custom PPPoE Periodic reset fields.");
		}
		if ($date_nums[1] < 1 || $date_nums[1] > 31) {
			$input_errors[] = gettext("A valid PPPoE reset day of month must be specified (1-31) in the Custom PPPoE Periodic reset fields. No checks are done on valid # of days per month");
		}
		if ($date_nums[2] < date("Y")) {
			$input_errors[] = gettext("A valid PPPoE reset year must be specified. Don't select a year in the past!");
		}
	}

	if (is_array($_POST['interfaces'])) {
		foreach ($_POST['interfaces'] as $iface) {
			if ($_POST['localip'][$iface] && !is_ipaddr($_POST['localip'][$iface])) {
				$input_errors[] = sprintf(gettext("A valid local IP address must be specified for %s."), $iface);
			}
			if ($_POST['gateway'][$iface] && !is_ipaddr($_POST['gateway'][$iface]) && !is_hostname($_POST['gateway'][$iface])) {
				$input_errors[] = sprintf(gettext("A valid gateway IP address OR hostname must be specified for %s."), $iface);
			}
			if ($_POST['bandwidth'][$iface] && !is_numericint($_POST['bandwidth'][$iface])) {
				$input_errors[] = sprintf(gettext("The bandwidth value for %s must be an integer."), $iface);
			}
			if ($_POST['mtu'][$iface] && ($_POST['mtu'][$iface] < 576)) {
				$input_errors[] = sprintf(gettext("The MTU for %s must be greater than 576 bytes."), $iface);
			}
			if ($_POST['mru'][$iface] && ($_POST['mru'][$iface] < 576)) {
				$input_errors[] = sprintf(gettext("The MRU for %s must be greater than 576 bytes."), $iface);
			}
		}
	}

	if (!$input_errors) {
		$ppp = array();
		$ppp['ptpid'] = $_POST['ptpid'];
		$ppp['type'] = $_POST['type'];
		$ppp['if'] = $ppp['type'].$ppp['ptpid'];
		$ppp['ports'] = implode(',', $_POST['interfaces']);
		$ppp['username'] = $_POST['username'];
		if ($_POST['passwordfld'] != DMYPWD) {
			$ppp['password'] = base64_encode($_POST['passwordfld']);
		} else {
			$ppp['password'] = $a_ppps[$id]['password'];
		}
		$ppp['ondemand'] = $_POST['ondemand'] ? true : false;
		if (!empty($_POST['idletimeout'])) {
			$ppp['idletimeout'] = $_POST['idletimeout'];
		} else {
			unset($ppp['idletimeout']);
		}
		$ppp['uptime'] = $_POST['uptime'] ? true : false;
		if (!empty($_POST['descr'])) {
			$ppp['descr'] = $_POST['descr'];
		} else {
			unset($ppp['descr']);
		}

		// Loop through fields associated with an individual link/port and make an array of the data
		$port_fields = array("localip", "gateway", "subnet", "bandwidth", "mtu", "mru", "mrru");
		foreach ($_POST['interfaces'] as $iface) {
			foreach ($port_fields as $field_label) {
				if (isset($_POST[$field_label][$iface])) {
					$port_data[$field_label][] = $_POST[$field_label][$iface];
				}
			}
		}

		switch ($_POST['type']) {
			case "ppp":
				if (!empty($_POST['initstr'])) {
					$ppp['initstr'] = base64_encode($_POST['initstr']);
				} else {
					unset($ppp['initstr']);
				}
				if (!empty($_POST['simpin'])) {
					$ppp['simpin'] = $_POST['simpin'];
					$ppp['pin-wait'] = $_POST['pin-wait'];
				} else {
					unset($ppp['simpin']);
					unset($ppp['pin-wait']);
				}

				if (!empty($_POST['apn'])) {
					$ppp['apn'] = $_POST['apn'];
					$ppp['apnum'] = $_POST['apnum'];
				} else {
					unset($ppp['apn']);
					unset($ppp['apnum']);
				}

				$ppp['phone'] = $_POST['phone'];
				$ppp['localip'] = implode(',', $port_data['localip']);
				$ppp['gateway'] = implode(',', $port_data['gateway']);
				if (!empty($_POST['connect-timeout'])) {
					$ppp['connect-timeout'] = $_POST['connect-timeout'];
				} else {
					unset($ppp['connect-timeout']);
				}
				break;
			case "pppoe":
				if (!empty($_POST['provider'])) {
					$ppp['provider'] = $_POST['provider'];
				} else {
					unset($ppp['provider']);
					$ppp['provider'] = $_POST['null_service'] ? true : false;
				}
				if (!empty($_POST['pppoe-reset-type'])) {
					$ppp['pppoe-reset-type'] = $_POST['pppoe-reset-type'];
				} else {
					unset($ppp['pppoe-reset-type']);
				}

				break;
			case "pptp":
			case "l2tp":
				$ppp['localip'] = implode(',', $port_data['localip']);
				$ppp['subnet'] = implode(',', $port_data['subnet']);
				$ppp['gateway'] = implode(',', $port_data['gateway']);
				break;
			default:
				break;

		}

		$ppp['shortseq'] = $_POST['shortseq'] ? true : false;
		$ppp['acfcomp'] = $_POST['acfcomp'] ? true : false;
		$ppp['protocomp'] = $_POST['protocomp'] ? true : false;
		$ppp['vjcomp'] = $_POST['vjcomp'] ? true : false;
		$ppp['tcpmssfix'] = $_POST['tcpmssfix'] ? true : false;
		$ppp['bandwidth'] = implode(',', $port_data['bandwidth']);
		if (is_array($port_data['mtu'])) {
			$ppp['mtu'] = implode(',', $port_data['mtu']);
		}
		if (is_array($port_data['mru'])) {
			$ppp['mru'] = implode(',', $port_data['mru']);
		}
		if (is_array($port_data['mrru'])) {
			$ppp['mrru'] = implode(',', $port_data['mrru']);
		}

		/* handle_pppoe_reset is called here because if user changes Link Type from PPPoE to another type we
		must be able to clear the config data in the <cron> section of config.xml if it exists
		*/
		handle_pppoe_reset($_POST);

		if (isset($id) && $a_ppps[$id]) {
			$a_ppps[$id] = $ppp;
		} else {
			$a_ppps[] = $ppp;
		}

		write_config();
		configure_cron();

		foreach ($iflist as $pppif => $ifdescr) {
			if ($config['interfaces'][$pppif]['if'] == $ppp['if']) {
				interface_ppps_configure($pppif);
			}
		}
		header("Location: interfaces_ppps.php");
		exit;
	}
} // end if ($_POST)

$pgtitle = array(gettext("Interfaces"), gettext("PPPs"), gettext("Edit"));
$shortcut_section = "interfaces";
include("head.inc");

$types = array("select" => gettext("Select"), "ppp" => "PPP", "pppoe" => "PPPoE", "pptp" => "PPTP", "l2tp" => "L2TP"/*, "tcp" => "TCP", "udp" => "UDP"*/);

$serviceproviders_xml = "/usr/local/share/mobile-broadband-provider-info/serviceproviders.xml";
$serviceproviders_contents = file_get_contents($serviceproviders_xml);
$serviceproviders_attr = xml2array($serviceproviders_contents, 1, "attr");

$serviceproviders = &$serviceproviders_attr['serviceproviders']['country'];

//print_r($serviceproviders);

function build_country_list() {
	global $serviceproviders;

	$list = array();

	// get_country_name is in pfSense-utils.inc
	$country_list = get_country_name("ALL");

	foreach ($country_list as $country) {
		$list[$country['code']] = $country['name'];
	}

	return($list);
}

$port_count = 0;
$serport_count = 0;

function build_link_list() {
	global $pconfig, $portlist, $port_count, $serport_count;

	$linklist = array('list'	 => array(),
					  'selected' => array());

	$selected_ports = array();

	if ($pconfig['interfaces']) {
		$selected_ports = explode(',', $pconfig['interfaces']);
	}

	if (!is_dir("/var/spool/lock")) {
		mwexec("/bin/mkdir -p /var/spool/lock");
	}

	if ($pconfig['type'] == 'ppp') {
		$serialports = glob("/dev/cua[a-zA-Z][0-9]{,.[0-9],.[0-9][0-9],[0-9],[0-9].[0-9],[0-9].[0-9][0-9]}", GLOB_BRACE);
		$serport_count = 0;

		foreach ($serialports as $port) {
			$serport_count++;

			$linklist['list'][$port] = trim($port);

			if (in_array($port, $selected_ports)) {
				array_push($linklist['selected'], $port);
			}
		}
	} else {
		$port_count = 0;
		foreach ($portlist as $ifn => $ifinfo) {
			$port_count++;
			$string = "";

			if (is_array($ifinfo)) {
				$string .= $ifn;
				if ($ifinfo['mac']) {
					$string .= " ({$ifinfo['mac']})";
				}
			} else {
				$string .= $ifinfo;
			}

			$linklist['list'][$ifn] = $string;

			if (in_array($ifn, $selected_ports)) {
				array_push($linklist['selected'], $ifn);
			}
		}

		if ($serport_count > $port_count) {
			$port_count = $serport_count;
		}
	}

	return($linklist);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$linkparamstr = gettext('Bandwidth is set only for MLPPP connections and when links have different bandwidths' . '<br />' .
						'MTU defaults to 1492' . '<br />' .
						'MRU will be auto-negotiated by default' . '<br />' .
						'Set only for MLPPP connections. MRRU will be auto-negotiated by default.');

$form = new Form();

$section = new Form_Section('PPPs Configuration');

$section->addInput(new Form_Select(
	'type',
	'Link Type',
	$pconfig['type'],
	$types
));

$linklist = build_link_list();

$section->addInput(new Form_Select(
	'interfaces',
	'Link Interface(s)',
	$linklist['selected'],
	$linklist['list'],
	true // Allow multiples
))->addClass('interfaces')->setHelp('Select at least two interfaces for Multilink (MLPPP) connections.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference. Description will appear in the "Interfaces Assign" select lists.');

$section->addInput(new Form_Select(
	'country',
	'Country',
	$pconfig['country'],
	build_country_list()
));

$section->addInput(new Form_Select(
	'provider',
	'Provider',
	$pconfig['provider'],
	[]
));

$section->addInput(new Form_Select(
	'providerplan',
	'Plan',
	$pconfig['providerplan'],
	[]
))->setHelp('Select to fill in data for your service provider.');

$section->addInput(new Form_Input(
	'username',
	'Username',
	'text',
	$pconfig['username']
));

$section->addPassword(new Form_Input(
	'passwordfld',
	'Password',
	'password',
	$pconfig['password']
));

// These elements are hidden by default, and un-hidden in Javascript
if ($pconfig['type'] == 'pptp' || $pconfig['type'] == 'l2tp') {
	$j = 0;
	foreach ($linklist['list'] as $ifnm => $nm) {

		$group = new Form_Group('IP/Gateway (' . $ifnm . ')');

		$group->add(new Form_IpAddress(
			'localiplabel' . $j,
			null,
			$pconfig['localip'][$j]
		))->addMask('subnet' . $j, $pconfig['subnet'][$j], 31)->setHelp('IP Address');

		$group->add(new Form_Input(
			'gateway' . $j,
			null,
			'text',
			$pconfig['gateway'][$j]
		))->setHelp('IP or Hostname');

		$j++;

		$group->addClass('localip')->addClass('localip' . $ifnm);
		$section->add($group);
	}
}

if ($pconfig['type'] == 'ppp') {
	$section->addInput(new Form_Input(
		'phone',
		'Phone number',
		'text',
		$pconfig['phone']
	))->setHelp('Typically *99# for GSM networks and #777 for CDMA networks');
}

$section->addInput(new Form_Input(
	'apn',
	'Access Point Name (APN)',
	'text',
	$pconfig['apn']
));

$section->addInput(new Form_Input(
	'apnum',
	'APN number (optional)',
	'text',
	$pconfig['apnum']
))->setHelp('Defaults to 1 if APN is set. Ignored if no APN is set.');

$section->addInput(new Form_Input(
	'simpin',
	'SIM PIN',
	'text',
	$pconfig['simpin']
));

$section->addInput(new Form_Input(
	'pin-wait',
	'SIM PIN wait',
	'text',
	$pconfig['pin-wait']
))->setHelp('Time to wait for SIM to discover network after PIN is sent to SIM (seconds).');

$section->addInput(new Form_Input(
	'initstr',
	'Init string',
	'text',
	$pconfig['initstr']
))->setHelp('Enter the modem initialization string here. Do NOT include the "AT" string at the beginning of the command. ' .
		  'Many modern USB 3G modems don\'t need an initialization string.');

$section->addInput(new Form_Input(
	'connect-timeout',
	'Connection Timeout',
	'text',
	$pconfig['connect-timeout']
))->setHelp('Enter timeout in seconds for connection to be established (sec.) Default is 45 sec.');

$section->addInput(new Form_Checkbox(
	'uptime',
	'Uptime logging',
	'Enable persistent logging of connection uptime. ',
	isset($pconfig['uptime'])
))->setHelp(sprintf('Causes cumulative uptime to be recorded and displayed on the %sStatus->Interfaces%s page.', '<a href="status_interfaces.php">', '</a>'));

$group = new Form_Group('Service name');
$group->addClass('pppoe');

$group->add(new Form_Input(
	'provider',
	null,
	'text',
	$pconfig['provider']
));

$group->add(new Form_Checkbox(
	'null_service',
	null,
	'Configure NULL service name',
	$pconfig['null_service']
));

$group->setHelp('This field can usually be left empty. Service name will not be configured if this field is empty. ' .
				'Check the "Configure NULL" box to configure a blank Service name.');

$section->add($group);

$section->addInput(new Form_Select(
	'pppoe-reset-type',
	'Periodic Reset',
	$pconfig['pppoe-reset-type'],
	array(
		'' => 'Disabled',
		'custom' => 'Custom',
		'preset' => 'Pre-set'
	)
))->addClass('pppoe')->setHelp('Select a reset timing type');

$group = new Form_Group('Reset Date/Time');
$group->addClass('pppoe-reset-date');

$group->add(new Form_Input(
	'pppoe_resethour',
	null,
	'text',
	$pconfig['pppoe_resethour']
))->setHelp('Hour');

$group->add(new Form_Input(
	'pppoe_resetminute',
	null,
	'text',
	$pconfig['pppoe_resetminute']
))->setHelp('Minute');

$group->add(new Form_Input(
	'pppoe_resetdate',
	null,
	'text',
	$pconfig['pppoe_resetdate'],
	['placeholder' => 'mm/dd/yyyy']
))->setHelp('Specific date');

$group->setHelp('Leaving the date field empty will cause the reset to be executed each day at the time you specified in the minutes and hour fields. ');

$section->add($group);

$group = new Form_Group('Reset frequency');
$group->addClass('pppoe-reset-cron');

$group->add(new Form_Checkbox(
	'pppoe_pr_preset_val',
	null,
	'Monthly (0 0 1 * *)',
	$pconfig['pppoe_monthly'],
	'monthly'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'pppoe_pr_preset_val',
	null,
	'Weekly (0 0 * * 0)',
	$pconfig['pppoe_weekly'],
	'weekly'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'pppoe_pr_preset_val',
	null,
	'Daily (0 0 * * *)',
	$pconfig['pppoe_daily'],
	'daily'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'pppoe_pr_preset_val',
	null,
	'Hourly (0 * * * *)',
	$pconfig['pppoe_hourly'],
	'hourly'
))->displayAsRadio();

$section->add($group);

$btnadvanced = new Form_Button(
		'btnadvanced',
		'Show'
);

$btnadvanced->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Advanced options',
	$btnadvanced
));

$form->add($section);

$section = new Form_Section('Advanced Configuration');
$section->addClass('sec-advanced'); // This will allow the section to be hidden/shown by calling e.g.: hideClass('advanced', true);

$section->addInput(new Form_Checkbox(
	'ondemand',
	'Dial On Demand',
	'Enable Dial-on-Demand mode. ',
	$pconfig['ondemand']
))->setHelp('Causes the interface to operate in dial-on-demand mode. Do NOT enable if you want your link to be always up. ' .
			'The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected.');

$section->addInput(new Form_Input(
	'idletimeout',
	'Idle Timeout',
	'text',
	$pconfig['idletimeout']
))->setHelp('If no incoming or outgoing packets are transmitted for the entered number of seconds the connection is brought down.' .
			'When the idle timeout occurs, if the dial-on-demand option is enabled, mpd goes back into dial-on-demand mode. ' .
			'Otherwise, the interface is brought down and all associated routes removed.');

$section->addInput(new Form_Checkbox(
	'vjcomp',
	'Compression',
	'Disable vjcomp (compression, auto-negotiated by default).',
	$pconfig['vjcomp']
))->setHelp('Disable vjcomp(compression) (auto-negotiated by default).' . '<br />' .
				'This option enables Van Jacobson TCP header compression, which saves several bytes per TCP data packet.' .
				'This option is almost always required. Compression is not effective for TCP connections with enabled modern extensions like time ' .
				'stamping or SACK, which modify TCP options between sequential packets.');

$section->addInput(new Form_Checkbox(
	'tcpmssfix',
	'TCPmssFix',
	'Disable tcpmssfix (enabled by default).',
	$pconfig['tcpmssfix']
))->setHelp('Causes mpd to adjust incoming and outgoing TCP SYN segments so that the requested maximum segment size is not greater than the amount ' .
			'allowed by the interface MTU. This is necessary in many setups to avoid problems caused by routers that drop ICMP Datagram Too Big messages. Without these messages, ' .
			'the originating machine sends data, it passes the rogue router then hits a machine that has an MTU that is not big enough for the data. Because the IP Don\'t Fragment option is set, ' .
			'this machine sends an ICMP Datagram Too Big message back to the originator and drops the packet. The rogue router drops the ICMP message and the originator never ' .
			'gets to discover that it must reduce the fragment size or drop the IP Don\'t Fragment option from its outgoing data.');

$section->addInput(new Form_Checkbox(
	'shortseq',
	'ShortSeq',
	'Disable shortseq (auto-negotiated by default).',
	$pconfig['shortseq']
))->setHelp('This option is only meaningful if multi-link PPP is negotiated. It proscribes shorter multi-link fragment headers, saving two bytes on every frame. ' .
			'It is not necessary to disable this for connections that are not multi-link.');

$section->addInput(new Form_Checkbox(
	'acfcomp',
	'ACFComp',
	'Disable ACF compression (auto-negotiated by default)',
	$pconfig['acfcomp']
))->setHelp('Address and control field compression. This option only applies to asynchronous link types. It saves two bytes per frame.');

$section->addInput(new Form_Checkbox(
	'protocomp',
	'ProtoComp',
	'Disable Protocol compression (auto-negotiated by default)',
	$pconfig['protocomp']
))->setHelp('Protocol field compression. This option saves one byte per frame for most frames.');

// Display the Link parameters. We will hide this by default, then un-hide the selected ones on clicking 'Advanced'
$j = 0;

foreach ($linklist['list'] as $ifnm =>$nm) {

	$group = new Form_Group('Link Parameters (' . $ifnm . ')');

	$group->add(new Form_Input(
		'bandwidth' . $j,
		null,
		'text',
		$pconfig['bandwidth'][$j]
	))->setHelp('Bandwidth');

	$group->add(new Form_Input(
		'mtu' . $j,
		null,
		'text',
		$pconfig['mtu'][$j]
	))->setHelp('MTU');

	$group->add(new Form_Input(
		'mru' . $j,
		null,
		'text',
		$pconfig['mru'][$j]
	))->setHelp('MRU');

	$group->add(new Form_Input(
		'mrru' . $j,
		null,
		'text',
		$pconfig['mrru'][$j]
	))->setHelp('MRRU');

	$j++;

	$section->add($group);

	$group->addClass('localip sec-advanced')->addClass('linkparam' . $ifnm);
}

$linkparamhelp = new Form_StaticText(
	null,
	'<span id="linkparamhelp">' . $linkparamstr . '</span>'
);

$section->addInput($linkparamhelp);

if (isset($id) && $a_ppps[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section->addInput(new Form_Input(
	'ptpid',
	null,
	'hidden',
	$ptpid
));

$form->add($section);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var showadvanced = false;

	function setAdvVisible() {
		// Update the button text and toggle showadvanced
		if (showadvanced) {
			$("#btnadvanced").prop('value', 'Hide');
			showadvanced = false;
		} else {
			$("#btnadvanced").prop('value', 'Show');
			showadvanced = true;
		}

		hideClass('sec-advanced', showadvanced);

		// The options that follow are only shown if type == 'ppp'
		var ppptype = ($('#type').val() == 'ppp');

		hideInput('apnum', showadvanced && ppptype);
		hideInput('simpin', showadvanced && ppptype);
		hideInput('pin-wait', showadvanced && ppptype);
		hideInput('initstr', showadvanced && ppptype);
		hideInput('connect-timeout', showadvanced && ppptype);
		hideCheckbox('uptime', showadvanced && ppptype);

		// The options that follow are only shown if type == 'pppoe'
		var pppoetype = ($('#type').val() != 'pppoe');

		hideClass('pppoe', showadvanced || pppoetype);
		hideInput('pppoe-reset-type', showadvanced || pppoetype);

		hideResetDisplay(true);

		hideInterfaces();
	}

	function hideResetDisplay(hide) {

		hideClass('pppoe-reset-date', true);
		hideClass('pppoe-reset-cron', true);

		if (!hide) {
			switch ($('#pppoe-reset-type').val()) {
				case 'custom' :
					hideClass('pppoe-reset-date', false);
					break;
				case 'preset' :
					hideClass('pppoe-reset-cron', false);
					break;
			}
		}
	}

	function hideInterfaces() {
		hideClass('localip', true);
		hideClass('linkparam', true);
		hideInput('linkparamhelp', true);

		var selected = $('#interfaces').val();
		var length = $("#interfaces :selected").length;

		for (var i=0; i<length; i++) {
			hideClass('localip' + selected[i], false);

			if (!showadvanced) {
				hideClass('linkparam' + selected[i], false);
				hideInput('linkparamhelp', false);
			}
		}
	}

	function hideProviders(hide) {
		hideInput('country', hide);
		hideInput('provider', hide);
		hideInput('providerplan', hide);
	}

	function providers_list() {
		$('#provider option').remove();
		$('#providerplan option').remove();
		$('#provider').append(new Option('', ''));
		$.ajax("getserviceproviders.php",{
			type: 'POST',
			data: {country : $('#country').val()},
			success: function(responseText) {
				var responseTextArr = responseText.split("\n");
				var value, i;
				responseTextArr.sort();
				for (i = 0; i < responseTextArr.length; i += 1) {
					value = responseTextArr[i];
					if (/\S/.test(value)) {
						$('#provider').append(new Option(value, value));
					}
				}
			}
		});
	}

	function providerplan_list() {
		$('#providerplan option').remove();
		$('#providerplan').append( new Option('','') );
		$.ajax("getserviceproviders.php",{
			type: 'POST',
			data: {country : $('#country').val(), provider : $('#provider').val()},
			success: function(responseText) {
				var responseTextArr = responseText.split("\n");
				var value, providerplan, i;
				responseTextArr.sort();
				for (i = 0; i < responseTextArr.length; i += 1) {
					value = responseTextArr[i];
					if (/\S/.test(value)) {
						providerplan = value.split(":");
						$('#providerplan').append(new Option(providerplan[0] + " - " + providerplan[1],
											  providerplan[1]));
					}
				}
			}
		});
	}

	function prefill_provider() {
		$.ajax("getserviceproviders.php",{
			type: "POST",
			data: {country : $('#country').val(), provider : $('#provider').val(), plan : $('#providerplan').val()},
			success: function(responseXML) {
				var xmldoc = responseXML;
				var provider = xmldoc.getElementsByTagName('connection')[0];
				$('#username').val('');
				$('#password').val('');
				if (provider.getElementsByTagName('apn')[0].firstChild.data == "CDMA") {
					$('#phone').val('#777');
					$('#apn').val('');
				} else {
					$('#phone').val('*99#');
					$('#apn').val(provider.getElementsByTagName('apn')[0].firstChild.data);
				}
				$('#username').val(provider.getElementsByTagName('username')[0].firstChild.data);
				$('#password').val(provider.getElementsByTagName('password')[0].firstChild.data);
			}
		});
	}

	// Make the ‘btnadvanced’ button a plain button, not a submit button
	$("#btnadvanced").prop('type','button');

	$("#btnadvanced").click(function() {
		setAdvVisible();
	});

	$('#pppoe-reset-type').on('change', function() {
		hideResetDisplay(false);
	});

	// When interfaces changed, read the selected items and unhide the corresponding IP/Gateway controls
	// Multiselect boxes must be handled by class
	$('.interfaces').on('change', function() {
		hideInterfaces();
	});

	// When type, country, provider or plan are changed, reload the page and build the new selector arrays
	$('#type').on('change', function() {
		window.location = 'interfaces_ppps_edit.php?id=' + $('#id').val() + '&type=' + this.value;
	});

	$('#country').on('change', function() {
		providers_list();
		hideInput('provider', false);
	});

	$('#provider').on('change', function() {
		providerplan_list();
		hideInput('providerplan', false);
	});

	$('#providerplan').on('change', function() {
		prefill_provider();
	});

	// Set element visibility on initial page load
	setAdvVisible();

	hideClass('linkparam', true);

	hideProviders($('#type').val() != "ppp");
	if ($('provider').size() == 0) {
		hideInput('provider', true);
	}

	if ($('providerplan').size() == 0) {
		hideInput('providerplan', true);
	}
});
//]]>

</script>
<?php

include("foot.inc");

