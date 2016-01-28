<?php
/*
	firewall_rules.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-firewall-rules
##|*NAME=Firewall: Rules
##|*DESCR=Allow access to the 'Firewall: Rules' page.
##|*MATCH=firewall_rules.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("ipsec.inc");
require_once("shaper.inc");

$pgtitle = array(gettext("Firewall"), gettext("Rules"));
$shortcut_section = "firewall";

foreach (pfSense_get_pf_rules() as $line) {
	foreach ($line as $key=>$value) {
		switch ($key) {
			case 'bytes':
			case 'states':
			case 'packets':
			case 'evaluations':
				$rules_count_array[$line['tracker']][$key] += $value;
				break;
			case 'id':
				if (isset($rules_count_array[$line['tracker']]['RuleId'])) {
					$rules_count_array[$line['tracker']]['RuleId'] .= "|{$value}";
				} else {
					$rules_count_array[$line['tracker']]['RuleId'] = $value;
				}
				break;
			case 'label':
				$rules_count_array[$line['tracker']][$key] = $value;
				break;
		}
	}
}

if ($_POST['action'] != "KillRuleStates") {
	?>
	<div id="RuleStates" class="modal fade" role="dialog">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>

					<h3 class="modal-title" id="myModalLabel"><?=gettext("Rule States")?></h3>
				</div>

				<div class="modal-body" id="RuleStates-modal-body">
				</div>
			</div>
		</div>
	</div>
	<?php
}

//Get rule Hit count
function get_rule_ht($tracker, $sum_ht=array(), $buit_in_rule=false) {
	global $g, $rules_count_array;

	//check if there is previous values
	$packets = (isset($sum_ht['packets']) ? $sum_ht['packets'] : 0);
	$states = (isset($sum_ht['bytes']) ? $sum_ht['bytes'] : 0);
	$rules_title = (isset($sum_ht['title']) ? $sum_ht['title'] : "");
	$rules_id = array();
	if (preg_match("/\s+/",$tracker)) {
		$rules_title .= "$tracker\n";
	}
	if ($buit_in_rule == true) {
		//get tracker based on builtin description rule
		foreach ($rules_count_array as $rule_tracker => $rule_info) {
			if ($rule_info['label']==$tracker) {
				$tracker = $rule_tracker;
				break;
			}
		}
	}
	$tracker = (int) $tracker;
	if (is_array($rules_count_array) && array_key_exists($tracker,$rules_count_array)) {
		$packets += $rules_count_array[$tracker]['packets'];
		$states += $rules_count_array[$tracker]['states'];
		foreach ($rules_count_array[$tracker] as $rck => $rcv) {
			switch($rck) {
				case "label":
					$label = $rcv;
					break;
				case "RuleId":
					$ruleid = $rcv;
					break;
				default:
					$rules_title .= "$rck: ".bd_nice_number($rcv)."\n";
			}
		}
	}
	$hitcount = bd_nice_number($packets)."/".bd_nice_number($states);
	if ($states > 0) {
		//NEED HELP HERE fixing width for states popup
		$html  = "<a href=\"#\" onmouseover=\"showState('{$ruleid}','{$hitcount}','{$label}');\" data-toggle=\"modal\" data-target=\"#RuleStates\" role=\"button\" aria-expanded=\"false\">";
		$html .= "<span onmouseover=\"showState('{$ruleid}','{$hitcount}','{$label}');\">{$hitcount}</span></a>";
	} else {
		$html="<span id=\"{$label}\" class=\"{$label}\">{$hitcount}</span>";
	}
	return (array(	'title'=> $rules_title,
					'packets' =>$packets,
					'states' =>$states,
					'html' =>$html,
					'id' => $label
	));
}

function bd_nice_number ($a) {
	$unim = array("","K","M","G","T","P");
	$c = 0;
	while ($a>=1024) {
		$c++;
		$a = $a/1024;
	}
	return number_format($a,($c ? 2 : 0),",",".").$unim[$c];
}

function delete_nat_association($id) {
	global $config;

	if (!$id || !is_array($config['nat']['rule'])) {
		return;
	}

	$a_nat = &$config['nat']['rule'];

	foreach ($a_nat as &$natent) {
		if ($natent['associated-rule-id'] == $id) {
			$natent['associated-rule-id'] = '';
		}
	}
}

if (!is_array($config['filter']['rule'])) {
	$config['filter']['rule'] = array();
}

filter_rules_sort();
$a_filter = &$config['filter']['rule'];

$if = $_GET['if'];

if ($_POST['if']) {
	$if = $_POST['if'];
}

$ifdescs = get_configured_interface_with_descr();

/* add group interfaces */
if (is_array($config['ifgroups']['ifgroupentry'])) {
	foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
		if (have_ruleint_access($ifgen['ifname'])) {
			$iflist[$ifgen['ifname']] = $ifgen['ifname'];
		}
	}
}

