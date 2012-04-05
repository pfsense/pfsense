<?php
/* $Id$ */
/*
	services_unbound_advanced.php
	part of the pfSense project (http://www.pfsense.com)
	Copyright (C) 2011	Warren Baker (warren@pfsense.org)
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
	pfSense_MODULE:	dnscache
*/

##|+PRIV
##|*IDENT=page-services-unbound
##|*NAME=Services: Unbound DNS page
##|*DESCR=Allow access to the 'Services: Unbound DNS' page.
##|*MATCH=services_unbound.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("unbound.inc");

if (!is_array($config['unbound']['hosts']))
	$config['unbound']['hosts'] = array();

if (!is_array($config['unbound']['domainoverrides']))
	$config['unbound']['domainoverrides'] = array();

if ($_POST) {

	unset($input_errors);

	$config['unbound']['enable'] = ($_POST['enable']) ? true : false;
	$config['unbound']['custom_options'] = str_replace("\r\n", "\n", $_POST['custom_options']);

	if (!$input_errors) {
		write_config("Unbound DNS configured.");

		$retval = 0;
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);

		// Relaod filter (we might need to sync to CARP hosts)
		filter_configure();
	}
} else {
	$pconfig = array();
	$pconfig['websec'] = $settings['websec'];
	$pconfig['active_interface'] = $settings['active_interface'];
	$pconfig['allow_interface'] = $settings['allow_interface'];
	$pconfig['sslscan'] = $settings['sslscan'];
	$pconfig['admin_email'] = $settings['admin_email'];
	$pconfig['caref'] = $settings['caref'];
	$pconfig['certref'] = $settings['certref'];
	$pconfig['crlref'] = $settings['crlref'];
	$pconfig['fwalias'] = $settings['fwalias'];
	$pconfig['auth_prompt'] = $settings['auth_prompt'];
	$pconfig['auth_ttl'] = $settings['auth_ttl'];
}

$pgtitle = array(gettext("Services"),gettext("Unbound DNS"));
include_once("head.inc");

?>

<script language="JavaScript">
<!--
function enable_change(enable_over) {
	var endis;
	endis = !(document.iform.enable.checked || enable_over);
	document.iform.regdhcp.disabled = endis;
	document.iform.regdhcpstatic.disabled = endis;
	document.iform.dhcpfirst.disabled = endis;
}
function show_advanced_dns() {
	document.getElementById("showadvbox").innerHTML='';
	aodiv = document.getElementById('showadv');
	aodiv.style.display = "block";
}
//-->
</script>
	
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_dnsmasq.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('hosts')): ?><p>
<?php print_info_box_np(gettext("The configuration for Unbound DNS, has been changed") . ".<br>" . gettext("You must apply the changes in order for them to take effect."));?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="6" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("General settings"), true, "services_unbound.php");
	$tab_array[] = array(gettext("Advanced settings"), false, "services_unbound_advanced.php");
	display_top_tabs($tab_array, true);
