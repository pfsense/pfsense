<?php
/*
 * diag_reboot.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

$guitimeout = 90;	// Seconds to wait before reloading the page after reboot
$guiretry = 20;		// Seconds to try again if $guitimeout was not long enough

$pgtitle = array(gettext("Diagnostics"), gettext("Reboot"));
include("head.inc");

if (($_SERVER['REQUEST_METHOD'] == 'POST') && (empty($_POST['override']) ||
    ($_POST['override'] != "yes"))):
	if (DEBUG) {
		print_info_box(gettext("Not actually rebooting (DEBUG is set true)."), 'success');
	} else {
		print('<div><pre>');
		system_reboot();
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
			url	 : "/index.php", // or other resource
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

?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('System Reboot Confirmation')?></h2>
	</div>
	<div class="panel-body">
		<div class="content">
			<p><?=gettext('Click "Reboot" to reboot the system immediately, or "Cancel" to go to the system dashboard without rebooting. (There will be a brief delay before the dashboard appears.)')?></p>
			<form action="diag_reboot.php" method="post">
				<button type="submit" class="btn btn-danger pull-center" name="Submit" value="<?=gettext("Reboot")?>" title="<?=gettext("Reboot the system")?>">
					<i class="fa fa-refresh"></i>
					<?=gettext("Reboot")?>
				</button>
				<a href="/" class="btn btn-info">
					<i class="fa fa-undo"></i>
					<?=gettext("Cancel")?>
				</a>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	//If we have been called with $_POST['override'] == "yes", then just reload the page to simulate the user clicking "Reboot"
	if ( "<?=$_POST['override']?>" == "yes") {
		$('form').submit();
	}
});
//]]>
</script>
<?php

endif;

include("foot.inc");
