<?php
/*
 * diag_reboot.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-rebootsystem
##|*NAME=Diagnostics: Reboot System
##|*DESCR=Allow access to the 'Diagnostics: Reboot System' page.
##|*MATCH=diag_reboot.php*
##|-PRIV

// Set DEBUG to true to prevent the system_reboot() function from being called
define("DEBUG", false);

global $g;

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

$guitimeout = 90;	// Seconds to wait before reloading the page after reboot
$guiretry = 20;		// Seconds to try again if $guitimeout was not long enough

$pgtitle = array(gettext("Diagnostics"), gettext("Reboot"));
$platform = system_identify_specific_platform();

include("head.inc");

if (isset($_POST['rebootmode'])):
	if (DEBUG) {
		print_info_box(gettext("Not actually rebooting (DEBUG is set true)."), 'success');
	} else {
		print('<div><pre>');
		switch ($_POST['rebootmode']) {
			case 'fsckreboot':
				if ((php_uname('m') != 'arm') && !is_module_loaded("zfs.ko")) {
					mwexec('/sbin/nextboot -e "pfsense.fsck.force=5"');
					notify_all_remote(sprintf(gettext("%s is rebooting for a filesystem check now."), g_get('product_label')));
					system_reboot();
				}
				break;
			case 'reroot':
				notify_all_remote(sprintf(gettext("%s is rerooting now."), g_get('product_label')));
				system_reboot_sync(true);
				break;
			case 'reboot':
				notify_all_remote(sprintf(gettext("%s is rebooting now."), g_get('product_label')));
				system_reboot();
				break;
			default:
				header('Location: /diag_reboot.php');
				break;
		}
		print('</pre></div>');
	}
?>

<div id="countdown" class="text-center"></div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	var time = 0;

	function checkonline() {
		$.ajax({
			url	: "/index.php", // or other resource
			type : "HEAD"
		})
		.done(function() {
			window.location="/index.php";
		});
	}

	function startCountdown() {
		setInterval(function() {
			if (time == "<?=$guitimeout?>") {
				$('#countdown').html('<h4><?=sprintf(gettext('Rebooting%1$sPage will automatically reload in %2$s seconds'), "<br />", "<span id=\"secs\"></span>");?></h4>');
			}

			if (time > 0) {
				$('#secs').html(time);
				time--;
			} else {
				time = "<?=$guiretry?>";
				$('#countdown').html('<h4><?=sprintf(gettext('Not yet ready%1$s Retrying in another %2$s seconds'), "<br />", "<span id=\"secs\"></span>");?></h4>');
				$('#secs').html(time);
				checkonline();
			}
		}, 1000);
	}

	time = "<?=$guitimeout?>";
	startCountdown();

});
//]]>
</script>
<?php
else:

$form = new Form(false);

$section = new Form_Section(gettext('Reboot Method'));

$help[] = gettext('Select "Normal reboot" to reboot the system immediately.');
$modeslist['reboot'] = gettext('Normal Reboot');

if ((php_uname('m') != 'arm') && !is_module_loaded("zfs.ko")) {
	$help[] = gettext('Select "Reboot with Filesystem Check" to reboot and run filesystem check.');
	$modeslist['fsckreboot'] = gettext('Reboot with Filesystem Check');
}

$help[] = gettext('Select "Reroot" to stop processes, remount disks and re-run startup sequence.');
$modeslist['reroot'] = gettext('Reroot');

$section->addInput(new Form_Select(
        'rebootmode',
        '*'.gettext('Reboot Method'),
        $rebootmode,
        $modeslist
))->setHelp(implode("<br />", $help));

$form->add($section);

$form->addGlobal(new Form_Button(
        'Submit',
        'Submit',
        null,
        'fa-solid fa-wrench'
))->addClass('btn-primary');

print($form);

endif;

include("foot.inc");
