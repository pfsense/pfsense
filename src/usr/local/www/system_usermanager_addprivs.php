<?php
/* $Id$ */
/*
	system_usermanager_addprivs.php

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Copyright (C) 2006 Daniel S. Haischt.
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
	pfSense_MODULE: auth
*/

##|+PRIV
##|*IDENT=page-system-usermanager-addprivs
##|*NAME=System: User Manager: Add Privileges page
##|*DESCR=Allow access to the 'System: User Manager: Add Privileges' page.
##|*MATCH=system_usermanager_addprivs.php*
##|-PRIV

function admusercmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

require("guiconfig.inc");

$pgtitle = array("System", "User manager", "Add privileges");

if (is_numericint($_GET['userid'])) {
	$userid = $_GET['userid'];
}

if (isset($_POST['userid']) && is_numericint($_POST['userid'])) {
	$userid = $_POST['userid'];
}

if (!isset($config['system']['user'][$userid]) && !is_array($config['system']['user'][$userid])) {
	pfSenseHeader("system_usermanager.php");
	exit;
}

$a_user = & $config['system']['user'][$userid];

if (!is_array($a_user['priv'])) {
	$a_user['priv'] = array();
}

if ($_POST) {
	conf_mount_rw();

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "sysprivs");
	$reqdfieldsn = array(gettext("Selected privileges"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if (!$input_errors) {

		if (!is_array($pconfig['sysprivs'])) {
			$pconfig['sysprivs'] = array();
		}

		if (!count($a_user['priv'])) {
			$a_user['priv'] = $pconfig['sysprivs'];
		} else {
			$a_user['priv'] = array_merge($a_user['priv'], $pconfig['sysprivs']);
		}

		$a_user['priv'] = sort_user_privs($a_user['priv']);
		local_user_set($a_user);
		$retval = write_config();
		$savemsg = get_std_save_message($retval);
		conf_mount_ro();

		post_redirect("system_usermanager.php", array('act' => 'edit', 'userid' => $userid));

		exit;
	}

	conf_mount_ro();
}

function build_priv_list() {
	global $priv_list, $a_user;

	$list = array();

	foreach($priv_list as $pname => $pdata) {
		if (in_array($pname, $a_user['priv']))
			continue;

		$list[$pname] = $pdata['name'];
	}

	return($list);
}

/* if ajax is calling, give them an update message */
if (isAjax()) {
	print_info_box_np($savemsg);
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

$tab_array = array();
$tab_array[] = array(gettext("Users"), true, "system_usermanager.php");
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

require_once('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('User privileges');

$section->addInput(new Form_Select(
	'sysprivs',
	'System',
	null,
	build_priv_list(),
	true
))->addClass('multiselect')->setHelp('Hold down CTRL (PC)/COMMAND (Mac) key to select multiple items');

if (isset($userid)) {
	$section->addInput(new Form_Input(
	'userid',
	null,
	'hidden',
	$userid
	));
}

$form->add($section);

print($form);
?>

<div class="panel panel-body alert-info" id="pdesc">Select a privilege from the list above for a description"</div>

<script>
//<![CDATA[
events.push(function(){

<?php

	// Build a list of privilege descriptions
	if (is_array($priv_list)) {
		$id = 0;

		$jdescs = "var descs = new Array();\n";
		foreach ($priv_list as $pname => $pdata) {
			if (in_array($pname, $a_user['priv'])) {
				continue;
			}
			$desc = addslashes(preg_replace("/pfSense/i", $g['product_name'], $pdata['descr']));
			$jdescs .= "descs[{$id}] = '{$desc}';\n";
			$id++;
		}

		echo $jdescs;
	}
?>
	// Set the number of options to display
	$('.multiselect').attr("size","20");

	// When the 'sysprivs" selector is clicked, we display a description
	$('.multiselect').click(function() {
		$('#pdesc').html(descs[$(this).children('option:selected').index()]);
	});
});
//]]>
</script>

<?php include("foot.inc");