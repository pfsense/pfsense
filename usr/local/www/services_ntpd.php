<?php
/*
	services_ntpd.php

	Copyright (C) 2013	Dagorlad
	Copyright (C) 2012	Jim Pingle
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
	pfSense_MODULE:	ntpd
*/

##|+PRIV
##|*IDENT=page-services-ntpd
##|*NAME=Services: NTP
##|*DESCR=Allow access to the 'Services: NTP' page.
##|*MATCH=services_ntpd.php*
##|-PRIV

require("guiconfig.inc");
require_once('rrd.inc');
require_once("shaper.inc");

if (!is_array($config['ntpd']))
	$config['ntpd'] = array();

if (empty($config['ntpd']['interface'])) {
	if (is_array($config['installedpackages']['openntpd']) && is_array($config['installedpackages']['openntpd']['config']) &&
	    is_array($config['installedpackages']['openntpd']['config'][0]) && !empty($config['installedpackages']['openntpd']['config'][0]['interface'])) {
		$pconfig['interface'] = explode(",", $config['installedpackages']['openntpd']['config'][0]['interface']);
		unset($config['installedpackages']['openntpd']);
		write_config("Upgraded settings from openttpd");
	} else
		$pconfig['interface'] = array();
} else
	$pconfig['interface'] = explode(",", $config['ntpd']['interface']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (!$input_errors) {
		if (is_array($_POST['interface']))
			$config['ntpd']['interface'] = implode(",", $_POST['interface']);
		elseif (isset($config['ntpd']['interface']))
			unset($config['ntpd']['interface']);

		if (!empty($_POST['gpsport']) && file_exists('/dev/'.$_POST['gpsport']))
			$config['ntpd']['gpsport'] = $_POST['gpsport'];
		elseif (isset($config['ntpd']['gpsport']))
			unset($config['ntpd']['gpsport']);

		unset($config['ntpd']['prefer']);
		unset($config['ntpd']['noselect']);
		$timeservers = '';
		for ($i = 0; $i < 10; $i++) {
			$tserver = trim($_POST["server{$i}"]);
			if (!empty($tserver)) {
				$timeservers .= "{$tserver} ";
				if (!empty($_POST["servprefer{$i}"])) $config['ntpd']['prefer'] .= "{$tserver} ";
				if (!empty($_POST["servselect{$i}"])) $config['ntpd']['noselect'].= "{$tserver} ";
			}
		}
		if (trim($timeservers) == "")
			$timeservers = "pool.ntp.org";
		$config['system']['timeservers'] = trim($timeservers);

		if (!empty($_POST['ntporphan']) && ($_POST['ntporphan'] < 17) && ($_POST['ntporphan'] != '12'))
			$config['ntpd']['orphan'] = $_POST['ntporphan'];
		elseif (isset($config['ntpd']['orphan']))
			unset($config['ntpd']['orphan']);

		if (!empty($_POST['logpeer']))
			$config['ntpd']['logpeer'] = $_POST['logpeer'];
		elseif (isset($config['ntpd']['logpeer']))
			unset($config['ntpd']['logpeer']);

		if (!empty($_POST['logsys']))
			$config['ntpd']['logsys'] = $_POST['logsys'];
		elseif (isset($config['ntpd']['logsys']))
			unset($config['ntpd']['logsys']);

		if (!empty($_POST['clockstats']))
			$config['ntpd']['clockstats'] = $_POST['clockstats'];
		elseif (isset($config['ntpd']['clockstats']))
			unset($config['ntpd']['clockstats']);

		if (!empty($_POST['loopstats']))
			$config['ntpd']['loopstats'] = $_POST['loopstats'];
		elseif (isset($config['ntpd']['loopstats']))
			unset($config['ntpd']['loopstats']);

		if (!empty($_POST['peerstats']))
			$config['ntpd']['peerstats'] = $_POST['peerstats'];
		elseif (isset($config['ntpd']['peerstats']))
			unset($config['ntpd']['peerstats']);

		if (empty($_POST['kod']))
			$config['ntpd']['kod'] = 'on';
		elseif (isset($config['ntpd']['kod']))
			unset($config['ntpd']['kod']);

		if (empty($_POST['nomodify']))
			$config['ntpd']['nomodify'] = 'on';
		elseif (isset($config['ntpd']['nomodify']))
			unset($config['ntpd']['nomodify']);

		if (!empty($_POST['noquery']))
			$config['ntpd']['noquery'] = $_POST['noquery'];
		elseif (isset($config['ntpd']['noquery']))
			unset($config['ntpd']['noquery']);

		if (!empty($_POST['noserve']))
			$config['ntpd']['noserve'] = $_POST['noserve'];
		elseif (isset($config['ntpd']['noserve']))
			unset($config['ntpd']['noserve']);

		if (empty($_POST['nopeer']))
			$config['ntpd']['nopeer'] = 'on';
		elseif (isset($config['ntpd']['nopeer']))
			unset($config['ntpd']['nopeer']);

		if (empty($_POST['notrap']))
			$config['ntpd']['notrap'] = 'on';
		elseif (isset($config['ntpd']['notrap']))
			unset($config['ntpd']['notrap']);

		if ((empty($_POST['statsgraph'])) != (isset($config['ntpd']['statsgraph'])));
			enable_rrd_graphing();
		if (!empty($_POST['statsgraph']))
			$config['ntpd']['statsgraph'] = $_POST['statsgraph'];
		elseif (isset($config['ntpd']['statsgraph']))
			unset($config['ntpd']['statsgraph']);

		if (!empty($_POST['leaptxt']))
			$config['ntpd']['leapsec'] = base64_encode($_POST['leaptxt']);
		elseif (isset($config['ntpd']['leapsec']))
			unset($config['ntpd']['leapsec']);

		if (is_uploaded_file($_FILES['leapfile']['tmp_name']))
			$config['ntpd']['leapsec'] = base64_encode(file_get_contents($_FILES['leapfile']['tmp_name']));

		write_config("Updated NTP Server Settings");

		$retval = 0;
		$retval = system_ntp_configure();
		$savemsg = get_std_save_message($retval);

	}
}
$closehead = false;
$pconfig = &$config['ntpd'];
if (empty($pconfig['interface']))
	$pconfig['interface'] = array();
