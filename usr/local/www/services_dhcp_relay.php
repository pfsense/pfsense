<?php
/*
	services_dhcp_relay.php

	Copyright (C) 2003-2004 Justin Ellison <justin@techadvise.com>.
	Copyright (C) 2010 	Ermal Luçi
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
	pfSense_MODULE:	dhcprelay
*/

##|+PRIV
##|*IDENT=page-services-dhcprelay
##|*NAME=Services: DHCP Relay page
##|*DESCR=Allow access to the 'Services: DHCP Relay' page.
##|*MATCH=services_dhcp_relay.php*
##|-PRIV

require("guiconfig.inc");

$pconfig['enable'] = isset($config['dhcrelay']['enable']);
if (empty($config['dhcrelay']['interface']))
	$pconfig['interface'] = array();
else
	$pconfig['interface'] = explode(",", $config['dhcrelay']['interface']);
$pconfig['server'] = $config['dhcrelay']['server'];
$pconfig['agentoption'] = isset($config['dhcrelay']['agentoption']);

$iflist = get_configured_interface_with_descr();

/*   set the enabled flag which will tell us if DHCP server is enabled
 *   on any interface.   We will use this to disable dhcp-relay since
 *   the two are not compatible with each other.
 */
$dhcpd_enabled = false;
if (is_array($config['dhcpd'])) {
	foreach($config['dhcpd'] as $dhcp) 
		if (isset($dhcp['enable'])) 
			$dhcpd_enabled = true;
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "server interface");
		$reqdfieldsn = array(gettext("Destination Server"), gettext("Interface"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if ($_POST['server']) {
			$checksrv = explode(",", $_POST['server']);
			foreach ($checksrv as $srv) {
				if (!is_ipaddr($srv))
					$input_errors[] = gettext("A valid Destination Server IP address must be specified.");
			}
		}
	}

	if (!$input_errors) {
		$config['dhcrelay']['enable'] = $_POST['enable'] ? true : false;
		$config['dhcrelay']['interface'] = implode(",", $_POST['interface']);
		$config['dhcrelay']['agentoption'] = $_POST['agentoption'] ? true : false;
		$config['dhcrelay']['server'] = $_POST['server'];

		write_config();

		$retval = 0;
		$retval = services_dhcrelay_configure();
		$savemsg = get_std_save_message($retval);

	}
}

$closehead = false;
$pgtitle = array(gettext("Services"),gettext("DHCP Relay"));
$shortcut_section = "dhcp";
include("head.inc");

?>

<script type="text/javascript">
//<![CDATA[
function enable_change(enable_over) {
	if (document.iform.enable.checked || enable_over) {
		document.iform.server.disabled = 0;
		document.iform.interface.disabled = 0;
		document.iform.agentoption.disabled = 0;
	} else {
		document.iform.server.disabled = 1;
		document.iform.interface.disabled = 1;
		document.iform.agentoption.disabled = 1;
	}
}
//]]>
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_dhcp_relay.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="dhcp relay">
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
		<tr>
<?php
	if ($dhcpd_enabled) {
		echo "<td>DHCP Server is currently enabled. Cannot enable the DHCP Relay service while the DHCP Server is enabled on any interface.";
			echo "</td></tr></table></div></td></tr></table></form></body>";
			echo "</html>";
			include("fend.inc"); 
			exit;
		}
?>

			<td colspan="2" valign="top" class="listtopic"><?=gettext("DHCP Relay configuration"); ?></td>
		</tr>
		<tr>
                        <td width="22%" valign="top" class="vncellreq">Enable</td>
                        <td width="78%" class="vtable">
			<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\""; ?> onclick="enable_change(false)" />
                          <strong><?php printf(gettext("Enable DHCP relay on interface"));?></strong>
			</td>
		</tr>
		<tr>
                        <td width="22%" valign="top" class="vncellreq">Interface(s)</td>
                        <td width="78%" class="vtable">
				<select id="interface" name="interface[]" multiple="multiple" class="formselect" size="3">
			<?php
                                foreach ($iflist as $ifent => $ifdesc) {
					if (!is_ipaddr(get_interface_ip($ifent)))
						continue;
					echo "<option value=\"{$ifent}\"";
					if (in_array($ifent, $pconfig['interface']))
						echo " selected=\"selected\"";
					echo ">{$ifdesc}</option>\n";
				}
			?>
                                </select>
				<br />Interfaces without an IP address will not be shown.
			</td>
		</tr>
		<tr>
	              <td width="22%" valign="top" class="vtable">&nbsp;</td>
                      <td width="78%" class="vtable">
<input name="agentoption" type="checkbox" value="yes" <?php if ($pconfig['agentoption']) echo "checked=\"checked\""; ?> />
                      <strong><?=gettext("Append circuit ID and agent ID to requests"); ?></strong><br />
                      <?php printf(gettext("If this is checked, the DHCP relay will append the circuit ID (%s interface number) and the agent ID to the DHCP request."), $g['product_name']); ?></td>
		</tr>
		<tr>
                        <td width="22%" valign="top" class="vncellreq"><?=gettext("Destination server");?></td>
                        <td width="78%" class="vtable">
                          <input name="server" type="text" class="formfld unknown" id="server" size="20" value="<?=htmlspecialchars($pconfig['server']);?>" />
                          <br />
			  <?=gettext("This is the IP address of the server to which DHCP requests are relayed. You can enter multiple server IP addresses, separated by commas. Select \"Proxy requests to DHCP server on WAN subnet\" to relay DHCP packets to the server that was used on the WAN interface.");?>
                        </td>
		</tr>
		<tr>
                        <td width="22%" valign="top">&nbsp;</td>
                        <td width="78%">
                          <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onclick="enable_change(true)" />
                        </td>
		</tr>
	</table>
	</div>
    </td>
  </tr>
</table>
</form>
<script type="text/javascript">
//<![CDATA[
enable_change(false);
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
