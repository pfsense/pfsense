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

$allowautocomplete = true;
$pgtitle = array(gettext("Diagnostics"), gettext("Ping"));
require_once("guiconfig.inc");


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

	$host = trim($_REQUEST['host']);
	$ipproto = $_REQUEST['ipproto'];
	if (($ipproto == "ipv4") && is_ipaddrv6($host))
		$input_errors[] = gettext("When using IPv4, the target host must be an IPv4 address or hostname.");
	if (($ipproto == "ipv6") && is_ipaddrv4($host))
		$input_errors[] = gettext("When using IPv6, the target host must be an IPv6 address or hostname.");

	if (!$input_errors) {
		$do_ping = true;
		$sourceip = $_REQUEST['sourceip'];
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
<tr><td>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="diag_ping.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
<tr>
	<td colspan="2" valign="top" class="listtopic"><?=gettext("Ping"); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncellreq"><?=gettext("Host"); ?></td>
	<td width="78%" class="vtable">
		<?=$mandfldhtml;?><input name="host" type="text" class="formfldunknown" id="host" size="20" value="<?=htmlspecialchars($host);?>"></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncellreq"><?=gettext("IP Protocol"); ?></td>
	<td width="78%" class="vtable">
		<select name="ipproto" class="formselect">
			<option value="ipv4" <?php if ($ipproto == "ipv4") echo 'selected="selected"' ?>>IPv4</option>
			<option value="ipv6" <?php if ($ipproto == "ipv6") echo 'selected="selected"' ?>>IPv6</option>
		</select>
	</td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?=gettext("Source Address"); ?></td>
	<td width="78%" class="vtable">
		<select name="sourceip" class="formselect">
			<option value="">Default</option>
		<?php $sourceips = get_possible_traffic_source_addresses(true);
			foreach ($sourceips as $sip):
				$selected = "";
				if (!link_interface_to_bridge($sip['value']) && ($sip['value'] == $sourceip))
					$selected = 'selected="selected"';
		?>
			<option value="<?=$sip['value'];?>" <?=$selected;?>>
				<?=htmlspecialchars($sip['name']);?>
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
		</select>
	</td>
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
		echo "<strong>" . gettext("Ping output") . ":</strong><br />";
		echo('<pre>');
		$ifscope = '';
		$command = "/sbin/ping";
		if ($ipproto == "ipv6") {
			$command .= "6";
			$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ipv6($sourceip);
			if (is_linklocal($ifaddr))
				$ifscope = get_ll_scope($ifaddr);
		} else {
			$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ip($sourceip);
		}
		if ($ifaddr && (is_ipaddr($host) || is_hostname($host))) {
			$srcip = "-S" . escapeshellarg($ifaddr);
			if (is_linklocal($host) && !strstr($host, "%") && !empty($ifscope))
				$host .= "%{$ifscope}";
		}

		$cmd = "{$command} {$srcip} -c" . escapeshellarg($count) . " " . escapeshellarg($host);
		//echo "Ping command: {$cmd}\n";
		system($cmd);
		echo('</pre>');
	}
	?>
	</td>
</tr>
<tr>
	<td width="22%" valign="top">&nbsp;</td>
	<td width="78%">&nbsp;</td>
</tr>
</table>
</form>
</td></tr>
</table>
<?php include("fend.inc"); ?>
