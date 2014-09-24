<?php
/* $Id$ */
/*
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
	pfSense_MODULE:	ntpd_gps
*/

##|+PRIV
##|*IDENT=page-services-ntpd-gps
##|*NAME=Services: NTP Serial GPS page
##|*DESCR=Allow access to the 'Services: NTP Serial GPS' page..
##|*MATCH=services_ntpd_gps.php*
##|-PRIV

require_once("guiconfig.inc");

function set_default_gps() {
	global $config;

	if (!is_array($config['ntpd']))
		$config['ntpd'] = array();
	if (is_array($config['ntpd']['gps']))
		unset($config['ntpd']['gps']);

	$config['ntpd']['gps'] = array();
	$config['ntpd']['gps']['type'] = 'Default';
	/* copy an existing configured GPS port if it exists, the unset may be uncommented post production */
	if (!empty($config['ntpd']['gpsport']) && empty($config['ntpd']['gps']['port'])) {
		$config['ntpd']['gps']['port'] = $config['ntpd']['gpsport'];
		unset($config['ntpd']['gpsport']); /* this removes the original port config from config.xml */
		$config['ntpd']['gps']['speed'] = 0;
		$config['ntpd']['gps']['nmea'] = 0;
	}

	write_config("Setting default NTPd settings");
}

if ($_POST) {

	unset($input_errors);

	if (!empty($_POST['gpsport']) && file_exists('/dev/'.$_POST['gpsport']))
		$config['ntpd']['gps']['port'] = $_POST['gpsport'];
	/* if port is not set, remove all the gps config */
	else unset($config['ntpd']['gps']);

	if (!empty($_POST['gpstype']))
		$config['ntpd']['gps']['type'] = $_POST['gpstype'];
	elseif (isset($config['ntpd']['gps']['type']))
		unset($config['ntpd']['gps']['type']);

	if (!empty($_POST['gpsspeed']))
		$config['ntpd']['gps']['speed'] = $_POST['gpsspeed'];
	elseif (isset($config['ntpd']['gps']['speed']))
		unset($config['ntpd']['gps']['speed']);

	if (!empty($_POST['gpsnmea']) && ($_POST['gpsnmea'][0] === "0"))
		$config['ntpd']['gps']['nmea'] = "0";
	else
		$config['ntpd']['gps']['nmea'] = strval(array_sum($_POST['gpsnmea']));

	if (!empty($_POST['gpsfudge1']))
		$config['ntpd']['gps']['fudge1'] = $_POST['gpsfudge1'];
	elseif (isset($config['ntpd']['gps']['fudge1']))
		unset($config['ntpd']['gps']['fudge1']);

	if (!empty($_POST['gpsfudge2']))
		$config['ntpd']['gps']['fudge2'] = $_POST['gpsfudge2'];
	elseif (isset($config['ntpd']['gps']['fudge2']))
		unset($config['ntpd']['gps']['fudge2']);

	if (!empty($_POST['gpsstratum']) && ($_POST['gpsstratum']) < 17 )
		$config['ntpd']['gps']['stratum'] = $_POST['gpsstratum'];
	elseif (isset($config['ntpd']['gps']['stratum']))
		unset($config['ntpd']['gps']['stratum']);

	if (empty($_POST['gpsprefer']))
		$config['ntpd']['gps']['prefer'] = 'on';
	elseif (isset($config['ntpd']['gps']['prefer']))
		unset($config['ntpd']['gps']['prefer']);

	if (!empty($_POST['gpsselect']))
		$config['ntpd']['gps']['noselect'] = $_POST['gpsselect'];
	elseif (isset($config['ntpd']['gps']['noselect']))
		unset($config['ntpd']['gps']['noselect']);

	if (!empty($_POST['gpsflag1']))
		$config['ntpd']['gps']['flag1'] = $_POST['gpsflag1'];
	elseif (isset($config['ntpd']['gps']['flag1']))
		unset($config['ntpd']['gps']['flag1']);

	if (!empty($_POST['gpsflag2']))
		$config['ntpd']['gps']['flag2'] = $_POST['gpsflag2'];
	elseif (isset($config['ntpd']['gps']['flag2']))
		unset($config['ntpd']['gps']['flag2']);

	if (!empty($_POST['gpsflag3']))
		$config['ntpd']['gps']['flag3'] = $_POST['gpsflag3'];
	elseif (isset($config['ntpd']['gps']['flag3']))
		unset($config['ntpd']['gps']['flag3']);

	if (!empty($_POST['gpsflag4']))
		$config['ntpd']['gps']['flag4'] = $_POST['gpsflag4'];
	elseif (isset($config['ntpd']['gps']['flag4']))
		unset($config['ntpd']['gps']['flag4']);

	if (!empty($_POST['gpssubsec']))
		$config['ntpd']['gps']['subsec'] = $_POST['gpssubsec'];
	elseif (isset($config['ntpd']['gps']['subsec']))
		unset($config['ntpd']['gps']['subsec']);

	if (!empty($_POST['gpsrefid']))
		$config['ntpd']['gps']['refid'] = $_POST['gpsrefid'];
	elseif (isset($config['ntpd']['gps']['refid']))
		unset($config['ntpd']['gps']['refid']);

	if (!empty($_POST['gpsinitcmd']))
		$config['ntpd']['gps']['initcmd'] = base64_encode($_POST['gpsinitcmd']);
	elseif (isset($config['ntpd']['gps']['initcmd']))
		unset($config['ntpd']['gps']['initcmd']);

	write_config("Updated NTP GPS Settings");

	$retval = system_ntp_configure();
	$savemsg = get_std_save_message($retval);
} else {
	/* set defaults if they do not already exist */
	if (!is_array($config['ntpd']) || !is_array($config['ntpd']['gps']) || empty($config['ntpd']['gps']['type'])) {
		set_default_gps();
	}
}
$closehead = false;
$pconfig = &$config['ntpd']['gps'];
$pgtitle = array(gettext("Services"),gettext("NTP GPS"));
$shortcut_section = "ntp";
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
	function show_advanced(showboxID, configvalueID) {
		document.getElementById(showboxID).innerHTML='';
		aodiv = document.getElementById(configvalueID);
		aodiv.style.display = "block";
	}

	function ToggleOther(clicked, checkOff) {
		if (document.getElementById(clicked).checked) {
			document.getElementById(checkOff).checked=false;
		}
	}

