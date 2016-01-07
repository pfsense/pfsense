<?php
/*
	diag_nanobsd.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
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
define(DEBUG, false);

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

	$savemsg = gettext("The boot slice has been set to") . " " . nanobsd_get_active_slice();
	$class = 'alert-success';
	// Survey slice info
	nanobsd_detect_slice_info();
}

if ($_POST['destslice'] && $_POST['duplicateslice']) {
	$statusmsg = gettext("Duplicating slice.  Please wait, this will take a moment...");

	if (!DEBUG && nanobsd_clone_slice($_POST['destslice'])) {
		$savemsg = gettext("The slice has been duplicated.") . "<p/>" . gettext("If you would like to boot from this newly duplicated slice please set it using the bootup information area.");
		$class = 'alert-success';
	} else {
		$savemsg = gettext("There was an error while duplicating the slice.	 Operation aborted.");
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

		write_config("Changed Permanent Read/Write Setting");
		conf_mount_ro();
	} else {
		$savemsg = 'Saved r/w permanently';
		$class = 'alert-success';
	}
}

print_info_box("The options on this page are intended for use by advanced users only.");

if ($savemsg) {
	print_info_box($savemsg, $class);
}

$form = new Form(false);

$section = new Form_Section('NanoBSD Options');

$section->addInput(new Form_StaticText(
	'Image Size',
	$NANOBSD_SIZE
));

$slicebtn = new Form_Button('bootslice', 'Switch Slice');
$slicebtn->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Bootup slice',
	$ACTIVE_SLICE . ' ' . $slicebtn
));

$refcount = refcount_read(1000);

if (is_writable("/")) {
	/* refcount_read returns -1 when shared memory section does not exist */
	/* refcount can be zero here when the user has set nanobsd_force_rw */
	/* refcount 1 is normal, so only display the count for abnormal values */
	if ($refcount == 1 || $refcount == 0 || $refcount == -1) {
		$refdisplay = "";
	} else {
		$refdisplay = " (Reference count " . $refcount . ")";
	}
	$lbl = gettext("Read/Write") . $refdisplay;
	$btnlbl = gettext("Switch to Read-Only");
} else {
	$lbl = gettext("Read-Only");
	$btnlbl = gettext("Switch to Read/Write");
}

// Only show the changero button if force read/write is off, or the file system is not in writable state, or there is an unusual refcount.
// If force read/write is on, and the file system is in writable state, and refcount is normal then the user has no reason to mess about.
if (!isset($config['system']['nanobsd_force_rw']) || !is_writable("/") || ($refcount > 1)) {
	$robtn = new Form_Button('changero', $btnlbl);
	$robtn->removeClass('btn-primary')->addClass('btn-default btn-sm');
	$lbl .= ' ' . $robtn;
}

$section->addInput(new Form_StaticText(
	'Read/Write status',
	$lbl
))->setHelp('This setting is only temporary, and can be switched dynamically in the background.');

$section->addInput(new Form_Checkbox(
	'nanobsd_force_rw',
	'Permanent Read/Write',
	'Keep media mounted read/write at all times. ',
	isset($config['system']['nanobsd_force_rw'])
));

$permbtn = new Form_Button('setrw', 'Save');
$permbtn->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	null,
	$permbtn
));

$section->addInput(new Form_Input(
	'destslice',
	null,
	'hidden',
	$COMPLETE_PATH
));

$dupbtn = new Form_Button('duplicateslice', 'Duplicate ' . $COMPLETE_BOOT_PATH . ' -> ' . $TOFLASH);
$dupbtn->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Duplicate boot slice',
	$dupbtn
))->setHelp('This will duplicate the bootup slice to the alternate slice.  Use this if you would like to duplicate the known good working boot partition to the alternate.');

$section->addInput(new Form_StaticText(
	'RRD/DHCP Backup',
	'These options have been relocated to the ' . '<a href="system_advanced_misc.php">' . 'System > Advanced, Miscellaneous</a> tab.'
));

if (file_exists("/conf/upgrade_log.txt")) {
	$viewbtn = new Form_Button('viewupgradelog', 'View log');
	$viewbtn->removeClass('btn-primary')->addClass('btn-default btn-sm');

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
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Previous upgrade log")?></h2></div>
			<!-- No white space between the <pre> and the first output or it will appear on the page! -->
			<pre><?=str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/upgrade_log.txt"))?>
				<br /><?=gettext("File list:")?>
				<?=str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/file_upgrade_log.txt"))?>
				<br /><?=gettext("Misc log:")?>
				<?=str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/firmware_update_misc_log.txt"))?>
				<br /><?=gettext("fdisk/bsdlabel log:")?>
				<?=str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/fdisk_upgrade_log.txt"))?>
			</pre>
	</div>
<?php
}
require("foot.inc");
