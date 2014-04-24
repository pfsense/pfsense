<?php
/* $Id$ */
/*
	Copyright (C) 2013	Dagorlad
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
	pfSense_MODULE:	ntpd_pps
*/

##|+PRIV
##|*IDENT=page-services-ntpd-pps
##|*NAME=Services: NTP PPS page
##|*DESCR=Allow access to the 'Services: NTP PPS' page..
##|*MATCH=services_ntpd_pps.php*
##|-PRIV

require_once("guiconfig.inc");

if (!is_array($config['ntpd']))
	$config['ntpd'] = array();
if (!is_array($config['ntpd']['pps']))
	$config['ntpd']['pps'] = array();

if ($_POST) {

	unset($input_errors);

	if (!$input_errors) {
		if (!empty($_POST['ppsport']) && file_exists('/dev/'.$_POST['ppsport']))
			$config['ntpd']['pps']['port'] = $_POST['ppsport'];
		/* if port is not set, remove all the pps config */
		else unset($config['ntpd']['pps']);

		if (!empty($_POST['ppsfudge1']))
			$config['ntpd']['pps']['fudge1'] = $_POST['ppsfudge1'];
		elseif (isset($config['ntpd']['pps']['fudge1']))
			unset($config['ntpd']['pps']['fudge1']);

		if (!empty($_POST['ppsstratum']) && ($_POST['ppsstratum']) < 17 )
			$config['ntpd']['pps']['stratum'] = $_POST['ppsstratum'];
		elseif (isset($config['ntpd']['pps']['stratum']))
			unset($config['ntpd']['pps']['stratum']);

		if (!empty($_POST['ppsselect']))
			$config['ntpd']['pps']['noselect'] = $_POST['ppsselect'];
		elseif (isset($config['ntpd']['pps']['noselect']))
			unset($config['ntpd']['pps']['noselect']);

		if (!empty($_POST['ppsflag2']))
			$config['ntpd']['pps']['flag2'] = $_POST['ppsflag2'];
		elseif (isset($config['ntpd']['pps']['flag2']))
			unset($config['ntpd']['pps']['flag2']);

		if (!empty($_POST['ppsflag3']))
			$config['ntpd']['pps']['flag3'] = $_POST['ppsflag3'];
		elseif (isset($config['ntpd']['pps']['flag3']))
			unset($config['ntpd']['pps']['flag3']);

		if (!empty($_POST['ppsflag4']))
			$config['ntpd']['pps']['flag4'] = $_POST['ppsflag4'];
		elseif (isset($config['ntpd']['pps']['flag4']))
			unset($config['ntpd']['pps']['flag4']);

		if (!empty($_POST['ppsrefid']))
			$config['ntpd']['pps']['refid'] = $_POST['ppsrefid'];
		elseif (isset($config['ntpd']['pps']['refid']))
			unset($config['ntpd']['pps']['refid']);
			
		write_config("Updated NTP PPS Settings");

		$retval = 0;
		$retval = system_ntp_configure();
		$savemsg = get_std_save_message($retval);
	}
}
$pconfig = &$config['ntpd']['pps'];

$pgtitle = array(gettext("Services"),gettext("NTP PPS"));
$shortcut_section = "ntp";
include("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_ntpd_pps.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="ntpd pps">
  <tr>
	<td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("NTP"), false, "services_ntpd.php");
	$tab_array[] = array(gettext("Serial GPS"), false, "services_ntpd_gps.php");
	$tab_array[] = array(gettext("PPS"), true, "services_ntpd_pps.php");
	display_top_tabs($tab_array);
