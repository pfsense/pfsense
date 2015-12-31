<?php
/*
	services_unbound_acls.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2011 Warren Baker <warren@decoy.co.za>
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
##|*IDENT=page-services-dnsresolver-acls
##|*NAME=Services: DNS Resolver: Access Lists
##|*DESCR=Allow access to the 'Services: DNS Resolver: Access Lists' page.
##|*MATCH=services_unbound_acls.php*
##|-PRIV

require("guiconfig.inc");
require("unbound.inc");

if (!is_array($config['unbound']['acls'])) {
	$config['unbound']['acls'] = array();
}

$a_acls = &$config['unbound']['acls'];

$id = $_GET['id'];

if (isset($_POST['aclid'])) {
	$id = $_POST['aclid'];
}

if (!empty($id) && !is_numeric($id)) {
	pfSenseHeader("services_unbound_acls.php");
	exit;
}

$act = $_GET['act'];

if (isset($_POST['act'])) {
	$act = $_POST['act'];
}

if ($act == "del") {
	if (!$a_acls[$id]) {
		pfSenseHeader("services_unbound_acls.php");
		exit;
	}

	unset($a_acls[$id]);
	write_config();
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

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;
	$deleting = false;

	// Delete a row from the networks table
	for ($idx = 0; $idx < 50; $idx++) {
		if ($pconfig['dlt' . $idx] == 'Delete') {
			unset($networkacl[$idx]);
			$deleting = true;
			break;
		}
	}

	if ($_POST['apply']) {
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			clear_subsystem_dirty('unbound');
		}
	} else if (!$deleting) {

		// input validation - only allow 50 entries in a single ACL
		for ($x = 0; $x < 50; $x++) {
			if (isset($pconfig["acl_network{$x}"])) {
				$networkacl[$x] = array();
				$networkacl[$x]['acl_network'] = $pconfig["acl_network{$x}"];
				$networkacl[$x]['mask'] = $pconfig["mask{$x}"];
				$networkacl[$x]['description'] = $pconfig["description{$x}"];
				if (!is_ipaddr($networkacl[$x]['acl_network'])) {
					$input_errors[] = gettext("You must enter a valid IP address for each row under Networks.");
				}

				if (is_ipaddr($networkacl[$x]['acl_network'])) {
					if (!is_subnet($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask'])) {
						$input_errors[] = gettext("You must enter a valid IPv4 netmask for each IPv4 row under Networks.");
					}
				} else if (function_exists("is_ipaddrv6")) {
					if (!is_ipaddrv6($networkacl[$x]['acl_network'])) {
						$input_errors[] = gettext("You must enter a valid IPv6 address for {$networkacl[$x]['acl_network']}.");
					} else if (!is_subnetv6($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask'])) {
						$input_errors[] = gettext("You must enter a valid IPv6 netmask for each IPv6 row under Networks.");
					}
				} else {
					$input_errors[] = gettext("You must enter a valid IP address for each row under Networks.");
				}
			} else if (isset($networkacl[$x])) {
				unset($networkacl[$x]);
			}
		}

		if (!$input_errors) {
			if (strtolower($pconfig['save']) == gettext("save")) {
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
				write_config();

				pfSenseHeader("/services_unbound_acls.php");
				exit;
			}
		}
	}
}

$actionHelp =
					'<span class="text-success"><strong>Deny:</strong></span> Stops queries from hosts within the netblock defined below.' . '<br />' .
					'<span class="text-success"><strong>Refuse:</strong></span> Stops queries from hosts within the netblock defined below, but sends a DNS rcode REFUSED error message back to the client.' . '<br />' .
					'<span class="text-success"><strong>Allow:</strong></span> Allow queries from hosts within the netblock defined below.' . '<br />' .
					'<span class="text-success"><strong>Allow Snoop:</strong></span> Allow recursive and nonrecursive access from hosts within the netblock defined below. Used for cache snooping and ideally should only be configured for your administrative host.';


$pgtitle = array(gettext("Services"), gettext("DNS Resolver"), gettext("Access Lists"));
$shortcut_section = "resolver";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('unbound')) {
	print_info_box_np(gettext("The configuration of the DNS Resolver, has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("General settings"), false, "/services_unbound.php");
$tab_array[] = array(gettext("Advanced settings"), false, "services_unbound_advanced.php");
$tab_array[] = array(gettext("Access Lists"), true, "/services_unbound_acls.php");
display_top_tabs($tab_array, true);

if ($act == "new" || $act == "edit") {

	$form = new Form();

	$section = new Form_Section('New Access List');

	$section->addInput(new Form_Input(
		'aclid',
		null,
		'hidden',
		$id
	));

	$section->addInput(new Form_Input(
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
		'Action',
		strtolower($pconfig['aclaction']),
		array('allow' => 'Allow', 'deny' => 'Deny', 'refuse' => 'Refuse', 'allow snoop' => 'Allow Snoop')
	))->setHelp($actionHelp);

	$section->addInput(new Form_Input(
		'description',
		'Description',
		'text',
		$pconfig['description']
	))->setHelp('You may enter a description here for your reference.');

	$numrows = count($networkacl) - 1;
	$counter = 0;

	foreach ($networkacl as $item) {
		$network = $item['acl_network'];
		$cidr = $item['mask'];
		$description = $item['description'];

		$group = new Form_Group($counter == 0 ? 'Networks':'');

		$group->add(new Form_IpAddress(
			'acl_network'.$counter,
			null,
			$network
		))->addMask('mask' . $counter, $cidr)->setWidth(4)->setHelp(($counter == $numrows) ? 'Network/mask':null);

		$group->add(new Form_Input(
			'description' . $counter,
			null,
			'text',
			$description
		))->setHelp(($counter == $numrows) ? 'Description':null);

		$group->add(new Form_Button(
			'deleterow' . $counter,
			'Delete'
		))->removeClass('btn-primary')->addClass('btn-warning');

		$group->addClass('repeatable');
		$section->add($group);

		$counter++;
	}

	$form->addGlobal(new Form_Button(
		'addrow',
		'Add network'
	))->removeClass('btn-primary')->addClass('btn-success');

	$form->add($section);
	print($form);
} else {
	// NOT 'edit' or 'add'
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Access Lists to control access to the DNS Resolver')?></h2></div>
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
							<a class="fa fa-trash"	title="<?=gettext('Delete ACL')?>" href="services_unbound_acls.php?act=del&amp;id=<?=$i?>"></a>
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

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();
});
//]]>
</script>
<?php
}

include("foot.inc");
