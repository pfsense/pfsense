<?php 
/* $Id$ */
/*
	reboot.php
	part of m0n0wall (http://m0n0.ch/wall)
	
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

##|+PRIV
##|*IDENT=page-diagnostics-rebootsystem
##|*NAME=Diagnostics: Reboot System page
##|*DESCR=Allow access to the 'Diagnostics: Reboot System' page.
##|*MATCH=reboot.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require("captiveportal.inc");

if ($_POST['Submit'] == " " . gettext("No") . " ") {
	header("Location: index.php");
	exit;
}

$pgtitle = array(gettext("Diagnostics"),gettext("Reboot System"));
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($_POST['Submit'] == " " . gettext("Yes") . " "): ?>
<meta http-equiv=\"refresh\" content=\"70;url=/\">
<?php	print_info_box(gettext("The system is rebooting now. This may take one minute.")); ?>
<pre>
<?php 	system_reboot(); ?>
</pre>
<?php else: ?>
<form action="reboot.php" method="post">
	<p><strong><?=gettext("Are you sure you want to reboot the system?");?></strong></p>
	<p>
	<input name="Submit" type="submit" class="formbtn" value=" <?=gettext("Yes");?> " />
	<input name="Submit" type="submit" class="formbtn" value=" <?=gettext("No");?> " />
	</p>
</form>
<?php endif; ?>
<?php include("fend.inc"); ?>
</body>
</html>
