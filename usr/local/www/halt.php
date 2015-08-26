<?php
/* $Id$ */
/*
	halt.php
	part of pfSense
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	part of m0n0wall as reboot.php (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

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
	pfSense_MODULE: header
*/

##|+PRIV
##|*IDENT=page-diagnostics-haltsystem
##|*NAME=Diagnostics: Halt system page
##|*DESCR=Allow access to the 'Diagnostics: Halt system' page.
##|*MATCH=halt.php*
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

$pgtitle = array(gettext("Diagnostics"), gettext("Halt system"));
include('head.inc');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
?>
	<meta http-equiv="refresh" content="70;url=/">
	<div class="alert alert-success" role="alert">
		<?=gettext("The system is halting now. This may take one minute or so.")?>
	</div>

<?php
	if(DEBUG)
	   print("Not actually halting (DEBUG is set true)<br>");
	else
		system_halt();
} else {
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Are you sure you want to halt the system?</h2>
	</div>
	<div class="panel-body">
		<p>Click "Yes" to halt the system immediately, or "No" to go to the system dashboard. (There will be a brief delay before the dashboard appears.)</p>
		<form action="halt.php" method="post">
			<input type="submit" class="btn btn-danger pull-center" name="save" value="Yes">
			<a href="/" class="btn btn-default">No</a>
		</form>
	</div>
</div>

<?php
}

include("foot.inc");
