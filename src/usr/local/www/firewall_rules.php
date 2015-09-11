<?php
/* $Id$ */
/*
	firewall_rules.php
	part of pfSense (https://www.pfsense.org)
	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	originally part of m0n0wall (http://m0n0.ch/wall)
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
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-firewall-rules
##|*NAME=Firewall: Rules page
##|*DESCR=Allow access to the 'Firewall: Rules' page.
##|*MATCH=firewall_rules.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rule_count.inc");

$pgtitle = array(gettext("Firewall"), gettext("Rules"));
$shortcut_section = "firewall";

//Get rule Hit count
function get_rule_ht($tracker,$sum_ht=array()){
	global $g,$rules_count_array;
	//check if there is previous values
	$packets=(isset($sum_ht['packets']) ? $sum_ht['packets'] : 0);
	$states=(isset($sum_ht['bytes']) ? $sum_ht['bytes'] : 0);
	$rules_title=(isset($sum_ht['title']) ? $sum_ht['title'] : "");
	$rules_id=array();
	if (preg_match("/\s+/",$tracker)){
			$rules_title.="$tracker\n";
		}
	if (is_array($rules_count_array) && array_key_exists($tracker,$rules_count_array)) {
		$packets +=$rules_count_array[$tracker]['Packets'];
		$states +=$rules_count_array[$tracker]['States'];
		foreach ($rules_count_array[$tracker] as $rck => $rcv) {
			switch($rck){
				case "Label":
					$label=$rcv;
					break;
				case "RuleId":
					$ruleid=$rcv;
					break;
				default:
					$rules_title.= "$rck: ".bd_nice_number($rcv)."\n";
			}
		}
	}
	$hitcount=bd_nice_number($packets)."/".bd_nice_number($states);
	if ($states > 0) {
		$html="<span id=\"{$label}\" class=\"{$label}\" title=\"Click to load rule details\" style=\"cursor:pointer;\" onclick=\"showState('{$ruleid}','{$hitcount}','{$label}');\">{$hitcount}</span><br>";
		$html.="<span title=\"Click to kill current rule active connections\" style=\"cursor:pointer;\" onclick=\"removeState('{$label}','".bd_nice_number($packets)."');\"><img src=\"./themes/{$g['theme']}/images/icons/icon_block.gif\" align= \"Right\" width=\"8\" height=\"8\"  border=\"0\" /></span>";
		//$resp_info="<span class=\'{$rulelabel}\' style=\'cursor:pointer;\' onclick=\'removeState(6789789,0);\'>Kill Rule States {$rulelabel}</span>" . $resp;
	} else {
		$html="<span id=\"{$label}\" class=\"{$label}\">{$hitcount}</span>";
	}
	return (array(	'title'=> $rules_title,
					'packets' =>$packets,
					'states' =>$states,
					'html' =>$html
	));
}

function bd_nice_number($n) {
	// first strip any formatting;
	$n = (0+str_replace(",","",$n));

	// is this a number?
	if (!is_numeric($n)) {
		return false;
	}

	// now filter it;
	if ($n>1000000000000) {
		return round(($n/1000000000000),1).'t';
	} else if ($n>1000000000) {
		return round(($n/1000000000),1).'g';
	} else if ($n>1000000) {
		return round(($n/1000000),1).'m';
	} else if ($n>1000) {
		return round(($n/1000),1).'k';
	}

	return number_format($n);
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
		$iflist['l2tp'] = "L2TP VPN";
	}
}

if ($config['pptpd']['mode'] == "server") {
	if (have_ruleint_access("pptp")) {
		$iflist['pptp'] = "PPTP VPN";
	}
}

if (is_array($config['pppoes']['pppoe'])) {
	foreach ($config['pppoes']['pppoe'] as $pppoes) {
		if (($pppoes['mode'] == 'server') && have_ruleint_access("pppoe")) {
			$iflist['pppoe'] = "PPPoE Server";
		}
	}
}

/* add ipsec interfaces */
if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])) {
	if (have_ruleint_access("enc0")) {
		$iflist["enc0"] = "IPsec";
	}
}

