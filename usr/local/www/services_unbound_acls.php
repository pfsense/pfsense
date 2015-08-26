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

// Add a row to the networks table
if($_GET && $_GET['addrow'])
    array_push($networkacl, array('acl_network' => '', 'mask' => '32', 'description' => ''));
             
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
		if ($retval == 0) {
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
			if ($pconfig['Submit'] == gettext("Save")) {
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

require('classes/Form.class.php');

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
	'action',
	'Action',
	strtolower($pconfig['aclaction']),
	array('allow' => 'Allow','deny' => 'Deny','refuse' => 'Refuse','allow snoop' => 'Allow Snoop')
    ))->setHelp('Choose what to do with DNS requests that match the criteria specified below.' . '<br />' .
                'Deny: Stops queries from hosts within the netblock defined below.' . '<br />' . 
                'Refuse: Stops queries from hosts within the netblock defined below, but sends a DNS rcode REFUSED error message back to the client.' . '<br />' .
                'Allow: Allow queries from hosts within the netblock defined below.' . '<br />' .
                'Allow Snoop: Allow recursive and nonrecursive access from hosts within the netblock defined below. Used for cache snooping and ideally should only be configured for your administrative host.');

    $counter = 0;
    $numrows = count($networkacl) - 1;
    
    foreach($networkacl as $item) {
    	$network = $item['acl_network'];
    	$cidr = $item['mask'];
    	$description = $item['description'];
    	
    	$group = new Form_Group($counter == 0 ? 'Networks':null);
    	
    	$group->add(new Form_IpAddress(
    	    'acl_network' . $counter,
    	    null,
    	    $network
    	))->setHelp(($counter == $numrows) ? 'Network':null);
    	
    	$group->add(new Form_Select(
	        'mask' . $counter,
	        null,
	        $cidr,
	        array_combine(range(32, 1, -1), range(32, 1, -1))
        ))->setWidth(2)->setHelp(($counter == $numrows) ? 'Mask':null);
        
        $group->add(new Form_Input(
    	    'description' . $counter,
    	    null,
    	    'text',
    	    $description
    	))->setWidth(3)->setHelp(($counter == $numrows) ? 'Description':null);
    	
    	$btndlt = new Form_Button(
    	    'dlt' . $counter,
    	    'Delete'
	    );
	   
	    $btndlt->removeClass('btn-primary')->addClass('btn-sm btn-danger');
	   
    	$group->add($btndlt);

    	$section->add($group);
    	
    	$counter++;
    }

	$btnadd = new Form_Button(
	    'btnadd',
	    'Add row',
	    'services_unbound_acls.php?act=' . $act . '&addrow=yes'
	);
	
	$btnadd->removeClass(btn-primary)->addClass('btn-sm btn-success');
	
	$section->addInput(new Form_StaticText(
	    'Add row',
	    $btnadd
	))->setHelp('Remember to save after each Add or Delete');
	
    $section->addInput(new Form_Input(
    	'descr',
    	'Description',
    	'text',
    	$pconfig['descr']
    ))->setHelp('You may enter a description here for your reference.');

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

include("foot.inc");