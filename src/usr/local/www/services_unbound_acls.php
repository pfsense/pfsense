<?php
/* $Id$ */
/*
	services_unbound_acls.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2011 Warren Baker <warren@decoy.co.za>
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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

if(!is_array($networkacl))
	$networkacl = array();

// Add a row to the networks table
if($act == 'new')
	$networkacl = array('0' => array('acl_network' => '', 'mask' => '', 'description' => ''));

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;
	$deleting = false;

	// Delete a row from the networks table
	for($idx = 0; $idx<50; $idx++) {
		if($pconfig['dlt' . $idx] == 'Delete') {
			unset($networkacl[$idx]);
			$deleting = true;
			break;
		}
	}

	if ($_POST['apply']) {
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('unbound');
	} else if(!$deleting) {

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
					'<strong><font color="green">Deny:</font></strong> Stops queries from hosts within the netblock defined below.' . '<br />' .
					'<strong><font color="green">Refuse:</font></strong> Stops queries from hosts within the netblock defined below, but sends a DNS rcode REFUSED error message back to the client.' . '<br />' .
					'<strong><font color="green">Allow:</font></strong> Allow queries from hosts within the netblock defined below.' . '<br />' .
					'<strong><font color="green">Allow Snoop:</font></strong> Allow recursive and nonrecursive access from hosts within the netblock defined below. Used for cache snooping and ideally should only be configured for your administrative host.';


$closehead = false;
$pgtitle = "Services: DNS Resolver: Access Lists";
$shortcut_section = "resolver";
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('unbound'))
	print_info_box_np(gettext("The configuration of the DNS Resolver, has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));

$tab_array = array();
$tab_array[] = array(gettext("General Settings"), false, "/services_unbound.php");
$tab_array[] = array(gettext("Advanced settings"), false, "services_unbound_advanced.php");
$tab_array[] = array(gettext("Access Lists"), true, "/services_unbound_acls.php");
display_top_tabs($tab_array, true);

require_once('classes/Form.class.php');

if($act=="new" || $act=="edit") {

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
		'Access LIst name',
		'text',
		$pconfig['aclname']
	))->setHelp('Provide an Access List name.');

	$section->addInput(new Form_Select(
	'aclaction',
	'Action',
	strtolower($pconfig['aclaction']),
	array('allow' => 'Allow','deny' => 'Deny','refuse' => 'Refuse','allow snoop' => 'Allow Snoop')
	))->setHelp($actionHelp);

	$section->addInput(new Form_Input(
		'description',
		'Description',
		'text',
		$pconfig['description']
	))->setHelp('You may enter a description here for your reference.');

	$numrows = count($networkacl) - 1;
	$counter = 0;

	foreach($networkacl as $item) {
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

	$section->addInput(new Form_Button(
		'addrow',
		'Add network'
	))->removeClass('btn-primary')->addClass('btn-success');

	$form->add($section);
	print($form);
}
else // NOT 'edit' or 'add'
{
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Access Lists to control access to the DNS Resolver')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Access List Name"); ?></th>
						<th><?=gettext("Action"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
<?php
	$i = 0;
	foreach($a_acls as $acl):
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
							<a href="services_unbound_acls.php?act=edit&amp;id=<?=$i?>" class="btn btn-xs btn-info" >Edit</a>
							<a href="services_unbound_acls.php?act=del&amp;id=<?=$i?>" class="btn btn-xs btn-danger">Delete</a>
						</td>
					</tr>
<?php
		$i++;
	endforeach;
?>
				</tbody>
			</table>
		</div>
		<nav class="action-buttons">
			<a href="services_unbound_acls.php?act=new" class="btn btn-sm btn-success">Add</a>
		</nav>
	</div>
</div>
<?php
}

?>
<script>
//<![CDATA[
// Most of this needs to live in a common include file. It will be moved before production release.
events.push(function(){

	function setMasks() {
		// Find all ipaddress masks and make dynamic based on address family of input
		$('span.pfIpMask + select').each(function (idx, select){
			var input = $(select).prevAll('input[type=text]');

			input.on('change', function(e){
				var isV6 = (input.val().indexOf(':') != -1), min = 0, max = 128;
				if (!isV6)
					max = 32;

				if (input.val() == "")
					return;

				while (select.options.length > max)
					select.remove(0);

				if (select.options.length < max)
				{
					for (var i=select.options.length; i<=max; i++)
						select.options.add(new Option(i, i), 0);
				}
			});

			// Fire immediately
			input.change();
		});
	}

	// Complicated function to move all help text associated with this input id to the same id
	// on the row above. That way if you delete the last row, you don't lose the help
	function moveHelpText(id) {
		$('#' + id).parent('div').parent('div').find('input').each(function() {	 // For each <span></span>
			var fromId = this.id;
			var toId = decrStringInt(fromId);
			var helpSpan;

			if(!$(this).hasClass('pfIpMask') && !$(this).hasClass('btn')) {

				helpSpan = $('#' + fromId).parent('div').parent('div').find('span:last').clone();
				if($(helpSpan).hasClass('help-block')) {
					if($('#' + decrStringInt(fromId)).parent('div').hasClass('input-group'))
						$('#' + decrStringInt(fromId)).parent('div').after(helpSpan);
					else
						$('#' + decrStringInt(fromId)).after(helpSpan);
				}
			}
		});
	}

	// Increment the number at the end of the string
	function bumpStringInt( str )	{
	  var data = str.match(/(\D*)(\d+)(\D*)/), newStr = "";

	  if( data )
		newStr = data[ 1 ] + ( Number( data[ 2 ] ) + 1 ) + data[ 3 ];

	  return newStr || str;
	}

	// Decrement the number at the end of the string
	function decrStringInt( str )	{
	  var data = str.match(/(\D*)(\d+)(\D*)/), newStr = "";

	  if( data )
		newStr = data[ 1 ] + ( Number( data[ 2 ] ) - 1 ) + data[ 3 ];

	  return newStr || str;
	}

	// Called after a delete so that there are no gaps in the numbering. Most of the time the config system doesn't care about
	// gaps, but I do :)
	function renumber() {
		var idx = 0;

		$('.repeatable').each(function() {

			$(this).find('input').each(function() {
				$(this).prop("id", this.id.replace(/\d+$/, "") + idx);
				$(this).prop("name", this.name.replace(/\d+$/, "") + idx);
			});

			$(this).find('select').each(function() {
				$(this).prop("id", this.id.replace(/\d+$/, "") + idx);
				$(this).prop("name", this.name.replace(/\d+$/, "") + idx);
			});

			$(this).find('label').attr('for', $(this).find('label').attr('for').replace(/\d+$/, "") + idx);

			idx++;
		});
	}


	function delete_row(row) {
		$('#' + row).parent('div').parent('div').remove();
		renumber();
	}

	function add_row() {
		// Find the lst repeatable group
		var lastRepeatableGroup = $('.repeatable:last');

		// Clone it
		var newGroup = lastRepeatableGroup.clone(true);

		// Increment the suffix number for each input elemnt in the new group
		$(newGroup).find('input').each(function() {
			$(this).prop("id", bumpStringInt(this.id));
			$(this).prop("name", bumpStringInt(this.name));
			if(!$(this).is('[id^=delete]'))
				$(this).val('');
		});

		// Do the same for selectors
		$(newGroup).find('select').each(function() {
			$(this).prop("id", bumpStringInt(this.id));
			$(this).prop("name", bumpStringInt(this.name));
			// If this selector lists mask bits, we need it to be reset to all 128 options
			// and no items selected, so that automatic v4/v6 selection still works
			if($(this).is('[id^=mask]')) {
				$(this).empty();
				for(idx=128; idx>0; idx--) {
					$(this).append($('<option>', {
						value: idx,
						text: idx
					}));
				}
			}
		});

		// And for "for" tags
		$(newGroup).find('label').attr('for', bumpStringInt($(newGroup).find('label').attr('for')));
		$(newGroup).find('label').text(""); // Clear the label. We only want it on the very first row

		// Insert the updated/cloned row
		$(lastRepeatableGroup).after(newGroup);

		// Delete any help text from the group we have cloned
		$(lastRepeatableGroup).find('.help-block').each(function() {
			$(this).remove();
		});

		setMasks();
	}

	// These are action buttons, not submit buttons
	$('[id^=addrow]').prop('type','button');
	$('[id^=delete]').prop('type','button');

	// on click . .
	$('[id^=addrow]').click(function() {
		add_row();
	});

	$('[id^=delete]').click(function(event) {
		if($('.repeatable').length > 1) {
			moveHelpText(event.target.id);
			delete_row(event.target.id);
		}
		else
			alert('<?php echo gettext("You may not delet the last one!")?>');
	});
});
//]]>
</script>

<?php
include("foot.inc");