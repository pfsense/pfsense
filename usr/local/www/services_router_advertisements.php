<?php
/* $Id$ */
/*
	services_router_advertisements.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	part of pfSense (https://www.pfsense.org)
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
		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))) ||
			(!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))))
			continue;
		$if = $ifent;
		break;
	}
}

if (is_array($config['dhcpdv6'][$if])) {
	/* RA specific */
	$pconfig['ramode'] = $config['dhcpdv6'][$if]['ramode'];
	$pconfig['rapriority'] = $config['dhcpdv6'][$if]['rapriority'];
	if($pconfig['rapriority'] == "")
		$pconfig['rapriority'] = "medium";
	$pconfig['rainterface'] = $config['dhcpdv6'][$if]['rainterface'];
	$pconfig['radomainsearchlist'] = $config['dhcpdv6'][$if]['radomainsearchlist'];
	list($pconfig['radns1'],$pconfig['radns2']) = $config['dhcpdv6'][$if]['radnsserver'];
	$pconfig['rasamednsasdhcp6'] = isset($config['dhcpdv6'][$if]['rasamednsasdhcp6']);

	$pconfig['subnets'] = $config['dhcpdv6'][$if]['subnets']['item'];
}
if (!is_array($pconfig['subnets']))
	$pconfig['subnets'] = array();

$advertise_modes = array("disabled" => "Disabled",
			 "router" => "Router Only",
			 "unmanaged" => "Unmanaged",
			 "managed" => "Managed",
			 "assist" => "Assisted");
$priority_modes = array("low" => "Low",
			"medium" => "Normal",
			"high" => "High");
$carplist = get_configured_carp_interface_list();

$subnets_help = gettext("Subnets are specified in CIDR format.  " .
			"Select the CIDR mask that pertains to each entry.  " .
			"/128 specifies a single IPv6 host; /64 specifies a normal IPv6 network; etc.  " .
			"If no subnets are specified here, the Router Advertisement (RA) Daemon will advertise to the subnet to which the router's interface is assigned.");

if ($_POST) {
	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */

	$pconfig['subnets'] = array();
	for ($x = 0; $x < 5000; $x += 1) {
		$address = trim($_POST['subnet_address' . $x]);
		if ($address === "")
			continue;

		$bits = trim($_POST['subnet_bits' . $x]);
		if ($bits === "")
			$bits = "128";

		if (is_alias($address)) {
			$pconfig['subnets'][] = $address;
		} else {
			$pconfig['subnets'][] = $address . "/" . $bits;
			if (!is_ipaddrv6($address))
				$input_errors[] = sprintf(gettext("An invalid subnet or alias was specified. [%s/%s]"), $address, $bits);
		}
	}

	if (($_POST['radns1'] && !is_ipaddrv6($_POST['radns1'])) || ($_POST['radns2'] && !is_ipaddrv6($_POST['radns2'])))
		$input_errors[] = gettext("A valid IPv6 address must be specified for the primary/secondary DNS servers.");
	if ($_POST['radomainsearchlist']) {
		$domain_array=preg_split("/[ ;]+/",$_POST['radomainsearchlist']);
		foreach ($domain_array as $curdomain) {
			if (!is_domain($curdomain)) {
				$input_errors[] = gettext("A valid domain search list must be specified.");
				break;
			}
		}
	}

	if (!$input_errors) {
		if (!is_array($config['dhcpdv6'][$if]))
			$config['dhcpdv6'][$if] = array();

		$config['dhcpdv6'][$if]['ramode'] = $_POST['ramode'];
		$config['dhcpdv6'][$if]['rapriority'] = $_POST['rapriority'];
		$config['dhcpdv6'][$if]['rainterface'] = $_POST['rainterface'];

		$config['dhcpdv6'][$if]['radomainsearchlist'] = $_POST['radomainsearchlist'];
		unset($config['dhcpdv6'][$if]['radnsserver']);
		if ($_POST['radns1'])
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns1'];
		if ($_POST['radns2'])
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns2'];

		$config['dhcpdv6'][$if]['rasamednsasdhcp6'] = ($_POST['rasamednsasdhcp6']) ? true : false;

		if (count($pconfig['subnets'])) {
			$config['dhcpdv6'][$if]['subnets']['item'] = $pconfig['subnets'];
		} else {
			unset($config['dhcpdv6'][$if]['subnets']);
		}

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

<script type="text/javascript" src="/javascript/row_helper.js">
</script>
<script type="text/javascript" src="/javascript/autosuggest.js">
</script>
<script type="text/javascript" src="/javascript/suggestions.js">
</script>
<script type="text/javascript">
//<![CDATA[
	rowname[0] = "subnet_address";
	rowtype[0] = "textbox";
	rowsize[0] = "30";
	rowname[1] = "subnet_bits";
	rowtype[1] = "select";
	rowsize[1] = "1";
	function add_alias_control() {
		var name = "subnet_address" + (totalrows - 1);
		obj = document.getElementById(name);
		obj.setAttribute('class', 'formfldalias');
		obj.setAttribute('autocomplete', 'off');
		objAlias[totalrows - 1] = new AutoSuggestControl(obj, new StateSuggestions(addressarray));
	}
//]]>
</script>

<form action="services_router_advertisements.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="router advert">
<tr><td>
<?php
	/* active tabs */
	$tab_array = array();
	$tabscounter = 0;
	$i = 0;
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))) ||
			(!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))))
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
		<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Router Advertisements");?></td>
			<td width="78%" class="vtable">
				<select name="ramode" id="ramode">
					<?php foreach($advertise_modes as $name => $value) { ?>
					<option value="<?=$name ?>" <?php if ($pconfig['ramode'] == $name) echo "selected=\"selected\""; ?> > <?=$value ?></option>
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
					<option value="<?=$name ?>" <?php if ($pconfig['rapriority'] == $name) echo "selected=\"selected\""; ?> > <?=$value ?></option>
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
					<option value="interface" <?php if ($pconfig['rainterface'] == "interface") echo "selected=\"selected\""; ?> > <?=strtoupper($if); ?></option>
					<option value="<?=$ifname ?>" <?php if ($pconfig['rainterface'] == $ifname) echo "selected=\"selected\""; ?> > <?="$ifname - $vip"; ?></option>
					<?php } ?>
				</select><br />
			<strong><?php printf(gettext("Select the Interface for the Router Advertisement (RA) Daemon."))?></strong>
			</td>
			</tr>
			<?php } ?>

			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("RA Subnet(s)");?></td>
			<td width="78%" class="vtable">
				<div><?= htmlentities($subnets_help) ?></div>
				<table id="maintable" summary="subnets">
				<tbody>
