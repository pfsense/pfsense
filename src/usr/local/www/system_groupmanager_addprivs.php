<?php
/*
 * system_groupmanager_addprivs.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006 Daniel S. Haischt.
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-system-groupmanager-addprivs
##|*NAME=System: Group Manager: Add Privileges
##|*DESCR=Allow access to the 'System: Group Manager: Add Privileges' page.
##|*WARN=standard-warning-root
##|*MATCH=system_groupmanager_addprivs.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");

$logging_level = LOG_WARNING;
$logging_prefix = gettext("Local User Database");

$groupid = $_REQUEST['groupid'];

$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Groups"), gettext("Edit"), gettext("Add Privileges"));
$pglinks = array("", "system_usermanager.php", "system_groupmanager.php", "system_groupmanager.php?act=edit&groupid=" . $groupid, "@self");

init_config_arr(array('system', 'group', $groupid));
$a_group = &$config['system']['group'][$groupid];

if (!is_array($a_group)) {
	pfSenseHeader("system_groupmanager.php?id={$groupid}");
	exit;
}

if (!is_array($a_group['priv'])) {
	$a_group['priv'] = array();
}

// Make a local copy and sort it
$spriv_list = $priv_list;
uasort($spriv_list, "compare_by_name");

/*
 * Check user privileges to test if the user is allowed to make changes.
 * Otherwise users can end up in an inconsistent state where some changes are
 * performed and others denied. See https://redmine.pfsense.org/issues/9259
 */
phpsession_begin();
$guiuser = getUserEntry($_SESSION['Username']);
$read_only = (is_array($guiuser) && userHasPrivilege($guiuser, "user-config-readonly"));
phpsession_end();

if (!empty($_POST) && $read_only) {
	$input_errors = array(gettext("Insufficient privileges to make the requested change (read only)."));
}

