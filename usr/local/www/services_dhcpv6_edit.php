<?php 
/* $Id$ */
/*
	services_dhcpv6_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>.
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
	pfSense_BUILDER_BINARIES:	/usr/sbin/arp
	pfSense_MODULE:	dhcpserver
*/

##|+PRIV
##|*IDENT=page-services-dhcpserverv6-editstaticmapping
##|*NAME=Services: DHCPv6 Server : Edit static mapping page
##|*DESCR=Allow access to the 'Services: DHCPv6 Server : Edit static mapping' page.
##|*MATCH=services_dhcpv6_edit.php*
##|-PRIV

function staticmapcmp($a, $b) {
        return ipcmp($a['ipaddrv6'], $b['ipaddrv6']);
}

function staticmaps_sort($ifgui) {
        global $g, $config;

        usort($config['dhcpdv6'][$ifgui]['staticmap'], "staticmapcmp");
}

require_once('globals.inc');

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/services_dhcpv6.php');

if(!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

require("guiconfig.inc");

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];
	
if (!$if) {
	header("Location: services_dhcpv6.php");
	exit;
}

if (!is_array($config['dhcpdv6']))
	$config['dhcpdv6'] = array();
if (!is_array($config['dhcpdv6'][$if]))
	$config['dhcpdv6'][$if] = array();
if (!is_array($config['dhcpdv6'][$if]['staticmap']))
	$config['dhcpdv6'][$if]['staticmap'] = array();

$netboot_enabled=isset($config['dhcpdv6'][$if]['netboot']);
$a_maps = &$config['dhcpdv6'][$if]['staticmap'];
$ifcfgipv6 = get_interface_ipv6($if);
$ifcfgsnv6 = get_interface_subnetv6($if);
$ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_maps[$id]) {
        $pconfig['duid'] = $a_maps[$id]['duid'];
	$pconfig['hostname'] = $a_maps[$id]['hostname'];
        $pconfig['ipaddrv6'] = $a_maps[$id]['ipaddrv6'];
	$pconfig['filename'] = $a_maps[$id]['filename'];
		$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
        $pconfig['descr'] = $a_maps[$id]['descr'];
} else {
        $pconfig['duid'] = $_GET['duid'];
	$pconfig['hostname'] = $_GET['hostname'];
	$pconfig['filename'] = $_GET['filename'];
		$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
        $pconfig['descr'] = $_GET['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "duid");
	$reqdfieldsn = array(gettext("DUID Identifier"));
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['hostname']) {
		preg_match("/\-\$/", $_POST['hostname'], $matches);
		if($matches)
			$input_errors[] = gettext("The hostname cannot end with a hyphen according to RFC952");		
		if (!is_hostname($_POST['hostname'])) {
			$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
		} else {
			if (strpos($_POST['hostname'],'.')) {
				$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
			}
		}
	}
	if (($_POST['ipaddrv6'] && !is_ipaddrv6($_POST['ipaddrv6']))) {
		$input_errors[] = gettext("A valid IPv6 address must be specified.");
	}
	if (empty($_POST['duid'])) {
		$input_errors[] = gettext("A valid DUID Identifier must be specified.");
	}
	
	/* check for overlaps */
	foreach ($a_maps as $mapent) {
		if (isset($id) && ($a_maps[$id]) && ($a_maps[$id] === $mapent))
			continue;

		if ((($mapent['hostname'] == $_POST['hostname']) && $mapent['hostname'])  || ($mapent['duid'] == $_POST['duid'])) {
			$input_errors[] = gettext("This Hostname, IP or DUID Identifier already exists.");
			break;
		}
	}
		
	/* make sure it's not within the dynamic subnet */
	if ($_POST['ipaddrv6']) {
		/* oh boy, we need to be able to somehow do this at some point. skip */
	}

	if (!$input_errors) {
		$mapent = array();
		$mapent['duid'] = $_POST['duid'];
		$mapent['ipaddrv6'] = $_POST['ipaddrv6'];
		$mapent['hostname'] = $_POST['hostname'];
		$mapent['descr'] = $_POST['descr'];
		$mapent['filename'] = $_POST['filename'];
		$mapent['rootpath'] = $_POST['rootpath'];

		if (isset($id) && $a_maps[$id])
			$a_maps[$id] = $mapent;
		else
			$a_maps[] = $mapent;
		staticmaps_sort($if);
		
		write_config();

		if(isset($config['dhcpdv6'][$if]['enable'])) {
			mark_subsystem_dirty('staticmaps');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic']))
				mark_subsystem_dirty('hosts');
			if (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcpstatic']))
				mark_subsystem_dirty('unbound');

		}

		header("Location: services_dhcpv6.php?if={$if}");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("DHCPv6"),gettext("Edit static mapping"));
$shortcut_section = "dhcp6";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_dhcpv6_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="static mapping">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Static DHCPv6 Mapping");?></td>
				</tr>	
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("DUID Identifier");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="duid" type="text" class="formfld unknown" id="duid" size="40" value="<?=htmlspecialchars($pconfig['duid']);?>" />
                    <br />
                    <span class="vexpl"><?=gettext("Enter a DUID Identifier in the following format: ");?><br />
"DUID-LLT - ETH -- TIME --- ---- address ----" <br />
"xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx"</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("IPv6 address");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="ipaddrv6" type="text" class="formfld unknown" id="ipaddrv6" size="28" value="<?=htmlspecialchars($pconfig['ipaddrv6']);?>" />
                    <br />
			<?=gettext("If an IPv6 address is entered, the address must be outside of the pool.");?>
			<br />
			<?=gettext("If no IPv6 address is given, one will be dynamically allocated from the pool.");?>
			</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("Hostname");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="hostname" type="text" class="formfld unknown" id="hostname" size="28" value="<?=htmlspecialchars($pconfig['hostname']);?>" />
                    <br /> <span class="vexpl"><?=gettext("Name of the host, without domain part.");?></span></td>
                </tr>				
                <?php if($netboot_enabled) { ?>
		<tr>
		  <td width="22%" valign="top" class="vncell">Netboot filename</td>
		  <td width="78%" class="vtable">
		    <input name="filename" type="text" class="formfld unknown" id="filename" size="28" value="<?=htmlspecialchars($pconfig['filename']);?>" />
		    <br /> <span class="vexpl">Name of the file that should be loaded when this host boots off of the network, overrides setting on main page.</span></td>
		</tr>
		<tr>
		  <td width="22%" valign="top" class="vncell">Root Path</td>
		  <td width="78%" class="vtable">
			<input name="rootpath" type="text" class="formfld unknown" id="rootpath" size="90" value="<?=htmlspecialchars($pconfig['rootpath']);?>" />
		    <br /> <span class="vexpl"><?=gettext("Enter the"); ?> <b><?=gettext("root-path"); ?></b>-<?=gettext("string");?>, overrides setting on main page.</span></td>
		</tr>
		<?php } ?>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                    <br /> <span class="vexpl"><?=gettext("You may enter a description here ".
                    "for your reference (not parsed).");?></span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
                    <input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
                    <?php if (isset($id) && $a_maps[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                    <?php endif; ?>
                    <input name="if" type="hidden" value="<?=htmlspecialchars($if);?>" />
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
