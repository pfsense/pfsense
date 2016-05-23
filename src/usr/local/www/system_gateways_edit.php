<?php
/*
	system_gateways_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-system-gateways-editgateway
##|*NAME=System: Gateways: Edit Gateway
##|*DESCR=Allow access to the 'System: Gateways: Edit Gateway' page.
##|*MATCH=system_gateways_edit.php*
##|-PRIV

require("guiconfig.inc");
require("pkg-utils.inc");

if (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_gateways.php');
}

$a_gateways = return_gateways_array(true, false, true);
$a_gateways_arr = array();
foreach ($a_gateways as $gw) {
	$a_gateways_arr[] = $gw;
}
$a_gateways = $a_gateways_arr;

if (!is_array($config['gateways']['gateway_item'])) {
	$config['gateways']['gateway_item'] = array();
}

$a_gateway_item = &$config['gateways']['gateway_item'];
$dpinger_default = return_dpinger_defaults();

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	$id = $_GET['dup'];
}

if (isset($id) && $a_gateways[$id]) {
	$pconfig = array();
	$pconfig['name'] = $a_gateways[$id]['name'];
	$pconfig['weight'] = $a_gateways[$id]['weight'];
	$pconfig['interval'] = $a_gateways[$id]['interval'];
	$pconfig['loss_interval'] = $a_gateways[$id]['loss_interval'];
	$pconfig['alert_interval'] = $a_gateways[$id]['alert_interval'];
	$pconfig['time_period'] = $a_gateways[$id]['time_period'];
	$pconfig['interface'] = $a_gateways[$id]['interface'];
	$pconfig['friendlyiface'] = $a_gateways[$id]['friendlyiface'];
	$pconfig['ipprotocol'] = $a_gateways[$id]['ipprotocol'];
	if (isset($a_gateways[$id]['dynamic'])) {
		$pconfig['dynamic'] = true;
	}
	$pconfig['gateway'] = $a_gateways[$id]['gateway'];
	$pconfig['defaultgw'] = isset($a_gateways[$id]['defaultgw']);
	$pconfig['force_down'] = isset($a_gateways[$id]['force_down']);
	$pconfig['latencylow'] = $a_gateways[$id]['latencylow'];
	$pconfig['latencyhigh'] = $a_gateways[$id]['latencyhigh'];
	$pconfig['losslow'] = $a_gateways[$id]['losslow'];
	$pconfig['losshigh'] = $a_gateways[$id]['losshigh'];
	$pconfig['monitor'] = $a_gateways[$id]['monitor'];
	$pconfig['monitor_disable'] = isset($a_gateways[$id]['monitor_disable']);
	$pconfig['data_payload'] = $a_gateways[$id]['data_payload'];
	$pconfig['nonlocalgateway'] = isset($a_gateways[$id]['nonlocalgateway']);
	$pconfig['descr'] = $a_gateways[$id]['descr'];
	$pconfig['attribute'] = $a_gateways[$id]['attribute'];
	$pconfig['disabled'] = isset($a_gateways[$id]['disabled']);
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	unset($id);
	unset($pconfig['attribute']);
}

if (isset($id) && $a_gateways[$id]) {
	$realid = $a_gateways[$id]['attribute'];
}

if ($_POST) {

	unset($input_errors);

	/* input validation */
	$reqdfields = explode(" ", "name interface");
	$reqdfieldsn = array(gettext("Name"), gettext("Interface"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!isset($_POST['name'])) {
		$input_errors[] = "A valid gateway name must be specified.";
	}
	if (!is_validaliasname($_POST['name'])) {
		$input_errors[] = invalidaliasnamemsg($_POST['name'], gettext("gateway"));
	} else if (isset($_POST['disabled'])) {
		// We have a valid gateway name that the user wants to mark as disabled.
		// Check if the gateway name is used in any gateway group.
		if (is_array($config['gateways']['gateway_group'])) {
			foreach ($config['gateways']['gateway_group'] as $group) {
				foreach ($group['item'] as $item) {
					$items = explode("|", $item);
					if ($items[0] == $_POST['name']) {
						$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use on Gateway Group "%2$s"'), $_POST['name'], $group['name']);
					}
				}
			}
		}

		// Check if the gateway name is used in any enabled Static Route.
		if (is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				if ($route['gateway'] == $_POST['name']) {
					if (!isset($route['disabled'])) {
						// There is a static route that uses this gateway and is enabled (not disabled).
						$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use on Static Route "%2$s"'), $_POST['name'], $route['network']);
					}
				}
			}
		}
	}
	/* skip system gateways which have been automatically added */
	if (($_POST['gateway'] && (!is_ipaddr($_POST['gateway'])) && ($_POST['attribute'] !== "system")) && ($_POST['gateway'] != "dynamic")) {
		$input_errors[] = gettext("A valid gateway IP address must be specified.");
	}

	if ($_POST['gateway'] && (is_ipaddr($_POST['gateway'])) && !$_REQUEST['isAjax']) {
		if (is_ipaddrv4($_POST['gateway'])) {
			$parent_ip = get_interface_ip($_POST['interface']);
			$parent_sn = get_interface_subnet($_POST['interface']);
			if (empty($parent_ip) || empty($parent_sn)) {
				$input_errors[] = gettext("Cannot add IPv4 Gateway Address because no IPv4 address could be found on the interface.");
			} elseif (!isset($_POST["nonlocalgateway"])) {
				$subnets = array(gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn);
				$vips = link_interface_to_vips($_POST['interface']);
				if (is_array($vips)) {
					foreach ($vips as $vip) {
						if (!is_ipaddrv4($vip['subnet'])) {
							continue;
						}
						$subnets[] = gen_subnet($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
					}
				}

				$found = false;
				foreach ($subnets as $subnet) {
					if (ip_in_subnet($_POST['gateway'], $subnet)) {
						$found = true;
						break;
					}
				}

				if ($found === false) {
					$input_errors[] = sprintf(gettext("The gateway address %1\$s does not lie within one of the chosen interface's subnets."), $_POST['gateway']);
				}
			}
		} else if (is_ipaddrv6($_POST['gateway'])) {
			/* do not do a subnet match on a link local address, it's valid */
			if (!is_linklocal($_POST['gateway'])) {
				$parent_ip = get_interface_ipv6($_POST['interface']);
				$parent_sn = get_interface_subnetv6($_POST['interface']);
				if (empty($parent_ip) || empty($parent_sn)) {
					$input_errors[] = gettext("Cannot add IPv6 Gateway Address because no IPv6 address could be found on the interface.");
				} elseif (!isset($_POST["nonlocalgateway"])) {
					$subnets = array(gen_subnetv6($parent_ip, $parent_sn) . "/" . $parent_sn);
					$vips = link_interface_to_vips($_POST['interface']);
					if (is_array($vips)) {
						foreach ($vips as $vip) {
							if (!is_ipaddrv6($vip['subnet'])) {
								continue;
							}
							$subnets[] = gen_subnetv6($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
						}
					}

					$found = false;
					foreach ($subnets as $subnet) {
						if (ip_in_subnet($_POST['gateway'], $subnet)) {
							$found = true;
							break;
						}
					}

					if ($found === false) {
						$input_errors[] = sprintf(gettext("The gateway address %1\$s does not lie within one of the chosen interface's subnets."), $_POST['gateway']);
					}
				}
			}
		}

		if (!empty($config['interfaces'][$_POST['interface']]['ipaddr'])) {
			if (is_ipaddr($config['interfaces'][$_POST['interface']]['ipaddr']) && (empty($_POST['gateway']) || $_POST['gateway'] == "dynamic")) {
				$input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv4 configuration.");
			}
		}
		if (!empty($config['interfaces'][$_POST['interface']]['ipaddrv6'])) {
			if (is_ipaddr($config['interfaces'][$_POST['interface']]['ipaddrv6']) && (empty($_POST['gateway']) || $_POST['gateway'] == "dynamic")) {
				$input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv6 configuration.");
			}
		}
	}
	if (($_POST['monitor'] != "") && !is_ipaddr($_POST['monitor']) && $_POST['monitor'] != "dynamic") {
		$input_errors[] = gettext("A valid monitor IP address must be specified.");
	}
	if (isset($_POST['data_payload']) && is_numeric($_POST['data_payload']) && $_POST['data_payload'] < 0) {
		$input_errors[] = gettext("A valid data payload must be specified.");
	}
	/* only allow correct IPv4 and IPv6 gateway addresses */
	if (($_POST['gateway'] <> "") && is_ipaddr($_POST['gateway']) && $_POST['gateway'] != "dynamic") {
		if (is_ipaddrv6($_POST['gateway']) && ($_POST['ipprotocol'] == "inet")) {
			$input_errors[] = sprintf(gettext("The IPv6 gateway address '%s' can not be used as a IPv4 gateway."), $_POST['gateway']);
		}
		if (is_ipaddrv4($_POST['gateway']) && ($_POST['ipprotocol'] == "inet6")) {
			$input_errors[] = sprintf(gettext("The IPv4 gateway address '%s' can not be used as a IPv6 gateway."), $_POST['gateway']);
		}
	}
	/* only allow correct IPv4 and IPv6 monitor addresses */
	if (($_POST['monitor'] <> "") && is_ipaddr($_POST['monitor']) && $_POST['monitor'] != "dynamic") {
		if (is_ipaddrv6($_POST['monitor']) && ($_POST['ipprotocol'] == "inet")) {
			$input_errors[] = sprintf(gettext("The IPv6 monitor address '%s' can not be used on a IPv4 gateway."), $_POST['monitor']);
		}
		if (is_ipaddrv4($_POST['monitor']) && ($_POST['ipprotocol'] == "inet6")) {
			$input_errors[] = sprintf(gettext("The IPv4 monitor address '%s' can not be used on a IPv6 gateway."), $_POST['monitor']);
		}
	}

	if (isset($_POST['name'])) {
		/* check for overlaps */
		foreach ($a_gateways as $gateway) {
			if (isset($id) && ($a_gateways[$id]) && ($a_gateways[$id] === $gateway)) {
				if ($gateway['name'] != $_POST['name']) {
					$input_errors[] = gettext("Changing name on a gateway is not allowed.");
				}
				continue;
			}
			if ($_POST['name'] <> "") {
				if (($gateway['name'] <> "") && ($_POST['name'] == $gateway['name']) && ($gateway['attribute'] !== "system")) {
					$input_errors[] = sprintf(gettext('The gateway name "%s" already exists.'), $_POST['name']);
					break;
				}
			}
			if (is_ipaddr($_POST['gateway'])) {
				if (($gateway['gateway'] <> "") && ($_POST['gateway'] == $gateway['gateway']) && ($gateway['attribute'] !== "system")) {
					$input_errors[] = sprintf(gettext('The gateway IP address "%s" already exists.'), $_POST['gateway']);
					break;
				}
			}
			if (is_ipaddr($_POST['monitor'])) {
				if (($gateway['monitor'] <> "") && ($_POST['monitor'] == $gateway['monitor']) && ($gateway['attribute'] !== "system")) {
					$input_errors[] = sprintf(gettext('The monitor IP address "%s" is already in use. A different monitor IP must be chosen.'), $_POST['monitor']);
					break;
				}
			}
		}
	}

	/* input validation of dpinger advanced parameters */

	$latencylow = $dpinger_default['latencylow'];
	if ($_POST['latencylow']) {
		if (!is_numeric($_POST['latencylow'])) {
			$input_errors[] = gettext("The low latency threshold needs to be a numeric value.");
		} else if ($_POST['latencylow'] < 1) {
			$input_errors[] = gettext("The low latency threshold needs to be positive.");
		} else {
			$latencylow = $_POST['latencylow'];
		}
	}

	$latencyhigh = $dpinger_default['latencyhigh'];
	if ($_POST['latencyhigh']) {
		if (!is_numeric($_POST['latencyhigh'])) {
			$input_errors[] = gettext("The high latency threshold needs to be a numeric value.");
		} else if ($_POST['latencyhigh'] < 1) {
			$input_errors[] = gettext("The high latency threshold needs to be positive.");
		} else {
			$latencyhigh = $_POST['latencyhigh'];
		}
	}

	$losslow = $dpinger_default['losslow'];
	if ($_POST['losslow']) {
		if (!is_numeric($_POST['losslow'])) {
			$input_errors[] = gettext("The low Packet Loss threshold needs to be a numeric value.");
		} else if ($_POST['losslow'] < 1) {
			$input_errors[] = gettext("The low Packet Loss threshold needs to be positive.");
		} else if ($_POST['losslow'] >= 100) {
			$input_errors[] = gettext("The low Packet Loss threshold needs to be less than 100.");
		} else {
			$losslow = $_POST['losslow'];
		}
	}

	$losshigh = $dpinger_default['losshigh'];
	if ($_POST['losshigh']) {
		if (!is_numeric($_POST['losshigh'])) {
			$input_errors[] = gettext("The high Packet Loss threshold needs to be a numeric value.");
		} else if ($_POST['losshigh'] < 1) {
			$input_errors[] = gettext("The high Packet Loss threshold needs to be positive.");
		} else if ($_POST['losshigh'] > 100) {
			$input_errors[] = gettext("The high Packet Loss threshold needs to be 100 or less.");
		} else {
			$losshigh = $_POST['losshigh'];
		}
	}

	$time_period = $dpinger_default['time_period'];
	if ($_POST['time_period']) {
		if (!is_numeric($_POST['time_period'])) {
			$input_errors[] = gettext("The time period over which results are averaged needs to be a numeric value.");
		} else if ($_POST['time_period'] < 1) {
			$input_errors[] = gettext("The time period over which results are averaged needs to be positive.");
		} else {
			$time_period = $_POST['time_period'];
		}
	}

	$interval = $dpinger_default['interval'];
	if ($_POST['interval']) {
		if (!is_numeric($_POST['interval'])) {
			$input_errors[] = gettext("The probe interval needs to be a numeric value.");
		} else if ($_POST['interval'] < 1) {
			$input_errors[] = gettext("The probe interval needs to be positive.");
		} else {
			$interval = $_POST['interval'];
		}
	}

	$loss_interval = $dpinger_default['loss_interval'];
	if ($_POST['loss_interval']) {
		if (!is_numeric($_POST['loss_interval'])) {
			$input_errors[] = gettext("The loss interval needs to be a numeric value.");
		} else if ($_POST['loss_interval'] < 1) {
			$input_errors[] = gettext("The loss interval setting needs to be positive.");
		} else {
			$loss_interval = $_POST['loss_interval'];
		}
	}

	$alert_interval = $dpinger_default['alert_interval'];
	if ($_POST['alert_interval']) {
		if (!is_numeric($_POST['alert_interval'])) {
			$input_errors[] = gettext("The alert interval needs to be a numeric value.");
		} else if ($_POST['alert_interval'] < 1) {
			$input_errors[] = gettext("The alert interval setting needs to be positive.");
		} else {
			$alert_interval = $_POST['alert_interval'];
		}
	}

	if ($latencylow >= $latencyhigh) {
		$input_errors[] = gettext(
		    "The high latency threshold needs to be greater than the low latency threshold");
	}

	if ($losslow >= $losshigh) {
		$input_errors[] = gettext(
		    "The high packet loss threshold needs to be higher than the low packet loss threshold");
	}

	// If the loss interval is less than latencyhigh, then high latency could never be recorded
	// because those high latency packets would be considered as lost. So do not allow that.
	if ($latencyhigh > $loss_interval) {
		$input_errors[] = gettext("The loss interval needs to be greater than or equal to the high latency threshold.");
	}

	// Ensure that the time period is greater than 2 times the probe interval plus the loss interval.
	if (($interval * 2 + $loss_interval) >= $time_period) {
		$input_errors[] = gettext("The time period needs to be greater than twice the probe interval plus the loss interval.");
	}

	// There is no point recalculating the average latency and loss more often than the probe interval.
	// So the alert interval needs to be >= probe interval.
	if ($interval > $alert_interval) {
		$input_errors[] = gettext("The alert interval needs to be greater than or equal to the probe interval.");
	}

	if (!$input_errors) {
		$reloadif = "";
		$gateway = array();

		if (empty($_POST['interface'])) {
			$gateway['interface'] = $pconfig['friendlyiface'];
		} else {
			$gateway['interface'] = $_POST['interface'];
		}
		if (is_ipaddr($_POST['gateway'])) {
			$gateway['gateway'] = $_POST['gateway'];
		} else {
			$gateway['gateway'] = "dynamic";
		}
		$gateway['name'] = $_POST['name'];
		$gateway['weight'] = $_POST['weight'];
		$gateway['ipprotocol'] = $_POST['ipprotocol'];
		if ($_POST['interval']) {
			$gateway['interval'] = $_POST['interval'];
		}

		if ($_POST['time_period']) {
			$gateway['time_period'] = $_POST['time_period'];
		}
		if ($_POST['alert_interval']) {
			$gateway['alert_interval'] = $_POST['alert_interval'];
		}

		$gateway['descr'] = $_POST['descr'];
		if ($_POST['monitor_disable'] == "yes") {
			$gateway['monitor_disable'] = true;
		}
		if ($_POST['nonlocalgateway'] == "yes") {
			$gateway['nonlocalgateway'] = true;
		}
		if ($_POST['force_down'] == "yes") {
			$gateway['force_down'] = true;
		}
		if (is_ipaddr($_POST['monitor'])) {
			$gateway['monitor'] = $_POST['monitor'];
		}
		if (isset($_POST['data_payload']) && $_POST['data_payload'] > 0) {
			$gateway['data_payload'] = $_POST['data_payload'];
		}

		/* NOTE: If gateway ip is changed need to cleanup the old static interface route */
		if ($_POST['monitor'] != "dynamic" && !empty($a_gateway_item[$realid]) && is_ipaddr($a_gateway_item[$realid]['gateway']) &&
		    $gateway['gateway'] != $a_gateway_item[$realid]['gateway'] &&
		    isset($a_gateway_item[$realid]["nonlocalgateway"])) {
			$realif = get_real_interface($a_gateway_item[$realid]['interface']);
			$inet = (!is_ipaddrv4($a_gateway_item[$realid]['gateway']) ? "-inet6" : "-inet");
			$cmd = "/sbin/route delete $inet " . escapeshellarg($a_gateway_item[$realid]['gateway']) . " -iface " . escapeshellarg($realif);
			mwexec($cmd);
		}

		/* NOTE: If monitor ip is changed need to cleanup the old static route */
		if ($_POST['monitor'] != "dynamic" && !empty($a_gateway_item[$realid]) && is_ipaddr($a_gateway_item[$realid]['monitor']) &&
		    $_POST['monitor'] != $a_gateway_item[$realid]['monitor'] && $gateway['gateway'] != $a_gateway_item[$realid]['monitor']) {
			if (is_ipaddrv4($a_gateway_item[$realid]['monitor'])) {
				mwexec("/sbin/route delete " . escapeshellarg($a_gateway_item[$realid]['monitor']));
			} else {
				mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateway_item[$realid]['monitor']));
			}
		}

		if ($_POST['defaultgw'] == "yes" || $_POST['defaultgw'] == "on") {
			$i = 0;
			/* remove the default gateway bits for all gateways with the same address family */
			foreach ($a_gateway_item as $gw) {
				if ($gateway['ipprotocol'] == $gw['ipprotocol']) {
					unset($config['gateways']['gateway_item'][$i]['defaultgw']);
					if ($gw['interface'] != $_POST['interface'] && $gw['defaultgw']) {
						$reloadif = $gw['interface'];
					}
				}
				$i++;
			}
			$gateway['defaultgw'] = true;
		}

		if ($_POST['latencylow']) {
			$gateway['latencylow'] = $_POST['latencylow'];
		}
		if ($_POST['latencyhigh']) {
			$gateway['latencyhigh'] = $_POST['latencyhigh'];
		}
		if ($_POST['losslow']) {
			$gateway['losslow'] = $_POST['losslow'];
		}
		if ($_POST['losshigh']) {
			$gateway['losshigh'] = $_POST['losshigh'];
		}
		if ($_POST['loss_interval']) {
			$gateway['loss_interval'] = $_POST['loss_interval'];
		}

		if (isset($_POST['disabled'])) {
			$gateway['disabled'] = true;
		} else {
			unset($gateway['disabled']);
		}

		/* when saving the manual gateway we use the attribute which has the corresponding id */
		if (isset($realid) && $a_gateway_item[$realid]) {
			$a_gateway_item[$realid] = $gateway;
		} else {
			$a_gateway_item[] = $gateway;
		}

		mark_subsystem_dirty('staticroutes');

		write_config();

		if ($_REQUEST['isAjax']) {
			echo $_POST['name'];
			exit;
		} else if (!empty($reloadif)) {
			send_event("interface reconfigure {$reloadif}");
		}

		header("Location: system_gateways.php");
		exit;
	} else {
		if ($_REQUEST['isAjax']) {
			header("HTTP/1.0 500 Internal Server Error");
			header("Content-type: text/plain");
			foreach ($input_errors as $error) {
				echo("$error\n");
			}
			exit;
		}

		$pconfig = $_POST;
		if (empty($_POST['friendlyiface'])) {
			$pconfig['friendlyiface'] = $_POST['interface'];
		}
	}
}

