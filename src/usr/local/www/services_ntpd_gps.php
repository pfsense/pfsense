<?php
/*
 * services_ntpd_gps.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Dagorlad
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-services-ntpd-gps
##|*NAME=Services: NTP Serial GPS
##|*DESCR=Allow access to the 'Services: NTP Serial GPS' page.
##|*MATCH=services_ntpd_gps.php*
##|-PRIV

require_once("guiconfig.inc");

$gpstypes = array(gettext('Custom'), gettext('Default'), 'Generic', 'Garmin', 'MediaTek', 'SiRF', 'U-Blox', 'SureGPS');

global $ntp_poll_min_default_gps, $ntp_poll_max_default_gps;
$ntp_poll_values = system_ntp_poll_values();

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

	write_config(gettext("Setting default NTPd settings"));
}

function parse_ublox(&$nmeaset, $splitline) {
	$id_idx = 1;
	$msg_idx = 2;
	$usart1_idx = 4;
	if ($splitline[$id_idx] == '40' && $splitline[$usart1_idx]) {
		$nmeaset['GP' . $splitline[$msg_idx]] = 1;
	}
}

function parse_garmin(&$nmeaset, $splitline) {
	$msg_idx = 1;
	$mode_idx = 2;
	if ($splitline[$mode_idx] == '1') {
		$nmeaset[$splitline[$msg_idx]] = 1;
	}
}

function parse_mtk(&$nmeaset, $splitline) {
	$nmeamap = [
		1 => 'GPGLL',
		2 => 'GPRMC',
		3 => 'GPVTG',
		4 => 'GPGGA',
		5 => 'GPGSA',
		6 => 'GPGSV',
		7 => 'GPGRS',
		8 => 'GPGST',
	];
	for ($x = 1; $x < 9; $x++) {
		if($splitline[$x]) {
			$nmeaset[$nmeamap[$x]] = 1;
		}
	}
}

function parse_sirf(&$nmeaset, $splitline) {
	$msg_idx = 1;
	$mode_idx = 2;
	$rate_idx = 3;
	$nmeamap = [
		0 => 'GPGGA',
		1 => 'GPGLL',
		2 => 'GPGSA',
		3 => 'GPGSV',
		4 => 'GPRMC',
		5 => 'GPVTG',
	];
	if (!(int)$splitline[$mode_idx] && (int)$splitline[$rate_idx]) {
		$nmeaset[$nmeamap[(int)$splitline[$msg_idx]]] = 1;
	}
}

function parse_initcmd(&$nmeaset, $initcmd) {
	$type_idx = 0;
	$nmeaset = [];
	$split_initcmd = preg_split('/[\s]+/', $initcmd);
	foreach ($split_initcmd as $line) {
		$splitline = preg_split('/[,\*]+/', $line);
		if ($splitline[$type_idx] == '$PUBX') {
			parse_ublox($nmeaset, $splitline);
		} elseif ($splitline[$type_idx] == '$PGRMO') {
			parse_garmin($nmeaset, $splitline);
		} elseif ($splitline[$type_idx] == '$PMTK314') {
			parse_mtk($nmeaset, $splitline);
		} elseif ($splitline[$type_idx] == '$PSRF103') {
			parse_sirf($nmeaset, $splitline);
		}
	}
}

function NMEAChecksum($cmd) {
	$checksum = 0;
	for ($i=0; $i<strlen($cmd); $i++) {
		$checksum = ($checksum ^ ord($cmd[$i]));
	}
	return strtoupper(str_pad(dechex($checksum), 2, '0', STR_PAD_LEFT));
}

function autocorrect_initcmd($initcmd) {
	$cmds = '';
	$split_initcmd = preg_split('/[\s]+/', $initcmd);
	foreach ($split_initcmd as $line) {
		if (!strlen($line)) {
			continue;
		}
		$begin = ($line[0] == '$') ? 1 : 0;
		$astpos = strrpos($line, '*');
		$end = ($astpos !== false) ? $astpos : strlen($line);
		$trimline = substr($line, $begin, $end-$begin);
		$cmds = $cmds . '$' . $trimline . '*' . NMEAChecksum($trimline) . "\r\n";
	}
	return $cmds;
}

if ($_POST) {
	$input_errors = array();
	if (!in_array($_POST['gpstype'], $gpstypes)) {
		$input_errors[] = gettext("The submitted GPS type is invalid.");
	}

	if (!array_key_exists($pconfig['gpsminpoll'], $ntp_poll_values)) {
		$input_errors[] = gettext("The supplied value for Minimum Poll Interval is invalid.");
	}

	if (!array_key_exists($pconfig['gpsmaxpoll'], $ntp_poll_values)) {
		$input_errors[] = gettext("The supplied value for Maximum Poll Interval is invalid.");
	}

	if (is_numericint($pconfig['gpsminpoll']) &&
	    is_numericint($pconfig['gpsmaxpoll']) ||
	    ($pconfig['gpsmaxpoll'] < $pconfig['gpsminpoll'])) {
		$input_errors[] = gettext("The supplied value for Minimum Poll Interval is higher than Maximum Poll Interval.");
	}

} else {
	/* set defaults if they do not already exist */
	if (!is_array($config['ntpd']) || !is_array($config['ntpd']['gps']) || empty($config['ntpd']['gps']['type'])) {
		set_default_gps();
	}
}