/* add openvpn/tun interfaces */
if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$iflist["openvpn"] = "OpenVPN";
}

pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/interfaces_override");

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

		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/apply");

		$savemsg = sprintf(gettext("The settings have been applied. The firewall rules are now reloading in the background.<br />You can also %s monitor %s the reload progress"), "<a href='status_filter_reload.php'>", "</a>");
	}
}

/* handle AJAX operation KillRuleStates */
if ($_POST['action'] == "KillRuleStates") {
	
	if (isset($_POST['label'])) {
		$rulelabel=html_entity_decode($_POST['label']);
		$cnt_pfctlk=array();
		exec("/sbin/pfctl -k label -k \"{$rulelabel}\" 2>&1",$cnt_pfctlk);
	} else {
		echo gettext("invalid input");
	}
	
	if (!empty($cnt_pfctlk)) {
		foreach($cnt_pfctlk as $line) {
			print "$line\n";
		}
	}
	return;
}

/* handle AJAX operation ShowRuleStates */
if($_POST['action'] == "ShowRuleStates") {
	
	$td1="<td class=\'listtopic\' valign=\'top\'>";
	$td2="<td>";
	//$td2="<td class=\'vncell\' style=\'background: #FFFFFF;color: #000000;\'>";
	$resp  = "<table width=\'100%\' border=\'0\' cellspacing=\'0\' cellpadding=\'0\' summary=\'status\'>";
	$resp .= "<tr>";
	$resp .= $td1 . gettext("Proto") . "</td>";
	$resp .= $td1 . gettext("Source -> Router -> Destination") . "</td>";
	$resp .= $td1 . gettext("State") . "</td>";
	$resp .= $td1 . gettext("Packets") . "</td>";
	$resp .= $td1 . gettext("bytes") . "</td>";
	$resp .= "</tr>";
	$state_count=0;
	
	if (isset($_POST['hitcount'])) {
		$hitcount=html_entity_decode($_POST['hitcount']);
	}
	
	if (isset($_POST['ruleid'])) {
		$ruleid=html_entity_decode($_POST['ruleid']);
		$cnt_pfctls=array();
		exec("/sbin/pfctl -vvss | /usr/bin/grep -EB2 \"rule ({$ruleid})\"",$cnt_pfctls);
	} else {
		echo gettext("invalid input");
	}
	
	if (!empty($cnt_pfctls)) {
		foreach($cnt_pfctls as $line) {
			if (preg_match("/^\w+\s+(\w+)\s+(.*)(.-.)(.*)\s+(\w+:\w+)/",$line,$mcon)) {
				$state_count++;
				$resp .= "<tr>{$td2}{$mcon[1]}</td>{$td2}{$mcon[2]}{$mcon[3]}{$mcon[4]}</td>{$td2}{$mcon[5]}</td>";
			} elseif (preg_match("/age.*, (\S+) pkts, (\S+) bytes, rule (\d+)/",$line,$mrule)) {
				list($pkt1,$pkt2)=explode(":",$mrule[1],2);
				list($bt1,$bt2)=explode(":",$mrule[2],2);
				$resp .= "{$td2}".bd_nice_number($pkt1)." / ".bd_nice_number($pkt2)."</td>{$td2}".bd_nice_number($bt1)." / ".bd_nice_number($bt2)."</td></tr>";
			}
		}
	}
	
	$resp .= "</table>";
	$html.="<span style=\"cursor: help;\" onmouseover=\"var response_html=domTT_activate(this, event, 'id','ttalias_{$rulelabel}','content','{$resp}', 'trail', true, 'delay', 300, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle','type','velcro','width',800);\" onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\"><u>{$hitcount}</u></span>";
	print ($html);
	return;
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
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			delete_nat_association($a_filter[$rulei]['associated-rule-id']);
			unset($a_filter[$rulei]);
		}
		if (write_config()) {
			mark_subsystem_dirty('filter');
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
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does -
	   so we use .x/.y to fine move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
		}
	}
	/* move selected rules before this rule */
	if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
		$a_filter_new = array();

		/* copy all rules < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['rule'])) {
				$a_filter_new[] = $a_filter[$i];
			}
		}

		/* copy all selected rules */
		for ($i = 0; $i < count($a_filter); $i++) {
			if ($i == $movebtn) {
				continue;
			}
			if (in_array($i, $_POST['rule'])) {
				$a_filter_new[] = $a_filter[$i];
			}
		}

		/* copy $movebtn rule */
		if ($movebtn < count($a_filter)) {
			$a_filter_new[] = $a_filter[$movebtn];
		}

		/* copy all rules > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_filter); $i++) {
			if (!in_array($i, $_POST['rule'])) {
				$a_filter_new[] = $a_filter[$i];
			}
		}

		$a_filter = $a_filter_new;
		if (write_config()) {
			mark_subsystem_dirty('filter');
		}
		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}
$closehead = false;

include("head.inc");
?>
<link type="text/css" rel="stylesheet" href="/javascript/chosen/chosen.css" />
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script src="/javascript/chosen/chosen.jquery.js" type="text/javascript"></script>
<?php include("fbegin.inc"); ?>
<form action="firewall_rules.php" method="post">
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
			jQuery('span[id="'+ mylabel + '"]').each(
					function(index,row) { jQuery(row).html(req.responseText); }
					);
			return;
	}
//]]>
</script>

<script type="text/javascript" src="/javascript/row_toggle.js"></script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('filter')): ?><p>
<?php	print_info_box_np(gettext("The firewall rule configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."), "apply", "", true); ?>
<br />
<?php endif; ?>
<?php
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/before_table");
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="firewall rules">
	<tr><td class="tabnavtbl">
	<?php
	/* active tabs */
	$tab_array = array();
	if ("FloatingRules" == $if) {
		$active = true;
	} else {
		$active = false;
	}
	$tab_array[] = array(gettext("Floating"), $active, "firewall_rules.php?if=FloatingRules");
	$tabscounter = 0;
	$i = 0;
	foreach ($iflist as $ifent => $ifname) {
		if ($ifent == $if) {
			$active = true;
		} else {
			$active = false;
		}
		$tab_array[] = array($ifname, $active, "firewall_rules.php?if={$ifent}");
	}
	display_top_tabs($tab_array);
	?>
	</td></tr>
	<tr><td>
		<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
			<?php
				pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/before_first_tr");
			?>
			<tr id="frheader">
				<td width="3%" class="list">&nbsp;</td>
				<td width="5%" class="list">&nbsp;</td>
				<td width="3%" class="listhdrr" title="<?=gettext("Bytes/States");?>"><?=gettext("Hits");?></td>
				<?php
					pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_id_tablehead");
				?>
				<?php
					if ('FloatingRules' == $if) {
				?>
				<td width="3%" class="listhdrr"><?=gettext('Interfaces');?></td>
				<?php
					}
				?>
				<td width="6%" class="listhdrr"><?=gettext("Proto");?></td>
				<td width="12%" class="listhdrr"><?=gettext("Source");?></td>
				<td width="6%" class="listhdrr"><?=gettext("Port");?></td>
				<td width="12%" class="listhdrr"><?=gettext("Destination");?></td>
				<td width="6%" class="listhdrr"><?=gettext("Port");?></td>
				<td width="5%" class="listhdrr"><?=gettext("Gateway");?></td>
				<td width="8%" class="listhdrr"><?=gettext("Queue");?></td>
				<td width="5%" class="listhdrr"><?=gettext("Schedule");?></td>
				<?php
					pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_desc_tablehead");
				?>
				<td width="19%" class="listhdr"><?=gettext("Description");?></td>
				<td width="10%" class="list">
					<table border="0" cellspacing="0" cellpadding="1" summary="delete selected rules">
						<tr>
						<?php
							$nrules = 0;
							for ($i = 0; isset($a_filter[$i]); $i++) {
								$filterent = $a_filter[$i];
								if ($filterent['interface'] != $if && !isset($filterent['floating'])) {
									continue;
								}
								if (isset($filterent['floating']) && "FloatingRules" != $if) {
									continue;
								}
								$nrules++;
							}
						?>
							<td>
							<?php if ($nrules == 0): ?>
								<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?gettext("delete selected rules"); ?>" border="0" alt="delete" /><?php else: ?>
								<input name="del" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" style="width:17;height:17" title="<?=gettext("delete selected rules");?>" onclick="return confirm('<?=gettext('Do you really want to delete the selected rules?');?>')" />
							<?php endif; ?>
							</td>
							<td align="center" valign="middle">
								<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>&amp;after=-1">
									<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add new rule");?>" width="17" height="17" border="0" alt="add" />
								</a>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<?php   // Show the anti-lockout rule if it's enabled, and we are on LAN with an if count > 1, or WAN with an if count of 1.
				if (!isset($config['system']['webgui']['noantilockout']) &&
				    (((count($config['interfaces']) > 1) && ($if == 'lan')) ||
				     ((count($config['interfaces']) == 1) && ($if == 'wan')))):

					$alports = implode('<br />', filter_get_antilockout_ports(true));
					$rule_hit_count=get_rule_ht("anti-lockout rule");
			?>
			<tr valign="top" id="antilockout">
				<td class="list">&nbsp;</td>
				<td class="listt" align="center"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif" width="11" height="11" border="0" alt="pass" /></td>
				<td class="listlr" style="background-color: #E0E0E0" title="<?=$rule_hit_count['title']; ?>"><?=$rule_hit_count['html']; ?></td>
				<?php
					pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_id_tr_antilockout");
				?>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0"><?=$iflist[$if];?> Address</td>
				<td class="listr" style="background-color: #E0E0E0"><?= $alports ?></td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">&nbsp;</td>
				<td class="listbg"><?=gettext("Anti-Lockout Rule");?></td>
				<td valign="middle" class="list nowrap">
					<table border="0" cellspacing="0" cellpadding="1" summary="move rules before">
						<tr>
							<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected rules before this rule");?>" alt="move" /></td>
							<td><a href="system_advanced_admin.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit rule");?>" width="17" height="17" border="0" alt="edit" /></a></td>
						</tr>
						<tr>
							<td align="center" valign="middle"></td>
							<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" title="<?=gettext("add a new rule based on this one");?>" width="17" height="17" border="0" alt="add" /></td>
						</tr>
					</table>
				</td>
			</tr>
