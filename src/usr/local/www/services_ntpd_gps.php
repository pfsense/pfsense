<?php
/*
	services_ntpd_gps.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2013 Dagorlad
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
##|*IDENT=page-services-ntpd-gps
##|*NAME=Services: NTP Serial GPS
##|*DESCR=Allow access to the 'Services: NTP Serial GPS' page.
##|*MATCH=services_ntpd_gps.php*
##|-PRIV

require_once("guiconfig.inc");

function set_default_gps() {
	global $config;

	if (!is_array($config['ntpd'])) {
		$config['ntpd'] = array();
	}
	if (is_array($config['ntpd']['gps'])) {
		unset($config['ntpd']['gps']);
	}

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

	if (!empty($_POST['gpsport']) && file_exists('/dev/'.$_POST['gpsport'])) {
		$config['ntpd']['gps']['port'] = $_POST['gpsport'];
	} else {
		/* if port is not set, remove all the gps config */
		unset($config['ntpd']['gps']);
	}

	if (!empty($_POST['gpstype'])) {
		$config['ntpd']['gps']['type'] = $_POST['gpstype'];
	} elseif (isset($config['ntpd']['gps']['type'])) {
		unset($config['ntpd']['gps']['type']);
	}

	if (!empty($_POST['gpsspeed'])) {
		$config['ntpd']['gps']['speed'] = $_POST['gpsspeed'];
	} elseif (isset($config['ntpd']['gps']['speed'])) {
		unset($config['ntpd']['gps']['speed']);
	}

	if (!empty($_POST['gpsnmea']) && ($_POST['gpsnmea'][0] === "0")) {
		$config['ntpd']['gps']['nmea'] = "0";
	} else {
		$config['ntpd']['gps']['nmea'] = strval(array_sum($_POST['gpsnmea']));
	}

	if (!empty($_POST['gpsfudge1'])) {
		$config['ntpd']['gps']['fudge1'] = $_POST['gpsfudge1'];
	} elseif (isset($config['ntpd']['gps']['fudge1'])) {
		unset($config['ntpd']['gps']['fudge1']);
	}

	if (!empty($_POST['gpsfudge2'])) {
		$config['ntpd']['gps']['fudge2'] = $_POST['gpsfudge2'];
	} elseif (isset($config['ntpd']['gps']['fudge2'])) {
		unset($config['ntpd']['gps']['fudge2']);
	}

	if (!empty($_POST['gpsstratum']) && ($_POST['gpsstratum']) < 17) {
		$config['ntpd']['gps']['stratum'] = $_POST['gpsstratum'];
	} elseif (isset($config['ntpd']['gps']['stratum'])) {
		unset($config['ntpd']['gps']['stratum']);
	}

	if (empty($_POST['gpsprefer'])) {
		$config['ntpd']['gps']['prefer'] = 'on';
	} elseif (isset($config['ntpd']['gps']['prefer'])) {
		unset($config['ntpd']['gps']['prefer']);
	}

	if (!empty($_POST['gpsselect'])) {
		$config['ntpd']['gps']['noselect'] = $_POST['gpsselect'];
	} elseif (isset($config['ntpd']['gps']['noselect'])) {
		unset($config['ntpd']['gps']['noselect']);
	}

	if (!empty($_POST['gpsflag1'])) {
		$config['ntpd']['gps']['flag1'] = $_POST['gpsflag1'];
	} elseif (isset($config['ntpd']['gps']['flag1'])) {
		unset($config['ntpd']['gps']['flag1']);
	}

	if (!empty($_POST['gpsflag2'])) {
		$config['ntpd']['gps']['flag2'] = $_POST['gpsflag2'];
	} elseif (isset($config['ntpd']['gps']['flag2'])) {
		unset($config['ntpd']['gps']['flag2']);
	}

	if (!empty($_POST['gpsflag3'])) {
		$config['ntpd']['gps']['flag3'] = $_POST['gpsflag3'];
	} elseif (isset($config['ntpd']['gps']['flag3'])) {
		unset($config['ntpd']['gps']['flag3']);
	}

	if (!empty($_POST['gpsflag4'])) {
		$config['ntpd']['gps']['flag4'] = $_POST['gpsflag4'];
	} elseif (isset($config['ntpd']['gps']['flag4'])) {
		unset($config['ntpd']['gps']['flag4']);
	}

	if (!empty($_POST['gpssubsec'])) {
		$config['ntpd']['gps']['subsec'] = $_POST['gpssubsec'];
	} elseif (isset($config['ntpd']['gps']['subsec'])) {
		unset($config['ntpd']['gps']['subsec']);
	}

	if (!empty($_POST['gpsrefid'])) {
		$config['ntpd']['gps']['refid'] = $_POST['gpsrefid'];
	} elseif (isset($config['ntpd']['gps']['refid'])) {
		unset($config['ntpd']['gps']['refid']);
	}

	if (!empty($_POST['gpsinitcmd'])) {
		$config['ntpd']['gps']['initcmd'] = base64_encode($_POST['gpsinitcmd']);
	} elseif (isset($config['ntpd']['gps']['initcmd'])) {
		unset($config['ntpd']['gps']['initcmd']);
	}

	write_config("Updated NTP GPS Settings");

	$retval = system_ntp_configure();
	$savemsg = get_std_save_message($retval);
} else {
	/* set defaults if they do not already exist */
	if (!is_array($config['ntpd']) || !is_array($config['ntpd']['gps']) || empty($config['ntpd']['gps']['type'])) {
		set_default_gps();
	}
}

