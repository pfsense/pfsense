<?php
/*
	system_routes_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-system-staticroutes-editroute
##|*NAME=System: Static Routes: Edit route
##|*DESCR=Allow access to the 'System: Static Routes: Edit route' page.
##|*MATCH=system_routes_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("util.inc");
require_once("gwlb.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_routes.php');

if (!is_array($config['staticroutes']['route'])) {
	$config['staticroutes']['route'] = array();
}

$a_routes = &$config['staticroutes']['route'];
$a_gateways = return_gateways_array(true, true);

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	$id = $_GET['dup'];
}

if (isset($id) && $a_routes[$id]) {
	list($pconfig['network'], $pconfig['network_subnet']) =
		explode('/', $a_routes[$id]['network']);
	$pconfig['gateway'] = $a_routes[$id]['gateway'];
	$pconfig['descr'] = $a_routes[$id]['descr'];
	$pconfig['disabled'] = isset($a_routes[$id]['disabled']);
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	unset($id);
}

if ($_POST) {

	global $aliastable;

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "network network_subnet gateway");
	$reqdfieldsn = explode(",",
		gettext("Destination network") . "," .
		gettext("Destination network bit count") . "," .
		gettext("Gateway"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['network'] && !is_ipaddr($_POST['network']) && !is_alias($_POST['network']))) {
		$input_errors[] = gettext("A valid IPv4 or IPv6 destination network must be specified.");
	}
	if (($_POST['network_subnet'] && !is_numeric($_POST['network_subnet']))) {
		$input_errors[] = gettext("A valid destination network bit count must be specified.");
	}
	if (($_POST['gateway']) && is_ipaddr($_POST['network'])) {
		if (!isset($a_gateways[$_POST['gateway']])) {
			$input_errors[] = gettext("A valid gateway must be specified.");
		} else if (isset($a_gateways[$_POST['gateway']]['disabled']) && !$_POST['disabled']) {
			$input_errors[] = gettext("The gateway is disabled but the route is not. You must disable the route in order to choose a disabled gateway.");
		} else {
			// Note that the 3rd parameter "disabled" must be passed as explicitly true or false.
			if (!validate_address_family($_POST['network'], $_POST['gateway'], $_POST['disabled'] ? true : false)) {
				$input_errors[] = gettext("The gateway '{$a_gateways[$_POST['gateway']]['gateway']}' is a different Address Family than network '{$_POST['network']}'.");
			}
		}
	}

	/* check for overlaps */
	$current_targets = get_staticroutes(true);
	$new_targets = array();
	if (is_ipaddrv6($_POST['network'])) {
		$osn = gen_subnetv6($_POST['network'], $_POST['network_subnet']) . "/" . $_POST['network_subnet'];
		$new_targets[] = $osn;
	}
	if (is_ipaddrv4($_POST['network'])) {
		if ($_POST['network_subnet'] > 32) {
			$input_errors[] = gettext("A IPv4 subnet can not be over 32 bits.");
		} else {
			$osn = gen_subnet($_POST['network'], $_POST['network_subnet']) . "/" . $_POST['network_subnet'];
			$new_targets[] = $osn;
		}
	} elseif (is_alias($_POST['network'])) {
		$osn = $_POST['network'];
		foreach (preg_split('/\s+/', $aliastable[$osn]) as $tgt) {
			if (is_ipaddrv4($tgt)) {
				$tgt .= "/32";
			}
			if (is_ipaddrv6($tgt)) {
				$tgt .= "/128";
			}
			if (!is_subnet($tgt)) {
				continue;
			}
			if (!is_subnetv6($tgt)) {
				continue;
			}
			$new_targets[] = $tgt;
		}
	}
	if (!isset($id)) {
		$id = count($a_routes);
	}
	$oroute = $a_routes[$id];
	$old_targets = array();
	if (!empty($oroute)) {
		if (is_alias($oroute['network'])) {
			foreach (filter_expand_alias_array($oroute['network']) as $tgt) {
				if (is_ipaddrv4($tgt)) {
					$tgt .= "/32";
				} else if (is_ipaddrv6($tgt)) {
					$tgt .= "/128";
				}
				if (!is_subnet($tgt)) {
					continue;
				}
				$old_targets[] = $tgt;
			}
		} else {
			$old_targets[] = $oroute['network'];
		}
	}

	$overlaps = array_intersect($current_targets, $new_targets);
	$overlaps = array_diff($overlaps, $old_targets);
	if (count($overlaps)) {
		$input_errors[] = gettext("A route to these destination networks already exists") . ": " . implode(", ", $overlaps);
	}

	if (is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $if) {
			if (is_ipaddrv4($_POST['network']) &&
			    isset($if['ipaddr']) && isset($if['subnet']) &&
			    is_ipaddrv4($if['ipaddr']) && is_numeric($if['subnet']) &&
			    ($_POST['network_subnet'] == $if['subnet']) &&
			    (gen_subnet($_POST['network'], $_POST['network_subnet']) == gen_subnet($if['ipaddr'], $if['subnet']))) {
				$input_errors[] = sprintf(gettext("This network conflicts with address configured on interface %s."), $if['descr']);
			} else if (is_ipaddrv6($_POST['network']) &&
			    isset($if['ipaddrv6']) && isset($if['subnetv6']) &&
			    is_ipaddrv6($if['ipaddrv6']) && is_numeric($if['subnetv6']) &&
			    ($_POST['network_subnet'] == $if['subnetv6']) &&
			    (gen_subnetv6($_POST['network'], $_POST['network_subnet']) == gen_subnetv6($if['ipaddrv6'], $if['subnetv6']))) {
				$input_errors[] = sprintf(gettext("This network conflicts with address configured on interface %s."), $if['descr']);
			}
		}
	}

	if (!$input_errors) {
		$route = array();
		$route['network'] = $osn;
		$route['gateway'] = $_POST['gateway'];
		$route['descr'] = $_POST['descr'];
		if ($_POST['disabled']) {
			$route['disabled'] = true;
		} else {
			unset($route['disabled']);
		}

		if (file_exists("{$g['tmp_path']}/.system_routes.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.system_routes.apply"));
		} else {
			$toapplylist = array();
		}
		$a_routes[$id] = $route;

		if (!empty($oroute)) {
			$delete_targets = array_diff($old_targets, $new_targets);
			if (count($delete_targets)) {
				foreach ($delete_targets as $dts) {
					if (is_ipaddrv6($dts)) {
						$family = "-inet6";
					}
					$toapplylist[] = "/sbin/route delete {$family} {$dts}";
				}
			}
		}
		file_put_contents("{$g['tmp_path']}/.system_routes.apply", serialize($toapplylist));

		mark_subsystem_dirty('staticroutes');

		write_config();

		header("Location: system_routes.php");
		exit;
	}
}