<?php
				$counter = 0;
				foreach ($pconfig['subnets'] as $subnet) {
					$address_name = "subnet_address" . $counter;
					$bits_name = "subnet_bits" . $counter;
					list($address, $subnet) = explode("/", $subnet);
?>
					<tr>
						<td>
							<input autocomplete="off" name="<?= $address_name ?>" type="text" class="formfldalias" id="<?= $address_name ?>" size="30" value="<?= htmlentities($address) ?>" />
						</td>
						<td>
							<select name="<?= $bits_name ?>" class="formselect" id="<?= $bits_name ?>">
							<option value="">
							<?php for ($i = 128; $i >= 0; $i -= 1) { ?>
								<option value="<?= $i ?>" <?= ("$subnet" === "$i") ? "selected='selected'" : "" ?>><?= $i ?></option>
							<?php } ?>
							</select>
						</td>
						<td>
							<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/<?echo $g['theme'];?>/images/icons/icon_x.gif" alt="" title="<?=gettext("remove this entry"); ?>" /></a>
						</td>
					</tr>
<?php
					$counter += 1;
				}
?>
				<tr style="display:none"><td></td></tr>
				</tbody>
				</table>
				<script type="text/javascript">
				//<![CDATA[
					field_counter_js = 2;
					totalrows = <?= $counter ?>;
				//]]>
				</script>
				<div id="addrowbutton">
					<a onclick="javascript:addRowTo('maintable'); add_alias_control(); return false;" href="#"><!--
					--><img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="<?=gettext("add another entry"); ?>" /></a>
				</div>
			</td>
			</tr>

			<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
			</tr>

			<tr>
			<td colspan="2" valign="top" class="listtopic">DNS</td>
			</tr>

			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("DNS servers");?></td>
			<td width="78%" class="vtable">
				<input name="radns1" type="text" class="formfld unknown" id="radns1" size="28" value="<?=htmlspecialchars($pconfig['radns1']);?>" /><br />
				<input name="radns2" type="text" class="formfld unknown" id="radns2" size="28" value="<?=htmlspecialchars($pconfig['radns2']);?>" /><br />
				<?=gettext("NOTE: leave blank to use the system default DNS servers - this interface's IP if DNS forwarder is enabled, otherwise the servers configured on the General page.");?>
			</td>
			</tr>

			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Domain search list");?></td>
			<td width="78%" class="vtable">
				<input name="radomainsearchlist" type="text" class="formfld unknown" id="radomainsearchlist" size="28" value="<?=htmlspecialchars($pconfig['radomainsearchlist']);?>" /><br />
				<?=gettext("The RA server can optionally provide a domain search list. Use the semicolon character as separator");?>
			</td>
			</tr>

			<tr>
			<td width="22%" valign="top" class="vncell">&nbsp;</td>
			<td width="78%" class="vtable">
				<input id="rasamednsasdhcp6" name="rasamednsasdhcp6" type="checkbox" value="yes" <?php if ($pconfig['rasamednsasdhcp6']) { echo "checked='checked'"; } ?> />
				<strong><?= gettext("Use same settings as DHCPv6 server"); ?></strong>
			</td>
			</tr>

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

<script type="text/javascript">
//<![CDATA[
	jQuery(function ($) {
		var $rasamednsasdhcp6 = $("#rasamednsasdhcp6");
		var $triggered_checkboxes = $("#radns1, #radns2, #radomainsearchlist");
		if ($rasamednsasdhcp6.length !== 1) { return; }
		var onchange = function () {
			var checked = $rasamednsasdhcp6.is(":checked");
			if (checked) {
				$triggered_checkboxes.each(function () { this.disabled = true; });
			} else {
				$triggered_checkboxes.each(function () { this.disabled = false; });
			}
		};
		$rasamednsasdhcp6.bind("change", onchange);
		onchange();
	});

	var addressarray = <?= json_encode(get_alias_list("host", "network", "openvpn", "urltable")); ?>;
	var objAlias = [];
	function createAutoSuggest () {
		<?php for ($i = 0; $i < $counter; $i += 1) { ?>
			objAlias.push(new AutoSuggestControl(document.getElementById('subnet_address<?= $i ?>'), new StateSuggestions(addressarray)));
		<?php } ?>
		new AutoSuggestControl(document.getElementById('radns1'), new StateSuggestions(addressarray));
		new AutoSuggestControl(document.getElementById('radns2'), new StateSuggestions(addressarray));
	}
	setTimeout(createAutoSuggest, 500);
//]]>
</script>

<?php include("fend.inc"); ?>
</body>
</html>