foreach ($ifdescs as $ifent => $ifdesc) {
	if (have_ruleint_access($ifent)) {
		$iflist[$ifent] = $ifdesc;
	}
}

if ($config['l2tp']['mode'] == "server") {
	if (have_ruleint_access("l2tp")) {
		$iflist['l2tp'] = gettext("L2TP VPN");
	}
}

if (is_array($config['pppoes']['pppoe'])) {
	foreach ($config['pppoes']['pppoe'] as $pppoes) {
		if (($pppoes['mode'] == 'server') && have_ruleint_access("pppoe")) {
			$iflist['pppoe'] = gettext("PPPoE Server");
		}
	}
}

/* add ipsec interfaces */
if (ipsec_enabled() && have_ruleint_access("enc0")) {
	$iflist["enc0"] = gettext("IPsec");
}

/* add openvpn/tun interfaces */
if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$iflist["openvpn"] = gettext("OpenVPN");
}

if (!$if || !isset($iflist[$if])) {
	if ("any" == $if) {
		$if = "FloatingRules";
	} else if ("FloatingRules" != $if) {
		if (isset($iflist['wan'])) {
			$if = "wan";
		} else {
			$if = "FloatingRules";
		}
	}
}

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval = filter_configure();

		clear_subsystem_dirty('filter');

		$savemsg = sprintf(gettext("The settings have been applied. The firewall rules are now reloading in the background.<br />You can also %s monitor %s the reload progress"),
									"<a href='status_filter_reload.php'>", "</a>");
	}

	/* handle AJAX operations */
	if ($_POST['action'] == "KillRuleStates") {
		if (isset($_POST['label'])) {
			$rulelabel=html_entity_decode($_POST['label']);
			$cnt_pfctlk=array();
			$rule_ids=explode("|",$rulelabel);
			foreach ($rule_ids as $rule_id) {
				$cnt_pfctlk[]="Rule ID: {$rule_id}";
				exec("/sbin/pfctl -k id -k " . escapeshellarg($rule_id) ." 2>&1",$cnt_pfctlk);
			}
		} else {
			echo gettext("invalid input");
		}

		if (!empty($cnt_pfctlk)) {
			foreach ($cnt_pfctlk as $line) {
				if (preg_match("/Rule ID/",$line)) {
					print "$line ";
				}else{
					print "$line\n";
				}
			}
		}
		return;
	}

	if($_POST['action'] == "ShowRuleStates") {
		$th1="<th>";
		$th2="<th>";
		//$td2="<td class=\'vncell\' style=\'background: #FFFFFF;color: #000000;\'>";
		$resp  = "<table class='table table-striped table-hover table-condensed'>";
		$resp .= "<thead>";
		$resp .= "<tr>";
		$resp .= $th1 . gettext("Proto") . "</th>";
		$resp .= $th1 . gettext("Source -> Router -> Destination") . "</th>";
		$resp .= $th1 . gettext("State") . "</th>";
		$resp .= $th1 . gettext("Packets") . "</th>";
		$resp .= $th1 . gettext("bytes") . "</th>";
		$resp .= "</tr>";
		$resp .= "</thead>";
		$resp .= "<tbody>";
		$state_count = 0;
		$remove_ids = "";
		if (isset($_POST['hitcount'])) {
			$hitcount=html_entity_decode($_POST['hitcount']);
		}

		if (isset($_POST['ruleid'])) {
			$ruleid=html_entity_decode($_POST['ruleid']);
			$cnt_pfctls=array();
			exec("/sbin/pfctl -vvss | /usr/bin/grep -EB2 -A1 \"rule ({$ruleid})\"",$cnt_pfctls);
		} else {
			echo gettext("invalid input");
		}

		if (isset($_POST['title'])) {
			$hittitle=html_entity_decode($_POST['title']);
		}

		if (!empty($cnt_pfctls)) {
			foreach($cnt_pfctls as $line) {
				if (preg_match("/^\w+\s+(\w+)\s+(.*)(.-.)(.*)\s+(\w+:\w+)/",$line,$mcon)) {
					$state_count++;
					$resp .= "<tr>{$th2}{$mcon[1]}</th>{$th2}{$mcon[2]}{$mcon[3]}{$mcon[4]}</th>{$th2}{$mcon[5]}</th>";
				} else if (preg_match("/age.*, (\S+) pkts, (\S+) bytes, rule (\d+)/",$line,$mrule)) {
					list($pkt1,$pkt2)=split(":",$mrule[1],2);
					list($bt1,$bt2)=split(":",$mrule[2],2);
					$resp .= "{$th2}".bd_nice_number($pkt1)." / ".bd_nice_number($pkt2)."</th>{$th2}".bd_nice_number($bt1)." / ".bd_nice_number($bt2)."</th></tr>";
				} else if (preg_match("/id:\s+(\w+)\s+creatorid:\s+(\w+)/",$line,$mid)) {
					$remove_ids .= ($remove_ids == "" ? $mid[1] : "|{$mid[1]}");
				}

			}
		}
		$resp .= "</tbody>";
		$resp .= "</table>";
		//
		$resp .= '<div class="modal-footer">';
		$resp .= '<button type="button" class="btn btn-default" data-dismiss="modal">' . gettext("Close") . '</button>';
		$resp .= '<button type="button" id="clearstates" class="btn btn-primary" onclick="removeState(' . "'{$remove_ids}')\">" . gettext("Kill Rule States") . "</button>";
		$resp .= '</div>';
		print($resp);
		return;
	}
}

