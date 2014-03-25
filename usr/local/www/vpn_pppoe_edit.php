<?php
/*
	vpn_pppoe_edit.php
	part of pfSense
	
	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
	Copyright (C) 2010 Ermal Luçi
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

##|+PRIV
##|*IDENT=page-services-pppoeserver-edit
##|*NAME=Services: PPPoE Server: Edit page
##|*DESCR=Allow access to the 'Services: PPPoE Server: Edit' page.
##|*MATCH=vpn_pppoe_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("vpn.inc");

function vpn_pppoe_get_id() {
	global $config;

	$vpnid = 1;
	if (is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			if ($vpnid == $pppoe['pppoeid'])
				$vpnid++;
			else
				return $vpnid; 
		}
	}

	return $vpnid;
}

if (!is_array($config['pppoes']['pppoe'])) {
	$config['pppoes']['pppoe'] = array();
}
$a_pppoes = &$config['pppoes']['pppoe'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_pppoes[$id]) {
	$pppoecfg =& $a_pppoes[$id];

	$pconfig['remoteip'] = $pppoecfg['remoteip'];
	$pconfig['localip'] = $pppoecfg['localip'];
	$pconfig['mode'] = $pppoecfg['mode'];
	$pconfig['interface'] = $pppoecfg['interface'];
	$pconfig['n_pppoe_units'] = $pppoecfg['n_pppoe_units'];
	$pconfig['pppoe_subnet'] = $pppoecfg['pppoe_subnet'];
	$pconfig['pppoe_dns1'] = $pppoecfg['dns1'];
	$pconfig['pppoe_dns2'] = $pppoecfg['dns2'];
	$pconfig['descr'] = $pppoecfg['descr'];
	$pconfig['username'] = $pppoecfg['username'];
	$pconfig['pppoeid'] = $pppoecfg['pppoeid'];
	if (is_array($pppoecfg['radius'])) {
		$pconfig['radacct_enable'] = isset($pppoecfg['radius']['accounting']);
		$pconfig['radiusissueips'] = isset($pppoecfg['radius']['radiusissueips']);
		if (is_array($pppoecfg['radius']['server'])) {
			$pconfig['radiusenable'] = isset($pppoecfg['radius']['server']['enable']);
			$pconfig['radiusserver'] = $pppoecfg['radius']['server']['ip'];
			$pconfig['radiusserverport'] = $pppoecfg['radius']['server']['port'];
			$pconfig['radiusserveracctport'] = $pppoecfg['radius']['server']['acctport'];
			$pconfig['radiussecret'] = $pppoecfg['radius']['server']['secret'];
		}
		if (is_array($pppoecfg['radius']['server2'])) {
			$pconfig['radiussecenable'] = isset($pppoecfg['radius']['server2']['enable']);
			$pconfig['radiusserver2'] = $pppoecfg['radius']['server2']['ip'];
			$pconfig['radiusserver2port'] = $pppoecfg['radius']['server2']['port'];
			$pconfig['radiusserver2acctport'] = $pppoecfg['radius']['server2']['acctport'];
			$pconfig['radiussecret2'] = $pppoecfg['radius']['server2']['secret2'];
		}
		$pconfig['radius_nasip'] = $pppoecfg['radius']['nasip'];
		$pconfig['radius_acct_update'] = $pppoecfg['radius']['acct_update'];
	}
}


if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['mode'] == "server") {
		$reqdfields = explode(" ", "localip remoteip");
		$reqdfieldsn = array(gettext("Server address"),gettext("Remote start address"));

		if ($_POST['radiusenable']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
			$reqdfieldsn = array_merge($reqdfieldsn,
				array(gettext("RADIUS server address"),gettext("RADIUS shared secret")));
		}

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

		if (($_POST['localip'] && !is_ipaddr($_POST['localip'])))
			$input_errors[] = gettext("A valid server address must be specified.");
		if (($_POST['pppoe_subnet'] && !is_ipaddr($_POST['remoteip'])))
			$input_errors[] = gettext("A valid remote start address must be specified.");
		if (($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver'])))
			$input_errors[] = gettext("A valid RADIUS server address must be specified.");

		$_POST['remoteip'] = $pconfig['remoteip'] = gen_subnet($_POST['remoteip'], $_POST['pppoe_subnet']);
		$subnet_start = ip2ulong($_POST['remoteip']);
		$subnet_end = ip2ulong($_POST['remoteip']) + $_POST['pppoe_subnet'] - 1;
		if ((ip2ulong($_POST['localip']) >= $subnet_start) &&
		    (ip2ulong($_POST['localip']) <= $subnet_end))
			$input_errors[] = gettext("The specified server address lies in the remote subnet.");	
		if ($_POST['localip'] == get_interface_ip($_POST['interface']))
			$input_errors[] = gettext("The specified server address is equal to an interface ip address.");	

		for($x=0; $x<4999; $x++) {
			if ($_POST["username{$x}"]) {
				if (empty($_POST["password{$x}"]))
					$input_errors[] = sprintf(gettext("No password specified for username %s"),$_POST["username{$x}"]);
				if ($_POST["ip{$x}"] <> "" && !is_ipaddr($_POST["ip{$x}"]))
					$input_errors[] = sprintf(gettext("Incorrect ip address  specified for username %s"),$_POST["username{$x}"]);
			}
		}
	}

	if ($_POST['pppoeid'] && !is_numeric($_POST['pppoeid']))
		$input_errors[] = gettext("Wrong data submitted");

	if (!$input_errors) {
		$pppoecfg = array();

		$pppoecfg['remoteip'] = $_POST['remoteip'];
		$pppoecfg['localip'] = $_POST['localip'];
		$pppoecfg['mode'] = $_POST['mode'];
		$pppoecfg['interface'] = $_POST['interface'];
		$pppoecfg['n_pppoe_units'] = $_POST['n_pppoe_units'];	
		$pppoecfg['pppoe_subnet'] = $_POST['pppoe_subnet'];
		$pppoecfg['descr'] = $_POST['descr'];
		if ($_POST['radiusserver'] || $_POST['radiusserver2']) {
			$pppoecfg['radius'] = array();

			$pppoecfg['radius']['nasip'] = $_POST['radius_nasip'];
                        $pppoecfg['radius']['acct_update'] = $_POST['radius_acct_update'];
		}
		if ($_POST['radiusserver']) {
			$pppoecfg['radius']['server'] = array();

			$pppoecfg['radius']['server']['ip'] = $_POST['radiusserver'];
			$pppoecfg['radius']['server']['secret'] = $_POST['radiussecret'];
			$pppoecfg['radius']['server']['port'] = $_POST['radiusserverport'];
			$pppoecfg['radius']['server']['acctport'] = $_POST['radiusserveracctport'];
		}
		if ($_POST['radiusserver2']) {
			$pppoecfg['radius']['server2'] = array();

			$pppoecfg['radius']['server2']['ip'] = $_POST['radiusserver2'];
			$pppoecfg['radius']['server2']['secret2'] = $_POST['radiussecret2'];
			$pppoecfg['radius']['server2']['port'] = $_POST['radiusserver2port'];
			$pppoecfg['radius']['server2']['acctport'] = $_POST['radiusserver2acctport'];
		}

 		if ($_POST['pppoe_dns1'] <> "") 
			$pppoecfg['dns1'] = $_POST['pppoe_dns1'];

 		if ($_POST['pppoe_dns2'] <> "") 
			$pppoecfg['dns2'] = $_POST['pppoe_dns2'];

		if($_POST['radiusenable'] == "yes")
			$pppoecfg['radius']['server']['enable'] = true;
			
		if($_POST['radiussecenable'] == "yes")
			$pppoecfg['radius']['server2']['enable'] = true;
			
		if($_POST['radacct_enable'] == "yes")
			$pppoecfg['radius']['accounting'] = true;

		if($_POST['radiusissueips'] == "yes")
			$pppoecfg['radius']['radiusissueips'] = true;

		if($_POST['pppoeid'])
			$pppoecfg['pppoeid'] = $_POST['pppoeid'];
		else
			$pppoecfg['pppoeid'] = vpn_pppoe_get_id();

		$users = array();
		for($x=0; $x<4999; $x++) {
			if ($_POST["username{$x}"]) {
				$usernam = $_POST["username{$x}"] . ":" . base64_encode($_POST["password{$x}"]);
				if ($_POST["ip{$x}"])
					$usernam .= ":" . $_POST["ip{$x}"];
				$users[] = $usernam;
			}
		}
		if (count($users) > 0)
			$pppoecfg['username'] = implode(" ", $users);

		if (!isset($id))
                        $id = count($a_pppoes);
                if (file_exists("{$g['tmp_path']}/.vpn_pppoe.apply"))
                        $toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.vpn_pppoe.apply"));
                else
                        $toapplylist = array();

                $toapplylist[] = $pppoecfg['pppoeid'];
		$a_pppoes[$id] = $pppoecfg;

		write_config();
		mark_subsystem_dirty('vpnpppoe');
		file_put_contents("{$g['tmp_path']}/.vpn_pppoe.apply", serialize($toapplylist));	
		header("Location: vpn_pppoe.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("PPPoE Server"), gettext("Edit"));
$shortcut_section = "pppoes";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/javascript" src="/javascript/row_helper.js">
</script>

<input type='hidden' name='username' value='textbox' class="formfld unknown" />
<input type='hidden' name='password' value='textbox' />
<input type='hidden' name='ip' value='textbox' />

<script type="text/javascript">
        rowname[0] = "username";
        rowtype[0] = "textbox";
        rowsize[0] = "20";

        rowname[1] = "password";
        rowtype[1] = "password";
        rowsize[1] = "20";

        rowname[2] = "ip";
        rowtype[2] = "textbox";
        rowsize[2] = "10";
</script>

<script type="text/JavaScript">
<!--
function get_radio_value(obj)
{
	for (i = 0; i < obj.length; i++) {
		if (obj[i].checked)
			return obj[i].value;
	}
	return null;
}

function enable_change(enable_over) {
	if ((get_radio_value(document.iform.mode) == "server") || enable_over) {
		document.iform.remoteip.disabled = 0;
		document.iform.descr.disabled = 0;
		document.iform.localip.disabled = 0;
		document.iform.radiusenable.disabled = 0;
		document.iform.interface.disabled = 0;
		document.iform.n_pppoe_units.disabled = 0;		
		document.iform.pppoe_subnet.disabled = 0;		
		document.iform.pppoe_dns1.disabled = 0;
		document.iform.pppoe_dns2.disabled = 0;		
		if (document.iform.radiusenable.checked || enable_over) {
			document.iform.radacct_enable.disabled = 0;
			document.iform.radiusserver.disabled = 0;
			document.iform.radiussecret.disabled = 0;
			document.iform.radiusserverport.disabled = 0;
			document.iform.radiusserveracctport.disabled = 0;
			document.iform.radiusissueips.disabled = 0;
			document.iform.radius_nasip.disabled = 0;
			document.iform.radiusissueips.disabled = 0;
			document.iform.radius_nasip.disabled = 0;
			document.iform.radius_acct_update.disabled = 0;
			document.iform.radiussecenable.disabled = 0;
			if (document.iform.radiussecenable.checked || enable_over) {
				document.iform.radiusserver2.disabled = 0;
				document.iform.radiussecret2.disabled = 0;
				document.iform.radiusserver2port.disabled = 0;
				document.iform.radiusserver2acctport.disabled = 0;
			} else {

				document.iform.radiusserver2.disabled = 1;
				document.iform.radiussecret2.disabled = 1;
				document.iform.radiusserver2port.disabled = 1;
				document.iform.radiusserver2acctport.disabled = 1;
			}
		} else {
			document.iform.radacct_enable.disabled = 1;
			document.iform.radiusserver.disabled = 1;
			document.iform.radiussecret.disabled = 1;
			document.iform.radiusserverport.disabled = 1;
			document.iform.radiusserveracctport.disabled = 1;
			document.iform.radiusissueips.disabled = 1;
			document.iform.radius_nasip.disabled = 1;
			document.iform.radius_acct_update.disabled = 1;
			document.iform.radiussecenable.disabled = 1;
		}
	} else {
		document.iform.interface.disabled = 1;
		document.iform.n_pppoe_units.disabled = 1;		
		document.iform.pppoe_subnet.disabled = 1;		
		document.iform.remoteip.disabled = 1;
		document.iform.descr.disabled = 1;
		document.iform.localip.disabled = 1;
		document.iform.pppoe_dns1.disabled = 1;
		document.iform.pppoe_dns2.disabled = 1;
		document.iform.radiusenable.disabled = 1;
		document.iform.radiussecenable.disabled = 1;
		document.iform.radacct_enable.disabled = 1;
		document.iform.radiusserver.disabled = 1;
		document.iform.radiussecret.disabled = 1;
		document.iform.radiusserverport.disabled = 1;
		document.iform.radiusserveracctport.disabled = 1;
		document.iform.radiusserver2.disabled = 1;
		document.iform.radiussecret2.disabled = 1;
		document.iform.radiusserver2port.disabled = 1;
		document.iform.radiusserver2acctport.disabled = 1;
		document.iform.radiusissueips.disabled = 1;
		document.iform.radius_nasip.disabled = 1;
		document.iform.radius_acct_update.disabled = 1;
	}
}
//-->
</script>
<form action="vpn_pppoe_edit.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr> 
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
                  <td height="16" colspan="2" class="listtopic" valign="top"><?php echo gettext("PPPoE server configuration"); ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable"> 
                    <input name="mode" type="radio" onclick="enable_change(false)" value="off"
				  	<?php if ($pconfig['mode'] != "server") echo "checked=\"checked\"";?>/>
                    <?=gettext("Off"); ?></td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
		    <input type="radio" name="mode" value="server" onclick="enable_change(false)" <?php if ($pconfig['mode'] == "server") echo "checked=\"checked\""; ?>/>
                    <?=gettext("Enable PPPoE server"); ?></td>
		</tr>

                <tr> 
                  <td width="22%" valign="top" class="vncell"><b><?=gettext("Interface"); ?></b></td>
                  <td width="78%" valign="top" class="vtable">

			<select name="interface" class="formselect" id="interface">
			  <?php
				$interfaces = get_configured_interface_with_descr();

				foreach ($interfaces as $iface => $ifacename):
			  ?>
			  <option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected=\"selected\""; ?>>
			  <?=htmlspecialchars($ifacename);?>
			  </option>
			  <?php endforeach; ?>
			</select> <br />			
                      
		  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Subnet netmask"); ?></td>
                  <td width="78%" class="vtable">
		    <select id="pppoe_subnet" name="pppoe_subnet">
		    <?php
		     for($x=0; $x<33; $x++) {
			if($x == $pconfig['pppoe_subnet'])
				$selected = "selected=\"selected\"";
			else
				$selected = "";
			echo "<option value=\"{$x}\" {$selected}>{$x}</option>\n";			
		     }
		    ?>
		    </select>
		    <br /><?=gettext("Hint"); ?>: 24 <?=gettext("is"); ?> 255.255.255.0
                  </td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("No. PPPoE users"); ?></td>
                  <td width="78%" class="vtable">
		    <select id="n_pppoe_units" name="n_pppoe_units">
		    <?php
		     for($x=0; $x<255; $x++) {
			if($x == $pconfig['n_pppoe_units'])
				$selected = "selected=\"selected\"";
			else
				$selected = "";
			echo "<option value=\"{$x}\" {$selected}>{$x}</option>\n";			
		     }
		    ?>
		    </select>
		    <br /><?=gettext("Hint: 10 is ten PPPoE clients"); ?>
                  </td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Server address"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="localip" type="text" class="formfld unknown" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip']);?>"/> 
			<br/>
			<?=gettext("Enter the IP address the PPPoE server should give to clients for use as their \"gateway\""); ?>.
			<br/>
			<?=gettext("Typically this is set to an unused IP just outside of the client range"); ?>.
			<br/>
			<br/>
			<?=gettext("NOTE: This should NOT be set to any IP address currently in use on this firewall"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Remote address range"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="remoteip" type="text" class="formfld unknown" id="remoteip" size="20" value="<?=htmlspecialchars($pconfig['remoteip']);?>"/>
                    <br />
                    <?=gettext("Specify the starting address for the client IP address subnet"); ?>.<br />
                    </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable">
                    <?=$mandfldhtml;?><input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"/>
                    <br/>
                    </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("DNS servers"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="pppoe_dns1" type="text" class="formfld unknown" id="pppoe_dns1" size="20" value="<?=htmlspecialchars($pconfig['pppoe_dns1']);?>"/>
                    <br />
			<input name="pppoe_dns2" type="text" class="formfld unknown" id="pppoe_dns2" size="20" value="<?=htmlspecialchars($pconfig['pppoe_dns2']);?>"/>
                    <br />
                    <?=gettext("If entered they will be given to all PPPoE clients, else LAN DNS and one WAN DNS will go to all clients"); ?><br />
                    </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS"); ?></td>
                  <td width="78%" class="vtable"> 
                      <input name="radiusenable" type="checkbox" id="radiusenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiusenable']) echo "checked=\"checked\""; ?>/>
                      <strong><?=gettext("Use a RADIUS server for authentication"); ?><br />
                      </strong><?=gettext("When set, all users will be authenticated using " .
                      "the RADIUS server specified below. The local user database " .
                      "will not be used"); ?>.<br />
                      <br />
                      <input name="radacct_enable" type="checkbox" id="radacct_enable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radacct_enable']) echo "checked=\"checked\""; ?>/>
                      <strong><?=gettext("Enable RADIUS accounting"); ?> <br />
			 <br />
                      </strong><?=gettext("Sends accounting packets to the RADIUS server"); ?>.<br />
                      <input name="radiussecenable" type="checkbox" id="radiussecenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiussecenable']) echo "checked=\"checked\""; ?>/>
                      <strong><?=gettext("Use Backup RADIUS Server"); ?></strong><br />
                      <?=gettext("When set, if primary server fails all requests will be sent via backup server"); ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("NAS IP Address"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="radius_nasip" type="text" class="formfld unknown" id="radius_nasip" size="20" value="<?=htmlspecialchars($pconfig['radius_nasip']);?>"/>
                    <br /><?=gettext("RADIUS server NAS IP Address"); ?><br />
                    </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("RADIUS Accounting Update"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="radius_acct_update" type="text" class="formfld unknown" id="radius_acct_update" size="20" value="<?=htmlspecialchars($pconfig['radius_acct_update']);?>"/>
                    <br /><?=gettext("RADIUS accounting update period in seconds"); ?>
                    </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS issued IPs"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiusissueips" value="yes" type="checkbox" class="formfld" id="radiusissueips" <?php if($pconfig['radiusissueips']) echo "checked=\"checked\""; ?>/>
                      <br /><?=gettext("Issue IP Addresses via RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS server Primary"); ?></td>
                  <td width="78%" class="vtable">
                      <input name="radiusserver" type="text" class="formfld unknown" id="radiusserver" size="20" value="<?=htmlspecialchars($pconfig['radiusserver']);?>"/>
			 <input name="radiusserverport" type="text" class="formfld unknown" id="radiusserverport" size="4" value="<?=htmlspecialchars($pconfig['radiusserverport']);?>"/>
			 <input name="radiusserveracctport" type="text" class="formfld unknown" id="radiusserveracctport" size="4" value="<?=htmlspecialchars($pconfig['radiusserveracctport']);?>"/>
                      <br /><?=gettext("Enter the IP address, authentication port and accounting port (optional) of the RADIUS server."); ?><br />
			 <br /> <?=gettext("standard port 1812 and 1813 accounting"); ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS primary shared secret"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiussecret" type="password" class="formfld pwd" id="radiussecret" size="20" value="<?=htmlspecialchars($pconfig['radiussecret']);?>"/>
                      <br /><?=gettext("Enter the shared secret that will be used to authenticate " .
                      "to the RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS server Secondary"); ?></td>
                  <td width="78%" class="vtable">
                      <input name="radiusserver2" type="text" class="formfld unknown" id="radiusserver2" size="20" value="<?=htmlspecialchars($pconfig['radiusserver2']);?>"/>
			 <input name="radiusserver2port" type="text" class="formfld unknown" id="radiusserver2port" size="4" value="<?=htmlspecialchars($pconfig['radiusserver2port']);?>"/>
			 <input name="radiusserver2acctport" type="text" class="formfld unknown" id="radiusserver2acctport" size="4" value="<?=htmlspecialchars($pconfig['radiusserver2acctport']);?>"/>
                      <br /><?=gettext("Enter the IP address, authentication port and accounting port (optional) of the backup RADIUS server."); ?><br />
			 <br /> <?=gettext("standard port 1812 and 1813 accounting"); ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS secondary shared secret"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiussecret2" type="password" class="formfld pwd" id="radiussecret2" size="20" value="<?=htmlspecialchars($pconfig['radiussecret2']);?>"/>
                      <br />
                      <?=gettext("Enter the shared secret that will be used to authenticate " .
                      "to the RADIUS server"); ?>.</td>
                </tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><div id="addressnetworkport"><?=gettext("User (s)");?></div></td>
			<td width="78%" class="vtable">
			<table id="usertable">
			<tbody>
			<tr>
				<td><div id="onecolumn"><?=gettext("Username");?></div></td>
				<td><div id="twocolumn"><?=gettext("Password");?></div></td>
				<td><div id="thirdcolumn"><?=gettext("IP");?></div></td>
			</tr>
		<?php	$counter = 0;
			$usernames = $pconfig['username'];
			if ($usernames <> "") {
				$item = explode(" ", $usernames);
				foreach($item as $ww) {
					$wws = explode(":", $ww);
					$user = $wws[0];
					$passwd = base64_decode($wws[1]);
					$ip = $wws[2];
                        		$tracker = $counter;
		?>
		<tr>
			<td>
				<input name="username<?php echo $tracker; ?>" type="text" class="formfld unknown" id="username<?php echo $tracker; ?>" size="20" value="<?=htmlspecialchars($user);?>" />
			</td>
			<td>
				<input name="password<?php echo $tracker; ?>" type="password" class="formfld pwd" id="password<?php echo $tracker; ?>" size="20" value="<?=htmlspecialchars($passwd);?>" />
			</td>
			<td>
				<input name="ip<?php echo $tracker; ?>" type="text" class="formfld unknown" id="ip<?php echo $tracker; ?>" size="10" value="<?=htmlspecialchars($ip);?>" />
			</td>
			<td>
				<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/<?echo $g['theme'];?>/images/icons/icon_x.gif" /></a>
			</td>
		</tr>
		<?php
					$counter++;
				} // end foreach
			} // end if
		?>
			</tbody>
			</table>
			<a onclick="javascript:addRowTo('usertable'); return false;" href="#">
        			<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="<?=gettext("add another entry");?>" />
      			</a>
                	</td>
		</tr>
                <tr> 
                  <td height="16" colspan="2" valign="top"></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
		<?php if (isset($id))
			echo "<input type='hidden' name='id' id='id' value='" . htmlspecialchars($id, ENT_QUOTES | ENT_HTML401) . "' />";
		?>
		<?php if (isset($pconfig['pppoeid']))
			echo "<input type='hidden' name='pppoeid' id='pppoeid' value='{$pppoeid}' />";
		?>
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>"  onclick="enable_change(true)"/> 
                    <a href="vpn_pppoe.php"><input name="Cancel" type="button" class="formbtn" value="<?=gettext("Cancel"); ?>"/></a> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong><?=gettext("Note"); ?>:<br />
                    </strong></span><?=gettext("don't forget to add a firewall rule to permit " .
                    "traffic from PPPoE clients"); ?>!</span></td>
                </tr>
              </table>
	   </div>
	 </td>
	</tr>
</table>
</form>
<script type="text/javascript">
enable_change(false);

field_counter_js = 3;
rows = 1;
totalrows = <?php echo $counter; ?>;
loaded = <?php echo $counter; ?>;

</script>
<?php include("fend.inc"); ?>
</body>
</html>