function build_nmea_list() {
	global $pconfig;

	$nmealist = array('options' => array(), 'selected' => array());

	$nmealist['options'][0] = 'All';
	$nmealist['options'][1] = 'RMC';
	$nmealist['options'][2] = 'GGA';
	$nmealist['options'][4] = 'GLL';
	$nmealist['options'][8] = 'ZDA or ZDG';

	if (!$pconfig['nmea']) {
		array_push($nmealist['selected'], 0);
	}

	foreach ($nmealist['options'] as $val => $opt) {
		if ($pconfig['nmea'] & $val) {
		  array_push($nmealist['selected'], $val);
		}
	}

	return($nmealist);
}

$pconfig = &$config['ntpd']['gps'];
$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("Serial GPS"));
$shortcut_section = "ntp";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("NTP"), false, "services_ntpd.php");
$tab_array[] = array(gettext("Serial GPS"), true, "services_ntpd_gps.php");
$tab_array[] = array(gettext("PPS"), false, "services_ntpd_pps.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('NTP Serial GPS Configuration');

$section->addInput(new Form_StaticText(
	'Notes',
	'A GPS connected via a serial port may be used as a reference clock for NTP. If the GPS also supports PPS and is properly configured, ' .
	'and connected, that GPS may also be used as a Pulse Per Second clock reference. NOTE: A USB GPS may work, but is not recommended due to USB bus timing issues.' . '<br />' .
	'For the best results, NTP should have at least three sources of time. So it is best to configure at least 2 servers under ' .
	'<a href="services_ntpd.php">Services > NTP</a>' .
	' to minimize clock drift if the GPS data is not valid over time. Otherwise ntpd may only use values from the unsynchronized local clock when providing time to clients.'
));

$gpstypes = array('Custom', 'Default', 'Generic', 'Garmin', 'MediaTek', 'SiRF', 'U-Blox', 'SureGPS');

$section->addInput(new Form_Select(
	'gpstype',
	'GPS Type',
	$pconfig['type'],
	array_combine($gpstypes, $gpstypes)
))->setHelp('This option allows you to select a predefined configuration. ' .
			'Default is the configuration of pfSense 2.1 and earlier (not recommended). Select Generic if your GPS is not listed.' . '<br /><br />' .
			'The predefined configurations assume your GPS has already been set to NMEA mode.');

$serialports = glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE);