$pgtitle = array(gettext("System"), gettext("Routing"), gettext("Gateways"), gettext("Edit"));
$shortcut_section = "gateways";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

/* If this is a system gateway we need this var */
if (($pconfig['attribute'] == "system") || is_numeric($pconfig['attribute'])) {
	$form->addGlobal(new Form_Input(
		'attribute',
		null,
		'hidden',
		$pconfig['attribute']
	));
}

if (isset($id) && $a_gateways[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->addGlobal(new Form_Input(
	'friendlyiface',
	null,
	'hidden',
	$pconfig['friendlyiface']
));

$section = new Form_Section('Edit Gateway');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this gateway',
	$pconfig['disabled']
))->setHelp('Set this option to disable this gateway without removing it from the '.
	'list.');

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['friendlyiface'],
	get_configured_interface_with_descr(false, true)
))->setHelp('Choose which interface this gateway applies to.');

$section->addInput(new Form_Select(
	'ipprotocol',
	'Address Family',
	$pconfig['ipprotocol'],
	array(
		"inet" => "IPv4",
		"inet6" => "IPv6"
	)
))->setHelp('Choose the Internet Protocol this gateway uses.');

$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
))->setHelp('Gateway name');

$egw = new Form_Input(
	'gateway',
	'Gateway',
	'text',
	($pconfig['dynamic'] ? 'dynamic' : $pconfig['gateway'])
);