/*
init commands are Base64 encoded
Default =	#Sponsored, probably a Ublox
		$PUBX,40,GSV,0,0,0,0*59
		$PUBX,40,GLL,0,0,0,0*5C
		$PUBX,40,ZDA,0,0,0,0*44
		$PUBX,40,VTG,0,0,0,0*5E
		$PUBX,40,GSV,0,0,0,0*59
		$PUBX,40,GSA,0,0,0,0*4E
		$PUBX,40,GGA,0,0,0,0
		$PUBX,40,TXT,0,0,0,0
		$PUBX,40,RMC,0,0,0,0*46
		$PUBX,41,1,0007,0003,4800,0
		$PUBX,40,ZDA,1,1,1,1

Generic =					#do nothing

Garmin =	#most Garmin
		$PGRMC,,,,,,,,,,3,,2,4*52	#enable PPS @ 100ms
		$PGRMC1,,1,,,,,,W,,,,,,,*30	#enable WAAS
		$PGRMO,,3*74			#turn off all sentences
		$PGRMO,GPRMC,1*3D		#enable RMC
		$PGRMO,GPGGA,1*20		#enable GGA
		$PGRMO,GPGLL,1*26		#enable GLL

MediaTek =	#Adafruit, Fastrax, some Garmin and others
		$PMTK225,0*2B			#normal power mode
		$PMTK314,1,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0*28	#enable GLL, RMC, GGA and ZDA
		$PMTK301,2*2E			#enable WAAS
		$PMTK320,0*2F			#power save off
		$PMTK330,0*2E			#set WGS84 datum
		$PMTK386,0*23			#disable static navigation (MT333X)
		$PMTK397,0*23			#disable static navigation (MT332X)
		$PMTK251,4800*14		#4800 baud rate

SiRF =		#used by many devices
		$PSRF103,00,00,01,01*25		#turn on GGA
		$PSRF103,01,00,01,01*24		#turn on GLL
		$PSRF103,02,00,00,01*24		#turn off GSA
		$PSRF103,03,00,00,01*24		#turn off GSV
		$PSRF103,04,00,01,01*24		#turn on RMC
		$PSRF103,05,00,00,01*24		#turn off VTG
		$PSRF100,1,4800,8,1,0*0E	#set port to 4800,N,8,1

U-Blox =	#U-Blox 5, 6 and probably 7
		$PUBX,40,GGA,1,1,1,1,0,0*5A	#turn on GGA all ports
		$PUBX,40,GLL,1,1,1,1,0,0*5C	#turn on GLL all ports
		$PUBX,40,GSA,0,0,0,0,0,0*4E	#turn off GSA all ports
		$PUBX,40,GSV,0,0,0,0,0,0*59	#turn off GSV all ports
		$PUBX,40,RMC,1,1,1,1,0,0*47	#turn on RMC all ports
		$PUBX,40,VTG,0,0,0,0,0,0*5E	#turn off VTG all ports
		$PUBX,40,GRS,0,0,0,0,0,0*5D	#turn off GRS all ports
		$PUBX,40,GST,0,0,0,0,0,0*5B	#turn off GST all ports
		$PUBX,40,ZDA,1,1,1,1,0,0*44	#turn on ZDA all ports
		$PUBX,40,GBS,0,0,0,0,0,0*4D	#turn off GBS all ports
		$PUBX,40,DTM,0,0,0,0,0,0*46	#turn off DTM all ports
		$PUBX,40,GPQ,0,0,0,0,0,0*5D	#turn off GPQ all ports
		$PUBX,40,TXT,0,0,0,0,0,0*43	#turn off TXT all ports
		$PUBX,40,THS,0,0,0,0,0,0*54	#turn off THS all ports (U-Blox 6)
		$PUBX,41,1,0007,0003,4800,0*13	# set port 1 to 4800 baud

SureGPS = 		#Sure Electronics SKG16B
		$PMTK225,0*2B
		$PMTK314,1,1,0,1,0,5,0,0,0,0,0,0,0,0,0,0,0,1,0*2D
		$PMTK301,2*2E
		$PMTK397,0*23
		$PMTK102*31
		$PMTK313,1*2E
		$PMTK513,1*28
		$PMTK319,0*25
		$PMTK527,0.00*00
		$PMTK251,9600*17	#really needs to work at 9600 baud

*/

	function set_gps_default(form) {
		//This handles a new config and also a reset to a defined default config
		var gpsdef = new Object();
		//get the text description of the selected type
		var e = document.getElementById("gpstype");
		var type = e.options[e.selectedIndex].text;

		//stuff the JS object as needed for each type
		switch(type) {
			case "Default":
				gpsdef['nmea'] = 0;
				gpsdef['speed'] = 0;
				gpsdef['fudge1'] = "0.155";
				gpsdef['fudge2'] = "";
				gpsdef['inittxt'] = "JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMCwwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRHLDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWCw0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQVUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEsMSwxLDEsMQ0K";
				break;

			case "Garmin":
				gpsdef['nmea'] = 0;
				gpsdef['speed'] = 0;
				gpsdef['fudge1'] = "";
				gpsdef['fudge2'] = "0.600";
				gpsdef['inittxt'] = "JFBHUk1DLCwsLCwsLCwsLDMsLDIsOCo1RQ0KJFBHUk1DMSwsMSwsLCwsLFcsLCwsLCwsKjMwDQokUEdSTU8sLDMqNzQNCiRQR1JNTyxHUFJNQywxKjNEDQokUEdSTU8sR1BHR0EsMSoyMA0KJFBHUk1PLEdQR0xMLDEqMjYNCg==";
				break;

			case "Generic":
				gpsdef['nmea'] = 0;
				gpsdef['speed'] = 0;
				gpsdef['fudge1'] = "";
				gpsdef['fudge2'] = "0.400";
				gpsdef['inittxt'] = "";
				break;

			case "MediaTek":
				gpsdef['nmea'] = 0;
				gpsdef['speed'] = 0;
				gpsdef['fudge1'] = "";
				gpsdef['fudge2'] = "0.400";
				gpsdef['inittxt'] = "JFBNVEsyMjUsMCoyQg0KJFBNVEszMTQsMSwxLDAsMSwwLDAsMCwwLDAsMCwwLDAsMCwwLDAsMCwwLDEsMCoyOA0KJFBNVEszMDEsMioyRQ0KJFBNVEszMjAsMCoyRg0KJFBNVEszMzAsMCoyRQ0KJFBNVEszODYsMCoyMw0KJFBNVEszOTcsMCoyMw0KJFBNVEsyNTEsNDgwMCoxNA0K";
				break;

			case "SiRF":
				gpsdef['nmea'] = 0;
				gpsdef['speed'] = 0;
				gpsdef['fudge1'] = "";
				gpsdef['fudge2'] = "0.704"; //valid for 4800, 0.688 @ 9600, 0.640 @ USB
				gpsdef['inittxt'] = "JFBTUkYxMDMsMDAsMDAsMDEsMDEqMjUNCiRQU1JGMTAzLDAxLDAwLDAxLDAxKjI0DQokUFNSRjEwMywwMiwwMCwwMCwwMSoyNA0KJFBTUkYxMDMsMDMsMDAsMDAsMDEqMjQNCiRQU1JGMTAzLDA0LDAwLDAxLDAxKjI0DQokUFNSRjEwMywwNSwwMCwwMCwwMSoyNA0KJFBTUkYxMDAsMSw0ODAwLDgsMSwwKjBFDQo=";
				break;

			case "U-Blox":
				gpsdef['nmea'] = 0;
				gpsdef['speed'] = 0;
				gpsdef['fudge1'] = "";
				gpsdef['fudge2'] = "0.400";
				gpsdef['inittxt'] = "JFBVQlgsNDAsR0dBLDEsMSwxLDEsMCwwKjVBDQokUFVCWCw0MCxHTEwsMSwxLDEsMSwwLDAqNUMNCiRQVUJYLDQwLEdTQSwwLDAsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR1NWLDAsMCwwLDAsMCwwKjU5DQokUFVCWCw0MCxSTUMsMSwxLDEsMSwwLDAqNDcNCiRQVUJYLDQwLFZURywwLDAsMCwwLDAsMCo1RQ0KJFBVQlgsNDAsR1JTLDAsMCwwLDAsMCwwKjVEDQokUFVCWCw0MCxHU1QsMCwwLDAsMCwwLDAqNUINCiRQVUJYLDQwLFpEQSwxLDEsMSwxLDAsMCo0NA0KJFBVQlgsNDAsR0JTLDAsMCwwLDAsMCwwKjREDQokUFVCWCw0MCxEVE0sMCwwLDAsMCwwLDAqNDYNCiRQVUJYLDQwLEdQUSwwLDAsMCwwLDAsMCo1RA0KJFBVQlgsNDAsVFhULDAsMCwwLDAsMCwwKjQzDQokUFVCWCw0MCxUSFMsMCwwLDAsMCwwLDAqNTQNCiRQVUJYLDQxLDEsMDAwNywwMDAzLDQ4MDAsMCoxMw0K";
				break;

			case "SureGPS":
				gpsdef['nmea'] = 1;
				gpsdef['speed'] = 16;
				gpsdef['fudge1'] = "";
				gpsdef['fudge2'] = "0.407";
				gpsdef['inittxt'] = "JFBNVEsyMjUsMCoyQg0KJFBNVEszMTQsMSwxLDAsMSwwLDUsMCwwLDAsMCwwLDAsMCwwLDAsMCwwLDEsMCoyRA0KJFBNVEszMDEsMioyRQ0KJFBNVEszOTcsMCoyMw0KJFBNVEsxMDIqMzENCiRQTVRLMzEzLDEqMkUNCiRQTVRLNTEzLDEqMjgNCiRQTVRLMzE5LDAqMjUNCiRQTVRLNTI3LDAuMDAqMDANCiRQTVRLMjUxLDk2MDAqMTcNCg==";
				break;
			default:
				return;
		}

		//then update the html and set the common stuff
		document.getElementById("gpsnmea").selectedIndex = gpsdef['nmea'];
		document.getElementById("gpsspeed").selectedIndex = gpsdef['speed'];
		form.gpsfudge1.value = gpsdef['fudge1'];
		form.gpsfudge2.value = gpsdef['fudge2'];
		form.gpsstratum.value = "";
		form.gpsrefid.value = "";
		form.gpsspeed.value = gpsdef['speed'];
		document.getElementById("gpsflag1").checked=true
		document.getElementById("gpsflag2").checked=false
		document.getElementById("gpsflag3").checked=true
		document.getElementById("gpsflag4").checked=false
		document.getElementById("gpssubsec").checked=false
		form.gpsinitcmd.value = atob(gpsdef['inittxt']);
	}

	//function to compute a NMEA checksum derived from the public domain function at http://www.hhhh.org/wiml/proj/nmeaxor.html
	function NMEAChecksum(cmd) {
		// Compute the checksum by XORing all the character values in the string.
		var checksum = 0;
		for(var i = 0; i < cmd.length; i++) {
			checksum = checksum ^ cmd.charCodeAt(i);
		}
		// Convert it to hexadecimal (base-16, upper case, most significant byte first).
		var hexsum = Number(checksum).toString(16).toUpperCase();
		if (hexsum.length < 2) {
			hexsum = ("00" + hexsum).slice(-2);
		}
		// Display the result
		document.getElementById("nmeachecksum").innerHTML = hexsum;
	}