if ($_POST && empty($input_errors)) {

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
		if ($_POST['gpsspeed'] == 'autoalways') {
			$fixghost = true;
		}
	} elseif (isset($config['ntpd']['gps']['speed'])) {
		unset($config['ntpd']['gps']['speed']);
	}
	
	if (!empty($_POST['autobaudinit'])) {
		$config['ntpd']['gps']['autobaudinit'] = $_POST['autobaudinit'];
	} elseif (isset($config['ntpd']['gps']['autobaudinit'])) {
		unset($config['ntpd']['gps']['autobaudinit']);
	}

	if (!empty($_POST['gpsnmea']) && ($_POST['gpsnmea'][0] === "0")) {
		$config['ntpd']['gps']['nmea'] = "0";
	} else {
		$config['ntpd']['gps']['nmea'] = strval(array_sum($_POST['gpsnmea']));
	}

	if (!empty($_POST['processpgrmf'])) {
		$config['ntpd']['gps']['processpgrmf'] = $_POST['processpgrmf'];
	} elseif (isset($config['ntpd']['gps']['processpgrmf'])) {
		unset($config['ntpd']['gps']['processpgrmf']);
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

	if (!empty($_POST['gpsnoselect'])) {
		$config['ntpd']['gps']['noselect'] = $_POST['gpsnoselect'];
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

	if (!empty($_POST['extstatus'])) {
		$config['ntpd']['gps']['extstatus'] = $_POST['extstatus'];
	} elseif (isset($config['ntpd']['gps']['extstatus'])) {
		unset($config['ntpd']['gps']['extstatus']);
	}

	if (!empty($_POST['autocorrect_initcmd'])) {
		$config['ntpd']['gps']['autocorrect_initcmd'] = $_POST['autocorrect_initcmd'];
	} elseif (isset($config['ntpd']['gps']['autocorrect_initcmd'])) {
		unset($config['ntpd']['gps']['autocorrect_initcmd']);
	}

	if (!empty($_POST['gpsinitcmd'])) {
		$initcmd = $_POST['gpsinitcmd'];
		if ($config['ntpd']['gps']['autocorrect_initcmd']) {
			$initcmd = autocorrect_initcmd($initcmd);
		}
		$config['ntpd']['gps']['initcmd'] = base64_encode($initcmd);
		parse_initcmd($config['ntpd']['gps']['nmeaset'], $initcmd);
	} elseif (isset($config['ntpd']['gps']['initcmd'])) {
		unset($config['ntpd']['gps']['initcmd']);
		unset($config['ntpd']['gps']['nmeaset']);
	}

	$config['ntpd']['gps']['gpsminpoll'] = $_POST['gpsminpoll'];
	$config['ntpd']['gps']['gpsmaxpoll'] = $_POST['gpsmaxpoll'];

	write_config(gettext("Updated NTP GPS Settings"));

	$changes_applied = true;
	$retval = 0;
	$retval |= system_ntp_configure();
	if ($fixghost) {
		$config['ntpd']['gps']['speed'] = 'autoalways';
	}
} else {
	/* set defaults if they do not already exist */
	if (!is_array($config['ntpd']) || !is_array($config['ntpd']['gps']) || empty($config['ntpd']['gps']['type'])) {
		set_default_gps();
	}
}

function build_nmea_list() {
	global $pconfig;

	$nmealist = array('options' => array(), 'selected' => array());

	$nmealist['options'][0] = gettext('All');
	$nmealist['options'][1] = gettext('RMC');
	$nmealist['options'][2] = gettext('GGA');
	$nmealist['options'][4] = gettext('GLL');
	$nmealist['options'][8] = gettext('ZDA or ZDG');

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

init_config_arr(array('ntpd', 'gps'));
$pconfig = &$config['ntpd']['gps'];
$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("Serial GPS"));
$pglinks = array("", "services_ntpd.php", "@self");
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "services_ntpd.php");
$tab_array[] = array(gettext("ACLs"), false, "services_ntpd_acls.php");
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
	'<a href="services_ntpd.php">Services > NTP > Settings</a>' .
	' to minimize clock drift if the GPS data is not valid over time. Otherwise ntpd may only use values from the unsynchronized local clock when providing time to clients.'
));

