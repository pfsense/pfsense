<?php
/*
 * diag_halt.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-haltsystem
##|*NAME=Diagnostics: Halt system
##|*DESCR=Allow access to the 'Diagnostics: Halt system' page.
##|*MATCH=diag_halt.php*
##|-PRIV

// Set DEBUG to true to prevent the system_halt() function from being called
define("DEBUG", false);

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

if ($_POST['save'] == 'No') {
	header("Location: index.php");
	exit;
}

$pgtitle = array(gettext("Diagnostics"), gettext("Halt System"));
include('head.inc');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
?>
	<meta http-equiv="refresh" content="70;url=/">
<?php
	print_info_box(gettext("The system is halting now. This may take one minute or so."), 'success', false);

	if (DEBUG) {
	   printf(gettext("Not actually halting (DEBUG is set true)%s"), "<br />");
	} else {
		print('<pre>');
		system_halt();
		print('</pre>');
	}
} else {
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('System Halt Confirmation')?></h2>
	</div>
	<div class="panel-body">
		<div class="content">
			<p><?=gettext('Click "Halt" to halt the system immediately, or "Cancel" to go to the system dashboard. (There will be a brief delay before the dashboard appears.)')?></p>
			<form action="diag_halt.php" method="post">
				<button type="submit" class="btn btn-danger pull-center" name="save" value="<?=gettext("Halt")?>" title="<?=gettext("Halt the system and power off")?>">
					<i class="fa fa-stop-circle"></i>
					<?=gettext("Halt")?>
				</button>
				<a href="/" class="btn btn-info">
					<i class="fa fa-undo"></i>
					<?=gettext("Cancel")?>
				</a>
			</form>
		</div>
	</div>
</div>



<?php
}

include("foot.inc");
