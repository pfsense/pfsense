<?php 
/* $Id$ */
/*
	system_gateway_groups_edit.php
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
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-system-gateways-editgatewaygroups
##|*NAME=System: Gateways: Edit Gateway Groups page
##|*DESCR=Allow access to the 'System: Gateways: Edit Gateway Groups' page.
##|*MATCH=system_gateway_groups_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_gateway_groups.php');

if (!is_array($config['gateways']['gateway_group']))
	$config['gateways']['gateway_group'] = array();

$a_gateway_groups = &$config['gateways']['gateway_group'];
$a_gateways = return_gateways_array();
$carplist = get_configured_carp_interface_list();

$categories = array('down' => gettext("Member Down"),
                    'downloss' => gettext("Packet Loss"),
                    'downlatency' => gettext("High Latency"),
                    'downlosslatency' => gettext("Packet Loss or High Latency"));

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($_GET['dup']) && is_numericint($_GET['dup']))
	$id = $_GET['dup'];

if (isset($id) && $a_gateway_groups[$id]) {
	$pconfig['name'] = $a_gateway_groups[$id]['name'];
	$pconfig['item'] = &$a_gateway_groups[$id]['item'];
	$pconfig['descr'] = $a_gateway_groups[$id]['descr'];
	$pconfig['trigger'] = $a_gateway_groups[$id]['trigger'];
}

if (isset($_GET['dup']) && is_numericint($_GET['dup']))
	unset($id);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	
	if (! isset($_POST['name'])) {
		$input_errors[] = gettext("A valid gateway group name must be specified.");
	}
	if (! is_validaliasname($_POST['name'])) {
		$input_errors[] = gettext("The gateway name must not contain invalid characters.");
	}

	if (isset($_POST['name'])) {
		/* check for overlaps */
		if(is_array($a_gateway_groups)) {
			foreach ($a_gateway_groups as $gateway_group) {
				if (isset($id) && ($a_gateway_groups[$id]) && ($a_gateway_groups[$id] === $gateway_group)) {
					if ($gateway_group['name'] != $_POST['name'])
						$input_errors[] = gettext("Changing name on a gateway group is not allowed.");
					continue;
				}

				if ($gateway_group['name'] == $_POST['name']) {
					$input_errors[] = sprintf(gettext('A gateway group with this name "%s" already exists.'), $_POST['name']);
					break;
				}
			}
		}
	}

	/* Build list of items in group with priority */
	$pconfig['item'] = array();
	foreach($a_gateways as $gwname => $gateway) {
		if($_POST[$gwname] > 0) {
			$vipname = "{$gwname}_vip";
			/* we have a priority above 0 (disabled), add item to list */
			$pconfig['item'][] = "{$gwname}|{$_POST[$gwname]}|{$_POST[$vipname]}";
		}
		/* check for overlaps */
		if ($_POST['name'] == $gwname)
			$input_errors[] = sprintf(gettext('A gateway group cannot have the same name with a gateway "%s" please choose another name.'), $_POST['name']);

	}
	if(count($pconfig['item']) == 0)
		$input_errors[] = gettext("No gateway(s) have been selected to be used in this group");

	if (!$input_errors) {
		$gateway_group = array();
		$gateway_group['name'] = $_POST['name'];
		$gateway_group['item'] = $pconfig['item'];
		$gateway_group['trigger'] = $_POST['trigger'];
		$gateway_group['descr'] = $_POST['descr'];

		if (isset($id) && $a_gateway_groups[$id])
			$a_gateway_groups[$id] = $gateway_group;
		else
			$a_gateway_groups[] = $gateway_group;
		
		mark_subsystem_dirty('staticroutes');
		mark_subsystem_dirty('gwgroup.' . $gateway_group['name']);
		
		write_config();
		
		header("Location: system_gateway_groups.php");
		exit;
	}
}

$pgtitle = array(gettext("System"),gettext("Gateways"),gettext("Edit gateway group"));
$shortcut_section = "gateway-groups";