<?php endif; ?>

<?php if (isset($config['interfaces'][$if]['blockpriv'])):
		$rule_hit_count=get_rule_ht("Block private networks from " . strtoupper($if) . " block 192.168/16");
		$rule_hit_count=get_rule_ht("Block private networks from " . strtoupper($if) . " block 127/8",$rule_hit_count);
		$rule_hit_count=get_rule_ht("Block private networks from " . strtoupper($if) . " block 172.16/12",$rule_hit_count);
		$rule_hit_count=get_rule_ht("Block private networks from " . strtoupper($if) . " block 10/8",$rule_hit_count);?>

			<tr valign="top" id="frrfc1918">
				<td class="list">&nbsp;</td>
				<td class="listt" align="center"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block.gif" width="11" height="11" border="0" alt="block" /></td>
				<td class="listlr" style="background-color: #E0E0E0" title="<?=$rule_hit_count['title'];?>"><?=$rule_hit_count['html']; ?></td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0"><?=gettext("RFC 1918 networks");?></td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">&nbsp;</td>
				<td class="listbg"><?=gettext("Block private networks");?></td>
				<td valign="middle" class="list nowrap">
					<table border="0" cellspacing="0" cellpadding="1" summary="move rules before">
						<tr>
							<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected rules before this rule");?>" alt="edit" /></td>
							<td><a href="interfaces.php?if=<?=htmlspecialchars($if)?>#rfc1918"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit rule");?>" width="17" height="17" border="0" alt="edit" /></a></td>
						</tr>
						<tr>
							<td align="center" valign="middle"></td>
							<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" title="<?=gettext("add a new rule based on this one");?>" width="17" height="17" border="0" alt="add" /></td>
						</tr>
					</table>
				</td>
			</tr>