$egw->setHelp('Gateway IP address');

if ($pconfig['dynamic']) {
	$egw->setReadonly();
}

$section->addInput($egw);

$section->addInput(new Form_Checkbox(
	'defaultgw',
	'Default Gateway',
	'This will select the above gateway as the default gateway.',
	$pconfig['defaultgw']
));

$section->addInput(new Form_Checkbox(
	'monitor_disable',
	'Gateway Monitoring',
	'Disable Gateway Monitoring',
	$pconfig['monitor_disable']
))->toggles('.toggle-monitor-ip')->setHelp('This will consider this gateway as always being up.');

$group = new Form_Group('Monitor IP');
$group->addClass('toggle-monitor-ip', 'collapse');

if (!$pconfig['monitor_disable'])
	$group->addClass('in');

$group->add(new Form_Input(
	'monitor',
	null,
	'text',
	($pconfig['gateway'] == $pconfig['monitor'] ? '' : $pconfig['monitor'])
))->setHelp('Enter an alternative address here to be '.
	'used to monitor the link. This is used for the quality RRD graphs as well as the '.
	'load balancer entries. Use this if the gateway does not respond to ICMP echo '.
	'requests (pings).');
$section->add($group);

$section->addInput(new Form_Checkbox(
	'force_down',
	'Force state',
	'Mark Gateway as Down',
	$pconfig['force_down']
))->setHelp('This will force this gateway to be considered down.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for reference (not parsed).');

