<?php
/* $Id$ */
/*
	services_router_advertisements.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	part of pfSense (http://www.pfsense.org)
	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
	pfSense_BUILDER_BINARIES:	/bin/rm
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-services-router-advertisements
##|*NAME=Services: Router advertisementspage
##|*DESCR=Allow access to the 'Services: Router Advertisements' page.
##|*MATCH=services_router_advertisements.php*
##|-PRIV

require("guiconfig.inc");

if(!$g['services_dhcp_server_enable']) {
	Header("Location: /");
	exit;
}

/*  Fix failover DHCP problem
 *  http://article.gmane.org/gmane.comp.security.firewalls.pfsense.support/18749
 */
ini_set("memory_limit","64M");

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];

/* if OLSRD is enabled, allow WAN to house DHCP. */
if($config['installedpackages']['olsrd']) {
	foreach($config['installedpackages']['olsrd']['config'] as $olsrd) {
			if($olsrd['enable']) {
				$is_olsr_enabled = true;
				break;
			}
	}
}

if (!$_GET['if'])
	$savemsg = "<p><b>" . gettext("The DHCPv6 Server can only be enabled on interfaces configured with static IP addresses") . ".</b></p>" .
		   "<p><b>" . gettext("Only interfaces configured with a static IP will be shown") . ".</b></p>";

$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!preg_match("/fe80::/", $oc['ipaddrv6'])))) ||
			(!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!preg_match("/fe80::/", $oc['ipaddrv6'])))))
			continue;
		$if = $ifent;
		break;
	}
}

if (is_array($config['dhcpdv6'][$if])){
	/* RA specific */
	$pconfig['ramode'] = $config['dhcpdv6'][$if]['ramode'];
	$pconfig['rapriority'] = $config['dhcpdv6'][$if]['rapriority'];
	if($pconfig['rapriority'] == "")
		$pconfig['rapriority'] = "medium";
	$pconfig['rainterface'] = $config['dhcpdv6'][$if]['rainterface'];
}

$advertise_modes = array("disabled" => "Disabled",
			 "router" => "Router Only",
			 "unmanaged" => "Unmanaged",
			 "managed" => "Managed",
			 "assist" => "Assisted");
$priority_modes = array("low" => "Low",
			"medium" => "Normal",
			"high" => "High");
$carplist = get_configured_carp_interface_list();

if ($_POST) {

	unset($input_errors);

	$pconfig = $_POST;

	if (!$input_errors) {
		if (!is_array($config['dhcpdv6'][$if]))
			$config['dhcpdv6'][$if] = array();

		$config['dhcpdv6'][$if]['ramode'] = $_POST['ramode'];
		$config['dhcpdv6'][$if]['rapriority'] = $_POST['rapriority'];
		$config['dhcpdv6'][$if]['rainterface'] = $_POST['rainterface'];
		
		write_config();
		$retval = services_radvd_configure();
		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = array(gettext("Services"),gettext("Router advertisements"));

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_router_advertisements.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
	/* active tabs */
	$tab_array = array();
	$tabscounter = 0;
	$i = 0;
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!preg_match("/fe80::/", $oc['ipaddrv6'])))) ||
			(!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!preg_match("/fe80::/", $oc['ipaddrv6'])))))
			continue;
		if ($ifent == $if)
			$active = true;
		else
			$active = false;
		$tab_array[] = array($ifname, $active, "services_dhcpv6.php?if={$ifent}");
		$tabscounter++;
	}
	if ($tabscounter == 0) {
		echo "</td></tr></table></form>";
		include("fend.inc");
		echo "</body>";
		echo "</html>";
		exit;
	}
	display_top_tabs($tab_array);
?>
</td></tr>
<tr><td class="tabnavtbl">
<?php
$tab_array = array();
$tab_array[] = array(gettext("DHCPv6 Server"),         false, "services_dhcpv6.php?if={$if}");
$tab_array[] = array(gettext("Router Advertisements"), true,  "services_router_advertisements.php?if={$if}");
display_top_tabs($tab_array);
?>
</td></tr>
<tr>
<td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Router Advertisements");?></td>
			<td width="78%" class="vtable">
				<select name="ramode" id="ramode">
					<?php foreach($advertise_modes as $name => $value) { ?>
					<option value="<?=$name ?>" <?php if ($pconfig['ramode'] == $name) echo "selected"; ?> > <?=$value ?></option>
					<?php } ?>
				</select><br />
			<strong><?php printf(gettext("Select the Operating Mode for the Router Advertisement (RA) Daemon."))?></strong>
			<?php printf(gettext("Use \"Router Only\" to only advertise this router, \"Unmanaged\" for Router Advertising with Stateless Autoconfig, \"Managed\" for assignment through (a) DHCPv6 Server, \"Assisted\" for DHCPv6 Server assignment combined with Stateless Autoconfig"));?>
			<?php printf(gettext("It is not required to activate this DHCPv6 server when set to \"Managed\", this can be another host on the network")); ?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Router Priority");?></td>
			<td width="78%" class="vtable">
				<select name="rapriority" id="rapriority">
					<?php foreach($priority_modes as $name => $value) { ?>
					<option value="<?=$name ?>" <?php if ($pconfig['rapriority'] == $name) echo "selected"; ?> > <?=$value ?></option>
					<?php } ?>
				</select><br />
			<strong><?php printf(gettext("Select the Priority for the Router Advertisement (RA) Daemon."))?></strong>
			</td>
			</tr>
			<?php
				$carplistif = array();
				if(count($carplist) > 0) {
					foreach($carplist as $ifname => $vip) {
						if((preg_match("/^{$if}_/", $ifname)) && (is_ipaddrv6($vip)))
							$carplistif[$ifname] = $vip;
					}
				}
				if(count($carplistif) > 0) {
			?>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("RA Interface");?></td>
			<td width="78%" class="vtable">
				<select name="rainterface" id="rainterface">
					<?php foreach($carplistif as $ifname => $vip) { ?>
					<option value="interface" <?php if ($pconfig['rainterface'] == "interface") echo "selected"; ?> > <?=strtoupper($if); ?></option>
					<option value="<?=$ifname ?>" <?php if ($pconfig['rainterface'] == $ifname) echo "selected"; ?> > <?="$ifname - $vip"; ?></option>
					<?php } ?>
				</select><br />
			<strong><?php printf(gettext("Select the Interface for the Router Advertisement (RA) Daemon."))?></strong>
			</td>
			</tr>
			<?php } ?>
			<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="if" type="hidden" value="<?=$if;?>" />
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
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