<?php endif; ?>
<?php if (isset($config['interfaces'][$if]['blockbogons'])):
		$rule_hit_count=get_rule_ht("block bogon IPv4 networks from ".strtoupper($if));
		$rule_hit_count=get_rule_ht("block bogon IPv6 networks from ".strtoupper($if),$rule_hit_count);
?>
			<tr valign="top" id="frrfc1918">
				<td class="list">&nbsp;</td>
				<td class="listt" align="center"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block.gif" width="11" height="11" border="0" alt="block" /></td>
				<td class="listlr" style="background-color: #E0E0E0" title="<?=$rule_hit_count['title'] ?>"><?=$rule_hit_count['html']; ?></td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0"><?=gettext("Reserved/not assigned by IANA");?></td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listr" style="background-color: #E0E0E0">*</td>
				<td class="listbg"><?=gettext("Block bogon networks");?></td>
				<td valign="middle" class="list nowrap">
					<table border="0" cellspacing="0" cellpadding="1" summary="move rules before">
						<tr>
							<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected rules before this rule");?>" alt="move" /></td>
							<td><a href="interfaces.php?if=<?=htmlspecialchars($if)?>#rfc1918"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit rule");?>" width="17" height="17" border="0" alt=" edit" /></a></td>
						</tr>
						<tr>
							<td align="center" valign="middle"></td>
							<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" title="<?=gettext("add a new rule based on this one");?>" width="17" height="17" border="0" alt="add" /></td>
						</tr>
					</table>
				</td>
			</tr>