$section->addInput(new Form_Select(
	'gpstype',
	'GPS Type',
	$pconfig['type'],
	array_combine($gpstypes, $gpstypes)
))->setHelp('This option allows a predefined configuration to be selected. ' .
    'Default is the configuration of %1$s 2.1 and earlier (not recommended). Select Generic if the GPS is not listed.%2$s' .
    'The predefined configurations assume the GPS has already been set to NMEA mode.', $g['product_label'], '<br /><br />');

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
		['' => gettext('None')] + $splist
	))->setHelp('All serial ports are listed, be sure to pick the port with the GPS attached. ');

	$section->addInput(new Form_Select(
		'gpsspeed',
		null,
		$pconfig['speed'],
		[0 => '4800', 16 => '9600', 32 => '19200', 48 => '38400', 64 => '57600', 80 => '115200', 'autoset' => 'Autoset', 'autoalways' => 'Always Auto']

	))->setHelp('A higher baud rate is generally only helpful if the GPS is sending too many sentences. ' .
				'It is recommended to configure the GPS to send only one sentence at a baud rate of 4800 or 9600.%1$s' .
				'Autoset tries to find the correct baud rate of the GPS device and then saves the configuration.%1$s' .
				'Always Auto tries to find the correct baud rate of the GPS device every time NTPd is started.', '<br /><br />');
	
	$section->addInput(new Form_Checkbox(
		'autobaudinit',
		null,
		'Check baud rate before sending init commands (default: unchecked).',
		$pconfig['autobaudinit']
	))->setHelp(
		'Before sending the initialization commands, check the GPS baud rate. ' . 
		'If it is not correct try to find the correct baud rate automatically, ' .
		'send the initialization commands if the correct rate is found, ' .
		'and then set the baud rate to the configured speed.%1$s' . 
		'This is useful if the GPS device resets back to a default rate on power loss ' .
		'or when changing the baud rate.', '<br /><br />');
}

$nmealist = build_nmea_list();
$section->addInput(new Form_Select(
	'gpsnmea',
	'NMEA Sentences',
	$nmealist['selected'],
	$nmealist['options'],
	true
))->setHelp('By default NTP will listen for all supported NMEA sentences. One or more sentences to listen for may be specified.');

$section->addInput(new Form_Checkbox(
	'processpgrmf',
	null,
	'Process PGRMF. Ignores ALL other NMEA sentences. (default: unchecked).',
	$pconfig['processpgrmf']
));

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
))->setHelp('This may be used to change the GPS Clock stratum (default: 0). This may be useful to, for some reason, have ntpd prefer a different clock.');

$section->addInput(new Form_Select(
	'gpsminpoll',
	'Minimum Poll Interval',
	$pconfig['gpsminpoll'],
	$ntp_poll_values
))->setHelp('Minimum poll interval for NTP messages. If set, must be less than or equal to Maximum Poll Interval.');

$section->addInput(new Form_Select(
	'gpsmaxpoll',
	'Maximum Poll Interval',
	$pconfig['gpsmaxpoll'],
	$ntp_poll_values
))->setHelp('Maximum poll interval for NTP messages. If set, must be greater than or equal to Minimum Poll Interval.');

$section->addInput(new Form_Checkbox(
	'gpsprefer',
	'Flags',
	'Prefer this clock (default: checked).',
	!$pconfig['prefer']
));