?>
	</td>
  </tr>
  <tr>
	<td>
	<div id="mainarea">
	<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("NTP PPS Configuration"); ?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">
			</td>
			<td width="78%" class="vtable"><?php echo gettext("Devices with a Pulse Per Second output such as radios that receive a time signal from DCF77 (DE), JJY (JP), MSF (GB) or WWVB (US) may be used as a PPS reference for NTP.");?> 
			<?php echo gettext("A serial GPS may also be used, but the serial GPS driver would usually be the better option.");?> 
			<?php echo gettext("A PPS signal only provides a reference to the change of a second, so at least one other source to number the seconds is required.");?>
			<br />
			<br /><strong><?php echo gettext("Note");?>:</strong> <?php echo gettext("At least 3 additional time sources should be configured under"); ?> <a href="services_ntpd.php"><?php echo gettext("Services > NTP"); ?></a> <?php echo gettext("to reliably supply the time of each PPS pulse."); ?>
			</td>
		</tr>
<?php $serialports = glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE); ?>
<?php if (!empty($serialports)): ?>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Serial port</td>
			<td width="78%" class="vtable">
				<select name="ppsport" class="formselect">
					<option value="">none</option>
					<?php foreach ($serialports as $port):
						$shortport = substr($port,5);
						$selected = ($shortport == $pconfig['port']) ? " selected=\"selected\"" : "";?>
						<option value="<?php echo $shortport;?>"<?php echo $selected;?>><?php echo $shortport;?></option>
					<?php endforeach; ?>
				</select>&nbsp;
				<?php echo gettext("All serial ports are listed, be sure to pick the port with the PPS source attached."); ?>
			</td>
		</tr>
<?php endif; ?>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Fudge time</td>
			<td width="78%" class="vtable">
				<input name="ppsfudge1" type="text" class="formfld unknown" id="ppsfudge1" min="-1" max="1" size="20" value="<?=htmlspecialchars($pconfig['fudge1']);?>" />(<?php echo gettext("seconds");?>)<br />
				<?php echo gettext("Fudge time is used to specify the PPS signal offset from the actual second such as the transmission delay between the transmitter and the receiver.");?> (<?php echo gettext("default");?>: 0.0).</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Stratum</td>
			<td width="78%" class="vtable">
				<input name="ppsstratum" type="text" class="formfld unknown" id="ppsstratum" max="16" size="20" value="<?=htmlspecialchars($pconfig['stratum']);?>" /><?php echo gettext("(0-16)");?><br />
				<?php echo gettext("This may be used to change the PPS Clock stratum");?> (<?php echo gettext("default");?>: 0). <?php echo gettext("This may be useful if, for some reason, you want ntpd to prefer a different clock and just monitor this source."); ?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Flags</td>
			<td width="78%" class="vtable">
				<table summary="flags">
					<tr>
						<td>
				<?php echo gettext("Normally there should be no need to change these options from the defaults."); ?><br />
						</td>
					</tr>
				</table>
				<table>
					<tr>
						<td>
							<input name="ppsflag2" type="checkbox" class="formcheckbox" id="ppsflag2"<?php if($pconfig['flag2']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("Enable falling edge PPS signal processing (default: rising edge)."); ?></span>
						</td>
					</tr>
					<tr>
						<td>
							<input name="ppsflag3" type="checkbox" class="formcheckbox" id="ppsflag3"<?php if($pconfig['flag3']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("Enable kernel PPS clock discipline (default: disabled)."); ?></span>
						</td>
					</tr>
					<tr>
						<td>
							<input name="ppsflag4" type="checkbox" class="formcheckbox" id="ppsflag4"<?php if($pconfig['flag4']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("Record a timestamp once for each second, useful for constructing Allan deviation plots (default: disabled)."); ?></span>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Clock ID</td>
			<td width="78%" class="vtable">
				<input name="ppsrefid" type="text" class="formfld unknown" id="ppsrefid" maxlength= "4" size="20" value="<?php htmlspecialchars($pconfig['refid']);?>" /><?php echo gettext("(1 to 4 charactors)");?><br />
				<?php echo gettext("This may be used to change the PPS Clock ID");?> (<?php echo gettext("default");?>: PPS).</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
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
