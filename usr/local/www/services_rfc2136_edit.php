<?php
/* $Id$ */
/*
	Copyright (C) 2008 Ermal Luçi
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
	pfSense_MODULE:	dnsupdate
*/

require("guiconfig.inc");

if (!is_array($config['dnsupdates']['dnsupdate'])) {
	$config['dnsupdates']['dnsupdate'] = array();
}

$a_rfc2136 = &$config['dnsupdates']['dnsupdate'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && isset($a_rfc2136[$id])) {
	$pconfig['enable'] = isset($a_rfc2136[$id]['enable']);
	$pconfig['host'] = $a_rfc2136[$id]['host'];
	$pconfig['ttl'] = $a_rfc2136[$id]['ttl'];
	if (!$pconfig['ttl'])
		$pconfig['ttl'] = 60;
	$pconfig['keydata'] = $a_rfc2136[$id]['keydata'];
	$pconfig['keyname'] = $a_rfc2136[$id]['keyname'];
	$pconfig['keytype'] = $a_rfc2136[$id]['keytype'];
	if (!$pconfig['keytype'])
		$pconfig['keytype'] = "zone";
	$pconfig['server'] = $a_rfc2136[$id]['server'];
	$pconfig['interface'] = $a_rfc2136[$id]['interface'];
	$pconfig['usetcp'] = isset($a_rfc2136[$id]['usetcp']);
	$pconfig['usepublicip'] = isset($a_rfc2136[$id]['usepublicip']);
	$pconfig['descr'] = $a_rfc2136[$id]['descr'];

}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array_merge($reqdfields, explode(" ", "host ttl keyname keydata"));
	$reqdfieldsn = array_merge($reqdfieldsn, array(gettext("Hostname"), gettext("TTL"), gettext("Key name"), gettext("Key")));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['host'] && !is_domain($_POST['host'])))  
		$input_errors[] = gettext("The DNS update host name contains invalid characters.");
	if (($_POST['ttl'] && !is_numericint($_POST['ttl']))) 
		$input_errors[] = gettext("The DNS update TTL must be an integer.");
	if (($_POST['keyname'] && !is_domain($_POST['keyname'])))
		$input_errors[] = gettext("The DNS update key name contains invalid characters.");

	if (!$input_errors) {
		$rfc2136 = array();
		$rfc2136['enable'] = $_POST['enable'] ? true : false;
		$rfc2136['host'] = $_POST['host'];
		$rfc2136['ttl'] = $_POST['ttl'];
		$rfc2136['keyname'] = $_POST['keyname'];
		$rfc2136['keytype'] = $_POST['keytype'];
		$rfc2136['keydata'] = $_POST['keydata'];
		$rfc2136['server'] = $_POST['server'];
		$rfc2136['usetcp'] = $_POST['usetcp'] ? true : false;
		$rfc2136['usepublicip'] = $_POST['usepublicip'] ? true : false;
		$rfc2136['interface'] = $_POST['interface'];
		$rfc2136['descr'] = $_POST['descr'];

		if (isset($id) && $a_rfc2136[$id])
			$a_rfc2136[$id] = $rfc2136;
		else
			$a_rfc2136[] = $rfc2136;

		write_config(gettext("New/Edited RFC2136 dnsupdate entry was posted."));

		if ($_POST['Submit'] == gettext("Save & Force Update"))
			$retval = services_dnsupdate_process("", $rfc2136['host'], true);
		else
			$retval = services_dnsupdate_process();

		header("Location: services_rfc2136.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("RFC 2136 client"), gettext("Edit"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="services_rfc2136_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="rfs2136 edit">
			  	<tr>
                  <td colspan="2" valign="top" class="optsect_t">
				  <table border="0" cellspacing="0" cellpadding="0" width="100%" summary="title">
				  	<tr><td class="optsect_s"><strong><?=gettext("RFC 2136 client");?></strong></td></tr>
				  </table>
				  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Enable");?></td>
				  <td width="78%" class="vtable">
				    <input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\""; ?> />
				  </td>
                </tr>
				<tr>
				   <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface to monitor");?></td>  
				   <td width="78%" class="vtable">
				   <select name="interface" class="formselect" id="interface">
				   <?php $iflist = get_configured_interface_with_descr();
				   		foreach ($iflist as $if => $ifdesc):?>
							<option value="<?=$if;?>" <?php if ($pconfig['interface'] == $if) echo "selected=\"selected\"";?>><?=$ifdesc;?></option>
					<?php endforeach; ?>
					</select>
					</td>
				</tr>	
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname");?></td>
                  <td width="78%" class="vtable">
                    <input name="host" type="text" class="formfld unknown" id="host" size="30" value="<?=htmlspecialchars($pconfig['host']);?>" />
			<br /><span>Fully qualified hostname of the host to be updated</span>
                  </td>
				</tr>
                <tr>
                  <td valign="top" class="vncellreq"><?=gettext("TTL"); ?></td>
                  <td class="vtable">
                    <input name="ttl" type="text" class="formfld unknown" id="ttl" size="6" value="<?=htmlspecialchars($pconfig['ttl']);?>" />
                  <?=gettext("seconds");?></td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq"><?=gettext("Key name");?></td>
                  <td class="vtable">
                    <input name="keyname" type="text" class="formfld unknown" id="keyname" size="30" value="<?=htmlspecialchars($pconfig['keyname']);?>" />
                    <br />
                    <?=gettext("This must match the setting on the DNS server.");?></td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq"><?=gettext("Key type");?> </td>
                  <td class="vtable">
				  <input name="keytype" type="radio" value="zone" <?php if ($pconfig['keytype'] == "zone") echo "checked=\"checked\""; ?> /> <?=gettext("Zone");?> &nbsp;
                  <input name="keytype" type="radio" value="host" <?php if ($pconfig['keytype'] == "host") echo "checked=\"checked\""; ?> /> <?=gettext("Host");?> &nbsp;
                  <input name="keytype" type="radio" value="user" <?php if ($pconfig['keytype'] == "user") echo "checked=\"checked\""; ?> /><?=gettext(" User");?>
				</td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq"><?=gettext("Key");?></td>
                  <td class="vtable">
                    <input name="keydata" type="text" class="formfld unknown" id="keydata" size="70" value="<?=htmlspecialchars($pconfig['keydata']);?>" />
                    <br />
                    <?=gettext("Paste an HMAC-MD5 key here.");?></td>
				</tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Server");?></td>
                  <td width="78%" class="vtable">
                    <input name="server" type="text" class="formfld" id="server" size="30" value="<?=htmlspecialchars($pconfig['server'])?>" />
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Protocol");?></td>
                  <td width="78%" class="vtable">
                    <input name="usetcp" type="checkbox" id="usetcp" value="<?=gettext("yes");?>" <?php if ($pconfig['usetcp']) echo "checked=\"checked\""; ?> />
                    <strong><?=gettext("Use TCP instead of UDP");?></strong></td>
				</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Use Public IP");?></td>
			<td width="78%" class="vtable">
				<input name="usepublicip" type="checkbox" id="usepublicip" value="<?=gettext("yes");?>" <?php if ($pconfig['usepublicip']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("If the interface IP is private, attempt to fetch and use the public IP instead.");?></strong>
			</td>
		</tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="60" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
					<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onclick="enable_change(true)" />
					<a href="services_rfc2136.php"><input name="Cancel" type="button" class="formbtn" value="<?=gettext("Cancel");?>" /></a>
					<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save &amp; Force Update");?>" onclick="enable_change(true)" />
					<?php if (isset($id) && $a_rfc2136[$id]): ?>
						<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
					<?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br />
                    </strong></span><?php printf(gettext("You must configure a DNS server in %sSystem: " .
                    "General setup %sor allow the DNS server list to be overridden " .
                    "by DHCP/PPP on WAN for dynamic DNS updates to work."),'<a href="system.php">', '</a>');?></span></td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
