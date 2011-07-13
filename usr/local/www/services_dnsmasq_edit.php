<?php 
/* $Id$ */
/*
	services_dnsmasq_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2004 Bob Zoller <bob@kludgebox.com> and Manuel Kasper <mk@neon1.net>.
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
	pfSense_MODULE:	dnsforwarder
*/

##|+PRIV
##|*IDENT=page-services-dnsforwarder-edithost
##|*NAME=Services: DNS Forwarder: Edit host page
##|*DESCR=Allow access to the 'Services: DNS Forwarder: Edit host' page.
##|*MATCH=services_dnsmasq_edit.php*
##|-PRIV

function hostcmp($a, $b) {
	return strcasecmp($a['host'], $b['host']);
}

function hosts_sort() {
        global $g, $config;

        if (!is_array($config['dnsmasq']['hosts']))
                return;

        usort($config['dnsmasq']['hosts'], "hostcmp");
}

require("guiconfig.inc");

if (!is_array($config['dnsmasq']['hosts'])) 
	$config['dnsmasq']['hosts'] = array();

$a_hosts = &$config['dnsmasq']['hosts'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_hosts[$id]) {
	$pconfig['host'] = $a_hosts[$id]['host'];
	$pconfig['domain'] = $a_hosts[$id]['domain'];
	$pconfig['ip'] = $a_hosts[$id]['ip'];
	$pconfig['descr'] = $a_hosts[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "domain ip");
	$reqdfieldsn = array(gettext("Domain"),gettext("IP address"));
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['host'] && !is_hostname($_POST['host']))) 
		$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");

	if (($_POST['domain'] && !is_domain($_POST['domain']))) 
		$input_errors[] = gettext("A valid domain must be specified.");
		
	if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) 
		$input_errors[] = gettext("A valid IP address must be specified.");

	/* check for overlaps */
	foreach ($a_hosts as $hostent) {
		if (isset($id) && ($a_hosts[$id]) && ($a_hosts[$id] === $hostent))
			continue;

		if (($hostent['host'] == $_POST['host']) && ($hostent['domain'] == $_POST['domain'])
			&& ((is_ipaddrv4($hostent['ip']) && is_ipaddrv4($_POST['ip'])) || (is_ipaddrv6($hostent['ip']) && is_ipaddrv6($_POST['ip'])))) {
			$input_errors[] = gettext("This host/domain already exists.");
			break;
		}
	}

	if (!$input_errors) {
		$hostent = array();
		$hostent['host'] = $_POST['host'];
		$hostent['domain'] = $_POST['domain'];
		$hostent['ip'] = $_POST['ip'];
		$hostent['descr'] = $_POST['descr'];

		if (isset($id) && $a_hosts[$id])
			$a_hosts[$id] = $hostent;
		else
			$a_hosts[] = $hostent;
		hosts_sort();
		
		mark_subsystem_dirty('hosts');
		
		write_config();
		
		header("Location: services_dnsmasq.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("DNS forwarder"),gettext("Edit host"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
        <form action="services_dnsmasq_edit.php" method="post" name="iform" id="iform">
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit DNS Forwarder entry");?></td>
				</tr>	
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Host");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="host" type="text" class="formfld" id="host" size="40" value="<?=htmlspecialchars($pconfig['host']);?>">
                    <br> <span class="vexpl"><?=gettext("Name of the host, without".
                   " domain part"); ?><br>
                   <?=gettext("e.g."); ?> <em><?=gettext("myhost"); ?></em></span></td>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Domain");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="domain" type="text" class="formfld" id="domain" size="40" value="<?=htmlspecialchars($pconfig['domain']);?>">
                    <br> <span class="vexpl"><?=gettext("Domain of the host"); ?><br>
                   <?=gettext("e.g."); ?> <em><?=gettext("example.com"); ?></em></span></td>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("IP address");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="ip" type="text" class="formfld" id="ip" size="40" value="<?=htmlspecialchars($pconfig['ip']);?>">
                    <br> <span class="vexpl"><?=gettext("IP address of the host"); ?><br>
                   <?=gettext("e.g."); ?> <em>192.168.100.100</em></span></td>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl"><?=gettext("You may enter a description here".
                   " for your reference (not parsed).");?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>"> <input class="formbtn" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()">
                    <?php if (isset($id) && $a_hosts[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
                    <?php endif; ?>
                  </td>
                </tr>
        </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
