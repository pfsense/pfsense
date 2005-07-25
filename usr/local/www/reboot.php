#!/usr/local/bin/php
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

require("guiconfig.inc");

$pgtitle = "Diagnostics: Reboot System";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<center>
<?php if ($rebootmsg): echo print_info_box($rebootmsg); else: ?>
      <form action="reboot.php" method="post">
        <p><strong>Are you sure you want to reboot the system?</strong></p>
        <p> 
          <input name="Submit" type="submit" class="formbtn" value=" No ">
	  <input name="Submit" type="submit" class="formbtn" value=" Yes ">
        </p>
      </form>
<?php endif; ?>
<?php include("fend.inc"); ?>
</body>
</html>

<?php
if ($_POST) {
	if ($_POST['Submit'] == " Yes ") {
		system_reboot();
		$rebootmsg = "The system is rebooting now. This may take one minute.";
	} else {
		header("Location: index.php");
		exit;
	}
}
?>