<?php endif; ?>
			<tbody>
<?php
	$nrules = 0;
	for ($i = 0; isset($a_filter[$i]); $i++):
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/row_start");
		$filterent = $a_filter[$i];
		if ($filterent['interface'] != $if && !isset($filterent['floating'])) {
			continue;
		}
		if (isset($filterent['floating']) && "FloatingRules" != $if) {
			continue;
		}
		$isadvset = firewall_check_for_advanced_options($filterent);
		if ($isadvset) {
			$advanced_set = "<img src=\"./themes/{$g['theme']}/images/icons/icon_advanced.gif\" title=\"" . gettext("advanced settings set") . ": {$isadvset}\" border=\"0\" alt=\"advanced\" />";
		} else {
			$advanced_set = "";
		}
?>
			<tr valign="top" id="fr<?=$nrules;?>">
				<td class="listt">
					<input type="checkbox" id="frc<?=$nrules;?>" name="rule[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$nrules;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
					<?php echo $advanced_set; ?>
				</td>
				<td class="listt" align="center">
				<?php
					if ($filterent['type'] == "block") {
						$iconfn = "block";
					} else if ($filterent['type'] == "reject") {
						$iconfn = "reject";
					} else if ($filterent['type'] == "match") {
						$iconfn = "match";
					} else {
						$iconfn = "pass";
					}
					if (isset($filterent['disabled'])) {
						$textss = "<span class=\"gray\">";
						$textse = "</span>";
						$iconfn .= "_d";
					} else {
						$textss = $textse = "";
					}
				?>
					<a href="?if=<?=htmlspecialchars($if);?>&amp;act=toggle&amp;id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn;?>.gif" width="11" height="11" border="0" title="<?=gettext("click to toggle enabled/disabled status");?>" alt="icon" /></a>
<?php
	if (isset($filterent['log'])):
		$iconfnlog = "log_s";
		if (isset($filterent['disabled'])) {
			$iconfnlog .= "_d";
		}
?>
					<br /><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfnlog;?>.gif" width="11" height="15" border="0" alt="icon" />
