<?php
/*
	system_usermanager_addprivs.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2006 Daniel S. Haischt.
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
##|*IDENT=page-system-usermanager-addprivs
##|*NAME=System: User Manager: Add Privileges
##|*DESCR=Allow access to the 'System: User Manager: Add Privileges' page.
##|*MATCH=system_usermanager_addprivs.php*
##|-PRIV

function admusercmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

require("guiconfig.inc");

$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Users"), gettext("Add Privileges"));

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

// Make a local copy and sort it
$spriv_list = $priv_list;
uasort($spriv_list, admusercmp);

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
	global $spriv_list, $a_user;

	$list = array();

	foreach($spriv_list as $pname => $pdata) {
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

$form = new Form();

$section = new Form_Section('User privileges');

$section->addInput(new Form_Select(
	'sysprivs',
	'Assigned privileges',
	null,
	build_priv_list(),
	true
))->addClass('multiselect')->setHelp('Hold down CTRL (PC)/COMMAND (Mac) key to select multiple items')->setAttribute('style', 'height:400px;');

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

<div class="panel panel-body alert-info col-sm-10 col-sm-offset-2" id="pdesc">Select a privilege from the list above for a description</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

<?php

	// Build a list of privilege descriptions
	if (is_array($spriv_list)) {
		$id = 0;

		$jdescs = "var descs = new Array();\n";
		foreach ($spriv_list as $pname => $pdata) {
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
		$('#pdesc').html('<span style="color: green;">' + descs[$(this).children('option:selected').index()] + '</span>');
	});
});
//]]>
</script>

<?php include("foot.inc");