else
	$pconfig['interface'] = explode(",", $pconfig['interface']);
$pgtitle = array(gettext("Services"),gettext("NTP"));
$shortcut_section = "ntp";
include("head.inc");

?>

<script type="text/javascript">
//<![CDATA[
	//Generic show an advanced option function
	function show_advanced(showboxID, configvalueID) {
		document.getElementById(showboxID).innerHTML='';
		aodiv = document.getElementById(configvalueID);
		aodiv.style.display = "block";
	}

	//Insure only one of two mutually exclusive options are checked
	function CheckOffOther(clicked, checkOff) {
		if (document.getElementById(clicked).checked) {
			document.getElementById(checkOff).checked=false;
		}
	}

	//Show another time server line, limited to 10 servers
	function NewTimeServer(add) {
		//If the last line has a value
		var CheckServer = 'server' + (add - 1);
		var LastId = document.getElementById(CheckServer);
		if (document.getElementById(CheckServer).value != '') {
			if (add < 10) {
				var TimeServerID = 'timeserver' + add;
				document.getElementById(TimeServerID).style.display = 'block';
				//then revise the add another server line
				if (add < 9) {
					var next = add + 1;
					var newdiv = '<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?php echo gettext("Add another Time server");?>" onclick="NewTimeServer(' + next + ')" alt="add" />\n';
					document.getElementById('addserver').innerHTML=newdiv;
				}else{
					document.getElementById('addserver').style.display = 'none';
				}
			}
		}
	}
//]]>
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_ntpd.php" method="post" name="iform" id="iform" enctype="multipart/form-data" accept-charset="utf-8">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="ntpd">
  <tr>
	<td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("NTP"), true, "services_ntpd.php");
	$tab_array[] = array(gettext("Serial GPS"), false, "services_ntpd_gps.php");
	$tab_array[] = array(gettext("PPS"), false, "services_ntpd_pps.php");
	display_top_tabs($tab_array);