if ($_GET['act'] == "del") {
	if ($a_filter[$_GET['id']]) {
		if (!empty($a_filter[$_GET['id']]['associated-rule-id'])) {
			delete_nat_association($a_filter[$_GET['id']]['associated-rule-id']);
		}
		unset($a_filter[$_GET['id']]);
		if (write_config()) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}

// Handle save msg if defined
if ($_REQUEST['savemsg']) {
	$savemsg = htmlentities($_REQUEST['savemsg']);
}

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	$deleted = false;

	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			delete_nat_association($a_filter[$rulei]['associated-rule-id']);
			unset($a_filter[$rulei]);
			$deleted = true;
		}

		if ($deleted) {
			if (write_config()) {
				mark_subsystem_dirty('filter');
			}
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} else if ($_GET['act'] == "toggle") {
	if ($a_filter[$_GET['id']]) {
		if (isset($a_filter[$_GET['id']]['disabled'])) {
			unset($a_filter[$_GET['id']]['disabled']);
		} else {
			$a_filter[$_GET['id']]['disabled'] = true;
		}
		if (write_config()) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} else if ($_POST['order-store']) {
	/* update rule order, POST[rule] is an array of ordered IDs */
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
		$a_filter_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_filter_new[] = $a_filter[$id];
		}

		$a_filter = $a_filter_new;

		$config['filter']['separator'][strtolower($if)] = "";

		if ($_POST['separator']) {
			$idx = 0;
			foreach ($_POST['separator'] as $separator) {
				$config['filter']['separator'][strtolower($separator['if'])]['sep' . $idx++] = $separator;
			}
		}

		if (write_config()) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}

$tab_array = array(array(gettext("Floating"), ("FloatingRules" == $if), "firewall_rules.php?if=FloatingRules"));

foreach ($iflist as $ifent => $ifname) {
	$tab_array[] = array($ifname, ($ifent == $if), "firewall_rules.php?if={$ifent}");
}

foreach ($tab_array as $dtab) {
	if ($dtab[1]) {
		$bctab = $dtab[0];
		break;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("Rules"), $bctab);
$shortcut_section = "firewall";

include("head.inc");
$nrules = 0;

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('filter')) {
	print_apply_box(gettext("The firewall rule configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));
}

display_top_tabs($tab_array);

$showantilockout = false;
$showprivate = false;
$showblockbogons = false;

if (!isset($config['system']['webgui']['noantilockout']) &&
    (((count($config['interfaces']) > 1) && ($if == 'lan')) ||
    ((count($config['interfaces']) == 1) && ($if == 'wan')))) {
	$showantilockout = true;
}

if (isset($config['interfaces'][$if]['blockpriv'])) {
	$showprivate = true;
}

if (isset($config['interfaces'][$if]['blockbogons'])) {
	$showblockbogons = true;
}

?>

<form method="post">
<script type="text/javascript">
//<![CDATA[
	function removeState(label,rtp) {
		myrtp = rtp;
		mylabel = label;
		var busy = function(index,icon) {
			jQuery(icon).bind("onclick","");
			jQuery(icon).css("cursor","wait");
		}

		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>",
			{
				type: "post",
				data: {
					action: "KillRuleStates",
					label: label
				},
				complete: RemoveComplete
			}
		);
	}
	function showState(ruleid,hitcount,label) {
		myruleid = ruleid;
		mylabel = label;
		myhitcount = hitcount;
		var busy = function(index,icon) {
			jQuery(icon).bind("onclick","");
			jQuery(icon).css("cursor","wait");
		}

		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>",
			{
				type: "post",
				data: {
					action: "ShowRuleStates",
					ruleid: myruleid,
					hitcount: myhitcount,
					label: label
				},
				complete: ShowComplete
			}
		);
	}
	function RemoveComplete(req) {
		alert(req.responseText);
		jQuery('span[id="'+ mylabel + '"]').each(
			function(index,row) { jQuery(row).html(myrtp + "/0"); }
			);
		return;
	}
	function ShowComplete(req) {
			jQuery('div[id="RuleStates-modal-body"]').each(
					function(index,row) { jQuery(row).html(req.responseText); }
					);
	return;
	}
//]]>
</script>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Rules (Drag to change order)")?></h2></div>
		<div id="mainarea" class="table-responsive panel-body">
			<table id="ruletable" class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th><!-- checkbox --></th>
						<th><!-- status icons --></th>
						<th><?=gettext("Hits");?></th>
						<th><?=gettext("Protocol")?></th>
						<th><?=gettext("Source")?></th>
						<th><?=gettext("Port")?></th>
						<th><?=gettext("Destination")?></th>
						<th><?=gettext("Port")?></th>
						<th><?=gettext("Gateway")?></th>
						<th><?=gettext("Queue")?></th>
						<th><?=gettext("Schedule")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>

