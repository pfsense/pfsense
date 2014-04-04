<?php
/* $Id$ */
/*
	services_dhcp_edit.php
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
/*
	pfSense_BUILDER_BINARIES:	/usr/sbin/arp
	pfSense_MODULE:	dhcpserver
*/

##|+PRIV
##|*IDENT=page-services-dhcpserver-editstaticmapping
##|*NAME=Services: DHCP Server : Edit static mapping page
##|*DESCR=Allow access to the 'Services: DHCP Server : Edit static mapping' page.
##|*MATCH=services_dhcp_edit.php*
##|-PRIV

function staticmapcmp($a, $b) {
	return ipcmp($a['ipaddr'], $b['ipaddr']);
}

function staticmaps_sort($ifgui) {
	global $g, $config;

	usort($config['dhcpd'][$ifgui]['staticmap'], "staticmapcmp");
}

require_once('globals.inc');

if(!$g['services_dhcp_server_enable']) {
	Header("Location: /");
	exit;
}

require("guiconfig.inc");

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];

if (!$if) {
	header("Location: services_dhcp.php");
	exit;
}

if (!is_array($config['dhcpd']))
	$config['dhcpd'] = array();
if (!is_array($config['dhcpd'][$if]))
	$config['dhcpd'][$if] = array();
if (!is_array($config['dhcpd'][$if]['staticmap']))
	$config['dhcpd'][$if]['staticmap'] = array();

if (!is_array($config['dhcpd'][$if]['pool']))
	$config['dhcpd'][$if]['pool'] = array();
$a_pools = &$config['dhcpd'][$if]['pool'];