<?php endif; ?>
				</td>
			<?php

				//build Alias popup box
				$alias_src_span_begin = "";
				$alias_src_port_span_begin = "";
				$alias_dst_span_begin = "";
				$alias_dst_port_span_begin = "";

				$alias_popup = rule_popup($filterent['source']['address'], pprint_port($filterent['source']['port']), $filterent['destination']['address'], pprint_port($filterent['destination']['port']));

				$alias_src_span_begin = $alias_popup["src"];
				$alias_src_port_span_begin = $alias_popup["srcport"];
				$alias_dst_span_begin = $alias_popup["dst"];
				$alias_dst_port_span_begin = $alias_popup["dstport"];

				$alias_src_span_end = $alias_popup["src_end"];
				$alias_src_port_span_end = $alias_popup["srcport_end"];
				$alias_dst_span_end = $alias_popup["dst_end"];
				$alias_dst_port_span_end = $alias_popup["dstport_end"];

				//build Schedule popup box
				$a_schedules = &$config['schedules']['schedule'];
				$schedule_span_begin = "";
				$schedule_span_end = "";
				$sched_caption_escaped = "";
				$sched_content = "";
				$schedstatus = false;
				$dayArray = array (gettext('Mon'), gettext('Tues'), gettext('Wed'), gettext('Thur'), gettext('Fri'), gettext('Sat'), gettext('Sun'));
				$monthArray = array (gettext('January'), gettext('February'), gettext('March'), gettext('April'), gettext('May'), gettext('June'), gettext('July'), gettext('August'), gettext('September'), gettext('October'), gettext('November'), gettext('December'));
				if ($config['schedules']['schedule'] <> "" and is_array($config['schedules']['schedule'])) {
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
													$dayFriendly .= $monthArray[$month-1] . " " . $day;
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
							$sched_caption_escaped = str_replace("'", "\'", $schedule['descr']);
							$schedule_span_begin = "<span style=\"cursor: help;\" onmouseover=\"domTT_activate(this, event, 'content', '<h1>{$sched_caption_escaped}</h1><p>{$sched_content}</p>', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\" onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\"><u>";
							$schedule_span_end = "</u></span>";
						}
					}
				}
				$printicon = false;
				$alttext = "";
				$image = "";
				if (!isset($filterent['disabled'])) {
					if ($schedstatus) {
						if ($iconfn == "block" || $iconfn == "reject") {
							$image = "icon_block";
							$alttext = gettext("Traffic matching this rule is currently being denied");
						} else {
							$image = "icon_pass";
							$alttext = gettext("Traffic matching this rule is currently being allowed");
						}
						$printicon = true;
					} else if ($filterent['sched']) {
						if ($iconfn == "block" || $iconfn == "reject") {
							$image = "icon_block_d";
						} else {
							$image = "icon_block";
						}
						$alttext = gettext("This rule is not currently active because its period has expired");
						$printicon = true;
					}
				}
				$rule_hit_count=get_rule_ht($filterent['tracker']);
			?>
				<td class="listlr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" title="<?=$rule_hit_count['title'];?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?><?=$rule_hit_count['html']; ?><?=$textse;?>
				</td>
			<?php
				pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_id_tr");
			?>
			<?php
				if ('FloatingRules' == $if) {
			?>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?>
			<?php
					if (isset($filterent['interface'])) {
						$selected_interfaces = explode(',', $filterent['interface']);
						unset($selected_descs);
						foreach ($selected_interfaces as $interface) {
							if (isset($ifdescs[$interface])) {
								$selected_descs[] = $ifdescs[$interface];
							} else {
								switch ($interface) {
									case 'l2tp':
										if ($config['l2tp']['mode'] == 'server') {
											$selected_descs[] = 'L2TP VPN';
										}
										break;
									case 'pptp':
										if ($config['pptpd']['mode'] == 'server') {
											$selected_descs[] = 'PPTP VPN';
										}
										break;
									case 'pppoe':
										if (is_pppoe_server_enabled()) {
											$selected_descs[] = 'PPPoE Server';
										}
										break;
									case 'enc0':
										if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])) {
											$selected_descs[] = 'IPsec';
										}
										break;
									case 'openvpn':
										if ($config['openvpn']['openvpn-server'] || $config['openvpn']['openvpn-client']) {
											$selected_descs[] = 'OpenVPN';
										}
										break;
									default:
										$selected_descs[] = $interface;
										break;
								}
							}
						}

						echo implode('<br/>', $selected_descs);
					}
			?>
					<?=$textse;?>
				</td>
			<?php
				}
			?>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?>
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
						echo ' <span style="cursor: help;" title="ICMP type: ' .
							($filterent['ipprotocol'] == "inet6" ? $icmp6types[$filterent['icmptype']] : $icmptypes[$filterent['icmptype']]) .
							'"><u>';
						echo $filterent['icmptype'];
						echo '</u></span>';
					}
				} else {
					echo "*";
				}
			?>
					<?=$textse;?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?><?php echo $alias_src_span_begin;?><?php echo htmlspecialchars(pprint_address($filterent['source']));?><?php echo $alias_src_span_end;?><?=$textse;?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?><?php echo $alias_src_port_span_begin;?><?php echo htmlspecialchars(pprint_port($filterent['source']['port'])); ?><?php echo $alias_src_port_span_end;?><?=$textse;?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?><?php echo $alias_dst_span_begin;?><?php echo htmlspecialchars(pprint_address($filterent['destination'])); ?><?php echo $alias_dst_span_end;?><?=$textse;?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?><?php echo $alias_dst_port_span_begin;?><?php echo htmlspecialchars(pprint_port($filterent['destination']['port'])); ?><?php echo $alias_dst_port_span_end;?><?=$textse;?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?><?php if (isset($config['interfaces'][$filterent['gateway']]['descr'])) echo htmlspecialchars($config['interfaces'][$filterent['gateway']]['descr']); else echo htmlspecialchars(pprint_port($filterent['gateway'])); ?><?=$textse;?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?>
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
					<?=$textse;?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<font color="black">
						<?php if ($printicon) { ?><img src="./themes/<?= $g['theme']; ?>/images/icons/<?php echo $image; ?>.gif" title="<?php echo $alttext;?>" border="0" alt="icon" /><?php } ?><?=$textss;?><?php echo $schedule_span_begin;?><?=htmlspecialchars($filterent['sched']);?>&nbsp;<?php echo $schedule_span_end; ?><?=$textse;?>
					</font>
				</td>
			<?php
				pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_descr_tr");
			?>
				<td class="listbg descr" onclick="fr_toggle(<?=$nrules;?>)" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
					<?=$textss;?><?=htmlspecialchars($filterent['descr']);?>&nbsp;<?=$textse;?>
				</td>
				<td valign="middle" class="list nowrap">
					<table border="0" cellspacing="0" cellpadding="1" summary="move before">
						<tr>
							<td><input name="move_<?=$i;?>" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" style="width:17;height:17" title="<?=gettext("move selected rules before this rule"); ?>" onmouseover="fr_insline(<?=$nrules;?>, true)" onmouseout="fr_insline(<?=$nrules;?>, false)" /></td>
							<td><a href="firewall_rules_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit rule"); ?>" width="17" height="17" border="0" alt="edit" /></a></td>
						</tr>
						<tr>
							<td align="center" valign="middle"><a href="firewall_rules.php?act=del&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete rule"); ?>" onclick="return confirm('Do you really want to delete this rule?')" alt="delete" /></a></td>
							<td><a href="firewall_rules_edit.php?dup=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add a new rule based on this one"); ?>" width="17" height="17" border="0" alt="add" /></a></td>
						</tr>
					</table>
				</td>
			</tr>
			<?php $nrules++; endfor; ?>
			<tr><td></td></tr>
			</tbody>
