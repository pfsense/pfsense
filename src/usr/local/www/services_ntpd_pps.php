<?php
/*
 * services_ntpd_pps.php
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
##|*IDENT=page-services-ntpd-pps
##|*NAME=Services: NTP PPS
##|*DESCR=Allow access to the 'Services: NTP PPS' page.
##|*MATCH=services_ntpd_pps.php*
##|-PRIV

require_once("guiconfig.inc");

global $ntp_poll_min_default_pps, $ntp_poll_max_default_pps;
$ntp_poll_values = system_ntp_poll_values();

$serialports = get_serial_ports(true);

if (!is_array($config['ntpd'])) {
	$config['ntpd'] = array();
}
if (!is_array($config['ntpd']['pps'])) {
	$config['ntpd']['pps'] = array();
}

if ($_POST) {
	unset($input_errors);

	if (!empty($_POST['ppsport']) && !array_key_exists($_POST['ppsport'], $serialports)) {
		$input_errors[] = gettext("The selected PPS port does not exist.");
	}

	if (!array_key_exists($pconfig['ppsminpoll'], $ntp_poll_values)) {
		$input_errors[] = gettext("The supplied value for Minimum Poll Interval is invalid.");
	}

	if (!array_key_exists($pconfig['ppsmaxpoll'], $ntp_poll_values)) {
		$input_errors[] = gettext("The supplied value for Maximum Poll Interval is invalid.");
	}

	if (is_numericint($pconfig['ppsminpoll']) &&
	    is_numericint($pconfig['ppsmaxpoll']) ||
	    ($pconfig['ppsmaxpoll'] < $pconfig['ppsminpoll'])) {
		$input_errors[] = gettext("The supplied value for Minimum Poll Interval is higher than Maximum Poll Interval.");
	}

	if (!$input_errors) {
		if (!empty($_POST['ppsport']) && array_key_exists($_POST['ppsport'], $serialports)) {
			$config['ntpd']['pps']['port'] = $_POST['ppsport'];
		} else {
			/* if port is not set, remove all the pps config */
			unset($config['ntpd']['pps']);
		}

		if (!empty($_POST['ppsfudge1'])) {
			$config['ntpd']['pps']['fudge1'] = $_POST['ppsfudge1'];
		} elseif (isset($config['ntpd']['pps']['fudge1'])) {
			unset($config['ntpd']['pps']['fudge1']);
		}

		if (!empty($_POST['ppsstratum']) && ($_POST['ppsstratum']) < 17) {
			$config['ntpd']['pps']['stratum'] = $_POST['ppsstratum'];
		} elseif (isset($config['ntpd']['pps']['stratum'])) {
			unset($config['ntpd']['pps']['stratum']);
		}

		if (!empty($_POST['ppsselect'])) {
			$config['ntpd']['pps']['noselect'] = $_POST['ppsselect'];
		} elseif (isset($config['ntpd']['pps']['noselect'])) {
			unset($config['ntpd']['pps']['noselect']);
		}

		if (!empty($_POST['ppsflag2'])) {
			$config['ntpd']['pps']['flag2'] = $_POST['ppsflag2'];
		} elseif (isset($config['ntpd']['pps']['flag2'])) {
			unset($config['ntpd']['pps']['flag2']);
		}

		if (!empty($_POST['ppsflag3'])) {
			$config['ntpd']['pps']['flag3'] = $_POST['ppsflag3'];
		} elseif (isset($config['ntpd']['pps']['flag3'])) {
			unset($config['ntpd']['pps']['flag3']);
		}

		if (!empty($_POST['ppsflag4'])) {
			$config['ntpd']['pps']['flag4'] = $_POST['ppsflag4'];
		} elseif (isset($config['ntpd']['pps']['flag4'])) {
			unset($config['ntpd']['pps']['flag4']);
		}

		if (!empty($_POST['ppsrefid'])) {
			$config['ntpd']['pps']['refid'] = $_POST['ppsrefid'];
		} elseif (isset($config['ntpd']['pps']['refid'])) {
			unset($config['ntpd']['pps']['refid']);
		}
		$config['ntpd']['pps']['ppsminpoll'] = $_POST['ppsminpoll'];
		$config['ntpd']['pps']['ppsmaxpoll'] = $_POST['ppsmaxpoll'];

		write_config("Updated NTP PPS Settings");

		$changes_applied = true;
		$retval = 0;
		$retval |= system_ntp_configure();
	}
}

