<?php
/*
	diag_ping.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2005 Bob Zoller (bob@kludgebox.com) and Manuel Kasper <mk@neon1.net>.
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
	pfSense_BUILDER_BINARIES:	/sbin/ping /sbin/ping6
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-diagnostics-ping
##|*NAME=Diagnostics: Ping page
##|*DESCR=Allow access to the 'Diagnostics: Ping' page.
##|*MATCH=diag_ping.php*
##|-PRIV

$pgtitle = array(gettext("Diagnostics"), gettext("Ping"));
require("guiconfig.inc");

define('MAX_COUNT', 10);
define('DEFAULT_COUNT', 3);

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);
	unset($do_ping);

	/* input validation */
	$reqdfields = explode(" ", "host count");
	$reqdfieldsn = array(gettext("Host"),gettext("Count"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_REQUEST['count'] < 1) || ($_REQUEST['count'] > MAX_COUNT)) {
		$input_errors[] = sprintf(gettext("Count must be between 1 and %s"), MAX_COUNT);
	}

	if (!$input_errors) {
		$do_ping = true;
		$host = $_REQUEST['host'];
		$interface = $_REQUEST['interface'];
		$count = $_POST['count'];
		if (preg_match('/[^0-9]/', $count) )
			$count = DEFAULT_COUNT;
	}
}
if (!isset($do_ping)) {
	$do_ping = false;
	$host = '';
	$count = DEFAULT_COUNT;
}

include("head.inc"); ?>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
        <tr>
                <td>
<?php if ($input_errors) print_input_errors($input_errors); ?>
			<form action="diag_ping.php" method="post" name="iform" id="iform">
			  <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Ping"); ?></td>
				</tr>
                <tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("Host"); ?></td>
				  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="host" type="text" class="formfld" id="host" size="20" value="<?=htmlspecialchars($host);?>"></td>
				</tr>
				<tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface"); ?></td>
				  <td width="78%" class="vtable">
				  <select name="interface" class="formfld">
                      <?php $interfaces = get_configured_interface_with_descr();
					  foreach ($interfaces as $iface => $ifacename): ?>
                      <option value="<?=$iface;?>" <?php if (!link_interface_to_bridge($iface) && $iface == $interface) echo "selected"; ?>> 
                      <?=htmlspecialchars($ifacename);?>
                      </option>
                      <?php endforeach; ?>
                    </select>
				  </td>
				</tr>
				<tr>
				  <td width="22%" valign="top" class="vncellreq"><?= gettext("Count"); ?></td>
				  <td width="78%" class="vtable">
					<select name="count" class="formfld" id="count">
					<?php for ($i = 1; $i <= MAX_COUNT; $i++): ?>
					<option value="<?=$i;?>" <?php if ($i == $count) echo "selected"; ?>><?=$i;?></option>
					<?php endfor; ?>
					</select></td>
				</tr>
				<tr>
				  <td width="22%" valign="top">&nbsp;</td>
				  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Ping"); ?>">
				</td>
				</tr>
				<tr>
				<td valign="top" colspan="2">
				<?php if ($do_ping) {
					echo "<font face='terminal' size='2'>";
					echo "<strong>" . gettext("Ping output") . ":</strong><br>";
					echo('<pre>');
					$ifaddr = get_interface_ip($interface);
					if ($ifaddr)
						system("/sbin/ping -S$ifaddr -c$count " . escapeshellarg($host));
					else
						system("/sbin/ping -c$count " . escapeshellarg($host));
					$ifaddr = get_interface_ipv6($interface);
					if ($ifaddr)
						system("/sbin/ping6 -S$ifaddr -c$count " . escapeshellarg($host));
					else
						system("/sbin/ping6 -c$count " . escapeshellarg($host));
					
					echo('</pre>');
				}
				?>
				</td>
				</tr>
				<tr>
				  <td width="22%" valign="top">&nbsp;</td>
				  <td width="78%"> 
				 </td>
				</tr>			
			</table>
		</form>
	</td>
</tr>
</table>
<?php include("fend.inc"); ?>

