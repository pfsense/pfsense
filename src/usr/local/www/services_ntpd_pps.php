<?php
/*
	services_ntpd_pps.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2013  Dagorlad
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
##|*IDENT=page-services-ntpd-pps
##|*NAME=Services: NTP PPS
##|*DESCR=Allow access to the 'Services: NTP PPS' page.
##|*MATCH=services_ntpd_pps.php*
##|-PRIV

require_once("guiconfig.inc");

if (!is_array($config['ntpd'])) {
	$config['ntpd'] = array();
}
if (!is_array($config['ntpd']['pps'])) {
	$config['ntpd']['pps'] = array();
}

if ($_POST) {
	unset($input_errors);

	if (!$input_errors) {
		if (!empty($_POST['ppsport']) && file_exists('/dev/'.$_POST['ppsport'])) {
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

		write_config("Updated NTP PPS Settings");

		$retval = 0;
		$retval = system_ntp_configure();
		$savemsg = get_std_save_message($retval);
	}
}

$pconfig = &$config['ntpd']['pps'];

$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("PPS"));
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
    print_input_errors($input_errors);
}

if ($savemsg) {
    print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("NTP"), false, "services_ntpd.php");
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
	'<a href="services_ntpd.php">' . 'Services > NTP' . '</a>' . ' to reliably supply the time of each PPS pulse.'
));

$serialports = glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE);

if (!empty($serialports)) {
	$splist = array();

	foreach ($serialports as $port) {
		$shortport = substr($port, 5);
		$splist[$shortport] = $shortport;
	}

	$section->addInput(new Form_Select(
		'ppsport',
		'Serial Port',
		$pconfig['port'],
			$splist
	))->setHelp('All serial ports are listed, be sure to pick the port with the PPS source attached. ');
}

$section->addInput(new Form_Input(
	'ppsfudge1',
	'Fudge Time',
	'text',
	$pconfig['fudge1']
))->setHelp('Fudge time is used to specify the PPS signal offset from the actual second such as the transmission delay between the transmitter and the receiver. (default: 0.0).');

$section->addInput(new Form_Input(
	'ppsstratum',
	'Stratum',
	'text',
	$pconfig['stratum']
))->setHelp('This may be used to change the PPS Clock stratum (default: 0). This may be useful if, for some reason, you want ntpd to prefer a different clock and just monitor this source.');

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