init_config_arr(array('ntpd', 'pps'));
$pconfig = &$config['ntpd']['pps'];
$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("PPS"));
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
$tab_array[] = array(gettext("Serial GPS"), false, "services_ntpd_gps.php");
$tab_array[] = array(gettext("PPS"), true, "services_ntpd_pps.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('NTP Serial PPS Configuration');

$section->addInput(new Form_StaticText(
	'Notes',
	'Devices with a Pulse Per Second output such as radios that receive a time signal from DCF77 (DE), JJY (JP), MSF (GB) or WWVB (US) may be used as a PPS reference for NTP. ' .
	'A serial GPS may also be used, but the serial GPS driver would usually be the better option. ' .
	'A PPS signal only provides a reference to the change of a second, so at least one other source to number the seconds is required.' . '<br /><br />' .
	'At least 3 additional time sources should be configured under ' .
	'<a href="services_ntpd.php">' . 'Services > NTP > Settings' . '</a>' . ' to reliably supply the time of each PPS pulse.'
));

if (!empty($serialports)) {
	$section->addInput(new Form_Select(
		'ppsport',
		'Serial Port',
		$pconfig['port'],
		['' => gettext('None')] + $serialports
	))->setHelp('All serial ports are listed, be sure to pick the port with the PPS source attached. ');
}

$section->addInput(new Form_Input(
	'ppsfudge1',
	'Fudge Time',
	'text',
	$pconfig['fudge1']
))->setHelp('Fudge time is used to specify the PPS signal offset from the actual second such as the transmission delay between the transmitter and the receiver (default: 0.0).');

$section->addInput(new Form_Input(
	'ppsstratum',
	'Stratum',
	'text',
	$pconfig['stratum']
))->setHelp('This may be used to change the PPS Clock stratum (default: 0). This may be useful to, for some reason, have ntpd prefer a different clock and just monitor this source.');

$section->addInput(new Form_Select(
	'ppsminpoll',
	'Minimum Poll Interval',
	$pconfig['ppsminpoll'],
	$ntp_poll_values
))->setHelp('Minimum poll interval for NTP messages. If set, must be less than or equal to Maximum Poll Interval.');

$section->addInput(new Form_Select(
	'ppsmaxpoll',
	'Maximum Poll Interval',
	$pconfig['ppsmaxpoll'],
	$ntp_poll_values
))->setHelp('Maximum poll interval for NTP messages. If set, must be greater than or equal to Minimum Poll Interval.');

$section->addInput(new Form_Checkbox(
	'ppsflag2',
	'Flags',
	'Enable falling edge PPS signal processing (default: unchecked, rising edge).',
	$pconfig['flag2']
));

$section->addInput(new Form_Checkbox(
	'ppsflag3',
	null,
	'Enable kernel PPS clock discipline (default: unchecked).',
	$pconfig['flag3']
));

$section->addInput(new Form_Checkbox(
	'ppsflag4',
	null,
	'Record a timestamp once for each second, useful for constructing Allan deviation plots (default: unchecked).',
	$pconfig['flag4']
));

$section->addInput(new Form_Input(
	'ppsrefid',
	'Clock ID',
	'text',
	$pconfig['refid'],
	['placeholder' => '1 to 4 characters']
))->setHelp('This may be used to change the PPS Clock ID (default: PPS).');

$form->add($section);
print($form);

include("foot.inc");