if ($_POST['save'] && !$read_only) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "sysprivs");
	$reqdfieldsn = array(gettext("Selected privileges"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {

		if (!is_array($pconfig['sysprivs'])) {
			$pconfig['sysprivs'] = array();
		}

		if (!count($a_group['priv'])) {
			$a_group['priv'] = $pconfig['sysprivs'];
		} else {
			$a_group['priv'] = array_merge($a_group['priv'], $pconfig['sysprivs']);
		}

		if (is_array($a_group['member'])) {
			foreach ($a_group['member'] as $uid) {
				$user = getUserEntryByUID($uid);
				if ($user) {
					local_user_set($user);
				}
			}
		}

		$savemsg = sprintf(gettext("Privileges changed for group: %s"), $a_group['name']);
		write_config($savemsg);
		syslog($logging_level, "{$logging_prefix}: {$savemsg}");

		pfSenseHeader("system_groupmanager.php?act=edit&groupid={$groupid}");
		exit;
	}
}

function build_priv_list() {
	global $spriv_list, $a_group;

	$list = array();

	foreach ($spriv_list as $pname => $pdata) {
		if (in_array($pname, $a_group['priv'])) {
			continue;
		}

		$list[$pname] = $pdata['name'];
	}

	return($list);
}

function get_root_priv_item_text() {
	global $priv_list;

	$priv_text = "";

	foreach ($priv_list as $pname => $pdata) {
		if (isset($pdata['warn']) && ($pdata['warn'] == 'standard-warning-root')) {
			$priv_text .= '<br/>' . $pdata['name'];
		}
	}

	return($priv_text);
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
if (!isAllowedPage("system_usermanager.php")) {
	$tab_array[] = array(gettext("User Password"), false, "system_usermanager_passwordmg.php");
} else {
	$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
}
$tab_array[] = array(gettext("Groups"), true, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Authentication Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

$form = new Form;
if (isset($groupid)) {
	$form->addGlobal(new Form_Input(
		'groupid',
		null,
		'hidden',
		$groupid
	));
}

$section = new Form_Section('Group Privileges');

$name_string = $a_group['name'];
if (!empty($a_group['descr'])) {
	$name_string .= " ({$a_group['descr']})";
}

$section->addInput(new Form_StaticText(
	'Group',
	$name_string
));

$section->addInput(new Form_Select(
	'sysprivs',
	'*Assigned privileges',
	$a_group['priv'],
	build_priv_list(),
	true
))->addClass('multiselect')
  ->setHelp('Hold down CTRL (PC)/COMMAND (Mac) key to select multiple items.');

$section->addInput(new Form_Select(
	'shadow',
	'Shadow',
	null,
	build_priv_list(),
	true
))->addClass('shadowselect')
  ->setHelp('Hold down CTRL (PC)/COMMAND (Mac) key to select multiple items.');

$section->addInput(new Form_Input(
	'filtertxt',
	'Filter',
	'text',
	null
))->setHelp('Show only the choices containing this term');

$section->addInput(new Form_StaticText(
	gettext('Privilege information'),
	'<span class="help-block">'.
	gettext('The following privileges effectively give administrator-level access to users in the group' .
		' because the user gains access to execute general commands, edit system files, ' .
		' modify users, change passwords or similar:') .
	'<br/>' .
	get_root_priv_item_text() .
	'<br/><br/>' .
	gettext('Please take care when granting these privileges.') .
	'</span>'
));

$btnfilter = new Form_Button(
	'btnfilter',
	'Filter',
	null,
	'fa-filter'
);

$btnfilter->setAttribute('type','button')->addClass('btn btn-info');

$form->addGlobal($btnfilter);

$btnclear = new Form_Button(
	'btnclear',
	'Clear',
	null,
	'fa-times'
);

$btnclear->setAttribute('type','button')->addClass('btn btn-warning');

$form->addGlobal($btnclear);
$form->add($section);

print $form;

?>
<div class="panel panel-body alert-info col-sm-10 col-sm-offset-2" id="pdesc"><?=gettext("Select a privilege from the list above for a description")?></div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

<?php

	// Build a list of privilege descriptions
	if (is_array($spriv_list)) {
		$id = 0;

		$jdescs = "var descs = new Array();\n";
		foreach ($spriv_list as $pname => $pdata) {
			if (in_array($pname, $a_group['priv'])) {
				continue;
			}

			$desc = preg_replace("/pfSense/i", $g['product_name'], $pdata['descr']);
			if (isset($pdata['warn']) && ($pdata['warn'] == 'standard-warning-root')) {
				$desc .= ' ' . gettext('(This privilege effectively gives administrator-level access to users in the group)');
			}
			$desc = addslashes($desc);
			$jdescs .= "descs[{$id}] = '{$desc}';\n";
			$id++;
		}

		echo $jdescs;
	}
?>
	$('.shadowselect').parent().parent('div').addClass('hidden');

	// Set the number of options to display
	$('.multiselect').attr("size","20");
	$('.shadowselect').attr("size","20");

	// When the 'sysprivs" selector is clicked, we display a description
	$('.multiselect').click(function() {
		var targetoption = $(this).children('option:selected').val();
		var idx =  $('.shadowselect option[value="' + targetoption + '"]').index();

		$('#pdesc').html('<span class="text-info">' + descs[idx] + '</span>');

		// and update the shadow list from the real list
		$(".multiselect option").each(function() {
			shadowoption = $('.shadowselect option').filter('[value=' + $(this).val() + ']');

			if ($(this).is(':selected')) {
				shadowoption.prop("selected", true);
			} else {
				shadowoption.prop("selected", false);
			}
		});
	});

	$('#btnfilter').click(function() {
		searchterm = $('#filtertxt').val().toLowerCase();
		copyselect(true);

		// Then filter
		$(".multiselect > option").each(function() {
			if (this.text.toLowerCase().indexOf(searchterm) == -1 ) {
				$(this).remove();
			}
		});
	});

	$('#btnclear').click(function() {
		// Copy all options from shadow to sysprivs
		copyselect(true)

		$('#filtertxt').val('');
	});

	$('#filtertxt').keypress(function(e) {
		if (e.which == 13) {
			e.preventDefault();
			$('#btnfilter').trigger('click');
		}
	});

	// On submit unhide all options (or else they will not submit)
	$('form').submit(function() {

		$(".multiselect > option").each(function() {
			$(this).show();
		});

		$('.shadowselect').remove();
	});

	function copyselect(selected) {
		// Copy all optionsfrom shadow to sysprivs
		$('.multiselect').html($('.shadowselect').html());

		if (selected) {
			// Update the shadow list from the real list
			$(".shadowselect option").each(function() {
				multioption = $('.multiselect option').filter('[value=' + $(this).val() + ']');
				if ($(this).is(':selected')) {
					multioption.prop("selected", true);
				} else {
					multioption.prop("selected", false);
				}
			});
		}
	}

	$('.multiselect').mouseup(function () {
		$('.multiselect').trigger('click');
	});
});
//]]>
</script>

<?php
include('foot.inc');
