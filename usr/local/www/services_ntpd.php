<?php
/*
	services_ntpd.php

	Copyright (C) 2012	Jim Pingle
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
	pfSense_MODULE:	ntpd
*/

##|+PRIV
##|*IDENT=page-services-ntpd
##|*NAME=Services: NTP
##|*DESCR=Allow access to the 'Services: NTP' page.
##|*MATCH=services_ntpd.php*
##|-PRIV

require("guiconfig.inc");

if (empty($config['ntpd']['interface']))
	if (empty($config['openntpd']['config']['interface'])) {
		$pconfig['interface'] = explode(",", $config['installedpackages']['openntpd']['config'][0]['interface']);
		unset($config['installedpackages']['openntpd']);
	} else
		$pconfig['interface'] = array();
else
	$pconfig['interface'] = explode(",", $config['ntpd']['interface']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface");
	$reqdfieldsn = array(gettext("Interface"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (!$input_errors) {
		$config['ntpd']['interface'] = implode(",", $_POST['interface']);

		write_config("Updated NTP Server Settings");

		$retval = 0;
		$retval = system_ntp_configure();
		$savemsg = get_std_save_message($retval);

	}
}

$pgtitle = array(gettext("Services"),gettext("NTP"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_ntpd.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td><div id="mainarea">
<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
<tr>
	<td colspan="2" valign="top" class="listtopic"><?=gettext("NTP Server Configuration"); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncellreq">Interface(s)</td>
	<td width="78%" class="vtable">
<?php
	$interfaces = get_configured_interface_with_descr();
	$carplist = get_configured_carp_interface_list();
	foreach ($carplist as $cif => $carpip)
		$interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
	$aliaslist = get_configured_ip_aliases_list();
	foreach ($aliaslist as $aliasip => $aliasif)
		$interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
	$size = (count($interfaces) < 10) ? count($interfaces) : 10;
?>
	<select id="interface" name="interface[]" multiple="true" class="formselect" size="<?php echo $size; ?>">
<?php	
	foreach ($interfaces as $iface => $ifacename) {
		if (!is_ipaddr(get_interface_ip($iface)) && !is_ipaddr($iface))
			continue;
		echo "<option value='{$iface}'";
		if (in_array($iface, $pconfig['interface']))
			echo "selected";
		echo ">{$ifacename}</option>\n";
	} ?>
	</select>
	<br/>Interfaces without an IP address will not be shown.
	<br/>
	<br/>Selecting no interfaces will listen on all interfaces with a wildcard.
	<br/>Selecting all interfaces will explicitly listen on only the interfaces/IPs specified.
	</td>
</tr>
<tr>
	<td width="22%" valign="top">&nbsp;</td>
	<td width="78%">
	<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onclick="enable_change(true)">
	</td>
</tr>
</table>
</div></td></tr></table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