function build_gateway_protocol_map (&$a_gateways) {
	$result = array();
	foreach ($a_gateways as $gwname => $gateway) {
		$result[$gwname] = $gateway['ipprotocol'];
	}
	return $result;
}

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php
$gateway_protocol = build_gateway_protocol_map($a_gateways);
$gateway_array    = array_keys($a_gateways);
$protocol_array   = array_values($gateway_protocol);
$protocol_array   = array_values(array_unique($gateway_protocol));
?>
<script type="text/javascript">
//<![CDATA[
jQuery(function ($) {
	var gateway_protocol = <?= json_encode($gateway_protocol) ?>;
	var gateways         = <?= json_encode($gateway_array) ?>;
	var protocols        = <?= json_encode($protocol_array) ?>;
	if (protocols.length <= 1) { return; }

	var update_gateway_visibilities = function () {
		var which_protocol_to_show = undefined;
		$.each(gateways, function (i, gateway) {
			var $select = $("#" + gateway);
			var value = $select.val();
			var protocol = gateway_protocol[gateway];
			if (value !== '0' /* i.e., an option is selected */) {
				if (which_protocol_to_show === undefined) {
					which_protocol_to_show = protocol;
				}
				else if (which_protocol_to_show !== protocol) {
					which_protocol_to_show = 'ALL OF THEM'; // this shouldn't happen
				}
			}
		});
		if (which_protocol_to_show !== undefined && which_protocol_to_show !== 'ALL OF THEM') {
			$.each(gateways, function (i, gateway) {
				var protocol = gateway_protocol[gateway];
				var $row = $("tr.gateway_row#" + gateway + "_row");
				if (protocol === which_protocol_to_show) {
					if ($row.is(":hidden")) {
						$row.fadeIn('slow');
					}
				} else {
					if (!$row.is(":hidden")) {
						$row.fadeOut('slow');
					}
				}
			});
		} else {
			$("tr.gateway_row").each(function () {
				if ($(this).is(":hidden")) {
					$(this).fadeIn('slow');
				}
			});
		}
	};
	$("select.gateway_tier_selector").change(update_gateway_visibilities);
	update_gateway_visibilities();
});
//]]>
</script>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="system_gateway_groups_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="system groups edit">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit gateway group entry"); ?></td>
		</tr>	
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Group Name"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="name" type="text" class="formfld unknown" id="name" size="20" value="<?=htmlspecialchars($pconfig['name']);?>" />
                    <br /> <span class="vexpl"><?=gettext("Group Name"); ?></span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Gateway Priority"); ?></td>
                  <td width="78%" class="vtable">
			<table border="0" cellpadding="6" cellspacing="0" summary="gateway priority">
			<tr>
				<td class="listhdrr">Gateway</td>
				<td class="listhdrr">Tier</td>
				<td class="listhdrr">Virtual IP</td>
				<td class="listhdrr">Description</td>
			</tr>
		<?php
			foreach($a_gateways as $gwname => $gateway) {
				if(!empty($pconfig['item'])) {
					$af = explode("|", $pconfig['item'][0]);
					$family = $a_gateways[$af[0]]['ipprotocol'];
					if($gateway['ipprotocol'] != $family)
						continue;
				}
				$interface = $gateway['friendlyiface'];
				$selected = array();
				foreach((array)$pconfig['item'] as $item) {
					$itemsplit = explode("|", $item);
					if($itemsplit[0] == $gwname) {
						$selected[$itemsplit[1]] = "selected=\"selected\"";
						break;
					} else {
						$selected[0] = "selected=\"selected\"";
					}
				}
				$tr_id = $gwname . "_row";
				echo "<tr class='gateway_row' id='{$tr_id}'>\n";
				echo "<td class='listlr'>";
				echo "<strong>{$gateway['name']} </strong>";
				echo "</td><td class='listr'>";
				echo "<select name='{$gwname}' class='gateway_tier_selector formfldselect' id='{$gwname}'>\n";
				echo "<option value='0' $selected[0] >" . gettext("Never") . "</option>\n";
				echo "<option value='1' $selected[1] >" . gettext("Tier 1") . "</option>\n";
				echo "<option value='2' $selected[2] >" . gettext("Tier 2") . "</option>\n";
				echo "<option value='3' $selected[3] >" . gettext("Tier 3") . "</option>\n";
				echo "<option value='4' $selected[4] >" . gettext("Tier 4") . "</option>\n";
				echo "<option value='5' $selected[5] >" . gettext("Tier 5") . "</option>\n";
				echo "</select>\n";
				echo "</td>";

				$selected = array();
				foreach((array)$pconfig['item'] as $item) {
					$itemsplit = explode("|", $item);
					if($itemsplit[0] == $gwname) {
						$selected[$itemsplit[2]] = "selected=\"selected\"";
						break;
					} else {
						$selected['address'] = "selected=\"selected\"";
					}
				}
				echo "<td class='listr'>";
				echo "<select name='{$gwname}_vip' class='gateway_vip_selector formfldselect' id='{$gwname}_vip'>\n";
				echo "<option value='address' {$selected['address']} >" . gettext("Interface Address") . "</option>\n";
				foreach($carplist as $vip => $address) {
					echo "<!-- $vip - $address - $interface -->\n";
					if(!preg_match("/^{$interface}_/i", $vip))
						continue;
					if(($gateway['ipprotocol'] == "inet") && (!is_ipaddrv4($address)))
						continue;
					if(($gateway['ipprotocol'] == "inet6") && (!is_ipaddrv6($address)))
						continue;
					echo "<option value='{$vip}' $selected[$vip] >$vip - $address</option>\n";
				}
				echo "</select></td>";
				echo "<td class='listr'><strong>{$gateway['descr']}&nbsp;</strong>";
				echo "</td></tr>";
		 	}
		?>
			</table>
			<br /><span class="vexpl">
			<strong><?=gettext("Link Priority"); ?></strong> <br />
			<?=gettext("The priority selected here defines in what order failover and balancing of links will be done. " .
			"Multiple links of the same priority will balance connections until all links in the priority will be exhausted. " .
			"If all links in a priority level are exhausted we will use the next available link(s) in the next priority level.") ?>
			<br />
			<strong><?=gettext("Virtual IP"); ?></strong> <br />
			<?=gettext("The virtual IP field selects what (virtual) IP should be used when this group applies to a local Dynamic DNS, IPsec or OpenVPN endpoint") ?>
			</span><br />
		   </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Trigger Level"); ?></td>
                  <td width="78%" class="vtable">
			<select name='trigger' class='formfldselect trigger_level_selector' id='trigger'>
			<?php
				foreach ($categories as $category => $categoryd) {
				        echo "<option value=\"$category\"";
				        if ($category == $pconfig['trigger']) echo " selected=\"selected\"";
					echo ">" . htmlspecialchars($categoryd) . "</option>\n";
				}
			?>
			</select>
                    <br /> <span class="vexpl"><?=gettext("When to trigger exclusion of a member"); ?></span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                    <br /> <span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed)."); ?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
                    <input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
                    <?php if (isset($id) && $a_gateway_groups[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