// Add a button to provide access to the advanced fields
$btnadv = new Form_Button(
	'btnadvopts',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	null,
	$btnadv
));

$form->add($section);
$section = new Form_Section('Advanced');

$section->addClass('adnlopts');

$section->addInput(new Form_Select(
	'weight',
	'Weight',
	$pconfig['weight'],
	array_combine(range(1, 30), range(1, 30))
))->setHelp('Weight for this gateway when used in a Gateway Group.');

$section->addInput(new Form_Input(
	'data_payload',
	'Data Payload',
	'number',
	$pconfig['data_payload'],
	['placeholder' => $dpinger_default['data_payload']]
))->setHelp('Define data payload to send on ICMP packets to gateway monitor IP.');

$group = new Form_Group('Latency thresholds');
$group->add(new Form_Input(
	'latencylow',
	'From',
	'number',
	$pconfig['latencylow'],
	['placeholder' => $dpinger_default['latencylow']]
));
$group->add(new Form_Input(
	'latencyhigh',
	'To',
	'number',
	$pconfig['latencyhigh'],
	['placeholder' => $dpinger_default['latencyhigh']]
));
$group->setHelp('Low and high thresholds for latency in milliseconds.
	Default is %d/%d.', [$dpinger_default['latencylow'], $dpinger_default['latencyhigh']]);

$section->add($group);

$group = new Form_Group('Packet Loss thresholds');
$group->add(new Form_Input(
	'losslow',
	'From',
	'number',
	$pconfig['losslow'],
	['placeholder' => $dpinger_default['losslow']]
));
$group->add(new Form_Input(
	'losshigh',
	'To',
	'number',
	$pconfig['losshigh'],
	['placeholder' => $dpinger_default['losshigh']]
));
$group->setHelp('Low and high thresholds for packet loss in %%.
	Default is %d/%d.', [$dpinger_default['losslow'], $dpinger_default['losshigh']]);
$section->add($group);

$section->addInput(new Form_Input(
	'interval',
	'Probe Interval',
	'number',
	$pconfig['interval'],
	[
		'placeholder' => $dpinger_default['interval'],
		'max' => 86400
	]
))->setHelp('How often an ICMP probe will be sent in milliseconds. Default is %d.', [$dpinger_default['interval']]);

$section->addInput(new Form_Input(
	'loss_interval',
	'Loss Interval',
	'number',
	$pconfig['loss_interval'],
	['placeholder' => $dpinger_default['loss_interval']]
))->setHelp('Time interval in milliseconds before packets are treated as lost. '.
	'Default is %d.', [$dpinger_default['loss_interval']]);

$group = new Form_Group('Time Period');
$group->add(new Form_Input(
	'time_period',
	null,
	'number',
	$pconfig['time_period'],
	[
		'placeholder' => $dpinger_default['time_period']
	]
));
$group->setHelp('Time period in milliseconds over which results are averaged. Default is %d.',
	[$dpinger_default['time_period']]);
$section->add($group);

$group = new Form_Group('Alert interval');
$group->add(new Form_Input(
	'alert_interval',
	null,
	'number',
	$pconfig['alert_interval'],
	[
		'placeholder' => $dpinger_default['alert_interval']
	]
));
$group->setHelp('Time interval in milliseconds between checking for an alert condition. Default is %d.',
	[$dpinger_default['alert_interval']]);
$section->add($group);

$section->addInput(new Form_StaticText(
	gettext('Additional information'),
	'<span class="help-block">'.
	gettext('The time period, probe interval and loss interval are closely related. The ' .
		'ratio between these values control the accuracy of the numbers reported and ' .
		'the timeliness of alerts.') .
	'<br/><br/>' .
	gettext('A longer time period will provide smoother results for round trip time ' .
		'and loss, but will increase the time before a latency or loss alert is triggered.') .
	'<br/><br/>' .
	gettext('A shorter probe interval will decrease the time required before a latency ' .
		'or loss alert is triggered, but will use more network resource. Longer ' .
		'probe intervals will degrade the accuracy of the quality graphs.') .
	'<br/><br/>' .
	gettext('The ratio of the probe interval to the time period (minus the loss interval) ' .
		'also controls the resolution of loss reporting. To determine the resolution, ' .
		'the following formula can be used:') .
	'<br/><br/>' .
	gettext('&nbsp;&nbsp;&nbsp;&nbsp;100 * probe interval / (time period - loss interval)') .
	'<br/><br/>' .
	gettext('Rounding up to the nearest whole number will yield the resolution of loss ' .
		'reporting in percent. The default values provide a resolution of 1%.') .
	'<br/><br/>' .
	gettext('The default settings are recommended for most use cases. However if ' .
		'changing the settings, please observe the following restrictions:') .
	'<br/><br/>' .
	gettext('- The time period must be greater than twice the probe interval plus the loss ' .
		'interval. This guarantees there is at least one completed probe at all times. ') .
	'<br/><br/>' .
	gettext('- The alert interval must be greater than or equal to the probe interval. There ' .
		'is no point checking for alerts more often than probes are done.') .
	'<br/><br/>' .
	gettext('- The loss interval must be greater than or equal to the high latency threshold.') .
	'</span>'
));

$section->addInput(new Form_Checkbox(
	'nonlocalgateway',
	'Use non-local gateway',
	'Use non-local gateway through interface specific route.',
	$pconfig['nonlocalgateway']
))->setHelp('This will allow use of a gateway outside of this interface\'s subnet. This is usually indicative of a configuration error, but is required for some scenarios.');

$form->add($section);

print $form;
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Show advanced additional opts options ===========================================================================
	var showadvopts = false;

	function show_advopts(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (!(!empty($pconfig['latencylow']) || !empty($pconfig['latencyhigh']) ||
			    !empty($pconfig['losslow']) || !empty($pconfig['losshigh']) || !empty($pconfig['data_payload']) ||
			    (isset($pconfig['weight']) && $pconfig['weight'] > 1) ||
			    (isset($pconfig['interval']) && !($pconfig['interval'] == $dpinger_default['interval'])) ||
			    (isset($pconfig['loss_interval']) && !($pconfig['loss_interval'] == $dpinger_default['loss_interval'])) ||
			    (isset($pconfig['time_period']) && !($pconfig['time_period'] == $dpinger_default['time_period'])) ||
			    (isset($pconfig['alert_interval']) && !($pconfig['alert_interval'] == $dpinger_default['alert_interval'])) ||
			    (isset($pconfig['nonlocalgateway']) && $pconfig['nonlocalgateway']))) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvopts = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvopts = !showadvopts;
		}

		hideClass('adnlopts', !showadvopts);

		if (showadvopts) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvopts').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvopts').click(function(event) {
		show_advopts();
	});

	// ---------- On initial page load ------------------------------------------------------------

	show_advopts(true);
});
//]]>
</script>

<?php include("foot.inc");