if (!empty($serialports)) {
	$splist = array();

	foreach ($serialports as $port) {
		$shortport = substr($port, 5);
		$splist[$shortport] = $shortport;
	}

	$section->addInput(new Form_Select(
		'gpsport',
		'Serial Port',
		$pconfig['port'],
		$splist
	))->setHelp('All serial ports are listed, be sure to pick the port with the GPS attached. ');

	$section->addInput(new Form_Select(
		'gpsspeed',
		null,
		$pconfig['speed'],
		[0 => '4800', 15 => '9600', 32 => '19200', 48 => '38400', 64 => '57600', 80 => '115200']

	))->setHelp('A higher baud rate is generally only helpful if the GPS is sending too many sentences. ' .
				'It is recommended to configure the GPS to send only one sentence at a baud rate of 4800 or 9600.');
}

$nmealist = build_nmea_list();
$section->addInput(new Form_Select(
	'gpsnmea',
	'NMEA Sentences',
	$nmealist['selected'],
	$nmealist['options'],
	true
))->setHelp('By default NTP will listen for all supported NMEA sentences. One or more sentences to listen for may be specified.');

$section->addInput(new Form_Input(
	'gpsfudge1',
	'Fudge Time 1',
	'text',
	$pconfig['fudge1']
))->setHelp('Fudge time 1 is used to specify the GPS PPS signal offset (default: 0.0).');

$section->addInput(new Form_Input(
	'gpsfudge2',
	'Fudge Time 2',
	'text',
	$pconfig['fudge2']
))->setHelp('Fudge time 2 is used to specify the GPS time offset (default: 0.0).');

$section->addInput(new Form_Input(
	'gpsstratum',
	'Stratum (0-16)',
	'text',
	$pconfig['stratum']
))->setHelp('This may be used to change the GPS Clock stratum (default: 0). This may be useful if, for some reason, you want ntpd to prefer a different clock');

$section->addInput(new Form_Checkbox(
	'gpsprefer',
	'Flags',
	'Prefer this clock (default: checked).',
	!$pconfig['prefer']
));

$section->addInput(new Form_Checkbox(
	'gpsselect',
	null,
	'Do not use this clock, display for reference only (default: unchecked).',
	$pconfig['noselect']
));

$section->addInput(new Form_Checkbox(
	'gpsflag1',
	null,
	'Enable PPS signal processing (default: checked).',
	$pconfig['flag1']
));

$section->addInput(new Form_Checkbox(
	'gpsflag2',
	null,
	'Enable falling edge PPS signal processing (default: unchecked, rising edge).',
	$pconfig['flag2']
));

$section->addInput(new Form_Checkbox(
	'gpsflag3',
	null,
	'Enable kernel PPS clock discipline (default: checked).',
	$pconfig['flag3']
));

$section->addInput(new Form_Checkbox(
	'gpsflag4',
	null,
	'Obscure location in timestamp (default: unchecked, unobscured).',
	$pconfig['flag4']
));

$section->addInput(new Form_Checkbox(
	'gpssubsec',
	null,
	'Log the sub-second fraction of the received time stamp (default: unchecked, not logged).',
	$pconfig['subsec']
))->setHelp('Enabling this will rapidly fill the log, but is useful for tuning Fudge time 2.');

$section->addInput(new Form_Input(
	'gpsrefid',
	'Clock ID',
	'text',
	$pconfig['refid'],
	['placeholder' => '1 to 4 characters']
))->setHelp('This may be used to change the GPS Clock ID (default: GPS).');

// Statistics logging section
$btnadvgps = new Form_Button(
	'btnadvgps',
	'Advanced'
);

$btnadvgps->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'GPS Initialization',
	$btnadvgps . '&nbsp' . 'Show GPS Initialization commands'
));

$section->addInput(new Form_Textarea(
	'gpsinitcmd',
	null,
	$pconfig['initcmd']
))->setHelp('Commands entered here will be sent to the GPS during initialization. Please read and understand your GPS documentation before making any changes here');