$static_arp_enabled=isset($config['dhcpd'][$if]['staticarp']);
$netboot_enabled=isset($config['dhcpd'][$if]['netboot']);
$a_maps = &$config['dhcpd'][$if]['staticmap'];
$ifcfgip = get_interface_ip($if);
$ifcfgsn = get_interface_subnet($if);
$ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_maps[$id]) {
	$pconfig['mac'] = $a_maps[$id]['mac'];
	$pconfig['cid'] = $a_maps[$id]['cid'];
	$pconfig['hostname'] = $a_maps[$id]['hostname'];
	$pconfig['ipaddr'] = $a_maps[$id]['ipaddr'];
	$pconfig['filename'] = $a_maps[$id]['filename'];
	$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
	$pconfig['descr'] = $a_maps[$id]['descr'];
	$pconfig['arp_table_static_entry'] = isset($a_maps[$id]['arp_table_static_entry']);
	$pconfig['deftime'] = $a_maps[$id]['defaultleasetime'];
	$pconfig['maxtime'] = $a_maps[$id]['maxleasetime'];
	$pconfig['gateway'] = $a_maps[$id]['gateway'];
	$pconfig['domain'] = $a_maps[$id]['domain'];
	$pconfig['domainsearchlist'] = $a_maps[$id]['domainsearchlist'];
	list($pconfig['wins1'],$pconfig['wins2']) = $a_maps[$id]['winsserver'];
	list($pconfig['dns1'],$pconfig['dns2']) = $a_maps[$id]['dnsserver'];
	$pconfig['ddnsdomain'] = $a_maps[$id]['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $a_maps[$id]['ddnsdomainprimary'];
	$pconfig['ddnsdomainkeyname'] = $a_maps[$id]['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkey'] = $a_maps[$id]['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($a_maps[$id]['ddnsupdate']);
	list($pconfig['ntp1'],$pconfig['ntp2']) = $a_maps[$id]['ntpserver'];
	$pconfig['tftp'] = $a_maps[$id]['tftp'];
} else {
	$pconfig['mac'] = $_GET['mac'];
	$pconfig['cid'] = $_GET['cid'];
	$pconfig['hostname'] = $_GET['hostname'];
	$pconfig['filename'] = $_GET['filename'];
	$pconfig['rootpath'] = $_GET['rootpath'];
	$pconfig['descr'] = $_GET['descr'];
	$pconfig['arp_table_static_entry'] = $_GET['arp_table_static_entry'];
	$pconfig['deftime'] = $_GET['defaultleasetime'];
	$pconfig['maxtime'] = $_GET['maxleasetime'];
	$pconfig['gateway'] = $_GET['gateway'];
	$pconfig['domain'] = $_GET['domain'];
	$pconfig['domainsearchlist'] = $_GET['domainsearchlist'];
	$pconfig['wins1'] = $_GET['wins1'];
	$pconfig['wins2'] = $_GET['wins2'];
	$pconfig['dns1'] = $_GET['dns1'];
	$pconfig['dns2'] = $_GET['dns2'];
	$pconfig['ddnsdomain'] = $_GET['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $_GET['ddnsdomainprimary'];
	$pconfig['ddnsdomainkeyname'] = $_GET['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkey'] = $_GET['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($_GET['ddnsupdate']);
	$pconfig['ntp1'] = $_GET['ntp1'];
	$pconfig['ntp2'] = $_GET['ntp2'];
	$pconfig['tftp'] = $_GET['tftp'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    /* either MAC or Client-ID must be specified */
    if (empty($_POST['mac']) && empty($_POST['cid']))
        $input_errors[] = gettext("Either MAC address or Client identifier must be specified");

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['mac'] = strtolower(str_replace("-", ":", $_POST['mac']));

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
	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) {
		$input_errors[] = gettext("A valid IP address must be specified.");
	}
	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}
	if($static_arp_enabled && !$_POST['ipaddr']) {
		$input_errors[] = gettext("Static ARP is enabled.  You must specify an IP address.");
	}

	/* check for overlaps */
	foreach ($a_maps as $mapent) {
		if (isset($id) && ($a_maps[$id]) && ($a_maps[$id] === $mapent))
			continue;

		if ((($mapent['hostname'] == $_POST['hostname']) && $mapent['hostname'])  || (($mapent['mac'] == $_POST['mac']) && $mapent['mac']) || (($mapent['ipaddr'] == $_POST['ipaddr']) && $mapent['ipaddr'] ) || (($mapent['cid'] == $_POST['cid']) && $mapent['cid'])) {
			$input_errors[] = gettext("This Hostname, IP, MAC address or Client identifier already exists.");
			break;
		}
	}

	/* make sure it's not within the dynamic subnet */
	if ($_POST['ipaddr']) {
		$dynsubnet_start = ip2ulong($config['dhcpd'][$if]['range']['from']);
		$dynsubnet_end = ip2ulong($config['dhcpd'][$if]['range']['to']);
		if ((ip2ulong($_POST['ipaddr']) >= $dynsubnet_start) &&
			(ip2ulong($_POST['ipaddr']) <= $dynsubnet_end)) {
			$input_errors[] = sprintf(gettext("The IP address must not be within the DHCP range for this interface."));
		}

		foreach ($a_pools as $pidx => $p) {
			if (is_inrange_v4($_POST['ipaddr'], $p['range']['from'], $p['range']['to'])) {
				$input_errors[] = gettext("The IP address must not be within the range configured on a DHCP pool for this interface.");
				break;
			}
		}

		$lansubnet_start = ip2ulong(long2ip32(ip2long($ifcfgip) & gen_subnet_mask_long($ifcfgsn)));
		$lansubnet_end = ip2ulong(long2ip32(ip2long($ifcfgip) | (~gen_subnet_mask_long($ifcfgsn))));
		if ((ip2ulong($_POST['ipaddr']) < $lansubnet_start) ||
			(ip2ulong($_POST['ipaddr']) > $lansubnet_end)) {
			$input_errors[] = sprintf(gettext("The IP address must lie in the %s subnet."),$ifcfgdescr);
		}
	}

	if (($_POST['gateway'] && !is_ipaddrv4($_POST['gateway'])))
		$input_errors[] = gettext("A valid IP address must be specified for the gateway.");
	if (($_POST['wins1'] && !is_ipaddrv4($_POST['wins1'])) || ($_POST['wins2'] && !is_ipaddrv4($_POST['wins2'])))
		$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary WINS servers.");

	$parent_ip = get_interface_ip($POST['if']);
	if (is_ipaddrv4($parent_ip) && $_POST['gateway']) {
		$parent_sn = get_interface_subnet($_POST['if']);
		if(!ip_in_subnet($_POST['gateway'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($_POST['if'], $_POST['gateway']))
			$input_errors[] = sprintf(gettext("The gateway address %s does not lie within the chosen interface's subnet."), $_POST['gateway']);
	}
	if (($_POST['dns1'] && !is_ipaddrv4($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddrv4($_POST['dns2'])))
		$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary DNS servers.");

	if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60)))
		$input_errors[] = gettext("The default lease time must be at least 60 seconds.");
	if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime'])))
		$input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
	if (($_POST['ddnsdomain'] && !is_domain($_POST['ddnsdomain'])))
		$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
	if (($_POST['ddnsdomain'] && !is_ipaddrv4($_POST['ddnsdomainprimary'])))
		$input_errors[] = gettext("A valid primary domain name server IP address must be specified for the dynamic domain name.");
	if (($_POST['ddnsdomainkey'] && !$_POST['ddnsdomainkeyname']) ||
		($_POST['ddnsdomainkeyname'] && !$_POST['ddnsdomainkey']))
		$input_errors[] = gettext("You must specify both a valid domain key and key name.");
	if ($_POST['domainsearchlist']) {
		$domain_array=preg_split("/[ ;]+/",$_POST['domainsearchlist']);
		foreach ($domain_array as $curdomain) {
			if (!is_domain($curdomain)) {
				$input_errors[] = gettext("A valid domain search list must be specified.");
				break;
			}
		}
	}

	if (($_POST['ntp1'] && !is_ipaddrv4($_POST['ntp1'])) || ($_POST['ntp2'] && !is_ipaddrv4($_POST['ntp2'])))
		$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary NTP servers.");
	if ($_POST['tftp'] && !is_ipaddrv4($_POST['tftp']) && !is_domain($_POST['tftp']) && !is_URL($_POST['tftp']))
		$input_errors[] = gettext("A valid IP address or hostname must be specified for the TFTP server.");
	if (($_POST['nextserver'] && !is_ipaddrv4($_POST['nextserver'])))
		$input_errors[] = gettext("A valid IP address must be specified for the network boot server.");

	if (!$input_errors) {
		$mapent = array();
		$mapent['mac'] = $_POST['mac'];
		$mapent['cid'] = $_POST['cid'];
		$mapent['ipaddr'] = $_POST['ipaddr'];
		$mapent['hostname'] = $_POST['hostname'];
		$mapent['descr'] = $_POST['descr'];
		$mapent['arp_table_static_entry'] = ($_POST['arp_table_static_entry']) ? true : false;
		$mapent['filename'] = $_POST['filename'];
		$mapent['rootpath'] = $_POST['rootpath'];
		$mapent['defaultleasetime'] = $_POST['deftime'];
		$mapent['maxleasetime'] = $_POST['maxtime'];

		unset($mapent['winsserver']);
		if ($_POST['wins1'])
			$mapent['winsserver'][] = $_POST['wins1'];
		if ($_POST['wins2'])
			$mapent['winsserver'][] = $_POST['wins2'];

		unset($mapent['dnsserver']);
		if ($_POST['dns1'])
			$mapent['dnsserver'][] = $_POST['dns1'];
		if ($_POST['dns2'])
			$mapent['dnsserver'][] = $_POST['dns2'];

		$mapent['gateway'] = $_POST['gateway'];
		$mapent['domain'] = $_POST['domain'];
		$mapent['domainsearchlist'] = $_POST['domainsearchlist'];
		$mapent['ddnsdomain'] = $_POST['ddnsdomain'];
		$mapent['ddnsdomainprimary'] = $_POST['ddnsdomainprimary'];
		$mapent['ddnsdomainkeyname'] = $_POST['ddnsdomainkeyname'];
		$mapent['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$mapent['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;

		unset($mapent['ntpserver']);
		if ($_POST['ntp1'])
			$mapent['ntpserver'][] = $_POST['ntp1'];
		if ($_POST['ntp2'])
			$mapent['ntpserver'][] = $_POST['ntp2'];

		$mapent['tftp'] = $_POST['tftp'];
		$mapent['ldap'] = $_POST['ldap'];

		if (isset($id) && $a_maps[$id])
			$a_maps[$id] = $mapent;
		else
			$a_maps[] = $mapent;
		staticmaps_sort($if);

		write_config();

		if(isset($config['dhcpd'][$if]['enable'])) {
			mark_subsystem_dirty('staticmaps');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic']))
				mark_subsystem_dirty('hosts');
		}

		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

$closehead = false;
$pgtitle = array(gettext("Services"),gettext("DHCP"),gettext("Edit static mapping"));
$shortcut_section = "dhcp";

include("head.inc");

?>

<script type="text/javascript">
//<![CDATA[
	function show_ddns_config() {
		document.getElementById("showddnsbox").innerHTML='';
		aodiv = document.getElementById('showddns');
		aodiv.style.display = "block";
	}

	function show_ntp_config() {
		document.getElementById("showntpbox").innerHTML='';
		aodiv = document.getElementById('showntp');
		aodiv.style.display = "block";
	}

	function show_tftp_config() {
		document.getElementById("showtftpbox").innerHTML='';
		aodiv = document.getElementById('showtftp');
		aodiv.style.display = "block";
	}
//]]>
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_dhcp_edit.php" method="post" name="iform" id="iform">
              <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="static mapping">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Static DHCP Mapping");?></td>
				</tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("MAC address");?></td>
                  <td width="78%" class="vtable">
                    <input name="mac" type="text" class="formfld unknown" id="mac" size="30" value="<?=htmlspecialchars($pconfig['mac']);?>" />
		    <?php
			$ip = getenv('REMOTE_ADDR');
			$mac = `/usr/sbin/arp -an | grep {$ip} | cut -d" " -f4`;
			$mac = str_replace("\n","",$mac);
		    ?>
		    <a onclick="document.forms[0].mac.value='<?=$mac?>';" href="#"><?=gettext("Copy my MAC address");?></a>
                    <br />
                    <span class="vexpl"><?=gettext("Enter a MAC address in the following format: ".
                    "xx:xx:xx:xx:xx:xx");?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Client identifier");?></td>
                  <td width="78%" class="vtable">
                    <input name="cid" type="text" class="formfld unknown" id="cid" size="30" value="<?=htmlspecialchars($pconfig['cid']);?>" />
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("IP address");?></td>
                  <td width="78%" class="vtable">
                    <input name="ipaddr" type="text" class="formfld unknown" id="ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>" />
                    <br />
			<?=gettext("If an IPv4 address is entered, the address must be outside of the pool.");?>
			<br />
			<?=gettext("If no IPv4 address is given, one will be dynamically allocated from the pool.");?>
			</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Hostname");?></td>
                  <td width="78%" class="vtable">
                    <input name="hostname" type="text" class="formfld unknown" id="hostname" size="20" value="<?=htmlspecialchars($pconfig['hostname']);?>" />
                    <br /> <span class="vexpl"><?=gettext("Name of the host, without domain part.");?></span></td>
                </tr>
                <?php if($netboot_enabled) { ?>
		<tr>
		  <td width="22%" valign="top" class="vncell">Netboot Filename</td>
		  <td width="78%" class="vtable">
		    <input name="filename" type="text" class="formfld unknown" id="filename" size="20" value="<?=htmlspecialchars($pconfig['filename']);?>" />
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
                  <td width="22%" valign="top" class="vncell"><?=gettext("ARP Table Static Entry");?></td>
                  <td width="78%" class="vtable">
                    <input name="arp_table_static_entry" id="arp_table_static_entry" type="checkbox" value="yes" <?php if ($pconfig['arp_table_static_entry']) echo "checked=\"checked\""; ?> />
                    <br /> <span class="vexpl"><?=gettext("Create an ARP Table Static Entry for this MAC &amp; IP Address pair. ".
                    "");?></span></td>
                </tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("WINS servers");?></td>
		<td width="78%" class="vtable">
			<input name="wins1" type="text" class="formfld unknown" id="wins1" size="20" value="<?=htmlspecialchars($pconfig['wins1']);?>" /><br />
			<input name="wins2" type="text" class="formfld unknown" id="wins2" size="20" value="<?=htmlspecialchars($pconfig['wins2']);?>" />
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("DNS servers");?></td>
		<td width="78%" class="vtable">
			<input name="dns1" type="text" class="formfld unknown" id="dns1" size="20" value="<?=htmlspecialchars($pconfig['dns1']);?>" /><br />
			<input name="dns2" type="text" class="formfld unknown" id="dns2" size="20" value="<?=htmlspecialchars($pconfig['dns2']);?>" /><br />
			<?=gettext("NOTE: leave blank to use the system default DNS servers - this interface's IP if DNS forwarder is enabled, otherwise the servers configured on the General page.");?>
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Gateway");?></td>
		<td width="78%" class="vtable">
			<input name="gateway" type="text" class="formfld host" id="gateway" size="20" value="<?=htmlspecialchars($pconfig['gateway']);?>" /><br />
			 <?=gettext("The default is to use the IP on this interface of the firewall as the gateway. Specify an alternate gateway here if this is not the correct gateway for your network.");?>
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Domain name");?></td>
		<td width="78%" class="vtable">
			<input name="domain" type="text" class="formfld unknown" id="domain" size="20" value="<?=htmlspecialchars($pconfig['domain']);?>" /><br />
			 <?=gettext("The default is to use the domain name of this system as the default domain name provided by DHCP. You may specify an alternate domain name here.");?>
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Domain search list");?></td>
		<td width="78%" class="vtable">
			<input name="domainsearchlist" type="text" class="formfld unknown" id="domainsearchlist" size="20" value="<?=htmlspecialchars($pconfig['domainsearchlist']);?>" /><br />
			<?=gettext("The DHCP server can optionally provide a domain search list. Use the semicolon character as separator ");?>
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Default lease time");?></td>
		<td width="78%" class="vtable">
			<input name="deftime" type="text" class="formfld unknown" id="deftime" size="10" value="<?=htmlspecialchars($pconfig['deftime']);?>" />
			<?=gettext("seconds");?><br />
			<?=gettext("This is used for clients that do not ask for a specific " .
			"expiration time."); ?><br />
			<?=gettext("The default is 7200 seconds.");?>
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Maximum lease time");?></td>
		<td width="78%" class="vtable">
			<input name="maxtime" type="text" class="formfld unknown" id="maxtime" size="10" value="<?=htmlspecialchars($pconfig['maxtime']);?>" />
			<?=gettext("seconds");?><br />
			<?=gettext("This is the maximum lease time for clients that ask".
			" for a specific expiration time."); ?><br />
			<?=gettext("The default is 86400 seconds.");?>
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Dynamic DNS");?></td>
		<td width="78%" class="vtable">
			<div id="showddnsbox">
				<input type="button" onClick="show_ddns_config()" value="<?=gettext("Advanced");?>" /> - <?=gettext("Show Dynamic DNS");?>
			</div>
			<div id="showddns" style="display:none">
				<input style="vertical-align:middle" type="checkbox" value="yes" name="ddnsupdate" id="ddnsupdate" <?php if($pconfig['ddnsupdate']) echo "checked=\"checked\""; ?> />&nbsp;
				<b><?=gettext("Enable registration of DHCP client names in DNS.");?></b><br />
				<p>
				<input name="ddnsdomain" type="text" class="formfld unknown" id="ddnsdomain" size="20" value="<?=htmlspecialchars($pconfig['ddnsdomain']);?>" /><br />
				<?=gettext("Note: Leave blank to disable dynamic DNS registration.");?><br />
				<?=gettext("Enter the dynamic DNS domain which will be used to register client names in the DNS server.");?>
				<input name="ddnsdomainprimary" type="text" class="formfld unknown" id="ddnsdomainprimary" size="20" value="<?=htmlspecialchars($pconfig['ddnsdomainprimary']);?>" /><br />
				<?=gettext("Enter the primary domain name server IP address for the dynamic domain name.");?><br />
				<input name="ddnsdomainkeyname" type="text" class="formfld unknown" id="ddnsdomainkeyname" size="20" value="<?=htmlspecialchars($pconfig['ddnsdomainkeyname']);?>" /><br />
				<?=gettext("Enter the dynamic DNS domain key name which will be used to register client names in the DNS server.");?>
				<input name="ddnsdomainkey" type="text" class="formfld unknown" id="ddnsdomainkey" size="20" value="<?=htmlspecialchars($pconfig['ddnsdomainkey']);?>" /><br />
				<?=gettext("Enter the dynamic DNS domain key secret which will be used to register client names in the DNS server.");?>
				</p>
			</div>
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("NTP servers");?></td>
		<td width="78%" class="vtable">
			<div id="showntpbox">
				<input type="button" onClick="show_ntp_config()" value="<?=gettext("Advanced");?>" /> - <?=gettext("Show NTP configuration");?>
			</div>
			<div id="showntp" style="display:none">
				<input name="ntp1" type="text" class="formfld unknown" id="ntp1" size="20" value="<?=htmlspecialchars($pconfig['ntp1']);?>" /><br />
				<input name="ntp2" type="text" class="formfld unknown" id="ntp2" size="20" value="<?=htmlspecialchars($pconfig['ntp2']);?>" />
			</div>
		</td>
		</tr>
		<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("TFTP server");?></td>
		<td width="78%" class="vtable">
		<div id="showtftpbox">
			<input type="button" onClick="show_tftp_config()" value="<?=gettext("Advanced");?>" /> - <?=gettext("Show TFTP configuration");?>
		</div>
		<div id="showtftp" style="display:none">
			<input name="tftp" type="text" class="formfld unknown" id="tftp" size="50" value="<?=htmlspecialchars($pconfig['tftp']);?>" /><br />
			<?=gettext("Leave blank to disable.  Enter a full hostname or IP for the TFTP server.");?>
		</div>
		</td>
		</tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" /> <input class="formbtn" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()" />
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
