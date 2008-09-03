<?php
/* $Id$ */
/*
	interfaces_lan.php
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
##|*IDENT=page-interfaces-lan
##|*NAME=Interfaces: LAN page
##|*DESCR=Allow access to the 'Interfaces: LAN' page.
##|*MATCH=interfaces_lan.php*
##|-PRIV


require("guiconfig.inc");

$lancfg = &$config['interfaces']['lan'];

$pconfig['ipaddr'] = $lancfg['ipaddr'];
$pconfig['subnet'] = $lancfg['subnet'];

$pconfig['disableftpproxy'] = isset($lancfg['disableftpproxy']);

/* Wireless interface? */
if (isset($lancfg['wireless'])) {
	require("interfaces_wlan.inc");
	wireless_config_init($lancfg);
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;
	$changedesc = "LAN Interface: ";

	/* input validation */
	$reqdfields = explode(" ", "ipaddr subnet");
	$reqdfieldsn = explode(",", "IP address,Subnet bit count");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) {
		$input_errors[] = "A valid IP address must be specified.";
	}
	if (($_POST['subnet'] && !is_numeric($_POST['subnet']))) {
		$input_errors[] = "A valid subnet bit count must be specified.";
	}

	/* Wireless interface? */
	if (isset($lancfg['wireless'])) {
		$wi_input_errors = wireless_config_post($lancfg);
		if ($wi_input_errors) {
			$input_errors = array_merge($input_errors, $wi_input_errors);
		}
	}

	if (!$input_errors) {
		
		unset($lancfg['disableftpproxy']);
		
		/* per interface pftpx helper */
		if($_POST['disableftpproxy'] == "yes") {
			$lancfg['disableftpproxy'] = true;
			system_start_ftp_helpers();
		} else {			
			system_start_ftp_helpers();
		}			
		
		if (($lancfg['ipaddr'] != $_POST['ipaddr']) || ($lancfg['subnet'] != $_POST['subnet'])) {
			update_if_changed("IP Address", &$lancfg['ipaddr'], $_POST['ipaddr']);
			update_if_changed("subnet", &$lancfg['subnet'], $_POST['subnet']);
		}

		write_config($changedesc);

		touch($d_landirty_path);

		/* restart snmp so that it binds to correct address */
		services_snmpd_configure();

		if ($_POST['apply'] <> "") {
			
			unlink($d_landirty_path);
			
			$savemsg = "The changes have been applied.  You may need to correct your web browser's IP address.";
			
		}
	}
}

$pgtitle = array("Interfaces","LAN");
include("head.inc");

?>

<script language="JavaScript">
<!--
function enable_change(enable_over) {
	return;
	var endis;
	endis = enable_over;
	document.iform.ipaddr.disabled = endis;
	document.iform.subnet.disabled = endis;
}
// -->
</script>


<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form action="interfaces_lan.php" method="post" name="iform" id="iform">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_landirty_path)): ?><p>
<?php print_info_box_np("The LAN configuration has been changed.<p>You must apply the changes in order for them to take effect.<p>Don't forget to adjust the DHCP Server range if needed before applying.");?><br>
<?php endif; ?>
<?php if ($savemsg) print_info_box_np($savemsg); ?>
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
                  <td colspan="2" valign="top" class="listtopic">IP configuration</td>
		</tr>	      
                <tr>
                  <td width="22%" valign="top" class="vncellreq">IP address</td>
                  <td width="78%" class="vtable">
                    <input name="ipaddr" type="text" class="formfld unknown" id="hostname" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>">
                    /
                    <select name="subnet" class="formselect" id="subnet">
					<?php
					for ($i = 32; $i > 0; $i--) {
						if($i <> 31) {
							echo "<option value=\"{$i}\" ";
							if ($i == $pconfig['subnet']) echo "selected";
							echo ">" . $i . "</option>";
						}
					}
					?>
                    </select></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" height="16"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">FTP Helper</td>
                </tr>		
		<tr>
			<td width="22%" valign="top" class="vncell">FTP Helper</td>
			<td width="78%" class="vtable">
				<input name="disableftpproxy" type="checkbox" id="disableftpproxy" value="yes" <?php if ($pconfig['disableftpproxy']) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>Disable the userland FTP-Proxy application</strong>
				<br />
			</td>
		</tr>			
				<?php /* Wireless interface? */
				if (isset($lancfg['wireless']))
					wireless_config_print($lancfg);
				?>


                <tr>
                  <td colspan="2" valign="top" height="16"></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save">
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Warning:<br>
                    </strong></span>after you click &quot;Save&quot;, you will need
                    to do one or more of the following steps before you can
                    access your firewall again:
                    <ul>
                      <li>change the IP address of your computer</li>
                      <li>renew its DHCP lease</li>
                      <li>access the webConfigurator with the new IP address</li>
		      <li>be sure to add <a href="firewall_rules.php">firewall rules</a> to permit traffic through the interface.</li>
		      <li>You also need firewall rules for an interface in bridged mode as the firewall acts as a filtering bridge.</li>
                    </ul>
                    </span></td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>

<?php

if ($_POST['apply'] <> "") {

	ob_flush();
	flush();
	
	interfaces_lan_configure();
	
	reset_carp();
	
	/* sync filter configuration */
	filter_configure();

	/* set up static routes */
	system_routing_configure();
	
	if(file_exists($d_landirty_path))
		unlink($d_landirty_path);
	
}

?>
