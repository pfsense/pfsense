<?php
/* $Id$ */
/*
	firewall_nat_edit.php
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
	pfSense_MODULE:	nat
*/

##|+PRIV
##|*IDENT=page-firewall-nat-portforward-edit
##|*NAME=Firewall: NAT: Port Forward: Edit page
##|*DESCR=Allow access to the 'Firewall: NAT: Port Forward: Edit' page.
##|*MATCH=firewall_nat_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("itemid.inc");
require_once("filter.inc");
require("shaper.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat.php');

$specialsrcdst = explode(" ", "any (self) pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

if (!is_array($config['nat']['rule'])) {
	$config['nat']['rule'] = array();
}
$a_nat = &$config['nat']['rule'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (is_numericint($_GET['after']) || $_GET['after'] == "-1")
	$after = $_GET['after'];
if (isset($_POST['after']) && (is_numericint($_POST['after']) || $_POST['after'] == "-1"))
	$after = $_POST['after'];

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
        $id = $_GET['dup'];
        $after = $_GET['dup'];
}

if (isset($id) && $a_nat[$id]) {
	if ( isset($a_nat[$id]['created']) && is_array($a_nat[$id]['created']) )
		$pconfig['created'] = $a_nat[$id]['created'];

	if ( isset($a_nat[$id]['updated']) && is_array($a_nat[$id]['updated']) )
		$pconfig['updated'] = $a_nat[$id]['updated'];

	$pconfig['disabled'] = isset($a_nat[$id]['disabled']);
	$pconfig['nordr'] = isset($a_nat[$id]['nordr']);
	address_to_pconfig($a_nat[$id]['source'], $pconfig['src'],
		$pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

	address_to_pconfig($a_nat[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	$pconfig['proto'] = $a_nat[$id]['protocol'];
	$pconfig['localip'] = $a_nat[$id]['target'];
	$pconfig['localbeginport'] = $a_nat[$id]['local-port'];
	$pconfig['descr'] = $a_nat[$id]['descr'];
	$pconfig['interface'] = $a_nat[$id]['interface'];
	$pconfig['associated-rule-id'] = $a_nat[$id]['associated-rule-id'];
	$pconfig['nosync'] = isset($a_nat[$id]['nosync']);
	$pconfig['natreflection'] = $a_nat[$id]['natreflection'];

	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";
} else {
	$pconfig['interface'] = "wan";
	$pconfig['src'] = "any";
	$pconfig['srcbeginport'] = "any";
	$pconfig['srcendport'] = "any";
}

if (isset($_GET['dup']) && is_numericint($_GET['dup']))
	unset($id);

/*  run through $_POST items encoding HTML entties so that the user
 *  cannot think he is slick and perform a XSS attack on the unwilling
 */
unset($input_errors);
foreach ($_POST as $key => $value) {
	$temp = $value;
	$newpost = htmlentities($temp);
	if($newpost <> $temp)
		$input_errors[] = sprintf(gettext("Invalid characters detected %s. Please remove invalid characters and save again."), $temp);
}

if ($_POST) {

	if(strtoupper($_POST['proto']) == "TCP" || strtoupper($_POST['proto']) == "UDP" || strtoupper($_POST['proto']) == "TCP/UDP") {
		if ($_POST['srcbeginport_cust'] && !$_POST['srcbeginport'])
			$_POST['srcbeginport'] = trim($_POST['srcbeginport_cust']);
		if ($_POST['srcendport_cust'] && !$_POST['srcendport'])
			$_POST['srcendport'] = trim($_POST['srcendport_cust']);

		if ($_POST['srcbeginport'] == "any") {
			$_POST['srcbeginport'] = 0;
			$_POST['srcendport'] = 0;
		} else {
			if (!$_POST['srcendport'])
				$_POST['srcendport'] = $_POST['srcbeginport'];
		}
		if ($_POST['srcendport'] == "any")
			$_POST['srcendport'] = $_POST['srcbeginport'];

		if ($_POST['dstbeginport_cust'] && !$_POST['dstbeginport'])
			$_POST['dstbeginport'] = trim($_POST['dstbeginport_cust']);
		if ($_POST['dstendport_cust'] && !$_POST['dstendport'])
			$_POST['dstendport'] = trim($_POST['dstendport_cust']);

		if ($_POST['dstbeginport'] == "any") {
			$_POST['dstbeginport'] = 0;
			$_POST['dstendport'] = 0;
		} else {
			if (!$_POST['dstendport'])
				$_POST['dstendport'] = $_POST['dstbeginport'];
		}
		if ($_POST['dstendport'] == "any")
			$_POST['dstendport'] = $_POST['dstbeginport'];

		if ($_POST['localbeginport_cust'] && !$_POST['localbeginport'])
			$_POST['localbeginport'] = trim($_POST['localbeginport_cust']);

		/* Make beginning port end port if not defined and endport is */
		if (!$_POST['srcbeginport'] && $_POST['srcendport'])
			$_POST['srcbeginport'] = $_POST['srcendport'];
		if (!$_POST['dstbeginport'] && $_POST['dstendport'])
			$_POST['dstbeginport'] = $_POST['dstendport'];
	} else {
		$_POST['srcbeginport'] = 0;
		$_POST['srcendport'] = 0;
		$_POST['dstbeginport'] = 0;
		$_POST['dstendport'] = 0;
	}

	if (is_specialnet($_POST['srctype'])) {
		$_POST['src'] = $_POST['srctype'];
		$_POST['srcmask'] = 0;
	} else if ($_POST['srctype'] == "single") {
		$_POST['srcmask'] = 32;
	}
	if (is_specialnet($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 0;
	} else if ($_POST['dsttype'] == "single") {
		$_POST['dstmask'] = 32;
	} else if (is_ipaddr($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 32;
		$_POST['dsttype'] = "single";
	}

	$pconfig = $_POST;

	/* input validation */
	if(strtoupper($_POST['proto']) == "TCP" or strtoupper($_POST['proto']) == "UDP" or strtoupper($_POST['proto']) == "TCP/UDP") {
		$reqdfields = explode(" ", "interface proto dstbeginport dstendport");
		$reqdfieldsn = array(gettext("Interface"),gettext("Protocol"),gettext("Destination port from"),gettext("Destination port to"));
	} else {
		$reqdfields = explode(" ", "interface proto");
		$reqdfieldsn = array(gettext("Interface"),gettext("Protocol"));
	}

	if ($_POST['srctype'] == "single" || $_POST['srctype'] == "network") {
		$reqdfields[] = "src";
		$reqdfieldsn[] = gettext("Source address");
	}
	if ($_POST['dsttype'] == "single" || $_POST['dsttype'] == "network") {
		$reqdfields[] = "dst";
		$reqdfieldsn[] = gettext("Destination address");
	}
	if (!isset($_POST['nordr'])) {
		$reqdfields[] = "localip";
		$reqdfieldsn[] = gettext("Redirect target IP");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$_POST['srcbeginport']) {
		$_POST['srcbeginport'] = 0;
		$_POST['srcendport'] = 0;
	}
	if (!$_POST['dstbeginport']) {
		$_POST['dstbeginport'] = 0;
		$_POST['dstendport'] = 0;
	}

	if ($_POST['src'])
		$_POST['src'] = trim($_POST['src']);
	if ($_POST['dst'])
		$_POST['dst'] = trim($_POST['dst']);
	if ($_POST['localip'])
		$_POST['localip'] = trim($_POST['localip']);

	if (!isset($_POST['nordr']) && ($_POST['localip'] && !is_ipaddroralias($_POST['localip']))) {
		$input_errors[] = sprintf(gettext("\"%s\" is not a valid redirect target IP address or host alias."), $_POST['localip']);
	}

	if ($_POST['srcbeginport'] && !is_portoralias($_POST['srcbeginport']))
		$input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."), $_POST['srcbeginport']);
	if ($_POST['srcendport'] && !is_portoralias($_POST['srcendport']))
		$input_errors[] = sprintf(gettext("%s is not a valid end source port. It must be a port alias or integer between 1 and 65535."), $_POST['srcendport']);
	if ($_POST['dstbeginport'] && !is_portoralias($_POST['dstbeginport']))
		$input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."), $_POST['dstbeginport']);
	if ($_POST['dstendport'] && !is_portoralias($_POST['dstendport']))
		$input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."), $_POST['dstendport']);

	if ((strtoupper($_POST['proto']) == "TCP" || strtoupper($_POST['proto']) == "UDP" || strtoupper($_POST['proto']) == "TCP/UDP") && (!isset($_POST['nordr']) && !is_portoralias($_POST['localbeginport']))) {
		$input_errors[] = sprintf(gettext("A valid redirect target port must be specified. It must be a port alias or integer between 1 and 65535."), $_POST['localbeginport']);
	}

	/* if user enters an alias and selects "network" then disallow. */
	if( ($_POST['srctype'] == "network" && is_alias($_POST['src']) ) 
	 || ($_POST['dsttype'] == "network" && is_alias($_POST['dst']) ) ) {
		$input_errors[] = gettext("You must specify single host or alias for alias entries.");
	}

	if (!is_specialnet($_POST['srctype'])) {
		if (($_POST['src'] && !is_ipaddroralias($_POST['src']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $_POST['src']);
		}
		if (($_POST['srcmask'] && !is_numericint($_POST['srcmask']))) {
			$input_errors[] = gettext("A valid source bit count must be specified.");
		}
	}
	if (!is_specialnet($_POST['dsttype'])) {
		if (($_POST['dst'] && !is_ipaddroralias($_POST['dst']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $_POST['dst']);
		}
		if (($_POST['dstmask'] && !is_numericint($_POST['dstmask']))) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
	}

	if ($_POST['srcbeginport'] > $_POST['srcendport']) {
		/* swap */
		$tmp = $_POST['srcendport'];
		$_POST['srcendport'] = $_POST['srcbeginport'];
		$_POST['srcbeginport'] = $tmp;
	}
	if ($_POST['dstbeginport'] > $_POST['dstendport']) {
		/* swap */
		$tmp = $_POST['dstendport'];
		$_POST['dstendport'] = $_POST['dstbeginport'];
		$_POST['dstbeginport'] = $tmp;
	}

	if (!$input_errors) {
		if (!isset($_POST['nordr']) && ($_POST['dstendport'] - $_POST['dstbeginport'] + $_POST['localbeginport']) > 65535)
			$input_errors[] = gettext("The target port range must be an integer between 1 and 65535.");
	}

	/* check for overlaps */
	foreach ($a_nat as $natent) {
		if (isset($id) && ($a_nat[$id]) && ($a_nat[$id] === $natent))
			continue;
		if ($natent['interface'] != $_POST['interface'])
			continue;
		if ($natent['destination']['address'] != $_POST['dst'])
			continue;
		if (($natent['proto'] != $_POST['proto']) && ($natent['proto'] != "tcp/udp") && ($_POST['proto'] != "tcp/udp"))
			continue;

		list($begp,$endp) = explode("-", $natent['destination']['port']);
		if (!$endp)
			$endp = $begp;

		if (!(   (($_POST['beginport'] < $begp) && ($_POST['endport'] < $begp))
		      || (($_POST['beginport'] > $endp) && ($_POST['endport'] > $endp)))) {

			$input_errors[] = gettext("The destination port range overlaps with an existing entry.");
			break;
		}
	}

	// Allow extending of the firewall edit page and include custom input validation 
	pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/input_validation");

	if (!$input_errors) {
		$natent = array();

		$natent['disabled'] = isset($_POST['disabled']) ? true:false;
		$natent['nordr'] = isset($_POST['nordr']) ? true:false;

		if ($natent['nordr']) {
			$_POST['associated-rule-id'] = '';
			$_POST['filter-rule-association'] = '';
		}

		pconfig_to_address($natent['source'], $_POST['src'],
			$_POST['srcmask'], $_POST['srcnot'],
			$_POST['srcbeginport'], $_POST['srcendport']);

		pconfig_to_address($natent['destination'], $_POST['dst'],
			$_POST['dstmask'], $_POST['dstnot'],
			$_POST['dstbeginport'], $_POST['dstendport']);

		$natent['protocol'] = $_POST['proto'];

		if (!$natent['nordr']) {
			$natent['target'] = $_POST['localip'];
			$natent['local-port'] = $_POST['localbeginport'];
		}
		$natent['interface'] = $_POST['interface'];
		$natent['descr'] = $_POST['descr'];
		$natent['associated-rule-id'] = $_POST['associated-rule-id'];

		if($_POST['filter-rule-association'] == "pass")
			$natent['associated-rule-id'] = "pass";

		if($_POST['nosync'] == "yes")
			$natent['nosync'] = true;
		else
			unset($natent['nosync']);

		if ($_POST['natreflection'] == "enable" || $_POST['natreflection'] == "purenat" || $_POST['natreflection'] == "disable")
			$natent['natreflection'] = $_POST['natreflection'];
		else
			unset($natent['natreflection']);

		// If we used to have an associated filter rule, but no-longer should have one
		if (!empty($a_nat[$id]) && ( empty($natent['associated-rule-id']) || $natent['associated-rule-id'] != $a_nat[$id]['associated-rule-id'] ) ) {
			// Delete the previous rule
			delete_id($a_nat[$id]['associated-rule-id'], $config['filter']['rule']);
			mark_subsystem_dirty('filter');
		}

		$need_filter_rule = false;
		// Updating a rule with a filter rule associated
		if (!empty($natent['associated-rule-id']))
			$need_filter_rule = true;
		// Create a rule or if we want to create a new one
		if( $natent['associated-rule-id']=='new' ) {
			$need_filter_rule = true;
			unset( $natent['associated-rule-id'] );
			$_POST['filter-rule-association']='add-associated';
		}
		// If creating a new rule, where we want to add the filter rule, associated or not
		else if( isset($_POST['filter-rule-association']) &&
			($_POST['filter-rule-association']=='add-associated' ||
			$_POST['filter-rule-association']=='add-unassociated') )
			$need_filter_rule = true;

		if ($need_filter_rule == true) {

			/* auto-generate a matching firewall rule */
			$filterent = array();
			unset($filterentid);
			// If a rule already exists, load it
			if (!empty($natent['associated-rule-id'])) {
				$filterentid = get_id($natent['associated-rule-id'], $config['filter']['rule']);
				if ($filterentid === false)
					$filterent['associated-rule-id'] = $natent['associated-rule-id'];
				else
					$filterent =& $config['filter']['rule'][$filterentid];
			}
			pconfig_to_address($filterent['source'], $_POST['src'],
				$_POST['srcmask'], $_POST['srcnot'],
				$_POST['srcbeginport'], $_POST['srcendport']);

			// Update interface, protocol and destination
			$filterent['interface'] = $_POST['interface'];
			$filterent['protocol'] = $_POST['proto'];
			$filterent['destination']['address'] = $_POST['localip'];

			$dstpfrom = $_POST['localbeginport'];
			$dstpto = $dstpfrom + $_POST['dstendport'] - $_POST['dstbeginport'];

			if ($dstpfrom == $dstpto)
				$filterent['destination']['port'] = $dstpfrom;
			else
				$filterent['destination']['port'] = $dstpfrom . "-" . $dstpto;

			/*
			 * Our firewall filter description may be no longer than
			 * 63 characters, so don't let it be.
			 */
			$filterent['descr'] = substr("NAT " . $_POST['descr'], 0, 62);

			// If this is a new rule, create an ID and add the rule
			if( $_POST['filter-rule-association']=='add-associated' ) {
				$filterent['associated-rule-id'] = $natent['associated-rule-id'] = get_unique_id();
				$filterent['created'] = make_config_revision_entry(null, gettext("NAT Port Forward"));
				$config['filter']['rule'][] = $filterent;
			}

			mark_subsystem_dirty('filter');
		}

		if ( isset($a_nat[$id]['created']) && is_array($a_nat[$id]['created']) )
			$natent['created'] = $a_nat[$id]['created'];

		$natent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation 
		pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/pre_write_config");

		// Update the NAT entry now
		if (isset($id) && $a_nat[$id])
			$a_nat[$id] = $natent;
		else {
			$natent['created'] = make_config_revision_entry();
			if (is_numeric($after))
				array_splice($a_nat, $after+1, 0, array($natent));
			else
				$a_nat[] = $natent;
		}

		if (write_config())
			mark_subsystem_dirty('natconf');

		header("Location: firewall_nat.php");
		exit;
	}
}

$closehead = false;
$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Port Forward"),gettext("Edit"));
include("head.inc");

?>
<link type="text/css" rel="stylesheet" href="/javascript/chosen/chosen.css" />
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script src="/javascript/chosen/chosen.jquery.js" type="text/javascript"></script>
<?php
include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="firewall nat edit">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit Redirect entry"); ?></td>
				</tr>
<?php
		// Allow extending of the firewall edit page and include custom input validation 
		pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/htmlphpearly");
?>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled"); ?></td>
			<td width="78%" class="vtable">
				<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Disable this rule"); ?></strong><br />
				<span class="vexpl"><?=gettext("Set this option to disable this rule without removing it from the list."); ?></span>
			</td>
		</tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("No RDR (NOT)"); ?></td>
                  <td width="78%" class="vtable">
                    <input type="checkbox" name="nordr" id="nordr" onclick="nordr_change();" <?php if($pconfig['nordr']) echo "checked=\"checked\""; ?> />
                    <span class="vexpl"><?=gettext("Enabling this option will disable redirection for traffic matching this rule."); ?>
                    <br /><?=gettext("Hint: this option is rarely needed, don't use this unless you know what you're doing."); ?></span>
                  </td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface"); ?></td>
                  <td width="78%" class="vtable">
					<select name="interface" class="formselect" onchange="dst_change(this.value,iface_old,document.iform.dsttype.value);iface_old = document.iform.interface.value;typesel_change();">
						<?php

						$iflist = get_configured_interface_with_descr(false, true);
						// Allow extending of the firewall edit interfaces 
						pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/pre_interfaces_edit");
						foreach ($iflist as $if => $ifdesc)
							if(have_ruleint_access($if))
								$interfaces[$if] = $ifdesc;

						if ($config['l2tp']['mode'] == "server")
							if(have_ruleint_access("l2tp"))
								$interfaces['l2tp'] = "L2TP VPN";

						if ($config['pptpd']['mode'] == "server")
							if(have_ruleint_access("pptp"))
								$interfaces['pptp'] = "PPTP VPN";

						if (is_pppoe_server_enabled() && have_ruleint_access("pppoe"))
							$interfaces['pppoe'] = "PPPoE VPN";

						/* add ipsec interfaces */
						if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
							if(have_ruleint_access("enc0"))
								$interfaces["enc0"] = "IPsec";

						/* add openvpn/tun interfaces */
						if  ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
							$interfaces["openvpn"] = "OpenVPN";

						foreach ($interfaces as $iface => $ifacename): ?>
						<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected=\"selected\""; ?>>
						<?=htmlspecialchars($ifacename);?>
						</option>
						<?php endforeach; ?>
					</select><br />
                     <span class="vexpl"><?=gettext("Choose which interface this rule applies to."); ?><br />
                     <?=gettext("Hint: in most cases, you'll want to use WAN here."); ?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Protocol"); ?></td>
                  <td width="78%" class="vtable">
                    <select name="proto" class="formselect" onchange="proto_change(); check_for_aliases();">
                      <?php $protocols = explode(" ", "TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP PIM OSPF"); foreach ($protocols as $proto): ?>
                      <option value="<?=strtolower($proto);?>" <?php if (strtolower($proto) == $pconfig['proto']) echo "selected=\"selected\""; ?>><?=htmlspecialchars($proto);?></option>
                      <?php endforeach; ?>
                    </select> <br /> <span class="vexpl"><?=gettext("Choose which IP protocol " .
                    "this rule should match."); ?><br />
                    <?=gettext("Hint: in most cases, you should specify"); ?> <em><?=gettext("TCP"); ?></em> &nbsp;<?=gettext("here."); ?></span></td>
                </tr>
		<tr id="showadvancedboxsrc" name="showadvancedboxsrc">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Source"); ?></td>
			<td width="78%" class="vtable">
				<input type="button" onclick="show_source()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show source address and port range"); ?>
			</td>
		</tr>
		<tr style="display: none;" id="srctable" name="srctable">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Source"); ?></td>
			<td width="78%" class="vtable">
				<input name="srcnot" type="checkbox" id="srcnot" value="yes" <?php if ($pconfig['srcnot']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("not"); ?></strong>
				<br />
				<?=gettext("Use this option to invert the sense of the match."); ?>
				<br />
				<br />
				<table border="0" cellspacing="0" cellpadding="0" summary="type">
					<tr>
						<td><?=gettext("Type:"); ?>&nbsp;&nbsp;</td>
						<td>
							<select name="srctype" class="formselect" onchange="typesel_change()">
<?php
								$sel = is_specialnet($pconfig['src']); ?>
								<option value="any"     <?php if ($pconfig['src'] == "any") { echo "selected=\"selected\""; } ?>><?=gettext("any"); ?></option>
								<option value="single"  <?php if (($pconfig['srcmask'] == 32) && !$sel) { echo "selected=\"selected\""; $sel = 1; } ?>><?=gettext("Single host or alias"); ?></option>
								<option value="network" <?php if (!$sel) echo "selected=\"selected\""; ?>><?=gettext("Network"); ?></option>
								<?php if(have_ruleint_access("pptp")): ?>
								<option value="pptp"    <?php if ($pconfig['src'] == "pptp") { echo "selected=\"selected\""; } ?>><?=gettext("PPTP clients"); ?></option>
								<?php endif; ?>
								<?php if(have_ruleint_access("pppoe")): ?>
								<option value="pppoe"   <?php if ($pconfig['src'] == "pppoe") { echo "selected=\"selected\""; } ?>><?=gettext("PPPoE clients"); ?></option>
								<?php endif; ?>
								 <?php if(have_ruleint_access("l2tp")): ?>
                                                                <option value="l2tp"   <?php if ($pconfig['src'] == "l2tp") { echo "selected=\"selected\""; } ?>><?=gettext("L2TP clients"); ?></option>
                                 <?php endif; ?>
<?php
								foreach ($ifdisp as $ifent => $ifdesc): ?>
								<?php if(have_ruleint_access($ifent)): ?>
									<option value="<?=$ifent;?>" <?php if ($pconfig['src'] == $ifent) { echo "selected=\"selected\""; } ?>><?=htmlspecialchars($ifdesc);?> <?=gettext("net"); ?></option>
									<option value="<?=$ifent;?>ip"<?php if ($pconfig['src'] ==  $ifent . "ip") { echo "selected=\"selected\""; } ?>>
										<?=$ifdesc?> <?=gettext("address");?>
									</option>
								<?php endif; ?>
<?php 							endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td><?=gettext("Address:"); ?>&nbsp;&nbsp;</td>
						<td>
							<input autocomplete='off' name="src" type="text" class="formfldalias" id="src" size="20" value="<?php if (!is_specialnet($pconfig['src'])) echo htmlspecialchars($pconfig['src']);?>" /> /
							<select name="srcmask" class="formselect" id="srcmask">
<?php						for ($i = 31; $i > 0; $i--): ?>
								<option value="<?=$i;?>" <?php if ($i == $pconfig['srcmask']) echo "selected=\"selected\""; ?>><?=$i;?></option>
<?php 						endfor; ?>
							</select>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr style="display:none" id="sprtable" name="sprtable">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Source port range"); ?></td>
			<td width="78%" class="vtable">
				<table border="0" cellspacing="0" cellpadding="0" summary="source port range">
					<tr>
						<td><?=gettext("from:"); ?>&nbsp;&nbsp;</td>
						<td>
							<select name="srcbeginport" class="formselect" onchange="src_rep_change();ext_change()">
								<option value="">(<?=gettext("other"); ?>)</option>
								<option value="any" <?php $bfound = 0; if ($pconfig['srcbeginport'] == "any") { echo "selected=\"selected\""; $bfound = 1; } ?>><?=gettext("any"); ?></option>
<?php 							foreach ($wkports as $wkport => $wkportdesc): ?>
									<option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['srcbeginport']) { echo "selected=\"selected\""; $bfound = 1; } ?>><?=htmlspecialchars($wkportdesc);?></option>
<?php 							endforeach; ?>
							</select>
							<input autocomplete='off' class="formfldalias" name="srcbeginport_cust" id="srcbeginport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['srcbeginport']) echo htmlspecialchars($pconfig['srcbeginport']); ?>" />
						</td>
					</tr>
					<tr>
						<td><?=gettext("to:"); ?></td>
						<td>
							<select name="srcendport" class="formselect" onchange="ext_change()">
								<option value="">(<?=gettext("other"); ?>)</option>
								<option value="any" <?php $bfound = 0; if ($pconfig['srcendport'] == "any") { echo "selected=\"selected\""; $bfound = 1; } ?>><?=gettext("any"); ?></option>
<?php							foreach ($wkports as $wkport => $wkportdesc): ?>
									<option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['srcendport']) { echo "selected=\"selected\""; $bfound = 1; } ?>><?=htmlspecialchars($wkportdesc);?></option>
<?php							endforeach; ?>
							</select>
							<input autocomplete='off' class="formfldalias" name="srcendport_cust" id="srcendport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['srcendport']) echo htmlspecialchars($pconfig['srcendport']); ?>" />
						</td>
					</tr>
				</table>
				<br />
				<span class="vexpl"><?=gettext("Specify the source port or port range for this rule"); ?>. <b><?=gettext("This is usually"); ?> <em><?=gettext("random"); ?></em> <?=gettext("and almost never equal to the destination port range (and should usually be 'any')"); ?>.</b> <br /> <?=gettext("Hint: you can leave the"); ?> <em>'<?=gettext("to"); ?>'</em> <?=gettext("field empty if you only want to filter a single port."); ?></span><br />
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Destination"); ?></td>
			<td width="78%" class="vtable">
				<input name="dstnot" type="checkbox" id="dstnot" value="yes" <?php if ($pconfig['dstnot']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("not"); ?></strong>
					<br />
				<?=gettext("Use this option to invert the sense of the match."); ?>
					<br />
					<br />
				<table border="0" cellspacing="0" cellpadding="0" summary="type">
					<tr>
						<td><?=gettext("Type:"); ?>&nbsp;&nbsp;</td>
						<td>
							<select name="dsttype" class="formselect" onchange="typesel_change()">
<?php
								$sel = is_specialnet($pconfig['dst']); ?>
								<option value="any" <?php if ($pconfig['dst'] == "any") { echo "selected=\"selected\""; } ?>><?=gettext("any"); ?></option>
								<option value="single" <?php if (($pconfig['dstmask'] == 32) && !$sel) { echo "selected=\"selected\""; $sel = 1; } ?>><?=gettext("Single host or alias"); ?></option>
								<option value="network" <?php if (!$sel) echo "selected=\"selected\""; ?>><?=gettext("Network"); ?></option>
								<option value="(self)" <?PHP if ($pconfig['dst'] == "(self)") echo "selected=\"selected\""; ?>><?=gettext("This Firewall (self)");?></option>
								<?php if(have_ruleint_access("pptp")): ?>
								<option value="pptp" <?php if ($pconfig['dst'] == "pptp") { echo "selected=\"selected\""; } ?>><?=gettext("PPTP clients"); ?></option>
								<?php endif; ?>
								<?php if(have_ruleint_access("pppoe")): ?>
								<option value="pppoe" <?php if ($pconfig['dst'] == "pppoe") { echo "selected=\"selected\""; } ?>><?=gettext("PPPoE clients"); ?></option>
								<?php endif; ?>
								<?php if(have_ruleint_access("l2tp")): ?>
                                                                <option value="l2tp" <?php if ($pconfig['dst'] == "l2tp") { echo "selected=\"selected\""; } ?>><?=gettext("L2TP clients"); ?></option>
                                                                <?php endif; ?>

<?php 							foreach ($ifdisp as $if => $ifdesc): ?>
								<?php if(have_ruleint_access($if)): ?>
									<option value="<?=$if;?>" <?php if ($pconfig['dst'] == $if) { echo "selected=\"selected\""; } ?>><?=htmlspecialchars($ifdesc);?> <?=gettext("net"); ?></option>
									<option value="<?=$if;?>ip"<?php if ($pconfig['dst'] == $if . "ip") { echo "selected=\"selected\""; } ?>>
										<?=$ifdesc;?> <?=gettext("address");?>
									</option>
								<?php endif; ?>
<?php 							endforeach; ?>

<?php							if (is_array($config['virtualip']['vip'])):
									foreach ($config['virtualip']['vip'] as $sn):
										if (isset($sn['noexpand']))
											continue;
										if ($sn['mode'] == "proxyarp" && $sn['type'] == "network"):
											$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
											$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
											$len = $end - $start;
											for ($i = 0; $i <= $len; $i++):
												$snip = long2ip32($start+$i);
?>
												<option value="<?=$snip;?>" <?php if ($snip == $pconfig['dst']) echo "selected=\"selected\""; ?>><?=htmlspecialchars("{$snip} ({$sn['descr']})");?></option>
<?php										endfor;
										else:
?>
											<option value="<?=$sn['subnet'];?>" <?php if ($sn['subnet'] == $pconfig['dst']) echo "selected=\"selected\""; ?>><?=htmlspecialchars("{$sn['subnet']} ({$sn['descr']})");?></option>
<?php									endif;
									endforeach;
								endif;
?>
							</select>
						</td>
					</tr>
					<tr>
						<td><?=gettext("Address:"); ?>&nbsp;&nbsp;</td>
						<td>
							<input autocomplete='off' name="dst" type="text" class="formfldalias" id="dst" size="20" value="<?php if (!is_specialnet($pconfig['dst'])) echo htmlspecialchars($pconfig['dst']);?>" />
							/
							<select name="dstmask" class="formselect" id="dstmask">
<?php
							for ($i = 31; $i > 0; $i--): ?>
								<option value="<?=$i;?>" <?php if ($i == $pconfig['dstmask']) echo "selected=\"selected\""; ?>><?=$i;?></option>
<?php						endfor; ?>
							</select>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr id="dprtr" name="dprtr">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Destination port range"); ?> </td>
			<td width="78%" class="vtable">
				<table border="0" cellspacing="0" cellpadding="0" summary="destination port range">
					<tr>
						<td><?=gettext("from:"); ?>&nbsp;&nbsp;</td>
						<td>
							<select name="dstbeginport" id="dstbeginport" class="formselect" onchange="dst_rep_change();ext_change()">
								<option value="">(<?=gettext("other"); ?>)</option>
<?php 							$bfound = 0;
								foreach ($wkports as $wkport => $wkportdesc): ?>
									<option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['dstbeginport']) { echo "selected=\"selected\""; $bfound = 1; }?>><?=htmlspecialchars($wkportdesc);?></option>
<?php 							endforeach; ?>
							</select>
							<input autocomplete='off' class="formfldalias" name="dstbeginport_cust" id="dstbeginport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['dstbeginport']) echo htmlspecialchars($pconfig['dstbeginport']); ?>" />
						</td>
					</tr>
					<tr>
						<td><?=gettext("to:"); ?></td>
						<td>
							<select name="dstendport" id="dstendport" class="formselect" onchange="ext_change()">
								<option value="">(<?=gettext("other"); ?>)</option>
<?php							$bfound = 0;
								foreach ($wkports as $wkport => $wkportdesc): ?>
									<option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['dstendport']) { echo "selected=\"selected\""; $bfound = 1; } ?>><?=htmlspecialchars($wkportdesc);?></option>
<?php 							endforeach; ?>
							</select>
							<input autocomplete='off' class="formfldalias" name="dstendport_cust" id="dstendport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['dstendport']) echo htmlspecialchars($pconfig['dstendport']); ?>" />
						</td>
					</tr>
				</table>
				<br />
				<span class="vexpl">
					<?=gettext("Specify the port or port range for the destination of the packet for this mapping."); ?>
					<br />
					<?=gettext("Hint: you can leave the"); ?> <em>'<?=gettext("to"); ?>'</em> <?=gettext("field empty if you only want to map a single port"); ?>
				</span>
			</td>
		</tr>
                <tr name="localiptable" id="localiptable">
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Redirect target IP"); ?></td>
                  <td width="78%" class="vtable">
                    <input autocomplete='off' name="localip" type="text" class="formfldalias" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip']);?>" />
                    <br /> <span class="vexpl"><?=gettext("Enter the internal IP address of " .
                    "the server on which you want to map the ports."); ?><br />
                    <?=gettext("e.g."); ?> <em>192.168.1.12</em></span></td>
                </tr>
                <tr name="lprtr" id="lprtr">
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Redirect target port"); ?></td>
                  <td width="78%" class="vtable">
                    <select name="localbeginport" id="localbeginport" class="formselect" onchange="ext_change();check_for_aliases();">
                      <option value="">(<?=gettext("other"); ?>)</option>
                      <?php $bfound = 0; foreach ($wkports as $wkport => $wkportdesc): ?>
                      <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['localbeginport']) {
							echo "selected=\"selected\"";
							$bfound = 1;
						}?>>
					  <?=htmlspecialchars($wkportdesc);?>
					  </option>
                      <?php endforeach; ?>
                    </select> <input onchange="check_for_aliases();" autocomplete='off' class="formfldalias" name="localbeginport_cust" id="localbeginport_cust" type="text" size="5" value="<?php if (!$bfound) echo htmlspecialchars($pconfig['localbeginport']); ?>" />
                    <br />
                    <span class="vexpl"><?=gettext("Specify the port on the machine with the " .
                    "IP address entered above. In case of a port range, specify " .
                    "the beginning port of the range (the end port will be calculated " .
                    "automatically)."); ?><br />
                    <?=gettext("Hint: this is usually identical to the 'from' port above"); ?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                    <br /> <span class="vexpl"><?=gettext("You may enter a description here " .
                    "for your reference (not parsed)."); ?></span></td>
                </tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("No XMLRPC Sync"); ?></td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="yes" name="nosync"<?php if($pconfig['nosync']) echo " checked=\"checked\""; ?> /><br />
						<?=gettext("Hint: This prevents the rule on Master from automatically syncing to other CARP members. This does NOT prevent the rule from being overwritten on Slave.");?>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("NAT reflection"); ?></td>
					<td width="78%" class="vtable">
						<select name="natreflection" class="formselect">
						<option value="default" <?php if ($pconfig['natreflection'] != "enable" && $pconfig['natreflection'] != "purenat" && $pconfig['natreflection'] != "disable") echo "selected=\"selected\""; ?>><?=gettext("Use system default"); ?></option>
						<option value="enable" <?php if ($pconfig['natreflection'] == "enable") echo "selected=\"selected\""; ?>><?=gettext("Enable (NAT + Proxy)"); ?></option>
						<option value="purenat" <?php if ($pconfig['natreflection'] == "purenat") echo "selected=\"selected\""; ?>><?=gettext("Enable (Pure NAT)"); ?></option>
						<option value="disable" <?php if ($pconfig['natreflection'] == "disable") echo "selected=\"selected\""; ?>><?=gettext("Disable"); ?></option>
						</select>
					</td>
				</tr>
				<?php if (isset($id) && $a_nat[$id] && (!isset($_GET['dup']) || !is_numericint($_GET['dup']))): ?>
				<tr name="assoctable" id="assoctable">
					<td width="22%" valign="top" class="vncell"><?=gettext("Filter rule association"); ?></td>
					<td width="78%" class="vtable">
						<select name="associated-rule-id">
							<option value=""><?=gettext("None"); ?></option>
							<option value="pass" <?php if($pconfig['associated-rule-id'] == "pass") echo " selected=\"selected\""; ?>><?=gettext("Pass"); ?></option>
							<?php
							$linkedrule = "";
							if (is_array($config['filter']['rule'])) {
							      filter_rules_sort();
							      foreach ($config['filter']['rule'] as $filter_id => $filter_rule) {
								if (isset($filter_rule['associated-rule-id'])) {
									echo "<option value=\"{$filter_rule['associated-rule-id']}\"";
									if ($filter_rule['associated-rule-id']==$pconfig['associated-rule-id']) {
										echo " selected=\"selected\"";
										$linkedrule = "<br /><a href=\"firewall_rules_edit.php?id={$filter_id}\">" . gettext("View the filter rule") . "</a><br />";
									}
									echo ">". htmlspecialchars('Rule ' . $filter_rule['descr']) . "</option>\n";

								}
							      }
							}
							if (isset($pconfig['associated-rule-id']))
								echo "<option value=\"new\">" . gettext("Create new associated filter rule") . "</option>\n";
						echo "</select>\n";
						echo $linkedrule;
						?>
					</td>
				</tr>
				<?php endif; ?>
                <?php if ((!(isset($id) && $a_nat[$id])) || (isset($_GET['dup']) && is_numericint($_GET['dup']))): ?>
                <tr name="assoctable" id="assoctable">
                  <td width="22%" valign="top" class="vncell"><?=gettext("Filter rule association"); ?></td>
                  <td width="78%" class="vtable">
                    <select name="filter-rule-association" id="filter-rule-association">
						<option value=""><?=gettext("None"); ?></option>
						<option value="add-associated" selected="selected"><?=gettext("Add associated filter rule"); ?></option>
						<option value="add-unassociated"><?=gettext("Add unassociated filter rule"); ?></option>
						<option value="pass"><?=gettext("Pass"); ?></option>
					</select>
					<br /><br /><?=gettext("NOTE: The \"pass\" selection does not work properly with Multi-WAN. It will only work on an interface containing the default gateway.")?>
				  </td>
                </tr><?php endif; ?>
<?php
		// Allow extending of the firewall edit page and include custom input validation 
		pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/htmlphplate");
?>
<?php
$has_created_time = (isset($a_nat[$id]['created']) && is_array($a_nat[$id]['created']));
$has_updated_time = (isset($a_nat[$id]['updated']) && is_array($a_nat[$id]['updated']));
?>
		<?php if ($has_created_time || $has_updated_time): ?>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Rule Information");?></td>
		</tr>
		<?php if ($has_created_time): ?>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Created");?></td>
			<td width="78%" class="vtable">
				<?= date(gettext("n/j/y H:i:s"), $a_nat[$id]['created']['time']) ?> <?= gettext("by") ?> <strong><?= $a_nat[$id]['created']['username'] ?></strong>
			</td>
		</tr>
		<?php endif; ?>
		<?php if ($has_updated_time): ?>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Updated");?></td>
			<td width="78%" class="vtable">
				<?= date(gettext("n/j/y H:i:s"), $a_nat[$id]['updated']['time']) ?> <?= gettext("by") ?> <strong><?= $a_nat[$id]['updated']['username'] ?></strong>
			</td>
		</tr>
		<?php endif; ?>
		<?php endif; ?>
				<tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">&nbsp;</td>
				</tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
                    <input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
                    <?php if (isset($id) && $a_nat[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                    <?php endif; ?>
                    <input name="after" type="hidden" value="<?=htmlspecialchars($after);?>" />
                  </td>
                </tr>
              </table>
</form>
<script type="text/javascript">
//<![CDATA[
	ext_change();
	dst_change(document.iform.interface.value,'<?=htmlspecialchars($pconfig['interface'])?>','<?=htmlspecialchars($pconfig['dst'])?>');
	var iface_old = document.iform.interface.value;
	typesel_change();
	proto_change();
	<?php if ($pconfig['srcnot'] || $pconfig['src'] != "any" || $pconfig['srcbeginport'] != "any" || $pconfig['srcendport'] != "any"): ?>
	show_source();
	<?php endif; ?>
	nordr_change();
//]]>
</script>
<script type="text/javascript">
//<![CDATA[
	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;
	var customarray  = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

	var oTextbox1 = new AutoSuggestControl(document.getElementById("localip"), new StateSuggestions(addressarray));
	var oTextbox2 = new AutoSuggestControl(document.getElementById("src"), new StateSuggestions(addressarray));
	var oTextbox3 = new AutoSuggestControl(document.getElementById("dst"), new StateSuggestions(addressarray));
	var oTextbox4 = new AutoSuggestControl(document.getElementById("dstbeginport_cust"), new StateSuggestions(customarray));
	var oTextbox5 = new AutoSuggestControl(document.getElementById("dstendport_cust"), new StateSuggestions(customarray));
	var oTextbox6 = new AutoSuggestControl(document.getElementById("srcbeginport_cust"), new StateSuggestions(customarray));
	var oTextbox7 = new AutoSuggestControl(document.getElementById("srcendport_cust"), new StateSuggestions(customarray));
	var oTextbox8 = new AutoSuggestControl(document.getElementById("localbeginport_cust"), new StateSuggestions(customarray));
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