?>
</td></tr>
</table>
<div id="mainarea">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?=gettext("General Unbound DNS Options");?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Enable");?></td>
		<td width="78%" class="vtable"><p>
			<input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable'] == "yes") echo "checked";?> onClick="enable_change(false)">
			<strong><?=gettext("Enable Unbound DNS");?><br>
			</strong></p></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Network interfaces");?></td>
		<td width="78%" class="vtable">
			<select name="active_interface[]" id="active_interface" multiple="true" size="3">
			<?php $iflist = get_configured_interface_with_descr();
				$active_iface = explode(",", $pconfig['active_interface']);
				$iflist['localhost'] = "Localhost";
				foreach ($iflist as $iface => $ifdescr) {
					echo "<option value='{$iface}' ";
					if (in_array($iface, $active_iface))
						echo "selected";
					echo ">{$ifdescr}</option>\n";
				}
			?>
			</select>
			<br/><span class="vexpl">
					<?=gettext("The Unbound DNS Server will listen on the selected interfaces. To add an interface click inside the interface box and select the interface from the drop down.");?> <br/>
				</span>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Query interfaces");?></td>
		<td width="78%" class="vtable">
			<select name="query_interface[]" id="query_interface" multiple="true" size="3">
			<?php $iflist = get_configured_interface_with_descr();
				$active_iface = explode(",", $pconfig['query_interface']);
				$iflist['localhost'] = "Localhost";
				foreach ($iflist as $iface => $ifdescr) {
					echo "<option value='{$iface}' ";
					if (in_array($iface, $active_iface))
						echo "selected";
					echo ">{$ifdescr}</option>\n";
				}
			?>
			</select>
			<br/><span class="vexpl">
					<?=gettext("Utilize different network interface(s) that Unbound DNS server will use to send queries to authoritative servers and receive their replies.");?> <br/>
				</span>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("DNSSEC");?></td>
		<td width="78%" class="vtable"><p>
			<input name="dnssec" type="checkbox" id="enable" value="yes" <?php if ($pconfig['dnssec'] == "yes") echo "checked";?>/>
			<strong><?=gettext("Enable DNSSEC Support");?><br>
			</strong></p></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Forwarding");?></td>
		<td width="78%" class="vtable"><p>
			<input name="dnssec" type="checkbox" id="enable" value="yes" <?php if ($pconfig['dnssec'] == "yes") echo "checked";?>/>
			<strong><?=gettext("Enable Forwarding Mode");?><br>
			</strong></p></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Static DHCP");?></td>
		<td width="78%" class="vtable"><p>
			<input name="dnssec" type="checkbox" id="enable" value="yes" <?php if ($pconfig['dnssec'] == "yes") echo "checked";?>/>
			<strong><?=gettext("Enable Forwarding Mode");?><br>
			</strong></p></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Forwarding");?></td>
		<td width="78%" class="vtable"><p>
			<input name="dnssec" type="checkbox" id="enable" value="yes" <?php if ($pconfig['dnssec'] == "yes") echo "checked";?>/>
			<strong><?=gettext("Enable Forwarding Mode");?><br>
			</strong></p></td>
	</tr>	
<tr>
	<td width="22%" valign="top" class="vncell"><?=gettext("Allow access from selected network interface");?></td>
	<td width="78%" class="vtable">
		<input type="checkbox" name="allow_interface" id="allow_interface" class="formfld unknown" value="on" <?php if (isset($pconfig['allow_interface'])) echo "checked"; ?> />
	<br/><span class="vexpl">
		<?=gettext("If this field is enabled, the network connected to the interface selected in the 'Network interface' field will be allowed to use the proxy, therefore there will be no need to add the interface's subnet to the list of ");?>  <a href='web_settings_access.php'>Access Control Lists</a>. It will automatically be added. <br/>
	</span>
	</td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?=gettext("Administrator email");?></td>
	<td width="78%" class="vtable">
		<input name="admin_email" id="admin_email" class="formfld unknown" size="60" value="<?php if ($pconfig['admin_email']) echo $pconfig['admin_email']; else echo "admin@localhost"; ?>" />
	<br/><span class="vexpl">
		<?=gettext("This is the email address displayed in error messages to the users.");?> <br/>
	</span>
	</td>
</tr>
<tr>
	<td colspan="2" class="listtopic">HTTPS MITM setttings<br></td>