$group = new Form_Group('NMEA Checksum Calculator');

$group->add(new Form_Input(
	'nmeastring',
	null
));

$btncalc = new Form_Button(
	'btncalc',
	'Calculate'
);

$btncalc->removeClass('btn-primary')->addClass('btn-success btn-sm');

$group->add($btncalc);

$group->add(new Form_Input(
	'result',
	null,
	'text',
	null,
	['placeholder' => 'Result']
));

$group->setHelp('Enter the text between &quot;$&quot; and &quot;*&quot; of a NMEA command string:');
$group->addClass('calculator');

$section->add($group);

$form->add($section);
print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function NMEAChecksum(cmd) {
		// Compute the checksum by XORing all the character values in the string.
		var checksum = 0;

		for (var i = 0; i < cmd.length; i++) {
			checksum = checksum ^ cmd.charCodeAt(i);
		}
		// Convert it to hexadecimal (base-16, upper case, most significant byte first).
		var hexsum = Number(checksum).toString(16).toUpperCase();

		if (hexsum.length < 2) {
			hexsum = ("00" + hexsum).slice(-2);
		}

		return(hexsum);
	}

	function set_gps_default(type) {
		$('#gpsnmea').val(0);
		$('#gpsspeed').val(0);
		$('#gpsfudge1').val(0);

		//stuff the JS object as needed for each type
		switch (type) {
			case "Default":
				$('#gpsfudge1').val("0.155");
				$('#gpsfudge2').val("");
				$('#gpsinitcmd').val(atob("JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMCwwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRHLDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWCw0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQVUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEsMSwxLDEsMQ0K"));
				break;

			case "Garmin":
				$('#gpsfudge2').val("0.600");
				$('#gpsinitcmd').val(atob("JFBHUk1DLCwsLCwsLCwsLDMsLDIsOCo1RQ0KJFBHUk1DMSwsMSwsLCwsLFcsLCwsLCwsKjMwDQokUEdSTU8sLDMqNzQNCiRQR1JNTyxHUFJNQywxKjNEDQokUEdSTU8sR1BHR0EsMSoyMA0KJFBHUk1PLEdQR0xMLDEqMjYNCg=="));
				break;

			case "Generic":
				$('#gpsfudge2').val("0.400");
				$('#gpsinitcmd').val("");
				break;

			case "MediaTek":
				$('#gpsfudge2').val("0.400");
				$('#gpsinitcmd').val(atob("JFBNVEsyMjUsMCoyQg0KJFBNVEszMTQsMSwxLDAsMSwwLDAsMCwwLDAsMCwwLDAsMCwwLDAsMCwwLDEsMCoyOA0KJFBNVEszMDEsMioyRQ0KJFBNVEszMjAsMCoyRg0KJFBNVEszMzAsMCoyRQ0KJFBNVEszODYsMCoyMw0KJFBNVEszOTcsMCoyMw0KJFBNVEsyNTEsNDgwMCoxNA0K"));
				break;

			case "SiRF":
				$('#gpsfudge2').val("0.704"); //valid for 4800, 0.688 @ 9600, 0.640 @ USB
				$('#gpsinitcmd').val(atob("JFBTUkYxMDMsMDAsMDAsMDEsMDEqMjUNCiRQU1JGMTAzLDAxLDAwLDAxLDAxKjI0DQokUFNSRjEwMywwMiwwMCwwMCwwMSoyNA0KJFBTUkYxMDMsMDMsMDAsMDAsMDEqMjQNCiRQU1JGMTAzLDA0LDAwLDAxLDAxKjI0DQokUFNSRjEwMywwNSwwMCwwMCwwMSoyNA0KJFBTUkYxMDAsMSw0ODAwLDgsMSwwKjBFDQo="));
				break;

			case "U-Blox":
				$('#gpsfudge2').val("0.400");
				$('#gpsinitcmd').val(atob("JFBVQlgsNDAsR0dBLDEsMSwxLDEsMCwwKjVBDQokUFVCWCw0MCxHTEwsMSwxLDEsMSwwLDAqNUMNCiRQVUJYLDQwLEdTQSwwLDAsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR1NWLDAsMCwwLDAsMCwwKjU5DQokUFVCWCw0MCxSTUMsMSwxLDEsMSwwLDAqNDcNCiRQVUJYLDQwLFZURywwLDAsMCwwLDAsMCo1RQ0KJFBVQlgsNDAsR1JTLDAsMCwwLDAsMCwwKjVEDQokUFVCWCw0MCxHU1QsMCwwLDAsMCwwLDAqNUINCiRQVUJYLDQwLFpEQSwxLDEsMSwxLDAsMCo0NA0KJFBVQlgsNDAsR0JTLDAsMCwwLDAsMCwwKjREDQokUFVCWCw0MCxEVE0sMCwwLDAsMCwwLDAqNDYNCiRQVUJYLDQwLEdQUSwwLDAsMCwwLDAsMCo1RA0KJFBVQlgsNDAsVFhULDAsMCwwLDAsMCwwKjQzDQokUFVCWCw0MCxUSFMsMCwwLDAsMCwwLDAqNTQNCiRQVUJYLDQxLDEsMDAwNywwMDAzLDQ4MDAsMCoxMw0K"));
				break;

			case "SureGPS":
				$('#gpsnmea').val(1);
				$('#gpsspeed').val(16);
				$('#gpsfudge2').val("0.407");
				$('#gpsinitcmd').val(atob("JFBNVEsyMjUsMCoyQg0KJFBNVEszMTQsMSwxLDAsMSwwLDUsMCwwLDAsMCwwLDAsMCwwLDAsMCwwLDEsMCoyRA0KJFBNVEszMDEsMioyRQ0KJFBNVEszOTcsMCoyMw0KJFBNVEsxMDIqMzENCiRQTVRLMzEzLDEqMkUNCiRQTVRLNTEzLDEqMjgNCiRQTVRLMzE5LDAqMjUNCiRQTVRLNTI3LDAuMDAqMDANCiRQTVRLMjUxLDk2MDAqMTcNCg=="));
				break;
			default:
				return;
		}

		$('#gpsstratum').val("");
		$('#gpsrefid').val("");
		$('#gpsflag1').prop('checked', true);
		$('#gpsflag2').prop('checked', false);
		$('#gpsflag3').prop('checked', true);
		$('#gpsflag4').prop('checked', false);
		$('#gpssubsec').prop('checked', false);
	}

	// Make the ‘Advanced’ button a plain button, not a submit button
	$('#btnadvgps').prop('type','button');

	// On click, show the controls in the GPS Initialization section
	$("#btnadvgps").click(function() {
		hideInput('gpsinitcmd', false);
		hideClass('calculator', false);
	});

	// Make the ‘Calculate’ button a plain button, not a submit button
	$('#btncalc').prop('type','button');
	$('#result').prop("disabled", true);

	// Onclick read the string from the nmeastring box, calculate the checksum
	// and display the results in the result box
	$("#btncalc").click(function() {
		$('#result').val(NMEAChecksum($('#nmeastring').val()));
	});

	// When the 'GPS' selector is changed, we set tth gps defaults
	$('#gpstype').on('change', function() {
		set_gps_default($(this).val());
	});

	hideInput('gpsinitcmd', true);
	hideClass('calculator', true);

	set_gps_default('<?=$pconfig['type']?>');

	//	Checkboxes gpsprefer and gpsselect are mutually exclusive
	$('#gpsprefer').click(function() {
		if ($(this).is(':checked')) {
			$('#gpsselect').prop('checked', false);
		}
	});

	$('#gpsselect').click(function() {
		if ($(this).is(':checked')) {
			$('#gpsprefer').prop('checked', false);
		}
	});
});
//]]>
</script>

<?php include("foot.inc");