//]]>
</script>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_ntpd_gps.php" method="post" name="iform" id="iform" accept-charset="utf-8">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="ntpd gps">
	<tr><td>
<?php
		$tab_array = array();
		$tab_array[] = array(gettext("NTP"), false, "services_ntpd.php");
		$tab_array[] = array(gettext("Serial GPS"), true, "services_ntpd_gps.php");
		$tab_array[] = array(gettext("PPS"), false, "services_ntpd_pps.php");
		display_top_tabs($tab_array);
?>
	</td></tr>
	<tr><td>
	<div id="mainarea">
	<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("NTP Serial GPS Configuration"); ?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">
			</td>
			<td width="78%" class="vtable">A GPS connected via a serial port may be used as a reference clock for NTP. If the GPS also supports PPS and is properly configured, and connected, that GPS may also be used as a Pulse Per Second clock reference. NOTE: a USB GPS may work, but is not recommended due to USB bus timing issues.
			<br />
			<br /><?php echo gettext("For the best results, NTP should have at least three sources of time. So it is best to configure at least 2 servers under"); ?> <a href="services_ntpd.php"><?php echo gettext("Services > NTP"); ?></a> <?php echo gettext("to minimize clock drift if the GPS data is not valid over time. Otherwise ntpd may only use values from the unsynchronized local clock when providing time to clients."); ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("GPS"); ?></td>
			<td width="78%" valign="top" class="vtable">
				<!-- Start with the original "Default", list a "Generic" and then specific configs alphabetically -->
				<select id="gpstype" name="gpstype" class="formselect" onchange="set_gps_default(this.form)">
					<option value="Custom"<?php if($pconfig['type'] == 'Custom') echo " selected=\"selected\""; ?>>Custom</option>
					<option value="Default"<?php if($pconfig['type'] == 'Default') echo " selected=\"selected\""; ?>>Default</option>
					<option value="Generic" title="Generic"<?php if($pconfig['type'] == 'Generic') echo " selected=\"selected\"";?>>Generic</option>
					<option value="Garmin" title="$PGRM... Most Garmin"<?php if($pconfig['type'] == 'Garmin') echo " selected=\"selected\"";?>>Garmin</option>
					<option value="MediaTek" title="$PMTK... Adafruit, Fastrax, some Garmin and others"<?php if($pconfig['type'] == 'MediaTek') echo " selected=\"selected\"";?>>MediaTek</option>
					<option value="SiRF" title="$PSRF... Used by many devices"<?php if($pconfig['type'] == 'sirf') echo " selected=\"selected\"";?>>SiRF</option>
					<option value="U-Blox" title="$PUBX... U-Blox 5, 6 and probably 7"<?php if($pconfig['type'] == 'U-Blox') echo " selected=\"selected\"";?>>U-Blox</option>
					<option value="SureGPS" title="$PMTK... Sure Electronics SKG16B"<?php if($pconfig['type'] == 'SureGPS') echo " selected=\"selected\"";?>>SureGPS</option>
				</select> <?php echo gettext("This option allows you to select a predefined configuration.");?>
				<br />
				<br />
				<strong><?php echo gettext("Note: ");?></strong><?php echo gettext("Default is the configuration of pfSense 2.1 and earlier"); ?>
				<?php echo gettext(" (not recommended). Select Generic if your GPS is not listed.)"); ?><br />
				<strong><?php echo gettext("Note: ");?></strong><?php echo gettext("The perdefined configurations assume your GPS has already been set to NMEA mode."); ?>
			</td>
		</tr>