<?php if ($showblockbogons || $showantilockout || $showprivate) :
?>
				<tbody>
<?php
		// Show the anti-lockout rule if it's enabled, and we are on LAN with an if count > 1, or WAN with an if count of 1.
		if ($showantilockout):
			$alports = implode('<br />', filter_get_antilockout_ports(true));
			$rule_hit_count=get_rule_ht("anti-lockout rule",array(),true);
?>
					<tr id="antilockout">
						<td></td>
						<td title="<?=gettext("traffic is passed")?>"><i class="fa fa-check text-success"></i></td>
						<td id="<?=$rule_hit_count['id']; ?>" title="<?=$rule_hit_count['title']; ?>"><?=$rule_hit_count['html']; ?></td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td><?=$iflist[$if];?> Address</td>
						<td><?=$alports?></td>
						<td>*</td>
						<td>*</td>
						<td></td>
						<td><?=gettext("Anti-Lockout Rule");?></td>
						<td>
							<a href="system_advanced_admin.php" title="<?=gettext("Settings");?>"><i class="fa fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
<?php 	if ($showprivate):
			$rule_hit_count=get_rule_ht("Block private networks from " . strtoupper($if) . " block 192.168/16",array(),true);
			$rule_hit_count=get_rule_ht("Block private networks from " . strtoupper($if) . " block 127/8",$rule_hit_count,true);
			$rule_hit_count=get_rule_ht("Block private networks from " . strtoupper($if) . " block 172.16/12",$rule_hit_count,true);
			$rule_hit_count=get_rule_ht("Block private networks from " . strtoupper($if) . " block 10/8",$rule_hit_count,true);
?>
					<tr id="frrfc1918">
						<td></td>
						<td title="<?=gettext("traffic is blocked")?>"><i class="fa fa-times text-danger"></i></td>
						<td id="<?=$rule_hit_count['id']; ?>" title="<?=$rule_hit_count['title'];?>"><?=$rule_hit_count['html']; ?></td>
						<td>*</td>
						<td><?=gettext("RFC 1918 networks");?></td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td></td>
						<td><?=gettext("Block private networks");?></td>
						<td>
							<a href="interfaces.php?if=<?=htmlspecialchars($if)?>" title="<?=gettext("Settings");?>"><i class="fa fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
<?php 	if ($showblockbogons):
			$rule_hit_count=get_rule_ht("block bogon IPv4 networks from ".strtoupper($if),array(),true);
			$rule_hit_count=get_rule_ht("block bogon IPv6 networks from ".strtoupper($if),$rule_hit_count,true);
?>
					<tr id="frrfc1918">
					<td></td>
						<td title="<?=gettext("traffic is blocked")?>"><i class="fa fa-times text-danger"></i></td>
						<td id="<?=$rule_hit_count['id']; ?>" title="<?=$rule_hit_count['title'] ?>"><?=$rule_hit_count['html']; ?></td>
						<td>*</td>
						<td><?=gettext("Reserved/not assigned by IANA");?></td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td></td>
						<td><?=gettext("Block bogon networks");?></td>
						<td>
							<a href="interfaces.php?if=<?=htmlspecialchars($if)?>" title="<?=gettext("Settings");?>"><i class="fa fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
			</tbody>
<?php endif;?>
			<tbody class="user-entries">
<?php
$nrules = 0;
$seps = 0;

// There can be a separator before any rules are listed
if ($config['filter']['separator'][strtolower($if)]['sep0']['row'][0] == "fr-1") {
	print('<tr class="ui-sortable-handle separator">' .
		'<td bgcolor="#cce5ff" colspan="11">' . '<font color="#002699">' . $config['filter']['separator'][strtolower($if)]['sep0']['text'] . '</font></td>' .
		'<td  bgcolor="#cce5ff"><a href="#"><i class="fa fa-trash no-confirm sepdel" title="delete this separator"></i></a></td>' .
		'</tr>' . "\n");
}

