<?php
/*
 * system_gateway_groups_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-system-gateways-editgatewaygroups
##|*NAME=System: Gateways: Edit Gateway Groups
##|*DESCR=Allow access to the 'System: Gateways: Edit Gateway Groups' page.
##|*MATCH=system_gateway_groups_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

config_init_path('gateways/gateway_group');
$a_gateways = get_gateways();

$categories = array(
	'down' => gettext("Member Down"),
	'downloss' => gettext("Packet Loss"),
	'downlatency' => gettext("High Latency"),
	'downlosslatency' => gettext("Packet Loss or High Latency"));

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	$id = $_REQUEST['dup'];
}

$this_gateway_group_config = isset($id) ? config_get_path("gateways/gateway_group/{$id}") : null;
if ($this_gateway_group_config) {
	$pconfig['name'] = $this_gateway_group_config['name'];
	$pconfig['item'] = $this_gateway_group_config['item'];
	$pconfig['descr'] = $this_gateway_group_config['descr'];
	$pconfig['trigger'] = $this_gateway_group_config['trigger'];
	$pconfig['keep_failover_states'] = $this_gateway_group_config['keep_failover_states'];
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	unset($id);
}

if (isset($_POST['save'])) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!isset($_POST['name'])) {
		$input_errors[] = gettext("A valid gateway group name must be specified.");
	}
	if (!is_validaliasname($_POST['name'])) {
		$input_errors[] = invalidaliasnamemsg($_POST['name'], gettext("gateway group"));
	}

	if (isset($_POST['name'])) {
		/* check for overlaps */
		foreach (config_get_path('gateways/gateway_group', []) as $gateway_group) {
			if ($this_gateway_group_config && ($this_gateway_group_config === $gateway_group)) {
				if ($gateway_group['name'] != $_POST['name']) {
					$input_errors[] = gettext("Changing name on a gateway group is not allowed.");
				}
				continue;
			}

			if ($gateway_group['name'] == $_POST['name']) {
				$input_errors[] = sprintf(gettext('A gateway group with this name "%s" already exists.'), $_POST['name']);
				break;
			}
		}
	}

	/* Build list of items in group with priority */
	$pconfig['item'] = array();
	foreach ($a_gateways as $gwname => $gateway) {
		if ($_POST[$gwname] > 0) {
			$vipname = "{$gwname}_vip";
			/* we have a priority above 0 (disabled), add item to list */
			$pconfig['item'][] = "{$gwname}|{$_POST[$gwname]}|{$_POST[$vipname]}";
		}
		/* check for overlaps */
		if ($_POST['name'] == $gwname) {
			$input_errors[] = sprintf(gettext('A gateway group cannot have the same name as a gateway "%s" please choose another name.'), $_POST['name']);
		}

	}
	if (count($pconfig['item']) == 0) {
		$input_errors[] = gettext("No gateway(s) have been selected to be used in this group");
	}

	if (!$input_errors) {
		$gateway_group = array();
		$gateway_group['name'] = $_POST['name'];
		$gateway_group['item'] = $pconfig['item'];
		$gateway_group['trigger'] = $_POST['trigger'];
		if (!empty($pconfig['keep_failover_states'])) {
			$gateway_group['keep_failover_states'] = $pconfig['keep_failover_states'];
		}
		$gateway_group['descr'] = $_POST['descr'];

		if ($this_gateway_group_config) {
			config_set_path("gateways/gateway_group/{$id}", $gateway_group);
		} else {
			config_set_path('gateways/gateway_group/', $gateway_group);
		}

		mark_subsystem_dirty('staticroutes');
		mark_subsystem_dirty('gwgroup.' . $gateway_group['name']);

		write_config("Gateway Groups settings saved");

		header("Location: system_gateway_groups.php");
		exit;
	}
}

$pgtitle = array(gettext("System"), gettext("Routing"), gettext("Gateway Groups"), gettext("Edit"));
$pglinks = array("", "system_gateways.php", "system_gateway_groups.php", "@self");
$shortcut_section = "gateway-groups";

function build_gateway_protocol_map (&$a_gateways) {
	$result = array();
	foreach ($a_gateways as $gwname => $gateway) {
		$result[$gwname] = $gateway['ipprotocol'];
	}

	return $result;
}

include("head.inc");

$gateway_protocol = build_gateway_protocol_map($a_gateways);
$gateway_array	= array_keys($a_gateways);
$protocol_array	  = array_values($gateway_protocol);
$protocol_array	  = array_values(array_unique($gateway_protocol));

if ($input_errors) {
	print_input_errors($input_errors);
}

function get_gw_family($gwname) {
	$gateways = get_gateways();

	if (is_array($gateways)) {
		foreach ($gateways as $gw) {
			if ($gw['name'] == $gwname) {
				return($gw['ipprotocol']);
			}
		}
	}

	return("none");
}

$form = new Form();

$section = new Form_Section('Edit Gateway Group Entry');

$section->addInput(new Form_Input(
	'name',
	'*Group Name',
	'text',
	$pconfig['name']
));

$row = 0;
$numrows = count($a_gateways) - 1;