<?php
	/* Probing would be nice, but much more complex. Would need to listen to each port for 1s+ and watch for strings. */
	$serialports = glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE);
	if (!empty($serialports)):
?>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Serial port</td>
			<td width="78%" class="vtable">
				<select name="gpsport" class="formselect">
					<option value="">none</option>
<?php
				foreach ($serialports as $port):
					$shortport = substr($port,5);
					$selected = ($shortport == $pconfig['port']) ? " selected=\"selected\"" : "";
?>
					<option value="<?php echo $shortport;?>"<?php echo $selected;?>><?php echo $shortport;?></option>
<?php
				endforeach;
?>
				</select>&nbsp;
				<?php echo gettext("All serial ports are listed, be sure to pick the port with the GPS attached."); ?>
				<br /><br />
				<select id="gpsspeed" name="gpsspeed" class="formselect">
					<option value="0"<?php if(!$pconfig['speed']) echo " selected=\"selected\""; ?>>4800</option>
					<option value="16"<?php if($pconfig['speed'] === '16') echo " selected=\"selected\"";?>>9600</option>
					<option value="32"<?php if($pconfig['speed'] === '32') echo " selected=\"selected\"";?>>19200</option>
					<option value="48"<?php if($pconfig['speed'] === '48') echo " selected=\"selected\"";?>>38400</option>
					<option value="64"<?php if($pconfig['speed'] === '64') echo " selected=\"selected\"";?>>57600</option>
					<option value="80"<?php if($pconfig['speed'] === '80') echo " selected=\"selected\"";?>>115200</option>
				</select>&nbsp;<?php echo gettext("Serial port baud rate."); ?>
				<br />
				<br />
				<?php echo gettext("Note: A higher baud rate is generally only helpful if the GPS is sending too many sentences. It is recommended to configure the GPS to send only one sentence at a baud rate of 4800 or 9600."); ?>
			</td>
		</tr>