$section->addInput(new Form_Checkbox(
	'gpsnoselect',
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

$section->addInput(new Form_Checkbox(
	'extstatus',
	null,
	'Display extended GPS status (default: checked).',
	$pconfig['extstatus']
))->setHelp('Enable extended GPS status if GPGSV or GPGGA are explicitly enabled by GPS initialization commands.');

$section->addInput(new Form_Input(
	'gpsrefid',
	'Clock ID',
	'text',
	$pconfig['refid'],
	['placeholder' => '1 to 4 characters']
))->setHelp('This may be used to change the GPS Clock ID (default: GPS).');

// Statistics logging section
$btnadv = new Form_Button(
	'btnadvgps',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'GPS Initialization',
	$btnadv
));

$section->addInput(new Form_Textarea(
	'gpsinitcmd',
	null,
	base64_decode($pconfig['initcmd'])
))->setHelp('Commands entered here will be sent to the GPS during initialization. Please read and understand the GPS documentation before making any changes here.');

$section->addInput(new Form_Checkbox(
	'autocorrect_initcmd',
	null,
	'Auto correct malformed initialization commands. (default: unchecked).',
	$pconfig['autocorrect_initcmd']
))->setHelp('Calculates and appends checksum and missing special characters "$" and "*". May not work with some GPS models.');

$group = new Form_Group('NMEA Checksum Calculator');

$group->add(new Form_Input(
	'nmeastring',
	null
));

$btncalc = new Form_Button(
	'btncalc',
	'Calculate',
	null,
	'fa-calculator'
);

$btncalc->setAttribute('type','button')->removeClass('btn-primary')->addClass('btn-success btn-sm');

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

	function get_base64_gps_string(type) {

		switch (type) {
			case "Default":
				return "JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMCwwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRHLDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWCw0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQVUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEsMSwxLDEsMQ0K";
				break;

			case "Garmin":
				return "JFBHUk1DLCwsLCwsLCwsLDMsLDIsOCo1RQ0KJFBHUk1DMSwsMSwsLCwsLFcsLCwsLCwsKjMwDQokUEdSTU8sLDIqNzUNCiRQR1JNTyxHUFJNQywxKjNEDQokUEdSTU8sR1BHR0EsMSoyMA0KJFBHUk1PLEdQR0xMLDEqMjYNCg==";
				break;

			case "Generic":
				return "";
				break;

			case "MediaTek":
				return "JFBNVEsyMjUsMCoyQg0KJFBNVEszMTQsMSwxLDAsMSwwLDAsMCwwLDAsMCwwLDAsMCwwLDAsMCwwLDEsMCoyOA0KJFBNVEszMDEsMioyRQ0KJFBNVEszMjAsMCoyRg0KJFBNVEszMzAsMCoyRQ0KJFBNVEszODYsMCoyMw0KJFBNVEszOTcsMCoyMw0KJFBNVEsyNTEsNDgwMCoxNA0K";
				break;

			case "SiRF":
				return "JFBTUkYxMDMsMDAsMDAsMDEsMDEqMjUNCiRQU1JGMTAzLDAxLDAwLDAxLDAxKjI0DQokUFNSRjEwMywwMiwwMCwwMCwwMSoyNA0KJFBTUkYxMDMsMDMsMDAsMDAsMDEqMjQNCiRQU1JGMTAzLDA0LDAwLDAxLDAxKjI0DQokUFNSRjEwMywwNSwwMCwwMCwwMSoyNA0KJFBTUkYxMDAsMSw0ODAwLDgsMSwwKjBFDQo=";
				break;

			case "U-Blox":
				return "JFBVQlgsNDAsR0dBLDEsMSwxLDEsMCwwKjVBDQokUFVCWCw0MCxHTEwsMSwxLDEsMSwwLDAqNUMNCiRQVUJYLDQwLEdTQSwwLDAsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR1NWLDAsMCwwLDAsMCwwKjU5DQokUFVCWCw0MCxSTUMsMSwxLDEsMSwwLDAqNDcNCiRQVUJYLDQwLFZURywwLDAsMCwwLDAsMCo1RQ0KJFBVQlgsNDAsR1JTLDAsMCwwLDAsMCwwKjVEDQokUFVCWCw0MCxHU1QsMCwwLDAsMCwwLDAqNUINCiRQVUJYLDQwLFpEQSwxLDEsMSwxLDAsMCo0NA0KJFBVQlgsNDAsR0JTLDAsMCwwLDAsMCwwKjREDQokUFVCWCw0MCxEVE0sMCwwLDAsMCwwLDAqNDYNCiRQVUJYLDQwLEdQUSwwLDAsMCwwLDAsMCo1RA0KJFBVQlgsNDAsVFhULDAsMCwwLDAsMCwwKjQzDQokUFVCWCw0MCxUSFMsMCwwLDAsMCwwLDAqNTQNCiRQVUJYLDQxLDEsMDAwNywwMDAzLDQ4MDAsMCoxMw0K";
				break;

			case "SureGPS":
				return "JFBNVEsyMjUsMCoyQg0KJFBNVEszMTQsMSwxLDAsMSwwLDUsMCwwLDAsMCwwLDAsMCwwLDAsMCwwLDEsMCoyRA0KJFBNVEszMDEsMioyRQ0KJFBNVEszOTcsMCoyMw0KJFBNVEsxMDIqMzENCiRQTVRLMzEzLDEqMkUNCiRQTVRLNTEzLDEqMjgNCiRQTVRLMzE5LDAqMjUNCiRQTVRLNTI3LDAuMDAqMDANCiRQTVRLMjUxLDk2MDAqMTcNCg==";
				break;
			default:
				return "";
		}
	}

	function get_gps_string(type) {
		return atob(get_base64_gps_string(type));
	}

	function set_gps_default(type) {
		$('#gpsnmea').val(0);
		$('#processpgrmf').prop('checked', false);
		$('#autobaudinit').prop('checked', false);
		$('#gpsfudge1').val(0);
		$('#gpsinitcmd').val(get_gps_string(type));

		//stuff the JS object as needed for each type
		switch (type) {
			case "Default":
				$('#gpsfudge1').val("0.155");
				$('#gpsfudge2').val("");
				break;

			case "Garmin":
				$('#gpsfudge2').val("0.600");
				break;

			case "Generic":
				$('#gpsfudge2').val("0.400");
				break;

			case "MediaTek":
				$('#gpsfudge2').val("0.400");
				break;

			case "SiRF":
				$('#gpsfudge2').val("0.704"); //valid for 4800, 0.688 @ 9600, 0.640 @ USB
				break;

			case "U-Blox":
				$('#gpsfudge2').val("0.400");
				break;

			case "SureGPS":
				$('#gpsnmea').val(1);
				$('#gpsspeed').val(16);
				$('#gpsfudge2').val("0.407");
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
		$('#extstatus').prop('checked', true);
		$('#autocorrect_initcmd').prop('checked', false);
	}

	// Show advanced GPS options ==============================================
	var showadvgps = false;

	function show_advgps(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
			// If the string in initcmd matches the GPS string for the currently-selected type or is empty
			// then we have default settings - do not show the advanced stuff.
			if (('<?=$pconfig['initcmd'] ?>' == get_base64_gps_string($('#gpstype').val())) ||
			    ('<?=$pconfig['initcmd']?>' == '')) {
				showadvgps = false;
			} else {
				showadvgps = true;
			}
		} else {
			// It was a click, swap the state.
			showadvgps = !showadvgps;
		}

		hideInput('gpsinitcmd', !showadvgps);
		hideInput('autocorrect_initcmd', !showadvgps);
		hideClass('calculator', !showadvgps);

		if (showadvgps) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvgps').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvgps').click(function(event) {
		show_advgps();
	});

	$('#result').prop("disabled", true);

	// Onclick read the string from the nmeastring box, calculate the checksum
	// and display the results in the result box
	$("#btncalc").click(function() {
		$('#result').val(NMEAChecksum($('#nmeastring').val()));
	});

	// When the 'GPS' selector is changed, we set the gps defaults
	$('#gpstype').on('change', function() {
		set_gps_default($(this).val());
		hideInput('processpgrmf', ($(this).val() !== "Garmin" && $(this).val() !== "Custom"));
	});
	hideInput('processpgrmf', (<?=json_encode($pconfig['type'])?> !== "Garmin" && <?=json_encode($pconfig['type'])?> !== "Custom"));

	if ('<?=$pconfig['initcmd']?>' == '') {
		set_gps_default(<?=json_encode($pconfig['type'])?>);
	}

	//	Checkboxes gpsprefer and gpsnoselect are mutually exclusive
	$('#gpsprefer').click(function() {
		if ($(this).is(':checked')) {
			$('#gpsnoselect').prop('checked', false);
		}
	});

	$('#gpsnoselect').click(function() {
		if ($(this).is(':checked')) {
			$('#gpsprefer').prop('checked', false);
		}
	});

	// ---------- On initial page load ---------------------------------------

	show_advgps(true);
});
//]]>
</script>

<?php include("foot.inc");
