<?php
/* $Id$ */
/*
	system_advanced_sysctl.php
	part of pfSense
	Copyright (C) 2005-2007 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright notice,
	   this list of conditions and the following disclaimer in the documentation
	   and/or other materials provided with the distribution.

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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-advanced-sysctl
##|*NAME=System: Advanced: Tunables page
##|*DESCR=Allow access to the 'System: Advanced: Tunables' page.
##|*MATCH=system_advanced_sysctl.php*
##|-PRIV

require("guiconfig.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_advanced_sysctl.php');

if (!is_array($config['sysctl'])) {
	$config['sysctl'] = array();
}
if (!is_array($config['sysctl']['item'])) {
	$config['sysctl']['item'] = array();
}

$a_tunable = &$config['sysctl']['item'];
$tunables = system_get_sysctls();

if (isset($_GET['id'])) {
	$id = htmlspecialchars_decode($_GET['id']);
}
if (isset($_POST['id'])) {
	$id = htmlspecialchars_decode($_POST['id']);
}
$act = $_GET['act'];
if (isset($_POST['act'])) {
	$act = $_POST['act'];
}
if ($act == "edit") {
	if (isset($a_tunable[$id])) {
		$pconfig['tunable'] = $a_tunable[$id]['tunable'];
		$pconfig['value'] = $a_tunable[$id]['value'];
		$pconfig['descr'] = $a_tunable[$id]['descr'];
	} else if (isset($tunables[$id])) {
		$pconfig['tunable'] = $tunables[$id]['tunable'];
		$pconfig['value'] = $tunables[$id]['value'];
		$pconfig['descr'] = $tunables[$id]['descr'];
	}
}
if ($act == "del") {
	if ($a_tunable[$id]) {
		/* if this is an AJAX caller then handle via JSON */
		if(isAjax() && is_array($input_errors)) {
			input_errors2Ajax($input_errors);
			exit;
		}
		if (!$input_errors) {
			unset($a_tunable[$id]);
			write_config();
			mark_subsystem_dirty('sysctl');
			pfSenseHeader("system_advanced_sysctl.php");
			exit;
		}
	}
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if ($_POST['apply']) {
		$retval = 0;
		system_setup_sysctl();
		$savemsg = get_std_save_message($retval);
		clear_subsystem_dirty('sysctl');
	}

	if ($_POST['Submit'] == gettext("Save")) {
		$tunableent = array();

		$tunableent['tunable'] = $_POST['tunable'];
		$tunableent['value'] = $_POST['value'];
		$tunableent['descr'] = $_POST['descr'];

		if (isset($id) && isset($a_tunable[$id])) {
			$a_tunable[$id] = $tunableent;
		} else {
			$a_tunable[] = $tunableent;
		}

		mark_subsystem_dirty('sysctl');
		write_config();
		pfSenseHeader("system_advanced_sysctl.php");
		exit;
	}
}

$pgtitle = array(gettext("System"),gettext("Advanced: System Tunables"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
if (is_subsystem_dirty('sysctl') && ($act != "edit" ))
	print_info_box_np(gettext("The firewall tunables have changed. You must apply the configuration to take affect."));

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), true, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

if ($act != "edit" ): ?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('System Tunables'); ?></h2>
	</div>
	<div class="panel-body">
		<div class="form-group">
			<table class="table table-responsive table-hover table-condensed">
				<caption><strong><?=gettext('NOTE: '); ?></strong><?=gettext('The options on this page are intended for use by advanced users only.'); ?></caption>
				<thead>
					<tr>
						<th class="col-sm-3"><?=gettext("Tunable Name"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th class="col-sm-1"><?=gettext("Value"); ?></th>
						<th><a class="btn btn-xs btn-primary" href="system_advanced_sysctl.php?act=edit"><?=gettext('New'); ?></a></th>
					</tr>
				</thead>
				<?php foreach ($tunables as $i => $tunable):
					if (!isset($tunable['modified']))
						$i = $tunable['tunable']; ?>
				<tr>
					<td><?=$tunable['tunable']; ?></td>
					<td><?=$tunable['descr']; ?></td>
					<td><?=$tunable['value']; ?>
					<?php if($tunable['value'] == "default")
						echo "(" . get_default_sysctl_value($tunable['tunable']) . ")"; ?>
					</td>
					<td>
					<a class="btn btn-xs btn-primary" href="system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>"><?=gettext('Edit'); ?></a>
						<?php if (isset($tunable['modified'])): ?>
						<a class="btn btn-xs btn-danger" href="system_advanced_sysctl.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?"); ?>')"><?=gettext('Delete/Reset'); ?></a>
						<?php endif; ?>
					</td>
				</tr>
				<?php
					endforeach;
					unset($tunables);
				?>
			</table>
		</div>
	</div>
</div>

<?php else:
	require('classes/Form.class.php');
	$form = new Form;
	$section = new Form_Section('Edit Tunable');

	$section->addInput(new Form_Input(
		'tunable',
		'Tunable',
		'text',
		$pconfig['tunable']
	))->setWidth(4);

	$section->addInput(new Form_Input(
		'descr',
		'Description',
		'text',
		$pconfig['descr']
	))->setWidth(4);

	$section->addInput(new Form_Input(
		'value',
		'Value',
		'text',
		$pconfig['value']
	))->setWidth(4);

	if (isset($id) && $a_tunable[$id]) {
		$form->addGlobal(new Form_Input(
			'id',
			'id',
			'hidden',
			$id
		));
	}

	$form->add($section);

	$form->addGlobal(new Form_Button(
		'cancel',
		'Cancel',
		$referer
	));
	print $form;

endif;

include("fend.inc");