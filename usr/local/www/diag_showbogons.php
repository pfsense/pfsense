<?php
/* $Id$ */
/*
    diag_showbogons.php
    Copyright (C) 2009 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/bin/ps	/usr/bin/grep
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-diag-showbogons
##|*NAME=Diagnostics: System Activity
##|*DESCR=Allows access to the 'Diagnostics: Show Bogons' page
##|*MATCH=diag_showbogons.php
##|-PRIV

require("guiconfig.inc");

if($_POST['Download']) {
	mwexec_bg("/etc/rc.update_bogons.sh now");
	$maxtimetowait = 0;
	$loading = true;
	while($loading == true) {
		$isrunning = `/bin/ps awwwux | /usr/bin/grep -v grep | /usr/bin/grep bogons`;
		if($isrunning == "") 
			$loading = false;
		$maxtimetowait++;
		if($maxtimetowait > 89) 
			$loading = false;
		sleep(1);
	}
	if($maxtimetowait < 90)
		$savemsg = gettext("The bogons database has been updated.");
}

$bogons = file_get_contents(trim("/etc/bogons"));
$pgtitle = array(gettext("Diagnostics"),gettext("Show Bogons"));

include("head.inc");
?>
<body link="#000000" vlink="#000000" alink="#000000">

<?php

include("fbegin.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<style type="text/css">
pre { font-size: 1.15em; }
</style> 
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form method="post" action="diag_showbogons.php">
<table width="100%" border="0" cellpadding="0" cellspacing="0">  
  <tr>
    <td>
	<table id="backuptable" class="tabcont" align="left" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td>
				<table>
					<tr>
						<td>
							<font size="+1">
								<b><?=gettext("Currently loaded bogons table");?>:</b><p/>
								<pre>
<?php echo $bogons; ?>
								</pre>
							</font>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	</div>
    </td>
  </tr>
</table>
<p/>
<input type="submit" name="Download" value="<?=gettext("Download");?>"><?=gettext("latest bogon data.");?>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