<?php if ($nrules == 0): ?>
			<tr>
				<td class="listt"></td>
				<td class="listt"></td>
	<?php
		if ($_REQUEST['if'] == "FloatingRules") {
			$ncolumns = "11";
		} else {
			$ncolumns = "10";
		}
	?>
				<td class="listlr" colspan=<?=$ncolumns;?> align="center" valign="middle">
					<span class="gray">
	<?php if ($_REQUEST['if'] == "FloatingRules"): ?>
					<?=gettext("No floating rules are currently defined."); ?><br /><br />
	<?php else: ?>
					<?=gettext("No rules are currently defined for this interface"); ?><br />
					<?=gettext("All incoming connections on this interface will be blocked until you add pass rules."); ?><br /><br />
	<?php endif; ?>
					<?=gettext("Click the"); ?> <a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add new rule");?>" border="0" width="17" height="17" align="middle" alt="add" /></a><?=gettext(" button to add a new rule.");?></span>
				</td>
			</tr>
<?php endif; ?>
			<tr id="fr<?=$nrules;?>">
				<td class="list"></td>
				<td class="list"></td>
			<?php
				pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_id_tr_belowtable");
			?>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">&nbsp;</td>
				<td class="list">
					<table border="0" cellspacing="0" cellpadding="1" summary="move rules">
						<tr>
							<td>
								<?php if ($nrules == 0): ?><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected rules to end");?>" border="0" alt="move" /><?php else: ?><input name="move_<?=$i;?>" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" style="width:17;height:17" title="<?=gettext("move selected rules to end");?>" onmouseover="fr_insline(<?=$nrules;?>, true)" onmouseout="fr_insline(<?=$nrules;?>, false)" /><?php endif; ?>
							</td>
							<td></td>
						</tr>
						<tr>
							<td>
