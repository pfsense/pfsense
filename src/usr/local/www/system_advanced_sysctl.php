<?php
/*
	system_advanced_sysctl.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2008 Shrew Soft Inc
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
/*
	pfSense_MODULE: system
*/

##|+PRIV
##|*IDENT=page-system-advanced-sysctl
##|*NAME=System: Advanced: Tunables
##|*DESCR=Allow access to the 'System: Advanced: Tunables' page.
##|*MATCH=system_advanced_sysctl.php*
##|-PRIV

require("guiconfig.inc");

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
		if (isAjax() && is_array($input_errors)) {
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

	if ($_POST['save'] == gettext("Save")) {

		$tunableent = array();

		if(!$_POST['tunable'] || !isset($_POST['value'])) {
			$input_errors[] = gettext("Both a name and a value must be specified.");
		} else if (!ctype_alnum($_POST['value'])) {
			$input_errors[] = gettext("The value may contain alphanumeric characters only.");
		} else {
			$tunableent['tunable'] = htmlspecialchars($_POST['tunable']);
			$tunableent['value'] = htmlspecialchars($_POST['value']);
			$tunableent['descr'] = strip_tags($_POST['descr']);

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
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("System Tunables"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('sysctl') && ($act != "edit" ))
	print_info_box_np(gettext("The firewall tunables have changed. You must apply the configuration for them to take affect."));

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
					<a class="fa fa-pencil" title="<?=gettext("Edit tunable"); ?>" href="system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>"></a>
						<?php if (isset($tunable['modified'])): ?>
						<a class="fa fa-trash" title="<?=gettext("Delete/Reset tunable")?>" href="system_advanced_sysctl.php?act=del&amp;id=<?=$i;?>"></a>
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
	$form = new Form;
	$section = new Form_Section('Edit Tunable');

	$section->addInput(new Form_Input(
		'tunable',
		'Tunable',
		'text',
		$pconfig['tunable']
	))->setWidth(4);

	$section->addInput(new Form_Input(
		'value',
		'Value',
		'text',
		$pconfig['value']
	))->setWidth(4);

	$section->addInput(new Form_Input(
		'descr',
		'Description',
		'text',
		$pconfig['descr']
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

	print $form;

endif;

include("foot.inc");
