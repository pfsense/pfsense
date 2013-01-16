<?php
/*
	diag_traceroute.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2005 Paul Taylor (paultaylor@winndixie.com) and Manuel Kasper <mk@neon1.net>.
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
	pfSense_BUILDER_BINARIES:	/usr/sbin/traceroute
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-diagnostics-traceroute
##|*NAME=Diagnostics: Traceroute page
##|*DESCR=Allow access to the 'Diagnostics: Traceroute' page.
##|*MATCH=diag_traceroute.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"),gettext("Traceroute"));
include("head.inc");

?>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<?php

define('MAX_TTL', 64);
define('DEFAULT_TTL', 18);

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);
	unset($do_traceroute);

	/* input validation */
	$reqdfields = explode(" ", "host ttl");
	$reqdfieldsn = array(gettext("Host"),gettext("ttl"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_REQUEST['ttl'] < 1) || ($_REQUEST['ttl'] > MAX_TTL)) {
		$input_errors[] = sprintf(gettext("Maximum number of hops must be between 1 and %s"), MAX_TTL);
	}

	if (!$input_errors) {
		$do_traceroute = true;
		$host = $_REQUEST['host'];
		$ttl = $_REQUEST['ttl'];

	}
}
if (!isset($do_traceroute)) {
	$do_traceroute = false;
	$host = '';
	$ttl = DEFAULT_TTL;
}
?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
			<form action="diag_traceroute.php" method="post" name="iform" id="iform">
			  <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Traceroute");?></td>
				</tr>	
                <tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("Host");?></td>
				  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="host" type="text" class="formfld" id="host" size="20" value="<?=htmlspecialchars($host);?>"></td>
				</tr>
				<tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("Maximum number of hops");?></td>
				  <td width="78%" class="vtable">
					<select name="ttl" class="formfld" id="ttl">
					<?php for ($i = 1; $i <= MAX_TTL; $i++): ?>
					<option value="<?=$i;?>" <?php if ($i == $ttl) echo "selected"; ?>><?=$i;?></option>
					<?php endfor; ?>
					</select></td>
				</tr>

				<tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("Use ICMP");?></td>
				  <td width="78%" class="vtable">
					<input name="useicmp" type="checkbox"<?php if($_REQUEST['useicmp']) echo " CHECKED"; ?>>
					</td>
				</tr>
				<tr>
				  <td width="22%" valign="top">&nbsp;</td>
				  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Traceroute");?>">
				</td>
				</tr>
				<tr>
				<td valign="top" colspan="2">
				<p><span class="vexpl"><span class="red"><b><?=gettext("Note: ");?></b></span><?=gettext("Traceroute may take a while to complete. You may hit the Stop button on your browser at any time to see the progress of failed traceroutes.");?></span><p>
				<?php if ($do_traceroute) {
					echo "<font face='terminal' size='2'>";
					echo("<br><strong>" . gettext("Traceroute output:") . "</strong><br>");
					echo('<pre>');
					ob_end_flush();
					if($_REQUEST['useicmp'])
						$useicmp = "-I";
					else
						$useicmp = "";
					system("/usr/sbin/traceroute $useicmp -w 2 -m " . escapeshellarg($ttl) . " " . escapeshellarg($host));
					system("/usr/sbin/traceroute6 $useicmp -w 2 -m " . escapeshellarg($ttl) . " " . escapeshellarg($host));
					echo('</pre>');
				}
				?>
				</td>
				</tr>
				<tr>
				  <td width="22%" valign="top">&nbsp;</td>
				  <td width="78%"> 
					<span class="vexpl"><b><?=gettext("Note: ");?></b><?=gettext("Multi-wan is not supported from this utility currently.");?></span>
				 </td>
				</tr>					
			</table>
</form>
<?php include("fend.inc"); ?>