<?php if ($nrules == 0): ?>
								<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected rules");?>" border="0" alt="delete" /><?php else: ?>
								<input name="del" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" style="width:17;height:17" title="<?=gettext("delete selected rules");?>" onclick="return confirm('<?=gettext('Do you really want to delete the selected rules?');?>')" />
<?php endif; ?>
							</td>
							<td>
								<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add new rule");?>" width="17" height="17" border="0" alt="add" /></a>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0" summary="icons">
			<tr>
				<td width="16"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif" width="11" height="11" alt="pass" /></td>
				<td width="100"><?=gettext("pass");?></td>
				<td width="14"></td>
				<td width="16"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_match.gif" width="11" height="11" alt="match" /></td>
				<td width="100"><?=gettext("match");?></td>
				<td width="14"></td>
				<td width="16"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block.gif" width="11" height="11" alt="block" /></td>
				<td width="100"><?=gettext("block");?></td>
				<td width="14"></td>
				<td width="16"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_reject.gif" width="11" height="11" alt="reject" /></td>
				<td width="100"><?=gettext("reject");?></td>
				<td width="14"></td>
				<td width="16"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_log.gif" width="11" height="11" alt="log" /></td>
				<td width="100"><?=gettext("log");?></td>
			</tr>
			<tr>
				<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_pass_d.gif" width="11" height="11" alt="pass disabled" /></td>
				<td class="nowrap"><?=gettext("pass (disabled)");?></td>
				<td>&nbsp;</td>
				<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_match_d.gif" width="11" height="11" alt="match disabled" /></td>
				<td class="nowrap"><?=gettext("match (disabled)");?></td>
				<td>&nbsp;</td>
				<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block_d.gif" width="11" height="11" alt="block disabled" /></td>
				<td class="nowrap"><?=gettext("block (disabled)");?></td>
				<td>&nbsp;</td>
				<td><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_reject_d.gif" width="11" height="11" alt="reject disabled" /></td>
				<td class="nowrap"><?=gettext("reject (disabled)");?></td>
				<td>&nbsp;</td>
				<td width="16"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_log_d.gif" width="11" height="11" alt="log disabled" /></td>
				<td class="nowrap"><?=gettext("log (disabled)");?></td>
			</tr>
			<tr>
				<td colspan="10">
					<p>&nbsp;</p>
					<strong>
						<span class="red"><?=gettext("Hint:");?></span>
					</strong><br />
					<ul>
					<?php if ("FloatingRules" != $if): ?>
						<li><?=gettext("Rules are evaluated on a first-match basis (i.e. " .
						"the action of the first rule to match a packet will be executed). " .
						"This means that if you use block rules, you'll have to pay attention " .
						"to the rule order. Everything that isn't explicitly passed is blocked " .
						"by default. ");?>
						</li>
					<?php else: ?>
						<li><?=gettext("Floating rules are evaluated on a first-match basis (i.e. " .
						"the action of the first rule to match a packet will be executed) only " .
						"if the 'quick' option is checked on a rule. Otherwise they will only apply if no " .
						"other rules match. Pay close attention to the rule order and options " .
						"chosen. If no rule here matches, the per-interface or default rules are used. ");?>
						</li>
					<?php endif; ?>
					</ul>
				</td>
			</tr>
		</table>
		</div>
	</td></tr>
</table>
<input type="hidden" name="if" value="<?=htmlspecialchars($if);?>" />
</form>
<?php include("fend.inc"); ?>
</body>
</html>