</tr>
<tr class="on_off">
  <td width="30%" valign="top" class="vncellreq">HTTPS Scanning</td>
  <td width="70%" class="vtable">
    <input type="checkbox" id="on_off" name="sslscan"<?php if(isset($pconfig['sslscan'])) echo " CHECKED"; ?>/><br />
	Enable this to perform content scanning on HTTPS web traffic.
  </td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?=gettext("Certificate Authority"); ?></td>
	<td width="78%" class="vtable">
		<div>
		<select id="caref" title="Select Certificate Authority" name="caref" size="5" style="width:150px;" class="chzn-select">
	<?php
	      if (is_array($config['ca']) && count($config['ca']) > 0):
			echo "<option value=''>None</option>\n";
			foreach ($config['ca'] as $ca):
				$selected = "";
				if ($pconfig['caref'] == $ca['refid'])
					$selected = "selected";
	?>
		<option value="<?=$ca['refid'];?>" <?=$selected;?>><?=$ca['descr'];?></option>
			<?php endforeach; ?>
		</select>
		</div>
	<?php else: ?>
		<b>No Certificate Authorities defined.</b> <br/>Create one under <a href="system_camanager.php">System &gt; Cert Manager</a>.
	<?php endif; ?>
	</td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?=gettext("Certificate Revocation List"); ?></td>
	<td width="78%" class="vtable">
	<?php if (is_array($config['crl']) && count($config['crl']) > 0): ?>
		<select id="crlref" title="Select Certificate Revocation List" name="crlref" size="5" style="width:150px;" class="chzn-select">
		<option value="">None</option>
	<?php
		foreach ($config['crl'] as $crl):
			$selected = "";
			$caname = "";
			$ca = lookup_ca($crl['caref']);
			if ($ca) {
				$caname = " (CA: {$ca['descr']})";
				if ($pconfig['crlref'] == $crl['refid'])
					$selected = "selected";
			}
	?>
		<option value="<?=$crl['refid'];?>" <?=$selected;?>><?php echo "{$crl['descr']} {$caname}";?></option>
		<?php endforeach; ?>
		</select>
	<?php else: ?>
		<b>No Certificate Revocation Lists (CRLs) defined.</b> <br/>Create one under <a href="system_crlmanager.php">System &gt; Cert Manager</a>.
	<?php endif; ?>
	</td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?=gettext("Server Certificate"); ?></td>
	<td width="78%" class="vtable">
	<?php if (is_array($config['cert']) && count($config['cert']) > 0): ?>
		<select id="certref" title="Select Server Certificate" name="certref" size="5" style="width:200px;" class="chzn-select">
	<?php
		foreach ($config['cert'] as $cert):
			$selected = "";
			$caname = "";
			$inuse = "";
			$revoked = "";
			$ca = lookup_ca($cert['caref']);
			if ($ca)
				$caname = " (CA: {$ca['descr']})";
			if ($pconfig['certref'] == $cert['refid'])
				$selected = "selected";
			if (cert_in_use($cert['refid']))
				$inuse = " *In Use";
			if (is_cert_revoked($cert))
				$revoked = " *Revoked";
	?>
		<option value="<?=$cert['refid'];?>" <?=$selected;?>><?php echo "{$cert['descr']} {$caname} {$inuse} {$revoked}";?></option>
		<?php endforeach; ?>
		</select>
	<?php else: ?>
		<b>No Certificates defined.</b> <br/>Create one under <a href="system_certmanager.php">System &gt; Cert Manager</a>.
	<?php endif; ?>
	</td>
</tr>
<tr>
	<td colspan="2" class="listtopic">Authentication Settings<br></td>
</tr>
<tr>
        <td width="22%" valign="top" class="vncell"><?=gettext("Authentication Server"); ?></td>
        <td width="78%" class="vtable">
			<div>
                <select name='auth_method[]' id='auth_method' multiple="true" data-placeholder="Select the Authentication types" size="3" style="width:150px;" class="chzn-select">
                <?php
                         $auth_servers = auth_get_authserver_list();
                         foreach ($auth_servers as $auth_server):
                                $selected = "";
                                if ($auth_server['name'] == $pconfig['auth_method'])
                                        $selected = "selected";
                                if (!isset($pconfig['auth_method']) && $auth_server['name'] == "none")
                                        $selected = "selected";

                ?>
                        <option value="<?=$auth_server['name'];?>" <?=$selected;?>><?=$auth_server['name'];?></option>
                        <?php   endforeach; ?>
                </select>
			</div>
        </td>
</tr>
<tr >
        <td width="22%" class="vncell">Authentication prompt</td>
        <td class="vtable">
                <input id="auth_prompt" name="auth_prompt" value="<?=$pconfig['auth_prompt'];?>" size="50" class="formfld unknown">
                <br/>
                This string will be displayed at the top of the authentication request window.
        </td>
</tr>
<tr valign="top">
        <td width="22%" class="vncell">Authentication TTL</td>
        <td class="vtable">
                <input id="auth_ttl" name="auth_ttl" value="<?=$pconfig['auth_ttl'];?>" class="formfld unknown">
                <br/>
                This specifies for how long (in minutes) the server assumes an externally validated username and password combination is valid (Time To Live). When the TTL expires, the user will be prompted f
or credentials again.
        </td>
</tr>
<tr>
	<td>&nbsp;</td>
</tr>
<tr>
	<td width="22%"></td>
	<td width="78%">
		<input type="submit" name="Save" class="formbtn" id="save" value="Save" />
	</td>
</tr>
</table>
</div>
</td></tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>