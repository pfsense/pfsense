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
/*
	pfSense_MODULE: routing
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
$apinger_default = return_apinger_defaults();

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
	$pconfig['avg_delay_samples'] = $a_gateways[$id]['avg_delay_samples'];
	$pconfig['avg_delay_samples_calculated'] = isset($a_gateways[$id]['avg_delay_samples_calculated']);
	$pconfig['avg_loss_samples'] = $a_gateways[$id]['avg_loss_samples'];
	$pconfig['avg_loss_samples_calculated'] = isset($a_gateways[$id]['avg_loss_samples_calculated']);
	$pconfig['avg_loss_delay_samples'] = $a_gateways[$id]['avg_loss_delay_samples'];
	$pconfig['avg_loss_delay_samples_calculated'] = isset($a_gateways[$id]['avg_loss_delay_samples_calculated']);
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
	$pconfig['down'] = $a_gateways[$id]['down'];
	$pconfig['monitor'] = $a_gateways[$id]['monitor'];
	$pconfig['monitor_disable'] = isset($a_gateways[$id]['monitor_disable']);
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
		$input_errors[] = gettext("The gateway name must not contain invalid characters.");
	} else if (isset($_POST['disabled'])) {
		// We have a valid gateway name that the user wants to mark as disabled.
		// Check if the gateway name is used in any gateway group.
		if (is_array($config['gateways']['gateway_group'])) {
			foreach ($config['gateways']['gateway_group'] as $group) {
				foreach ($group['item'] as $item) {
					$items = explode("|", $item);
					if ($items[0] == $_POST['name']) {
						$input_errors[] = sprintf(gettext("Gateway '%s' cannot be disabled because it is in use on Gateway Group '%s'"), $_POST['name'], $group['name']);
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
						$input_errors[] = sprintf(gettext("Gateway '%s' cannot be disabled because it is in use on Static Route '%s'"), $_POST['name'], $route['network']);
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
	/* only allow correct IPv4 and IPv6 gateway addresses */
	if (($_POST['gateway'] <> "") && is_ipaddr($_POST['gateway']) && $_POST['gateway'] != "dynamic") {
		if (is_ipaddrv6($_POST['gateway']) && ($_POST['ipprotocol'] == "inet")) {
			$input_errors[] = gettext("The IPv6 gateway address '{$_POST['gateway']}' can not be used as a IPv4 gateway'.");
		}
		if (is_ipaddrv4($_POST['gateway']) && ($_POST['ipprotocol'] == "inet6")) {
			$input_errors[] = gettext("The IPv4 gateway address '{$_POST['gateway']}' can not be used as a IPv6 gateway'.");
		}
	}
	/* only allow correct IPv4 and IPv6 monitor addresses */
	if (($_POST['monitor'] <> "") && is_ipaddr($_POST['monitor']) && $_POST['monitor'] != "dynamic") {
		if (is_ipaddrv6($_POST['monitor']) && ($_POST['ipprotocol'] == "inet")) {
			$input_errors[] = gettext("The IPv6 monitor address '{$_POST['monitor']}' can not be used on a IPv4 gateway'.");
		}
		if (is_ipaddrv4($_POST['monitor']) && ($_POST['ipprotocol'] == "inet6")) {
			$input_errors[] = gettext("The IPv4 monitor address '{$_POST['monitor']}' can not be used on a IPv6 gateway'.");
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
					$input_errors[] = sprintf(gettext('The monitor IP address "%s" is already in use. You must choose a different monitor IP.'), $_POST['monitor']);
					break;
				}
			}
		}
	}

	/* input validation of apinger advanced parameters */
	if ($_POST['latencylow']) {
		if (!is_numeric($_POST['latencylow'])) {
			$input_errors[] = gettext("The low latency threshold needs to be a numeric value.");
		} else {
			if ($_POST['latencylow'] < 1) {
				$input_errors[] = gettext("The low latency threshold needs to be positive.");
			}
		}
	}

	if ($_POST['latencyhigh']) {
		if (!is_numeric($_POST['latencyhigh'])) {
			$input_errors[] = gettext("The high latency threshold needs to be a numeric value.");
		} else {
			if ($_POST['latencyhigh'] < 1) {
				$input_errors[] = gettext("The high latency threshold needs to be positive.");
			}
		}
	}

	if ($_POST['losslow']) {
		if (!is_numeric($_POST['losslow'])) {
			$input_errors[] = gettext("The low Packet Loss threshold needs to be a numeric value.");
		} else {
			if ($_POST['losslow'] < 1) {
				$input_errors[] = gettext("The low Packet Loss threshold needs to be positive.");
			}
			if ($_POST['losslow'] >= 100) {
				$input_errors[] = gettext("The low Packet Loss threshold needs to be less than 100.");
			}
		}
	}

	if ($_POST['losshigh']) {
		if (!is_numeric($_POST['losshigh'])) {
			$input_errors[] = gettext("The high Packet Loss threshold needs to be a numeric value.");
		} else {
			if ($_POST['losshigh'] < 1) {
				$input_errors[] = gettext("The high Packet Loss threshold needs to be positive.");
			}
			if ($_POST['losshigh'] > 100) {
				$input_errors[] = gettext("The high Packet Loss threshold needs to be 100 or less.");
			}
		}
	}

	if (($_POST['latencylow']) && ($_POST['latencyhigh'])) {
		if ((is_numeric($_POST['latencylow'])) && (is_numeric($_POST['latencyhigh']))) {
			if (($_POST['latencylow'] > $_POST['latencyhigh'])) {
				$input_errors[] = gettext("The high latency threshold needs to be higher than the low latency threshold");
			}
		}
	} else {
		if ($_POST['latencylow']) {
			if (is_numeric($_POST['latencylow'])) {
				if ($_POST['latencylow'] > $apinger_default['latencyhigh']) {
					$input_errors[] = gettext(sprintf("The low latency threshold needs to be less than the default high latency threshold (%d)", $apinger_default['latencyhigh']));
				}
			}
		}
		if ($_POST['latencyhigh']) {
			if (is_numeric($_POST['latencyhigh'])) {
				if ($_POST['latencyhigh'] < $apinger_default['latencylow']) {
					$input_errors[] = gettext(sprintf("The high latency threshold needs to be higher than the default low latency threshold (%d)", $apinger_default['latencylow']));
				}
			}
		}
	}

	if (($_POST['losslow']) && ($_POST['losshigh'])) {
		if ((is_numeric($_POST['losslow'])) && (is_numeric($_POST['losshigh']))) {
			if ($_POST['losslow'] > $_POST['losshigh']) {
				$input_errors[] = gettext("The high Packet Loss threshold needs to be higher than the low Packet Loss threshold");
			}
		}
	} else {
		if ($_POST['losslow']) {
			if (is_numeric($_POST['losslow'])) {
				if ($_POST['losslow'] > $apinger_default['losshigh']) {
					$input_errors[] = gettext(sprintf("The low Packet Loss threshold needs to be less than the default high Packet Loss threshold (%d)", $apinger_default['losshigh']));
				}
			}
		}
		if ($_POST['losshigh']) {
			if (is_numeric($_POST['losshigh'])) {
				if ($_POST['losshigh'] < $apinger_default['losslow']) {
					$input_errors[] = gettext(sprintf("The high Packet Loss threshold needs to be higher than the default low Packet Loss threshold (%d)", $apinger_default['losslow']));
				}
			}
		}
	}

	if ($_POST['interval']) {
		if (!is_numeric($_POST['interval'])) {
			$input_errors[] = gettext("The probe interval needs to be a numeric value.");
		} else {
			if ($_POST['interval'] < 1) {
				$input_errors[] = gettext("The probe interval needs to be positive.");
			}
		}
	}

	if ($_POST['down']) {
		if (!is_numeric($_POST['down'])) {
			$input_errors[] = gettext("The down time setting needs to be a numeric value.");
		} else {
			if ($_POST['down'] < 1) {
				$input_errors[] = gettext("The down time setting needs to be positive.");
			}
		}
	}

	if (($_POST['interval']) && ($_POST['down'])) {
		if ((is_numeric($_POST['interval'])) && (is_numeric($_POST['down']))) {
			if ($_POST['interval'] > $_POST['down']) {
				$input_errors[] = gettext("The probe interval needs to be less than the down time setting.");
			}
		}
	} else {
		if ($_POST['interval']) {
			if (is_numeric($_POST['interval'])) {
				if ($_POST['interval'] > $apinger_default['down']) {
					$input_errors[] = gettext(sprintf("The probe interval needs to be less than the default down time setting (%d)", $apinger_default['down']));
				}
			}
		}
		if ($_POST['down']) {
			if (is_numeric($_POST['down'])) {
				if ($_POST['down'] < $apinger_default['interval']) {
					$input_errors[] = gettext(sprintf("The down time setting needs to be higher than the default probe interval (%d)", $apinger_default['interval']));
				}
			}
		}
	}

	if ($_POST['avg_delay_samples']) {
		if (!is_numeric($_POST['avg_delay_samples'])) {
			$input_errors[] = gettext("The average delay replies qty needs to be a numeric value.");
		} else {
			if ($_POST['avg_delay_samples'] < 1) {
				$input_errors[] = gettext("The average delay replies qty needs to be positive.");
			}
		}
	}

	if ($_POST['avg_loss_samples']) {
		if (!is_numeric($_POST['avg_loss_samples'])) {
			$input_errors[] = gettext("The average packet loss probes qty needs to be a numeric value.");
		} else {
			if ($_POST['avg_loss_samples'] < 1) {
				$input_errors[] = gettext("The average packet loss probes qty needs to be positive.");
			}
		}
	}

	if ($_POST['avg_loss_delay_samples']) {
		if (!is_numeric($_POST['avg_loss_delay_samples'])) {
			$input_errors[] = gettext("The lost probe delay needs to be a numeric value.");
		} else {
			if ($_POST['avg_loss_delay_samples'] < 1) {
				$input_errors[] = gettext("The lost probe delay needs to be positive.");
			}
		}
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
		$gateway['interval'] = $_POST['interval'];

		$gateway['avg_delay_samples'] = $_POST['avg_delay_samples'];
		if ($_POST['avg_delay_samples_calculated'] == "yes" || $_POST['avg_delay_samples_calculated'] == "on") {
			$gateway['avg_delay_samples_calculated'] = true;
		}

		$gateway['avg_loss_samples'] = $_POST['avg_loss_samples'];
		if ($_POST['avg_loss_samples_calculated'] == "yes" || $_POST['avg_loss_samples_calculated'] == "on") {
			$gateway['avg_loss_samples_calculated'] = true;
		}

		$gateway['avg_loss_delay_samples'] = $_POST['avg_loss_delay_samples'];
		if ($_POST['avg_loss_delay_samples_calculated'] == "yes" || $_POST['avg_loss_delay_samples_calculated'] == "on") {
			$gateway['avg_loss_delay_samples_calculated'] = true;
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
		if ($_POST['down']) {
			$gateway['down'] = $_POST['down'];
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


$pgtitle = array(gettext("System"), gettext("Gateways"), gettext("Edit gateway"));
$shortcut_section = "gateways";

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

?>
<script type="text/javascript">
//<![CDATA[
var systemGatewaysEditRecalculate = function(){
	var interval = $('#interval')[0].value;

	$('input[name$=_calculated]').each(function (idx, c){
		c = $(c);
		i = $(c.parents('.form-group').find('input[type=number]')[0]);

		c.prop('disabled', interval==0);

		if (interval==0)
			c.prop('checked', false);

		if (!c.prop('checked'))
			return i.prop('readonly', false);

		switch (i.attr('name'))
		{
			case 'avg_delay_samples':
				// How many replies should be used to compute average delay
				// for controlling "delay" alarms.
				// Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
				i.attr('value', Math.round(60 * (1/6) / Math.pow(interval, 0.333)));
			break;

			case 'avg_loss_samples':
				// How many probes should be used to compute average loss.
				// Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
				i.attr('value', Math.round(60 / interval));
			break;

			case 'avg_loss_delay_samples':
				// The delay (in samples) after which loss is computed
				// without this delays larger than interval would be treated as loss.
				// Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
				i.attr('value', Math.round(60 * (1/3) / interval));
			break;
		}

		i.prop('readonly', true);
	});
};

events.push(function(){
	$('#interval').on('change', systemGatewaysEditRecalculate);
	$('input[name$=_calculated]').on('change', systemGatewaysEditRecalculate);

	systemGatewaysEditRecalculate();
});
//]]>
</script>
<?php

$form = new Form;

/* If this is a system gateway we need this var */
if(($pconfig['attribute'] == "system") || is_numeric($pconfig['attribute'])) {
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

$section = new Form_Section('Edit gateway');

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

$section->addInput(new Form_Input(
	'gateway',
	'Gateway',
	'text',
	($pconfig['dynamic'] ? 'dynamic' : $pconfig['gateway'])
))->setHelp('Gateway IP address');

$section->addInput(new Form_Checkbox(
	'defaultgw',
	'Default Gateway',
	'This will select the above gateway as the default gateway',
	$pconfig['defaultgw']
));

$section->addInput(new Form_Checkbox(
	'monitor_disable',
	'Gateway Monitoring',
	'Disable Gateway Monitoring',
	$pconfig['monitor_disable']
))->toggles('.toggle-monitor-ip')->setHelp('This will consider this gateway as always being up');

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
))->setHelp('This will force this gateway to be considered Down');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

// If any of the advanced options are non-default, we will not show the "Advanced" button
// and will display the advanced section
if (!(!empty($pconfig['latencylow']) || !empty($pconfig['latencyhigh']) || !empty($pconfig['losslow']) ||
	!empty($pconfig['losshigh']) || (isset($pconfig['weight']) && $pconfig['weight'] > 1) ||
	(isset($pconfig['interval']) && ($pconfig['interval'] > $apinger_default['interval'])) ||
	(isset($pconfig['down']) && !($pconfig['down'] == $apinger_default['down'])))) {

	$btnadvanced = new Form_Button(
		'toggle-advanced',
		'Advanced options'
	);

	$advdflt = true;

	$btnadvanced->toggles('.advanced-options')->setAttribute('type', 'button');
	$btnadvanced->removeClass('btn-primary')->addClass('btn-default');

	$section->addInput(new Form_StaticText(
		null,
		$btnadvanced
	));
}

$form->add($section);
$section = new Form_Section('Advanced');

if(isset($advdflt)) {
	$section->addClass('collapse');
}

$section->addClass('advanced-options');

$section->addInput(new Form_Select(
	'weight',
	'Weight',
	$pconfig['weight'],
	array_combine(range(1, 5), range(1, 5))
))->setHelp('Weight for this gateway when used in a Gateway Group.');

$group = new Form_Group('Latency thresholds');
$group->add(new Form_Input(
	'latencylow',
	'From',
	'number',
	$pconfig['latencylow'],
	['placeholder' => $apinger_default['latencylow']]
));
$group->add(new Form_Input(
	'latencyhigh',
	'To',
	'number',
	$pconfig['latencyhigh'],
	['placeholder' => $apinger_default['latencyhigh']]
));
$group->setHelp('Low and high thresholds for latency in milliseconds.
	Default is %d/%d.', [$apinger_default['latencylow'], $apinger_default['latencyhigh']]);

$section->add($group);

$group = new Form_Group('Packet Loss thresholds');
$group->add(new Form_Input(
	'losslow',
	'From',
	'number',
	$pconfig['losslow'],
	['placeholder' => $apinger_default['losslow']]
));
$group->add(new Form_Input(
	'losshigh',
	'To',
	'number',
	$pconfig['losshigh'],
	['placeholder' => $apinger_default['losshigh']]
));
$group->setHelp('Low and high thresholds for packet loss in milliseconds.
	Default is %d/%d.', [$apinger_default['losslow'], $apinger_default['losshigh']]);
$section->add($group);

$section->addInput(new Form_Input(
	'interval',
	'Probe Interval',
	'number',
	$pconfig['interval'],
	[
		'placeholder' => $apinger_default['interval'],
		'max' => 86400
	]
))->setHelp('How often an ICMP probe will be sent in seconds. Default is %d.'.
	'NOTE: The quality graph is averaged over seconds, not intervals, so as '.
	'the probe interval is increased the accuracy of the quality graph is '.
	'decreased.', [$apinger_default['interval']]);

$section->addInput(new Form_Input(
	'down',
	'Down',
	'number',
	$pconfig['down'],
	['placeholder' => $apinger_default['down']]
))->setHelp('The number of seconds of failed probes before the alarm '.
	'will fire. Default is %d.', [$apinger_default['down']]);

$group = new Form_Group('Avg. Delay Replies Qty');
$group->add(new Form_Input(
	'avg_delay_samples',
	null,
	'number',
	$pconfig['avg_delay_samples'],
	[
		'placeholder' => $apinger_default['avg_delay_samples'],
		'max' => 100
	]
));
$group->add(new Form_Checkbox(
	'avg_delay_samples_calculated',
	null,
	'Use calculated value.',
	$pconfig['avg_delay_samples_calculated']
));
$group->setHelp('How many replies should be used to compute average delay for '.
	'controlling "delay" alarms? Default is %d.',
	[$apinger_default['avg_delay_samples']]);
$section->add($group);

$group = new Form_Group('Avg. Packet Loss Probes');
$group->add(new Form_Input(
	'avg_loss_samples',
	null,
	'number',
	$pconfig['avg_loss_samples'],
	[
		'placeholder' => $apinger_default['avg_loss_samples'],
		'max' => 1000
	]
));
$group->add(new Form_Checkbox(
	'avg_loss_samples_calculated',
	null,
	'Use calculated value.',
	$pconfig['avg_loss_samples_calculated']
));
$group->setHelp('How many probes should be useds to compute average packet loss? '.
	'Default is %d.',
	[$apinger_default['avg_loss_samples']]);
$section->add($group);

$group = new Form_Group('Lost Probe Delay');
$group->add(new Form_Input(
	'avg_loss_delay_samples',
	null,
	'number',
	$pconfig['avg_loss_delay_samples'],
	[
		'placeholder' => $apinger_default['avg_loss_delay_samples'],
		'max' => 200
	]
));
$group->add(new Form_Checkbox(
	'avg_loss_delay_samples_calculated',
	null,
	'Use calculated value.',
	$pconfig['avg_loss_samples_calculated']
));
$group->setHelp('The delay (in qty of probe samples) after which loss is '.
	'computed. Without this, delays longer than the probe interval would be '.
	'treated as packet loss.  Default is %d.',
	[$apinger_default['avg_loss_delay_samples']]);
$section->add($group);

$section->addInput(new Form_StaticText(
	'Additional information',
	'<span class="help-block">'.
	gettext('The probe interval must be less than the down time, otherwise the '.
		'gateway will seem to go down then come up again at the next probe.').
	'<br/><br/>'.
	gettext('The down time defines the length of time before the gateway is marked '.
		'as down, but the accuracy is controlled by the probe interval. For example, '.
		'if your down time is 40 seconds but on a 30 second probe interval, only one '.
		'probe would have to fail before the gateway is marked down at the 40 second '.
		'mark. By default, the gateway is considered down after 10 seconds, and the '.
		'probe interval is 1 second, so 10 probes would have to fail before the gateway '.
		'is marked down.').
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

include("foot.inc");
