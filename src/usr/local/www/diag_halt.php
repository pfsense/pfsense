<?php
/*
	diag_halt.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-diagnostics-haltsystem
##|*NAME=Diagnostics: Halt system
##|*DESCR=Allow access to the 'Diagnostics: Halt system' page.
##|*MATCH=diag_halt.php*
##|-PRIV

// Set DEBUG to true to prevent the system_halt() function from being called
define("DEBUG", false);

require("guiconfig.inc");
require("functions.inc");
require("captiveportal.inc");

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