$pgtitle = array(gettext("System"), gettext("Routing"), gettext("Static Routes"), gettext("Edit"));
$shortcut_section = "routing";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

if (isset($id) && $a_routes[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section = new Form_Section('Edit route entry');

$section->addInput(new Form_IpAddress(
	'network',
	'Destination network',
	$pconfig['network']
))->addMask('network_subnet', $pconfig['network_subnet'])->setPattern('[.a-zA-Z0-9_]+')->setHelp('Destination network for this static route');

$allGateways = array_combine(
	array_map(function($g){ return $g['name']; }, $a_gateways),
	array_map(function($g){ return $g['name'] .' - '. $g['gateway']; }, $a_gateways)
);
$section->addInput(new Form_Select(
	'gateway',
	'Gateway',
	$pconfig['gateway'],
	$allGateways
))->setHelp('Choose which gateway this route applies to or <a href="'.
	'/system_gateways_edit.php">add a new one first</a>');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this static route',
	$pconfig['disabled']
))->setHelp('Set this option to disable this static route without removing it from '.
	'the list.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	htmlspecialchars($pconfig['descr'])
))->setHelp('You may enter a description here for your reference (not parsed).');

$form->add($section);

print $form;

?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// --------- Autocomplete -----------------------------------------------------------------------------------------
	var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;

	$('#network').autocomplete({
		source: addressarray
	});
});
//]]>
</script>
<?php
include("foot.inc");
