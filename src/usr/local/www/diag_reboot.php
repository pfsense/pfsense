<?php
/*
	diag_reboot.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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

##|+PRIV
##|*IDENT=page-diagnostics-rebootsystem
##|*NAME=Diagnostics: Reboot System
##|*DESCR=Allow access to the 'Diagnostics: Reboot System' page.
##|*MATCH=diag_reboot.php*
##|-PRIV

// Set DEBUG to true to prevent the system_reboot() function from being called
define("DEBUG", false);

require("guiconfig.inc");
require("functions.inc");
require("captiveportal.inc");

$guitimeout = 90;	// Seconds to wait before reloading the page after reboot
$guiretry = 20;		// Seconds to try again if $guitimeout was not long enough

$pgtitle = array(gettext("Diagnostics"), gettext("Reboot System"));
include("head.inc");


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	if (DEBUG) {
	   print_info_box("Not actually rebooting (DEBUG is set true)", 'success');
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

	var timeoutmsg = '<h4>Rebooting<br />Page will automatically reload in ';
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
			if (time > 0) {
				$('#countdown').html(timeoutmsg + time + ' seconds.</h4>');
				time--;
			} else {
				time = "<?=$guiretry?>";
				timeoutmsg = '<h4>Not yet ready<br />Retrying in another ';
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
} else {

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Are you sure you want to reboot the system?</h2></div>
	<div class="panel-body">
		<div class="content">
			<p>Click "Reboot" to reboot the system immediately, or "No" to go to the system dashboard without rebooting. (There will be a brief delay before the dashboard appears.)</p>
			<form action="diag_reboot.php" method="post">
				<input type="submit" class="btn btn-danger pull-center" name="Submit" value="Reboot">
				<a href="/" class="btn btn-default">No</a>
			</form>
		</div>
	</div>
</div>

<?php

}

include("foot.inc");