?>
	</td>
  </tr>
  <tr>
	<td>
		<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?=gettext("NTP Server Configuration"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq">Interface(s)</td>
				<td width="78%" class="vtable">
<?php
	$interfaces = get_configured_interface_with_descr();
	$carplist = get_configured_carp_interface_list();
	foreach ($carplist as $cif => $carpip)
		$interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
	$aliaslist = get_configured_ip_aliases_list();
	foreach ($aliaslist as $aliasip => $aliasif)
		$interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
	$size = (count($interfaces) < 10) ? count($interfaces) : 10;
?>
			<select id="interface" name="interface[]" multiple="multiple" class="formselect" size="<?php echo $size; ?>">
<?php	
	foreach ($interfaces as $iface => $ifacename) {
		if (!is_ipaddr(get_interface_ip($iface)) && !is_ipaddr($iface))
			continue;
		echo "<option value='{$iface}'";
		if (is_array($pconfig['interface']))
			if (in_array($iface, $pconfig['interface'])) echo " selected=\"selected\"";
		echo ">" . htmlspecialchars($ifacename) . "</option>\n";
	} ?>
					</select>
					<br />
					<br /><?php echo gettext("Interfaces without an IP address will not be shown."); ?>
					<br />
					<br /><?php echo gettext("Selecting no interfaces will listen on all interfaces with a wildcard."); ?>
					<br /><?php echo gettext("Selecting all interfaces will explicitly listen on only the interfaces/IPs specified."); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq">Time servers</td>
				<td width="78%" class="vtable">
					<?php
					$timeservers = explode( ' ', $config['system']['timeservers']);
					for ($i = $j = 0; $i < 10; $i++){
						echo "<div id=\"timeserver{$i}\"";
						if ((isset($timeservers[$i])) || ($i < 3)) {
							$j++;
						}else{
							echo " style=\"display:none\"";
						}
						echo ">\n";
						
						echo "<input name=\"server{$i}\" class=\"formfld unknown\" id=\"server{$i}\" size=\"30\" value=\"{$timeservers[$i]}\" type=\"text\" />&emsp;";
						echo "\n<input name=\"servprefer{$i}\" class=\"formcheckbox\" id=\"servprefer{$i}\" onclick=\"CheckOffOther('servprefer{$i}', 'servselect{$i}')\" type=\"checkbox\"";
						if (substr_count($config['ntpd']['prefer'], $timeservers[$i])) echo " checked=\"checked\"";
						echo " />&nbsp;prefer&emsp;";
						echo "\n<input name=\"servselect{$i}\" class=\"formcheckbox\" id=\"servselect{$i}\" onclick=\"CheckOffOther('servselect{$i}', 'servprefer{$i}')\" type=\"checkbox\"";
						if (substr_count($config['ntpd']['noselect'], $timeservers[$i])) echo " checked=\"checked\"";
						echo " />&nbsp;noselect\n<br />\n</div>\n";
					}
					?>
					<div id="addserver">
					<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?php echo gettext("Add another Time server");?>" onclick="NewTimeServer(<?php echo $j;?>)" alt="add" />
					</div>
					<br />
					<?php echo gettext('For best results three to five servers should be configured here.'); ?>
					<br />
					<?php echo gettext('The <i>prefer</i> option indicates that NTP should favor the use of this server more than all others.'); ?>
					<br />
					<?php echo gettext('The <i>noselect</i> option indicates that NTP should not use this server for time, but stats for this server will be collected and displayed.'); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq">Orphan mode</td>
				<td width="78%" class="vtable">
					<input name="ntporphan" type="text" class="formfld unknown" id="ntporphan" min="1" max="16" size="20" value="<?=htmlspecialchars($pconfig['orphan']);?>" /><?php echo gettext("(0-15)");?><br />
					<?php echo gettext("Orphan mode allows the system clock to be used when no other clocks are available. The number here specifies the stratum reported during orphan mode and should normally be set to a number high enough to insure that any other servers available to clients are preferred over this server. (default: 12)."); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq">NTP graphs</td>
				<td width="78%" class="vtable">
					<input name="statsgraph" type="checkbox" class="formcheckbox" id="statsgraph" <?php if($pconfig['statsgraph']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Enable rrd graphs of NTP statistics (default: disabled)."); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq">Syslog logging</td>
				<td width="78%" class="vtable">
					<?php echo gettext("These options enable additional messages from NTP to be written to the System Log");?> (<a href="diag_logs_ntpd.php"><?php echo gettext("Status > System Logs > NTP"); ?></a>).
					<br /><br />
					<input name="logpeer" type="checkbox" class="formcheckbox" id="logpeer"<?php if($pconfig['logpeer']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Enable logging of peer messages (default: disabled)."); ?>
					<br />
					<input name="logsys" type="checkbox" class="formcheckbox" id="logsys"<?php if($pconfig['logsys']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Enable logging of system messages (default: disabled)."); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq">Statistics logging</td>
				<td width="78%" class="vtable">
					<div id="showstatisticsbox">
					<input type="button" onclick="show_advanced('showstatisticsbox', 'showstatistics')" value="<?=gettext("Advanced");?>" /> - <?=gettext("Show statistics logging options");?>
					</div>
					<div id="showstatistics" style="display:none">
					<strong><?php echo gettext("Warning: ")?></strong><?php echo gettext("these options will create persistant daily log files in /var/log/ntp."); ?>
					<br /><br />
					<input name="clockstats" type="checkbox" class="formcheckbox" id="clockstats"<?php if($pconfig['clockstats']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Enable logging of reference clock statistics (default: disabled)."); ?>
					<br />
					<input name="loopstats" type="checkbox" class="formcheckbox" id="loopstats"<?php if($pconfig['loopstats']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Enable logging of clock discipline statistics (default: disabled)."); ?>
					<br />
					<input name="peerstats" type="checkbox" class="formcheckbox" id="peerstats"<?php if($pconfig['peerstats']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Enable logging of NTP peer statistics (default: disabled)."); ?>
					</div>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq">Access restrictions</td>
				<td width="78%" class="vtable">
					<div id="showrestrictbox">
					<input type="button" onclick="show_advanced('showrestrictbox', 'showrestrict')" value="<?=gettext("Advanced");?>" /> - <?=gettext("Show access restriction options");?>
					</div>
					<div id="showrestrict" style="display:none">
					<?php echo gettext("these options control access to NTP from the WAN."); ?>
					<br /><br />
					<input name="kod" type="checkbox" class="formcheckbox" id="kod"<?php if(!$pconfig['kod']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Enable Kiss-o'-death packets (default: enabled)."); ?>
					<br />
					<input name="nomodify" type="checkbox" class="formcheckbox" id="nomodify"<?php if(!$pconfig['nomodify']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Deny state modifications (i.e. run time configuration) by ntpq and ntpdc (default: enabled)."); ?>
					<br />
					<input name="noquery" type="checkbox" class="formcheckbox" id="noquery"<?php if($pconfig['noquery']) echo " checked=\"checked\""; ?> />>
					<?php echo gettext("Disable ntpq and ntpdc queries (default: disabled)."); ?>
					<br />
					<input name="noserve" type="checkbox" class="formcheckbox" id="noserve"<?php if($pconfig['noserve']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Disable all except ntpq and ntpdc queries (default: disabled)."); ?>
					<br />
					<input name="nopeer" type="checkbox" class="formcheckbox" id="nopeer"<?php if(!$pconfig['nopeer']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Deny packets that attempt a peer association (default: enabled)."); ?>
					<br />
					<input name="notrap" type="checkbox" class="formcheckbox" id="notrap"<?php if(!$pconfig['notrap']) echo " checked=\"checked\""; ?> />
					<?php echo gettext("Deny mode 6 control message trap service (default: enabled)."); ?>
					</div>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq">Leap seconds</td>
				<td width="78%" class="vtable">
					<div id="showleapsecbox">
					<input type="button" onclick="show_advanced('showleapsecbox', 'showleapsec')" value="<?=gettext("Advanced");?>" /> - <?=gettext("Show Leap second configuration");?>
					</div>
					<div id="showleapsec" style="display:none">
					<?php echo gettext("A leap second file allows NTP to advertize an upcoming leap second addition or subtraction.");?>
					<?php echo gettext("Normally this is only useful if this server is a stratum 1 time server.");?>
					<br /><br />
					<?php echo gettext("Enter Leap second configuration as text:");?><br />
					<textarea name="leaptxt" class="formpre" id="leaptxt" cols="65" rows="7"><?php $text = base64_decode(chunk_split($pconfig['leapsec'])); echo $text;?></textarea><br />
					<strong><?php echo gettext("Or");?></strong>, <?php echo gettext("select a file to upload:");?>
					<input type="file" name="leapfile" class="formfld file" id="leapfile" />
					</div>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
				</td>
			</tr>
		</table>
</div></td></tr></table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