for ($i = 0; isset($a_filter[$i]); $i++):
	$filterent = $a_filter[$i];

	if (($filterent['interface'] != $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" != $if)) {
		$display = 'style="display: none;"';
	} else {
		$display = "";
	}

?>
					<tr id="fr<?=$nrules;?>" <?=$display?> onClick="fr_toggle(<?=$nrules;?>)" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';" <?=(isset($filterent['disabled']) ? ' class="disabled"' : '')?>>
						<td>
							<input type="checkbox" id="frc<?=$nrules;?>" onClick="fr_toggle(<?=$nrules;?>)" name="rule[]" value="<?=$i;?>"/>
						</td>

	<?php
		if ($filterent['type'] == "block") {
			$iconfn = "times text-danger";
			$title_text = gettext("traffic is blocked");
		} else if ($filterent['type'] == "reject") {
			$iconfn = "hand-stop-o text-warning";
			$title_text = gettext("traffic is rejected");
		} else if ($filterent['type'] == "match") {
			$iconfn = "filter";
			$title_text = gettext("traffic is matched");
		} else {
			$iconfn = "check text-success";
			$title_text = gettext("traffic is passed");
		}
	?>
						<td title="<?=$title_text?>">

							<i class="fa fa-<?=$iconfn?>"></i>
	<?php
		$isadvset = firewall_check_for_advanced_options($filterent);
		if ($isadvset) {
			print '<i class="fa fa-cog" title="'. gettext("advanced setting") .': '. $isadvset .'"></i>';
		}

		if (isset($filterent['log'])) {
			print '<i class="fa fa-tasks" title="'. gettext("traffic is logged") .'"></i>';
		}
	?>
						</td>
	<?php
		$alias = rule_columns_with_alias(
			$filterent['source']['address'],
			pprint_port($filterent['source']['port']),
			$filterent['destination']['address'],
			pprint_port($filterent['destination']['port'])
		);

		//build Schedule popup box
		$a_schedules = &$config['schedules']['schedule'];
		$schedule_span_begin = "";
		$schedule_span_end = "";
		$sched_caption_escaped = "";
		$sched_content = "";
		$schedstatus = false;
		$dayArray = array (gettext('Mon'), gettext('Tues'), gettext('Wed'), gettext('Thur'), gettext('Fri'), gettext('Sat'), gettext('Sun'));
		$monthArray = array (gettext('January'), gettext('February'), gettext('March'), gettext('April'), gettext('May'), gettext('June'), gettext('July'), gettext('August'), gettext('September'), gettext('October'), gettext('November'), gettext('December'));
		if ($config['schedules']['schedule'] != "" && is_array($config['schedules']['schedule'])) {
			$idx = 0;
			foreach ($a_schedules as $schedule) {
				if ($schedule['name'] == $filterent['sched']) {
					$schedstatus = filter_get_time_based_rule_status($schedule);

					foreach ($schedule['timerange'] as $timerange) {
						$tempFriendlyTime = "";
						$tempID = "";
						$firstprint = false;
						if ($timerange) {
							$dayFriendly = "";
							$tempFriendlyTime = "";

							//get hours
							$temptimerange = $timerange['hour'];
							$temptimeseparator = strrpos($temptimerange, "-");

							$starttime = substr ($temptimerange, 0, $temptimeseparator);
							$stoptime = substr ($temptimerange, $temptimeseparator+1);

							if ($timerange['month']) {
								$tempmontharray = explode(",", $timerange['month']);
								$tempdayarray = explode(",", $timerange['day']);
								$arraycounter = 0;
								$firstDayFound = false;
								$firstPrint = false;
								foreach ($tempmontharray as $monthtmp) {
									$month = $tempmontharray[$arraycounter];
									$day = $tempdayarray[$arraycounter];

									if (!$firstDayFound) {
										$firstDay = $day;
										$firstmonth = $month;
										$firstDayFound = true;
									}

									$currentDay = $day;
									$nextDay = $tempdayarray[$arraycounter+1];
									$currentDay++;
									if (($currentDay != $nextDay) || ($tempmontharray[$arraycounter] != $tempmontharray[$arraycounter+1])) {
										if ($firstPrint) {
											$dayFriendly .= ", ";
										}
										$currentDay--;
										if ($currentDay != $firstDay) {
											$dayFriendly .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
										} else {
											$dayFriendly .=	 $monthArray[$month-1] . " " . $day;
										}
										$firstDayFound = false;
										$firstPrint = true;
									}
									$arraycounter++;
								}
							} else {
								$tempdayFriendly = $timerange['position'];
								$firstDayFound = false;
								$tempFriendlyDayArray = explode(",", $tempdayFriendly);
								$currentDay = "";
								$firstDay = "";
								$nextDay = "";
								$counter = 0;
								foreach ($tempFriendlyDayArray as $day) {
									if ($day != "") {
										if (!$firstDayFound) {
											$firstDay = $tempFriendlyDayArray[$counter];
											$firstDayFound = true;
										}
										$currentDay =$tempFriendlyDayArray[$counter];
										//get next day
										$nextDay = $tempFriendlyDayArray[$counter+1];
										$currentDay++;
										if ($currentDay != $nextDay) {
											if ($firstprint) {
												$dayFriendly .= ", ";
											}
											$currentDay--;
											if ($currentDay != $firstDay) {
												$dayFriendly .= $dayArray[$firstDay-1] . " - " . $dayArray[$currentDay-1];
											} else {
												$dayFriendly .= $dayArray[$firstDay-1];
											}
											$firstDayFound = false;
											$firstprint = true;
										}
										$counter++;
									}
								}
							}
							$timeFriendly = $starttime . " - " . $stoptime;
							$description = $timerange['rangedescr'];
							$sched_content .= $dayFriendly . "; " . $timeFriendly . "<br />";
						}
					}
					#FIXME
					$sched_caption_escaped = str_replace("'", "\'", $schedule['descr']);
					$schedule_span_begin = '<a href="/firewall_schedule_edit.php?id=' . $idx . '" data-toggle="popover" data-trigger="hover focus" title="' . $schedule['name'] . '" data-content="' .
						$sched_caption_escaped . '" data-html="true">';
					$schedule_span_end = "</a>";
				}
			}
			$idx++;
		}
		$printicon = false;
		$alttext = "";
		$image = "";
		if (!isset($filterent['disabled'])) {
			if ($schedstatus) {
				if ($filterent['type'] == "block" || $filterent['type'] == "reject") {
					$image = "times-circle";
					$dispcolor = "text-danger";
					$alttext = gettext("Traffic matching this rule is currently being denied");
				} else {
					$image = "play-circle";
					$dispcolor = "text-success";
					$alttext = gettext("Traffic matching this rule is currently being allowed");
				}
				$printicon = true;
			} else if ($filterent['sched']) {
				if ($filterent['type'] == "block" || $filterent['type'] == "reject") {
					$image = "times-circle";
				} else {
					$image = "play-circle";
				}
				$alttext = gettext("This rule is not currently active because its period has expired");
				$dispcolor = "text-warning";
				$printicon = true;
			}
		}
		$rule_hit_count=get_rule_ht($filterent['tracker']);
	?>
				<td id="<?=$rule_hit_count['id']; ?>" title="<?=$rule_hit_count['title'] ?>"><?=$rule_hit_count['html']; ?></td>
				<td>
	<?php
		if (isset($filterent['ipprotocol'])) {
			switch ($filterent['ipprotocol']) {
				case "inet":
					echo "IPv4 ";
					break;
				case "inet6":
					echo "IPv6 ";
					break;
				case "inet46":
					echo "IPv4+6 ";
					break;
			}
		} else {
			echo "IPv4 ";
		}

		if (isset($filterent['protocol'])) {
			echo strtoupper($filterent['protocol']);

			if (strtoupper($filterent['protocol']) == "ICMP" && !empty($filterent['icmptype'])) {
				echo ' <span style="cursor: help;" title="' . gettext('ICMP type') . ': ' .
					($filterent['ipprotocol'] == "inet6" ? $icmp6types[$filterent['icmptype']] : $icmptypes[$filterent['icmptype']]) .
					'"><u>';
				echo $filterent['icmptype'];
				echo '</u></span>';
			}
		} else echo "*";

	?>
						</td>
						<td>
							<?php if (isset($alias['src'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
									<?=htmlspecialchars(pprint_address($filterent['source']))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_address($filterent['source']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['srcport'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['srcport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['srcport'])?>" data-html="true">
									<?=htmlspecialchars(pprint_port($filterent['source']['port']))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_port($filterent['source']['port']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['dst'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
									<?=htmlspecialchars(pprint_address($filterent['destination']))?>
								</a>
							<?php else :?>
								<?=htmlspecialchars(pprint_address($filterent['destination']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['dstport'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['dstport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
									<?=htmlspecialchars(pprint_port($filterent['destination']['port']))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_port($filterent['destination']['port']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($config['interfaces'][$filterent['gateway']]['descr'])):?>
								<?=htmlspecialchars($config['interfaces'][$filterent['gateway']]['descr'])?>
							<?php else: ?>
								<?=htmlspecialchars(pprint_port($filterent['gateway']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php
								if (isset($filterent['ackqueue']) && isset($filterent['defaultqueue'])) {
									$desc = $filterent['ackqueue'] ;
									echo "<a href=\"firewall_shaper_queues.php?queue={$filterent['ackqueue']}&amp;action=show\">{$desc}</a>";
									$desc = $filterent['defaultqueue'];
									echo "/<a href=\"firewall_shaper_queues.php?queue={$filterent['defaultqueue']}&amp;action=show\">{$desc}</a>";
								} else if (isset($filterent['defaultqueue'])) {
									$desc = $filterent['defaultqueue'];
									echo "<a href=\"firewall_shaper_queues.php?queue={$filterent['defaultqueue']}&amp;action=show\">{$desc}</a>";
								} else {
									echo gettext("none");
								}
							?>
						</td>
						<td>
							<?php if ($printicon) { ?>
								<i class="fa fa-<?=$image?> <?=$dispcolor?>" title="<?=$alttext;?>"></i>
							<?php } ?>
							<?=$schedule_span_begin;?><?=htmlspecialchars($filterent['sched']);?>&nbsp;<?=$schedule_span_end;?>
						</td>
						<td>
							<?=htmlspecialchars($filterent['descr']);?>
						</td>
						<td class="action-icons">
						<!-- <?=(isset($filterent['disabled']) ? 'enable' : 'disable')?> -->
							<a href="firewall_rules_edit.php?id=<?=$i;?>" class="fa fa-pencil" title="<?=gettext('Edit')?>"></a>
							<a href="firewall_rules_edit.php?dup=<?=$i;?>" class="fa fa-clone" title="<?=gettext('Copy')?>"></a>
<?php if (isset($filterent['disabled'])) {
?>
							<a href="?act=toggle&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$i;?>" class="fa fa-check-square-o" title="<?=gettext('Enable')?>"></a>
<?php } else {
?>
							<a href="?act=toggle&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$i;?>" class="fa fa-ban" title="<?=gettext('Disable')?>"></a>
<?php }
?>
							<a href="?act=del&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$i;?>" class="fa fa-trash" title="<?=gettext('Delete')?>"></a>
						</td>
					</tr>
<?php
		if (isset($config['filter']['separator'][strtolower($if)]['sep0'])) {
			foreach ($config['filter']['separator'][strtolower($if)] as $rulesep) {
				if ($rulesep['row']['0'] == "fr" . $nrules) {
					$cellcolor = $rulesep['color'];
					print('<tr class="ui-sortable-handle separator">' .
						'<td class="' . $cellcolor . '" colspan="11">' . '<span class="' . $cellcolor . '">' . $rulesep['text'] . '</span></td>' .
						'<td  class="' . $cellcolor . '"><a href="#"><i class="fa fa-trash no-confirm sepdel" title="delete this separator"></i></a></td>' .
						'</tr>' . "\n");
				}
			}
		}

		$nrules++;
		endfor;
?>
				</tbody>
			</table>
		</div>
	</div>

<?php if ($nrules == 0): ?>
	<div class="alert alert-warning" role="alert">
		<p>
		<?php if ($_REQUEST['if'] == "FloatingRules"): ?>
			<?=gettext("No floating rules are currently defined.");?>
		<?php else: ?>
			<?=gettext("No rules are currently defined for this interface");?><br />
			<?=gettext("All incoming connections on this interface will be blocked until you add pass rules.");?>
		<?php endif;?>
			<?=gettext("Click the button to add a new rule.");?>
		</p>
	</div>
<?php endif;?>

	<nav class="action-buttons">
		<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>&amp;after=-1" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add rule to the top of the list')?>">
			<i class="fa fa-level-up icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add rule to the end of the list')?>">
			<i class="fa fa-level-down icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected rules"); ?>" title="<?=gettext('Delete selected rules')?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-sm btn-primary" value="store changes" disabled title="<?=gettext('Save rule order')?>">
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext("Save")?>
		</button>
		<button type="submit" id="addsep" name="addsep" class="btn btn-sm btn-warning" title="<?=gettext('Add separator')?>">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Separator")?>
		</button>
	</nav>
</form>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert"><div class="pull-left">
		<dl class="dl-horizontal responsive">
		<!-- Legend -->
			<dt><?=gettext('Legend')?></dt>				<dd></dd>
			<dt><i class="fa fa-check text-success"></i></dt>		<dd><?=gettext("Pass");?></dd>
			<dt><i class="fa fa-filter"></i></dt>	<dd><?=gettext("Match");?></dd>
			<dt><i class="fa fa-times text-danger"></i></dt>	<dd><?=gettext("Block");?></dd>
			<dt><i class="fa fa-hand-stop-o text-warning"></i></dt>		<dd><?=gettext("Reject");?></dd>
			<dt><i class="fa fa-tasks"></i></dt>	<dd> <?=gettext("Log");?></dd>
			<dt><i class="fa fa-cog"></i></dt>		<dd> <?=gettext("Advanced filter");?></dd>
		</dl>

<?php
	if ("FloatingRules" != $if) {
		print(gettext("Rules are evaluated on a first-match basis (i.e. " .
			"the action of the first rule to match a packet will be executed). ") . '<br />' .
			gettext("This means that if you use block rules, you'll have to pay attention " .
			"to the rule order. Everything that isn't explicitly passed is blocked " .
			"by default. "));
	} else {
		print(gettext("Floating rules are evaluated on a first-match basis (i.e. " .
			"the action of the first rule to match a packet will be executed) only " .
			"if the 'quick' option is checked on a rule. Otherwise they will only match if no " .
			"other rules match. Pay close attention to the rule order and options " .
			"chosen. If no rule here matches, the per-interface or default rules are used. "));
	}
?>
	</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Make rules sortable
	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
			dirty = true;
		}
	});

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
		$('[id^=frc]').prop('checked', true);

		// Save the separator bar configuration
		save_separators();

		// Suppress the "Do you really want to leave the page" message
		saving = true;
	});

	// Separator bar stuff ------------------------------------------------------------------------

	// Globals
	gColor = 'bg-info';
	newSeperator = false;
	saving = false;
	dirty = false;

	$("#addsep").prop('type' ,'button');

	$("#addsep").click(function() {
		if (newSeperator) {
			return(false);
		}

		gColor = 'bg-info';
		// Inset a temporary bar in which the user can enter some optional text
		$('#ruletable > tbody:last').append('<tr>' +
			'<td class="' + gColor + '" colspan="10"><input id="newsep" placeholder="<?=gettext("Enter a description, Save, then drag to final location.")?>" class="col-md-12" type="text" /></td>' +
			'<td class="' + gColor + '" colspan="2"><button class="btn btn-default btn-sm" id="btnnewsep"><?=gettext("Save")?></button>' +
			'<button class="btn btn-default btn-sm" id="btncncsep"><?=gettext("Cancel")?></button>' +
			'&nbsp;&nbsp;&nbsp;&nbsp;' +
			'&nbsp;&nbsp;<a href="#" id="sepclrblue" value="bg-info"><i class="fa fa-circle text-info"></i></a>' +
			'&nbsp;&nbsp;<a href="#" id="sepclrred" value="bg-danger"><i class="fa fa-circle text-danger"></i></a>' +
			'&nbsp;&nbsp;<a href="#" id="sepclrgreen" value="bg-success"><i class="fa fa-circle text-success"></i></a>' +
			'&nbsp;&nbsp;<a href="#" id="sepclrorange" value="bg-warning"><i class="fa fa-circle text-warning"></i></a>' +
			'</td></tr>');

		$('#newsep').focus();
		newSeperator = true;

		$("#btnnewsep").prop('type' ,'button');

		// Watch escape and enter keys
		$('#newsep').keyup(function(e) {
			if(e.which == 27) {
				$('#btncncsep').trigger('click');
			}
		});

		$('#newsep').keypress(function(e) {
			if(e.which == 13) {
				$('#btnnewsep').trigger('click');
			}
		});

		handle_colors();

		// Remove the temporary separator bar and replace it with the final version containing the
		// user's text and a delete icon
		$("#btnnewsep").click(function() {
			var septext = escapeHtml($('#newsep').val());
			$('#ruletable > tbody:last >tr:last').remove();
			$('#ruletable > tbody:last').append('<tr class="ui-sortable-handle separator">' +
				'<td class="' + gColor + '" colspan="11">' + '<span class="' + gColor + '">' + septext + '</span></td>' +
				'<td class="' + gColor + '"><a href="#"><i class="fa fa-trash sepdel"></i></a>' +
				'</td></tr>');

			$('#order-store').removeAttr('disabled');
			newSeperator = false;
			dirty = true;
		});

		// Cancel button
		$('#btncncsep').click(function(e) {
			e.preventDefault();
			$(this).parents('tr').remove();
			newSeperator = false;
		});
	});

	// Delete a separator row
	$(function(){
		$('table').on('click','tr a .sepdel',function(e){
			e.preventDefault();
			$(this).parents('tr').remove();
			$('#order-store').removeAttr('disabled');
			dirty = true;
		});
	});

	// Compose an inout array containing the row #, color and text for each separator
	function save_separators() {
		var seprow = 0;
		var sepinput;
		var sepnum = 0;

		$('#ruletable > tbody > tr').each(function() {
			if ($(this).hasClass('separator')) {
				seprow = $(this).prev('tr').attr("id");
				if (seprow == undefined) {
					seprow = "fr-1";
				}

				sepinput = '<input type="hidden" name="separator[' + sepnum + '][row]" value="' + seprow + '"></input>';
				$('form').append(sepinput);
				sepinput = '<input type="hidden" name="separator[' + sepnum + '][text]" value="' + escapeHtml($(this).find('td').text()) + '"></input>';
				$('form').append(sepinput);
				sepinput = '<input type="hidden" name="separator[' + sepnum + '][color]" value="' + $(this).find('td').prop('class') + '"></input>';
				$('form').append(sepinput);
				sepinput = '<input type="hidden" name="separator[' + sepnum + '][if]" value="<?=strtolower($if)?>"></input>';
				$('form').append(sepinput);
				sepnum++;
			}

			if ($(this).parent('tbody').hasClass('user-entries')) {
				seprow++;
			}
		});
	}

	function handle_colors() {
		$('[id^=sepclr]').prop("type", "button");

		$('[id^=sepclr]').click(function () {
			var color =	 $(this).attr('value');
			// Clear all the color classes
			$(this).parent('td').prop('class', '');
			$(this).parent('td').prev('td').prop('class', '');
			// Install our new color class
			$(this).parent('td').addClass(color);
			$(this).parent('td').prev('td').addClass(color);
			// Set the global color
			gColor = color;
		});
	}

	// provide a warning message if the user tries to change page before saving
	$(window).bind('beforeunload', function(){
		if ((!saving && dirty) || newSeperator) {
			return ("<?=gettext('You have moved one or more rules but have not yet saved')?>");
		} else {
			return undefined;
		}
	});

	//JS equivalent to PHP htmlspecialchars()
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};

		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	}
	// --------------------------------------------------------------------------------------------
});
//]]>
</script>

<?php include("foot.inc");?>