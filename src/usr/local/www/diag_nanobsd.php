<?php
/*
 * diag_nanobsd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
##|*IDENT=page-diagnostics-nanobsd
##|*NAME=Diagnostics: NanoBSD
##|*DESCR=Allow access to the 'Diagnostics: NanoBSD' page.
##|*MATCH=diag_nanobsd.php*
##|-PRIV

ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);
ini_set('max_input_time', '9999');

require_once("guiconfig.inc");
require_once("config.inc");

// Setting DEBUG to true causes the dangerous stuff on this page to be simulated rather than executed.
// MUST be set to false for production of course
define('DEBUG', false);

$pgtitle = array(gettext("Diagnostics"), gettext("NanoBSD"));
include("head.inc");

// Survey slice info
global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;
nanobsd_detect_slice_info();

$NANOBSD_SIZE = nanobsd_get_size();
$class = 'alert-warning';

if ($_POST['bootslice']) {
	if (!DEBUG) {
	   nanobsd_switch_boot_slice();
	} else {
	   sleep(4);
	}

	$savemsg = sprintf(gettext("The boot slice has been set to %s."), nanobsd_get_active_slice());
	$class = 'alert-success';
	// Survey slice info
	nanobsd_detect_slice_info();
}

if ($_POST['destslice'] && $_POST['duplicateslice']) {
	$statusmsg = gettext("Duplicating slice.  Please wait, this will take a moment...");

	if (!DEBUG && nanobsd_clone_slice($_POST['destslice'])) {
		$savemsg = gettext("The slice has been duplicated.") . "<p/>" . gettext("To boot from this newly duplicated slice set it using the bootup information area.");
		$class = 'alert-success';
	} else {
		$savemsg = gettext("There was an error while duplicating the slice. Operation aborted.");
		$class = 'alert-danger';
	}
	// Re-Survey slice info
	nanobsd_detect_slice_info();
}

if ($_POST['changero']) {
	if (!DEBUG && is_writable("/")) {
		conf_mount_ro();
	} else {
		conf_mount_rw();
	}
}

if ($_POST['setrw']) {
	if (!DEBUG) {
		conf_mount_rw();
		if (isset($_POST['nanobsd_force_rw'])) {
			$savemsg = gettext("Permanent read/write has been set successfully.");
			$class = 'alert-success';
			$config['system']['nanobsd_force_rw'] = true;
		} else {
			$savemsg = gettext('Permanent read/write has been cleared successfully.');
			$class = 'alert-success';
			unset($config['system']['nanobsd_force_rw']);
		}

		write_config(gettext("Changed Permanent Read/Write Setting"));
		conf_mount_ro();
	} else {
		$savemsg = gettext('Saved read/write permanently.');
		$class = 'alert-success';
	}
}

print_info_box(gettext("The options on this page are intended for use by advanced users only."));

if ($savemsg) {
	print_info_box($savemsg, $class);
}

$form = new Form(false);

$section = new Form_Section('NanoBSD Options');

$section->addInput(new Form_StaticText(
	'Image Size',
	$NANOBSD_SIZE
));

$slicebtn = new Form_Button(
	'bootslice',
	'Switch Slice',
	null,
	'fa-retweet'
);
$slicebtn->addClass('btn-warning btn-sm');

$section->addInput(new Form_StaticText(
	'Bootup slice',
	$ACTIVE_SLICE . ' ' . $slicebtn
));

$refcount = refcount_read(1000);
$mounted_rw = is_writable("/");

if ($mounted_rw) {
	/* refcount_read returns -1 when shared memory section does not exist */
	/* refcount can be zero here when the user has set nanobsd_force_rw */
	/* refcount 1 is normal, so only display the count for abnormal values */
	/*
	if ($refcount == 1 || $refcount == 0 || $refcount == -1) {
		$refdisplay = "";
	} else {
		$refdisplay = " ". sprintf(gettext("(Reference count %s)"), $refcount);
	}
	*/
	$lbl = gettext("Read/Write") . $refdisplay;
	$btnlbl = gettext("Switch to Read-Only");
} else {
	$lbl = gettext("Read-Only");
	$btnlbl = gettext("Switch to Read/Write");
}

// Only show the changero button if force read/write is off, or the file system is not in writable state, or there is an unusual refcount.
// If force read/write is on, and the file system is in writable state, and refcount is normal then the user has no reason to mess about.
/*
if (!isset($config['system']['nanobsd_force_rw']) || !$mounted_rw || ($refcount > 1)) {
	$robtn = new Form_Button(
		'changero',
		$btnlbl,
		null,
		($mounted_rw) ? 'fa-lock' : 'fa-unlock'
	);
	$robtn->addClass(($mounted_rw) ? 'btn-success' : 'btn-warning' . ' btn-sm');
	$lbl .= ' ' . $robtn;
}
*/
$section->addInput(new Form_StaticText(
	'Read/Write status',
	$lbl
))->setHelp('NanoBSD is now always read-write to avoid read-write to read-only mount problems.');
//))->setHelp('This setting is only temporary, and can be switched dynamically in the background.');

/*
$section->addInput(new Form_Checkbox(
	'nanobsd_force_rw',
	'Permanent Read/Write',
	'Keep media mounted read/write at all times. ',
	isset($config['system']['nanobsd_force_rw'])
));

$permbtn = new Form_Button(
	'setrw',
	'Save',
	null,
	'fa-save'
);
$permbtn->addClass('btn-primary btn-sm');

$section->addInput(new Form_StaticText(
	null,
	$permbtn
));
*/

$section->addInput(new Form_Input(
	'destslice',
	null,
	'hidden',
	$COMPLETE_PATH
));

$dupbtn = new Form_Button(
	'duplicateslice',
	'Duplicate ' . $COMPLETE_BOOT_PATH . ' -> ' . $TOFLASH,
	null,
	'fa-clone'
);
$dupbtn->addClass('btn-success btn-sm');

$section->addInput(new Form_StaticText(
	'Duplicate boot slice',
	$dupbtn
))->setHelp('This will duplicate the bootup slice to the alternate slice.  Use this to duplicate the known good working boot partition to the alternate.');

$section->addInput(new Form_StaticText(
	'RRD/DHCP Backup',
	'These options have been relocated to the ' . '<a href="system_advanced_misc.php">' . 'System > Advanced, Miscellaneous</a> tab.'
));

if (file_exists("/conf/upgrade_log.txt")) {
	$viewbtn = new Form_Button(
		'viewupgradelog',
		'View log',
		null,
		'fa-file-text-o'
	);
	$viewbtn->addClass('btn-primary btn-sm');

	$section->addInput(new Form_StaticText(
		'View previous upgrade log',
		$viewbtn
	));
}
$form->add($section);
print($form);

if (file_exists("/conf/upgrade_log.txt") && $_POST['viewupgradelog']) {
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Previous Upgrade Log")?></h2></div>
			<!-- No white space between the <pre> and the first output or it will appear on the page! -->
			<pre>
				<?=str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/upgrade_log.txt"))?>
			</pre>
	</div>
<?php
}
require_once("foot.inc");
