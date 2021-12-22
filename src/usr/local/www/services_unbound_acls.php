<?php
/*
 * services_unbound_acls.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014 Warren Baker (warren@pfsense.org)
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
##|*IDENT=page-services-dnsresolver-acls
##|*NAME=Services: DNS Resolver: Access Lists
##|*DESCR=Allow access to the 'Services: DNS Resolver: Access Lists' page.
##|*MATCH=services_unbound_acls.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("unbound.inc");

init_config_arr(array('unbound', 'acls'));
$a_acls = &$config['unbound']['acls'];

$id = $_REQUEST['id'];

if (isset($_POST['aclid'])) {
	$id = $_POST['aclid'];
}

if (!empty($id) && !is_numeric($id)) {
	pfSenseHeader("services_unbound_acls.php");
	exit;
}

$act = $_REQUEST['act'];

if ($_POST['act'] == "del") {
	if (!$a_acls[$id]) {
		pfSenseHeader("services_unbound_acls.php");
		exit;
	}

	unset($a_acls[$id]);
	write_config(gettext("Access list deleted from DNS Resolver."));
	mark_subsystem_dirty('unbound');
}

if ($act == "new") {
	$id = unbound_get_next_id();
}

if ($act == "edit") {
	if (isset($id) && $a_acls[$id]) {
		$pconfig = $a_acls[$id];
		$networkacl = $a_acls[$id]['row'];
	}
}

if (!is_array($networkacl)) {
	$networkacl = array();
}

// Add a row to the networks table
if ($act == 'new') {
	$networkacl = array('0' => array('acl_network' => '', 'mask' => '', 'description' => ''));
}

if ($_POST['apply']) {
	$retval = 0;
	$retval |= services_unbound_configure();
	if ($retval == 0) {
		clear_subsystem_dirty('unbound');
	}
}
if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;
	$deleting = false;

	// input validation - only allow 50 entries in a single ACL
	for ($x = 0; $x < 50; $x++) {
		if (isset($pconfig["acl_network{$x}"])) {
			$networkacl[$x] = array();
			$networkacl[$x]['acl_network'] = $pconfig["acl_network{$x}"];
			$networkacl[$x]['mask'] = $pconfig["mask{$x}"];
			$networkacl[$x]['description'] = $pconfig["description{$x}"];
			if (!is_ipaddr($networkacl[$x]['acl_network'])) {
				$input_errors[] = gettext("A valid IP address must be entered for each row under Networks.");
			}

			if (is_ipaddr($networkacl[$x]['acl_network'])) {
				if (!is_subnet($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask'])) {
					$input_errors[] = gettext("A valid IPv4 netmask must be entered for each IPv4 row under Networks.");
				}
			} else if (function_exists("is_ipaddrv6")) {
				if (!is_ipaddrv6($networkacl[$x]['acl_network'])) {
					$input_errors[] = gettext("A valid IPv6 address must be entered for {$networkacl[$x]['acl_network']}.");
				} else if (!is_subnetv6($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask'])) {
					$input_errors[] = gettext("A valid IPv6 netmask must be entered for each IPv6 row under Networks.");
				}
			} else {
				$input_errors[] = gettext("A valid IP address must be entered for each row under Networks.");
			}
		} else if (isset($networkacl[$x])) {
			unset($networkacl[$x]);
		}
	}

	if (!$input_errors) {
		if (strtolower($pconfig['save']) == strtolower(gettext("Save"))) {
			$acl_entry = array();
			$acl_entry['aclid'] = $pconfig['aclid'];
			$acl_entry['aclname'] = $pconfig['aclname'];
			$acl_entry['aclaction'] = $pconfig['aclaction'];
			$acl_entry['description'] = $pconfig['description'];
			$acl_entry['aclid'] = $pconfig['aclid'];
			$acl_entry['row'] = array();

			foreach ($networkacl as $acl) {
				$acl_entry['row'][] = $acl;
			}

			if (isset($id) && $a_acls[$id]) {
				$a_acls[$id] = $acl_entry;
			} else {
				$a_acls[] = $acl_entry;
			}

			mark_subsystem_dirty("unbound");
			write_config(gettext("Access list configured for DNS Resolver."));

			pfSenseHeader("/services_unbound_acls.php");
			exit;
		}
	}
}

$actionHelp =
	sprintf(gettext('%1$sDeny:%2$s Stops queries from hosts within the netblock defined below.%3$s'), '<span class="text-success"><strong>', '</strong></span>', '<br />') .
	sprintf(gettext('%1$sRefuse:%2$s Stops queries from hosts within the netblock defined below, but sends a DNS rcode REFUSED error message back to the client.%3$s'), '<span class="text-success"><strong>', '</strong></span>', '<br />') .
	sprintf(gettext('%1$sAllow:%2$s Allow queries from hosts within the netblock defined below.%3$s'), '<span class="text-success"><strong>', '</strong></span>', '<br />') .
	sprintf(gettext('%1$sAllow Snoop:%2$s Allow recursive and nonrecursive access from hosts within the netblock defined below. Used for cache snooping and ideally should only be configured for the administrative host.%3$s'), '<span class="text-success"><strong>', '</strong></span>', '<br />') .
	sprintf(gettext('%1$sDeny Nonlocal:%2$s Allow only authoritative local-data queries from hosts within the netblock defined below. Messages that are disallowed are dropped.%3$s'), '<span class="text-success"><strong>', '</strong></span>', '<br />') .
	sprintf(gettext('%1$sRefuse Nonlocal:%2$s Allow only authoritative local-data queries from hosts within the netblock defined below. Sends a DNS rcode REFUSED error message back to the client for messages that are disallowed.'), '<span class="text-success"><strong>', '</strong></span>');

$pgtitle = array(gettext("Services"), gettext("DNS Resolver"), gettext("Access Lists"));
$pglinks = array("", "services_unbound.php", "@self");

if ($act == "new" || $act == "edit") {
	$pgtitle[] = gettext('Edit');
}
$shortcut_section = "resolver";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('unbound')) {
	print_apply_box(gettext("The DNS resolver configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("General Settings"), false, "/services_unbound.php");
$tab_array[] = array(gettext("Advanced Settings"), false, "services_unbound_advanced.php");
$tab_array[] = array(gettext("Access Lists"), true, "/services_unbound_acls.php");
display_top_tabs($tab_array, true);

if ($act == "new" || $act == "edit") {

	$form = new Form();

	$section = new Form_Section('New Access List');

	$form->addGlobal(new Form_Input(
		'aclid',
		null,
		'hidden',
		$id
	));

	$form->addGlobal(new Form_Input(
		'act',
		null,
		'hidden',
		$act
	));

	$section->addInput(new Form_Input(
		'aclname',
		'Access List name',
		'text',
		$pconfig['aclname']
	))->setHelp('Provide an Access List name.');

	$section->addInput(new Form_Select(
		'aclaction',
		'*Action',
		strtolower($pconfig['aclaction']),
		array('allow' => gettext('Allow'), 'deny' => gettext('Deny'), 'refuse' => gettext('Refuse'), 'allow snoop' => gettext('Allow Snoop'), 'deny nonlocal' => gettext('Deny Nonlocal'), 'refuse nonlocal' => gettext('Refuse Nonlocal'))
	))->setHelp($actionHelp);

	$section->addInput(new Form_Input(
		'description',
		'Description',
		'text',
		$pconfig['description']
	))->setHelp('A description may be entered here for administrative reference.');

	$numrows = count($networkacl) - 1;
	$counter = 0;

	foreach ($networkacl as $item) {
		$network = $item['acl_network'];
		$cidr = $item['mask'];
		$description = $item['description'];

		$group = new Form_Group($counter == 0 ? '*Networks':'');

		$group->add(new Form_IpAddress(
			'acl_network'.$counter,
			null,
			$network
		))->addMask('mask' . $counter, $cidr, 128, 0)->setWidth(4)->setHelp(($counter == $numrows) ? 'Network/mask':null);

		$group->add(new Form_Input(
			'description' . $counter,
			null,
			'text',
			$description
		))->setHelp(($counter == $numrows) ? 'Description':null);

		$group->add(new Form_Button(
			'deleterow' . $counter,
			'Delete',
			null,
			'fa-trash'
		))->addClass('btn-warning');

		$group->addClass('repeatable');
		$section->add($group);

		$counter++;
	}

	$form->addGlobal(new Form_Button(
		'addrow',
		'Add Network',
		null,
		'fa-plus'
	))->addClass('btn-success');

	$form->add($section);
	print($form);
} else {
	// NOT 'edit' or 'add'
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Access Lists to Control Access to the DNS Resolver')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Access List Name")?></th>
						<th><?=gettext("Action")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php
	$i = 0;
	foreach ($a_acls as $acl):
?>
					<tr ondblclick="document.location='services_unbound_acls.php?act=edit&amp;id=<?=$i?>'">
						<td>
							<?=htmlspecialchars($acl['aclname'])?>
						</td>
						<td>
							<?=htmlspecialchars($acl['aclaction'])?>
						</td>
						<td>
							<?=htmlspecialchars($acl['description'])?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit ACL')?>" href="services_unbound_acls.php?act=edit&amp;id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete ACL')?>" href="services_unbound_acls.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
		$i++;
	endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_unbound_acls.php?act=new" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php
}

?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

});
//]]>
</script>

<?php
include("foot.inc");
