<?php
/* $Id$ */
/*
	system_hasync.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2012 Darren Embry <dse@webonastick.com>.
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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-hasync
##|*NAME=System: High Availability Sync
##|*DESCR=Allow access to the 'System: High Availability Sync' page.
##|*MATCH=system_hasync.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['hasync']))
	$config['hasync'] = array();

$a_hasync = &$config['hasync'];

$checkbox_names = array('pfsyncenabled',
			'synchronizeusers',
			'synchronizeauthservers',
			'synchronizecerts',
			'synchronizerules',
			'synchronizeschedules',
			'synchronizealiases',
			'synchronizenat',
			'synchronizeipsec',
			'synchronizeopenvpn',
			'synchronizedhcpd',
			'synchronizewol',
			'synchronizestaticroutes',
			'synchronizelb',
			'synchronizevirtualip',
			'synchronizetrafficshaper',
			'synchronizetrafficshaperlimiter',
			'synchronizetrafficshaperlayer7',
			'synchronizednsforwarder',
			'synchronizecaptiveportal');

if ($_POST) {
	$pconfig = $_POST;
	foreach ($checkbox_names as $name) {
		$a_hasync[$name] = $pconfig[$name] ? $pconfig[$name] : false;
	}
	$a_hasync['pfsyncpeerip']    = $pconfig['pfsyncpeerip'];
	$a_hasync['pfsyncinterface'] = $pconfig['pfsyncinterface'];
	$a_hasync['synchronizetoip'] = $pconfig['synchronizetoip'];
	$a_hasync['username']        = $pconfig['username'];
	$a_hasync['password']        = $pconfig['password'];
	write_config("Updated High Availability Sync configuration");
	interfaces_carp_setup();
	header("Location: system_hasync.php");
	exit();
}

foreach ($checkbox_names as $name) {
	$pconfig[$name] = $a_hasync[$name];
}
$pconfig['pfsyncpeerip']    = $a_hasync['pfsyncpeerip'];
$pconfig['pfsyncinterface'] = $a_hasync['pfsyncinterface'];
$pconfig['synchronizetoip'] = $a_hasync['synchronizetoip'];
$pconfig['username']        = $a_hasync['username'];
$pconfig['password']        = $a_hasync['password'];

$ifaces = get_configured_interface_with_descr();
$ifaces["lo0"] = "loopback";

$pgtitle = array(gettext("System"),gettext("High Availability Sync"));
$shortcut_section = "carp";
include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<form action="system_hasync.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="high availability sync">
<tr>
<td id="mainarea">
<div class="tabcont">

	<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
	<tr>
		<td colspan="2" class="listtopic">State Synchronization Settings (pfsync)</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize States</td>
		<td class="vtable">
			<input id='pfsyncenabled' type='checkbox' name='pfsyncenabled' value='on' <?php if ($pconfig['pfsyncenabled'] === "on") echo "checked='checked'"; ?> />
			<br />
			pfsync transfers state insertion, update, and deletion messages between firewalls. Each firewall sends these messages out via multicast on a specified interface, using the PFSYNC protocol (IP Protocol 240). It also listens on that interface for similar messages from other firewalls, and imports them into the local state table.<p>This setting should be enabled on all members of a failover group.</p><p>NOTE: Clicking save will force a configuration sync if it is enabled! (see Configuration Synchronization Settings below)</p>
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Interface</td>
		<td class="vtable">
			<select id='pfsyncinterface' name="pfsyncinterface">
			<?php foreach ($ifaces as $ifname => $iface) { ?>
				<?php $selected = ($pconfig['pfsyncinterface'] === $ifname) ? 'selected="selected"' : ''; ?>
				<option value="<?= htmlentities($ifname); ?>" <?= $selected ?>><?= htmlentities($iface); ?></option>
			<?php } ?>
			</select>
			<br />
			If Synchronize States is enabled, it will utilize this interface for communication.<br />
			<b>NOTE:</b>  We recommend setting this to a interface other than LAN!  A dedicated interface works the best.<br />
			<b>NOTE:</b>  You must define a IP on each machine participating in this failover group.<br />
			<b>NOTE:</b>  You must have an IP assigned to the interface on any participating sync nodes.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">pfsync Synchronize Peer IP</td>
		<td class="vtable">
			<input  id='pfsyncpeerip' name='pfsyncpeerip' class='formfld unknown' value='<?= htmlentities($pconfig['pfsyncpeerip']); ?>' />
			<br />
			Setting this option will force pfsync to synchronize its state table to this IP address.  The default is directed multicast.
		</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="2" class="listtopic">Configuration Synchronization Settings (XMLRPC Sync)</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Config to IP</td>
		<td class="vtable">
			<input  id='synchronizetoip' name='synchronizetoip' class='formfld unknown' value='<?= htmlentities($pconfig['synchronizetoip']); ?>' />
			<br />
			Enter the IP address of the firewall to which the selected configuration sections should be synchronized.<br />
			<br />
			NOTE: XMLRPC sync is currently only supported over connections using the same protocol and port as this system - make sure the remote system's port and protocol are set accordingly!<br />
			<br />
			NOTE: <b>Do not use the Synchronize Config to IP and password option on backup cluster members!</b>
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Remote System Username</td>
		<td class="vtable">
			<input  id='username' name='username' class='formfld unknown' value='<?= htmlentities($pconfig['username']); ?>' />
			<br />
			Enter the webConfigurator username of the system entered above for synchronizing your configuration.<br />
			<br />
			NOTE: <b>Do not use the Synchronize Config to IP and username option on backup cluster members!</b>
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Remote System Password</td>
		<td class="vtable">
			<input  id='password' type='password'  name='password' class='formfld pwd' value='<?= htmlentities($pconfig['password']); ?>' />
			<br />
			Enter the webConfigurator password of the system entered above for synchronizing your configuration.<br />
			<br />
			NOTE: <b>Do not use the Synchronize Config to IP and password option on backup cluster members!</b>
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Users and Groups</td>
		<td class="vtable">
			<input id='synchronizeusers' type='checkbox' name='synchronizeusers' value='on' <?php if ($pconfig['synchronizeusers'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the users and groups over to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Auth Servers</td>
		<td class="vtable">
			<input id='synchronizeauthservers' type='checkbox' name='synchronizeauthservers' value='on' <?php if ($pconfig['synchronizeauthservers'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the authentication servers (e.g. LDAP, RADIUS) over to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Certificates</td>
		<td class="vtable">
			<input id='synchronizecerts' type='checkbox' name='synchronizecerts' value='on' <?php if ($pconfig['synchronizecerts'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the Certificate Authorities, Certificates, and Certificate Revocation Lists over to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize rules</td>
		<td class="vtable">
			<input id='synchronizerules' type='checkbox' name='synchronizerules' value='on' <?php if ($pconfig['synchronizerules'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the firewall rules to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Firewall Schedules</td>
		<td class="vtable">
			<input id='synchronizeschedules' type='checkbox' name='synchronizeschedules' value='on' <?php if ($pconfig['synchronizeschedules'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the firewall schedules to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize aliases</td>
		<td class="vtable">
			<input id='synchronizealiases' type='checkbox' name='synchronizealiases' value='on' <?php if ($pconfig['synchronizealiases'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the aliases over to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize NAT</td>
		<td class="vtable">
			<input id='synchronizenat' type='checkbox' name='synchronizenat' value='on' <?php if ($pconfig['synchronizenat'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the NAT rules over to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize IPsec</td>
		<td class="vtable">
			<input id='synchronizeipsec' type='checkbox' name='synchronizeipsec' value='on' <?php if ($pconfig['synchronizeipsec'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the IPsec configuration to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize OpenVPN</td>
		<td class="vtable">
			<input id='synchronizeopenvpn' type='checkbox' name='synchronizeopenvpn' value='on' <?php if ($pconfig['synchronizeopenvpn'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the OpenVPN configuration to the other HA host when changes are made. Using this option implies "Synchronize Certificates" as they are required for OpenVPN.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize DHCPD</td>
		<td class="vtable">
			<input id='synchronizedhcpd' type='checkbox' name='synchronizedhcpd' value='on' <?php if ($pconfig['synchronizedhcpd'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the DHCP Server settings over to the other HA host when changes are made. This only applies to DHCP for IPv4.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Wake on LAN</td>
		<td class="vtable">
			<input id='synchronizewol' type='checkbox' name='synchronizewol' value='on' <?php if ($pconfig['synchronizewol'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the WoL configuration to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Static Routes</td>
		<td class="vtable">
			<input id='synchronizestaticroutes' type='checkbox' name='synchronizestaticroutes' value='on' <?php if ($pconfig['synchronizestaticroutes'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the Static Route configuration to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Load Balancer</td>
		<td class="vtable">
			<input id='synchronizelb' type='checkbox' name='synchronizelb' value='on' <?php if ($pconfig['synchronizelb'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the Load Balancer configuration to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Virtual IPs</td>
		<td class="vtable">
			<input id='synchronizevirtualip' type='checkbox' name='synchronizevirtualip' value='on' <?php if ($pconfig['synchronizevirtualip'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the CARP Virtual IPs to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize traffic shaper(queues)</td>
		<td class="vtable">
			<input id='synchronizetrafficshaper' type='checkbox' name='synchronizetrafficshaper' value='on' <?php if ($pconfig['synchronizetrafficshaper'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the traffic shaper configuration for queues to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize traffic shaper(limiter)</td>
		<td class="vtable">
			<input id='synchronizetrafficshaperlimiter' type='checkbox' name='synchronizetrafficshaperlimiter' value='on' <?php if ($pconfig['synchronizetrafficshaperlimiter'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the traffic shaper configuration for limiters to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize traffic shaper(layer7)</td>
		<td class="vtable">
			<input id='synchronizetrafficshaperlayer7' type='checkbox' name='synchronizetrafficshaperlayer7' value='on' <?php if ($pconfig['synchronizetrafficshaperlayer7'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the traffic shaper configuration for layer7 to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize DNS Forwarder</td>
		<td class="vtable">
			<input id='synchronizednsforwarder' type='checkbox' name='synchronizednsforwarder' value='on' <?php if ($pconfig['synchronizednsforwarder'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the DNS Forwarder configuration to the other HA host when changes are made.
		</td>
	</tr>
	<tr valign="top">
		<td width="22%" class="vncell">Synchronize Captive Portal</td>
		<td class="vtable">
			<input id='synchronizecaptiveportal' type='checkbox' name='synchronizecaptiveportal' value='on' <?php if ($pconfig['synchronizecaptiveportal'] === "on") echo "checked='checked'"; ?> />
			<br />
			When this option is enabled, this system will automatically sync the Captive Portal configuration to the other HA host when changes are made.
		</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%">
			<input name="id" type="hidden" value="0" />
			<input name="Submit" type="submit" class="formbtn" value="Save" />
			<input class="formbtn" type="button" value="Cancel" onclick="history.back()" />
		</td>
	</tr>
	</table>

</div>
</td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