<?php
	endif;
?>
		<tr>
			<!-- 1 = RMC, 2 = GGA, 4 = GLL, 8 = ZDA or ZDG -->
			<td width="22%" valign="top" class="vncellreq">NMEA sentences</td>
			<td width="78%" class="vtable">
				<select id="gpsnmea" name="gpsnmea[]" multiple="multiple" class="formselect" size="5">
					<option value="0"<?php if(!$pconfig['nmea']) echo " selected=\"selected\""; ?>>All</option>
					<option value="1"<?php if($pconfig['nmea'] & 1) echo " selected=\"selected\"";?>>RMC</option>
					<option value="2"<?php if($pconfig['nmea'] & 2) echo " selected=\"selected\"";?>>GGA</option>
					<option value="4"<?php if($pconfig['nmea'] & 4) echo " selected=\"selected\"";?>>GLL</option>
					<option value="8"<?php if($pconfig['nmea'] & 8) echo " selected=\"selected\"";?>>ZDA or ZDG</option>
				</select><br />
				<?php echo gettext("By default NTP will listen for all supported NMEA sentences. Here one or more sentences to listen for may be specified."); ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Fudge time 1</td>
			<td width="78%" class="vtable">
				<input name="gpsfudge1" type="text" class="formfld unknown" id="gpsfudge1" min="-1" max="1" size="20" value="<?=htmlspecialchars($pconfig['fudge1']);?>" />(<?php echo gettext("seconds");?>)<br />
				<?php echo gettext("Fudge time 1 is used to specify the GPS PPS signal offset");?> (<?php echo gettext("default");?>: 0.0).</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Fudge time 2</td>
			<td width="78%" class="vtable">
				<input name="gpsfudge2" type="text" class="formfld unknown" id="gpsfudge2" min="-1" max="1" size="20" value="<?=htmlspecialchars($pconfig['fudge2']);?>" />(<?php echo gettext("seconds");?>)<br />
				<?php echo gettext("Fudge time 2 is used to specify the GPS time offset");?> (<?php echo gettext("default");?>: 0.0).</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Stratum</td>
			<td width="78%" class="vtable">
				<input name="gpsstratum" type="text" class="formfld unknown" id="gpsstratum" max="16" size="20" value="<?=htmlspecialchars($pconfig['stratum']);?>" /><?php echo gettext("(0-16)");?><br />
				<?php echo gettext("This may be used to change the GPS Clock stratum");?> (<?php echo gettext("default");?>: 0). <?php echo gettext("This may be useful if, for some reason, you want ntpd to prefer a different clock"); ?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Flags</td>
			<td width="78%" class="vtable">
				<table>
					<tr>
						<td>
				<?php echo gettext("Normally there should be no need to change these options from the defaults."); ?><br />
						</td>
					</tr>
				</table>
				<table>
					<tr>
						<td>
							<input name="gpsprefer" type="checkbox" class="formcheckbox" id="gpsprefer" onclick="ToggleOther('gpsprefer', 'gpsselect')"<?php if(!$pconfig['prefer']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("NTP should prefer this clock (default: enabled)."); ?></span>
						</td>
					</tr>
					<tr>
						<td>
							<input name="gpsselect" type="checkbox" class="formcheckbox" id="gpsselect" onclick="ToggleOther('gpsselect', 'gpsprefer')"<?php if($pconfig['noselect']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("NTP should not use this clock, it will be displayed for reference only(default: disabled)."); ?></span>
						</td>
					</tr>
					<tr>
						<td>
							<input name="gpsflag1" type="checkbox" class="formcheckbox" id="gpsflag1"<?php if($pconfig['flag1']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("Enable PPS signal processing (default: enabled)."); ?></span>
						</td>
					</tr>
					<tr>
						<td>
							<input name="gpsflag2" type="checkbox" class="formcheckbox" id="gpsflag2"<?php if($pconfig['flag2']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("Enable falling edge PPS signal processing (default: rising edge)."); ?></span>
						</td>
					</tr>
					<tr>
						<td>
							<input name="gpsflag3" type="checkbox" class="formcheckbox" id="gpsflag3"<?php if($pconfig['flag3']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("Enable kernel PPS clock discipline (default: enabled)."); ?></span>
						</td>
					</tr>
					<tr>
						<td>
							<input name="gpsflag4" type="checkbox" class="formcheckbox" id="gpsflag4"<?php if($pconfig['flag4']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("Obscure location in timestamp (default: unobscured)."); ?></span>
						</td>
					</tr>
					<tr>
						<td>
							<input name="gpssubsec" type="checkbox" class="formcheckbox" id="gpssubsec"<?php if($pconfig['subsec']) echo " checked=\"checked\""; ?> />
						</td>
						<td>
							<span class="vexpl"><?php echo gettext("Log the sub-second fraction of the received time stamp (default: Not logged).<br />Note: enabling this will rapidly fill the log, but is useful for tuning Fudge time 2."); ?></span>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Clock ID</td>
			<td width="78%" class="vtable">
				<input name="gpsrefid" type="text" class="formfld unknown" id="gpsrefid" maxlength= "4" size="20" value="<?=htmlspecialchars($pconfig['refid']);?>" /><?php echo gettext("(1 to 4 charactors)");?><br />
				<?php echo gettext("This may be used to change the GPS Clock ID");?> (<?php echo gettext("default");?>: GPS).</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">GPS Initialization</td>
			<td width="78%" class="vtable">
				<div id="showgpsinitbox">
					<input type="button" onclick="show_advanced('showgpsinitbox', 'showgpsinit')" value="<?=gettext("Advanced");?>" /> - <?=gettext("Show GPS Initialization commands");?>
				</div>
				<div id="showgpsinit" style="display:none">
					<p>
					<textarea name="gpsinitcmd" class="formpre" id="gpsinitcmd" cols="65" rows="7"><?=htmlspecialchars(base64_decode($pconfig['initcmd'])); /*resultmatch*/?></textarea><br />
					<?php echo gettext("Note: Commands entered here will be sent to the GPS during initialization. Please read and understand your GPS documentation before making any changes here.");?><br /><br />
					<strong><?php echo gettext("NMEA checksum calculator");?>:</strong>
					<br />
					<?php echo gettext("Enter the text between &quot;$&quot; and &quot;*&quot; of a NMEA command string:");?><br /> $<input name="nmeastring" type="text" class="formfld unknown" id="nmeastring" size="30" value="" />*<span id="nmeachecksum"><?php echo gettext("checksum");?></span>&nbsp;&nbsp;
					<input type="button" onclick="NMEAChecksum(nmeastring.value)" value="<?=gettext("Calculate NMEA checksum");?>" /><br /></p>
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
	</div>
	</td></tr>
</table>
<script type="text/javascript">
//<![CDATA[
set_gps_default(this.form);
//]]>
</script>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