$group = new Form_Group('*Gateway Priority');
$group->add(new Form_StaticText('', ''))->setReadonly();
$group->add(new Form_StaticText('', ''))->setReadonly();
$group->add(new Form_StaticText('', ''))->setReadonly();
$group->add(new Form_StaticText('', ''))->setWidth(3)->setReadonly();
$section->add($group);

// Determine the protocol family this group pertains to. We loop through every item
// just in case any have been removed and so have no family (orphans?)

if (is_array($pconfig['item'])) {
	foreach ($pconfig['item'] as $item) {
		$itemsplit = explode("|", $item);

		$family = get_gw_family($itemsplit[0]);

		if (($family == "inet") || ($family == "inet6")) {
			break;
		}
	}
}

foreach ($a_gateways as $gwname => $gateway) {
	if (!empty($pconfig['item'])) {
		$af = explode("|", $pconfig['item'][0]);
		if ($gateway['ipprotocol'] != $family) {
			$rows++;
			continue;
		}
	}

	$selected = '0';
	$vaddress = '';
	foreach ((array)$pconfig['item'] as $item) {
		$itemsplit = explode("|", $item);
		if ($itemsplit[0] == $gwname) {
			$selected = $itemsplit[1];
			if (count($itemsplit) >= 3) {
				$vaddress = $itemsplit[2];
			}
			break;
		}
	}

	$group = new Form_Group(null);
	$group->addClass($gateway['ipprotocol']);

	$group->add(new Form_Input(
		'gwname' . $row,
		'Group Name',
		'text',
		$gateway['name']
	))->setReadonly();

	$tr = gettext("Tier");
	$group->add(new Form_Select(
		$gwname,
		'Tier',
		$selected,
		array(
			'0' => 'Never',
			'1' => $tr . ' 1',
			'2' => $tr . ' 2',
			'3' => $tr . ' 3',
			'4' => $tr . ' 4',
			'5' => $tr . ' 5'
		)
	))->addClass('row')->addClass($gateway['ipprotocol']);

	$group->add(new Form_Select(
		$gwname . '_vip',
		'Virtual IP',
		$vaddress,
		build_vip_list($gateway['friendlyiface'], $gateway['ipprotocol'])
	));

	$group->add(new Form_Input(
		'description',
		'Group Name',
		'text',
		$gateway['descr']
	))->setWidth(3)->setReadonly();

	$section->add($group);

	$row++;
} // e-o-foreach

$group = new Form_Group(null);
$group->add(new Form_StaticText('', ''))->setHelp('Gateway')->setReadonly();
$group->add(new Form_StaticText('', ''))->setHelp('Tier')->setReadonly();
$group->add(new Form_StaticText('', ''))->setHelp('Virtual IP')->setReadonly();
$group->add(new Form_StaticText('', ''))->setWidth(3)->setHelp('Description')->setReadonly();
$section->add($group);

$section->addInput(new Form_StaticText(
	'Link Priority',
	'The priority selected here defines in what order failover and balancing of links will be done. ' .
	'Multiple links of the same priority will balance connections until all links in the priority will be exhausted. ' .
	'If all links in a priority level are exhausted then the next available link(s) in the next priority level will be used.'
));

$section->addInput(new Form_StaticText(
	'Virtual IP',
	'The virtual IP field selects which (virtual) IP should be used when this group applies to a local Dynamic DNS, IPsec or OpenVPN endpoint.'
));

$section->addInput(new Form_Select(
	'keep_failover_states',
	'*Keep Failover States',
	$pconfig['keep_failover_states'],
	[
		'' => 'Use global behavior (default)',
		'keep' => 'Keep states on gateway recovery',
		'kill' => 'Kill states on gateway recovery',
	]
))->setHelp('%2$sKeep states on gateway recovery%3$s: states for this gateway ' .
	'group are unaffected.%1$s%2$sKill states on gateway recovery%3$s: kill  ' .
	'policy routing states for lower-priority gateways.%1$sNote: gateway ' .
	'priority changes may not affect states created before those changes.',
	'<br/>', '<strong>', '</strong>');

$section->addInput(new Form_Select(
	'trigger',
	'*Trigger Level',
	$pconfig['trigger'],
	$categories
))->setHelp('When to trigger exclusion of a member');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if ($this_gateway_group_config) {
	$form->addGlobal(new Form_Input(
	'id',
	null,
	'hidden',
	$id
	));
}

$form->add($section);

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Hides all elements of the specified class. This will usually be a section or group
	function hideClass(s_class, hide) {
		if (hide) {
			$('.' + s_class).hide();
		} else {
			$('.' + s_class).show();
		}
	}

	// On changing a Tier selector on any row, find which protocol it uses (class)
	// and disable the opposite
	$('.row').on('change', function() {
		// If user selects 'Never', unhide all rows
		if ($(this).find(":selected").index() == 0) {
			hideClass('inet', false);
			hideClass('inet6', false);
		} else { // Otherwise hide the rows that are not of 'this' protocol
			if ($(this).hasClass('inet6')) {
				hideClass('inet', true);
			} else {
				hideClass('inet6', true);
			}
		}
	});
});
//]]>
</script>

<?php
include("foot.inc");
