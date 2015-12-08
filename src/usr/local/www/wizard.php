<?php
/*
	wizard.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-pfsensewizardsubsystem
##|*NAME=pfSense wizard subsystem
##|*DESCR=Allow access to the 'pfSense wizard subsystem' page.
##|*MATCH=wizard.php*
##|-PRIV

require("globals.inc");
require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require_once("rrd.inc");
require_once("system.inc");

// This causes the step #, field type and field name to be printed at the top of the page
define(DEBUG, false);

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

global $g;

$stepid = htmlspecialchars($_GET['stepid']);
if (isset($_POST['stepid'])) {
	$stepid = htmlspecialchars($_POST['stepid']);
}

if (!$stepid) {
	$stepid = "0";
}

$xml = htmlspecialchars($_GET['xml']);
if ($_POST['xml']) {
	$xml = htmlspecialchars($_POST['xml']);
}

if (empty($xml)) {
	$xml = "not_defined";
	print_info_box_np(sprintf(gettext("ERROR:  Could not open %s."), $xml));
	die;
} else {
	$wizard_xml_prefix = "{$g['www_path']}/wizards";
	$wizard_full_path = "{$wizard_xml_prefix}/{$xml}";
	if (substr_compare(realpath($wizard_full_path), $wizard_xml_prefix, 0, strlen($wizard_xml_prefix))) {
		print_info_box_np(gettext("ERROR: Invalid path specified."));
		die;
	}
	if (file_exists($wizard_full_path)) {
		$pkg = parse_xml_config_pkg($wizard_full_path, "pfsensewizard");
	} else {
		print_info_box_np(sprintf(gettext("ERROR:  Could not open %s."), $xml));
		die;
	}
}

if (!is_array($pkg)) {
	print_info_box_np(sprintf(gettext("ERROR: Could not parse %s/wizards/%s file."), $g['www_path'], $xml));
	die;
}

$title	   = preg_replace("/pfSense/i", $g['product_name'], $pkg['step'][$stepid]['title']);
$description = preg_replace("/pfSense/i", $g['product_name'], $pkg['step'][$stepid]['description']);
$totalsteps	 = $pkg['totalsteps'];

if ($pkg['includefile']) {
	require_once($pkg['includefile']);
}

if ($pkg['step'][$stepid]['includefile']) {
	require_once($pkg['step'][$stepid]['includefile']);
}

if ($pkg['step'][$stepid]['stepsubmitbeforesave']) {
	eval($pkg['step'][$stepid]['stepsubmitbeforesave']);
}

if ($_POST && !$input_errors) {
	foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
		if (!empty($field['bindstofield']) and $field['type'] != "submit") {
			$fieldname = $field['name'];
			$fieldname = str_replace(" ", "", $fieldname);
			$fieldname = strtolower($fieldname);
			// update field with posted values.
			if ($field['unsetfield'] != "") {
				$unset_fields = "yes";
			} else {
				$unset_fields = "";
			}

			if ($field['arraynum'] != "") {
				$arraynum = $field['arraynum'];
			} else {
				$arraynum = "";
			}

			update_config_field($field['bindstofield'], $_POST[$fieldname], $unset_fields, $arraynum, $field['type']);
		}

	}
	// run custom php code embedded in xml config.
	if ($pkg['step'][$stepid]['stepsubmitphpaction'] != "") {
		eval($pkg['step'][$stepid]['stepsubmitphpaction']);
	}
	if (!$input_errors) {
		write_config();
	}

	$stepid++;
	if ($stepid > $totalsteps) {
		$stepid = $totalsteps;
	}
}

function update_config_field($field, $updatetext, $unset, $arraynum, $field_type) {
	global $config;
	$field_split = explode("->", $field);
	foreach ($field_split as $f) {
		$field_conv .= "['" . $f . "']";
	}
	if ($field_conv == "") {
		return;
	}
	if ($arraynum != "") {
		$field_conv .= "[" . $arraynum . "]";
	}
	if (($field_type == "checkbox" and $updatetext != "on") || $updatetext == "") {
		/*
		 * item is a checkbox, it should have the value "on"
		 * if it was checked
		 */
		$var = "\$config{$field_conv}";
		$text = "if (isset({$var})) unset({$var});";
		eval($text);
		return;
	}

	if ($field_type == "interfaces_selection") {
		$var = "\$config{$field_conv}";
		$text = "if (isset({$var})) unset({$var});";
		$text .= "\$config" . $field_conv . " = \"" . $updatetext . "\";";
		eval($text);
		return;
	}

	if ($unset == "yes") {
		$text = "unset(\$config" . $field_conv . ");";
		eval($text);
	}
	$text = "\$config" . $field_conv . " = \"" . addslashes($updatetext) . "\";";
	eval($text);
}

$title	   = preg_replace("/pfSense/i", $g['product_name'], $pkg['step'][$stepid]['title']);
$description = preg_replace("/pfSense/i", $g['product_name'], $pkg['step'][$stepid]['description']);

// handle before form display event.
do {
	$oldstepid = $stepid;
	if ($pkg['step'][$stepid]['stepbeforeformdisplay'] != "") {
		eval($pkg['step'][$stepid]['stepbeforeformdisplay']);
	}
} while ($oldstepid != $stepid);

$closehead = false;
$pgtitle = array($title);
$notitle = true;
include("head.inc");

if ($pkg['step'][$stepid]['fields']['field'] != "") { ?>
<script type="text/javascript">
//<![CDATA[


	function FieldValidate(userinput, regexp, message) {
		if (!userinput.match(regexp)) {
			alert(message);
		}
	}

	function enablechange() {

	<?php

		foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
			if (isset($field['enablefields']) or isset($field['checkenablefields'])) {
				print "\t" . 'if ( $("#" + "' . strtolower($field['name']) . '").prop("checked") ) {' . "\n";

				if (isset($field['enablefields'])) {
					$enablefields = explode(',', $field['enablefields']);
					foreach ($enablefields as $enablefield) {
						$enablefield = strtolower($enablefield);
						print "\t\t" . '$("#" + "' . $enablefield . '").prop("disabled", false);' . "\n";
					}
				}

				if (isset($field['checkenablefields'])) {
					$checkenablefields = explode(',', $field['checkenablefields']);
					foreach ($checkenablefields as $checkenablefield) {
						$checkenablefield = strtolower($checkenablefield);
						print "\t\t" . '$("#" + "' . $checkenablefield . '").prop("checked", true);' . "\n";
					}
				}

				print "\t" . '} else {' . "\n";
				if (isset($field['enablefields'])) {
					$enablefields = explode(',', $field['enablefields']);
					foreach ($enablefields as $enablefield) {
						$enablefield = strtolower($enablefield);
						print "\t\t" . '$("#" + "' . $enablefield . '").prop("disabled", true);' . "\n";

					}
				}

			if (isset($field['checkdisablefields'])) {
				$checkenablefields = explode(',', $field['checkdisablefields']);
				foreach ($checkenablefields as $checkenablefield) {
					$checkenablefield = strtolower($checkenablefield);
						print "\t\t" . '$("#" + "' . $checkenablefield . '").prop("checked", false);' . "\n";
					}
				}

				print "\t" . '}' . "\n";
			}
		}
	?>

	}

	function disablechange() {
	<?php
		foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
			if (isset($field['disablefields']) or isset($field['checkdisablefields'])) {

				print "\t" . 'if ( $("#" + "' . strtolower($field['name']) . '").prop("checked") ) {' . "\n";

				if (isset($field['disablefields'])) {
					$enablefields = explode(',', $field['disablefields']);
					foreach ($enablefields as $enablefield) {
						$enablefield = strtolower($enablefield);

						print "\t\t" . '$("#" + "' . $enablefield . '").prop("disabled", true);' . "\n";
					}
				}
				if (isset($field['checkdisablefields'])) {
					$checkenablefields = explode(',', $field['checkdisablefields']);
					foreach ($checkenablefields as $checkenablefield) {
						$checkenablefield = strtolower($checkenablefield);
						print "\t\t" . '$("#" + "' . $checkenablefield . '").prop("checked", true);' . "\n";
					}
				}
				print "\t" . '} else {' . "\n";
				if (isset($field['disablefields'])) {
					$enablefields = explode(',', $field['disablefields']);
					foreach ($enablefields as $enablefield) {
						$enablefield = strtolower($enablefield);
						print "\t\t" . '$("#" + "' . $enablefield . '").prop("disabled", false);' . "\n";
					}
				}
				if (isset($field['checkdisablefields'])) {
					$checkenablefields = explode(',', $field['checkdisablefields']);
					foreach ($checkenablefields as $checkenablefield) {
						$checkenablefield = strtolower($checkenablefield);
						print "\t\t" . '$("#" + "' . $checkenablefield . '").prop("checked", false);' . "\n";
					}
				}
				print "\t" . '}' . "\n";
			}
		}
	?>
	}

	function showchange() {
<?php
		foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
			if (isset($field['showfields'])) {
				print "\t" . 'if (document.iform.' . strtolower($field['name']) . '.checked == false) {' . "\n";
				if (isset($field['showfields'])) {
					$showfields = explode(',', $field['showfields']);
					foreach ($showfields as $showfield) {
						$showfield = strtolower($showfield);
						//print "\t\t" . 'document.iform.' . $showfield . ".display =\"none\";\n";
						print "\t\t jQuery('#". $showfield . "').hide();";
					}
				}
				print "\t" . '} else {' . "\n";
				if (isset($field['showfields'])) {
					$showfields = explode(',', $field['showfields']);
					foreach ($showfields as $showfield) {
						$showfield = strtolower($showfield);
						#print "\t\t" . 'document.iform.' . $showfield . ".display =\"\";\n";
						print "\t\t jQuery('#". $showfield . "').show();";
					}
				}
				print "\t" . '}' . "\n";
			}
		}
?>
	}

//]]>
</script>
<?php }

function fixup_string($string) {
	global $config, $g, $myurl, $title;
	$newstring = $string;
	// fixup #1: $myurl -> http[s]://ip_address:port/
	switch ($config['system']['webgui']['protocol']) {
		case "http":
			$proto = "http";
			break;
		case "https":
			$proto = "https";
			break;
		default:
			$proto = "http";
			break;
	}
	$port = $config['system']['webgui']['port'];
	if ($port != "") {
		if (($port == "443" and $proto != "https") or ($port == "80" and $proto != "http")) {
			$urlport = ":" . $port;
		} elseif ($port != "80" and $port != "443") {
			$urlport = ":" . $port;
		} else {
			$urlport = "";
		}
	}

	$http_host = $_SERVER['SERVER_NAME'];
	$urlhost = $http_host;
	// If finishing the setup wizard, check if accessing on a LAN or WAN address that changed
	if ($title == "Reload in progress") {
		if (is_ipaddr($urlhost)) {
			$host_if = find_ip_interface($urlhost);
			if ($host_if) {
				$host_if = convert_real_interface_to_friendly_interface_name($host_if);
				if ($host_if && is_ipaddr($config['interfaces'][$host_if]['ipaddr'])) {
					$urlhost = $config['interfaces'][$host_if]['ipaddr'];
				}
			}
		} else if ($urlhost == $config['system']['hostname']) {
			$urlhost = $config['wizardtemp']['system']['hostname'];
		} else if ($urlhost == $config['system']['hostname'] . '.' . $config['system']['domain']) {
			$urlhost = $config['wizardtemp']['system']['hostname'] . '.' . $config['wizardtemp']['system']['domain'];
		}
	}

	if ($urlhost != $http_host) {
		file_put_contents("{$g['tmp_path']}/setupwizard_lastreferrer", $proto . "://" . $http_host . $urlport . $_SERVER['REQUEST_URI']);
	}

	$myurl = $proto . "://" . $urlhost . $urlport . "/";

	if (strstr($newstring, "\$myurl")) {
		$newstring = str_replace("\$myurl", $myurl, $newstring);
	}
	// fixup #2: $wanip
	if (strstr($newstring, "\$wanip")) {
		$curwanip = get_interface_ip();
		$newstring = str_replace("\$wanip", $curwanip, $newstring);
	}
	// fixup #3: $lanip
	if (strstr($newstring, "\$lanip")) {
		$lanip = get_interface_ip("lan");
		$newstring = str_replace("\$lanip", $lanip, $newstring);
	}
	// fixup #4: fix'r'up here.
	return $newstring;
}

function is_timezone($elt) {
	return !preg_match("/\/$/", $elt);
}

if ($title == "Reload in progress") {
	$ip = fixup_string("\$myurl");
} else {
	$ip = "/";
}

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}
if ($_GET['message'] != "") {
	print_info_box(htmlspecialchars($_GET['message']));
}
if ($_POST['message'] != "") {
	print_info_box(htmlspecialchars($_POST['message']));
}

$completion = ($stepid == 0) ? 0:($stepid * 100) / ($totalsteps -1);
?>

<!-- Present the pfSense logo -->
<div style="text-align:center"><p><a href="<?=$ip?>"><img border="0" src="logo-black.png" alt="logo-black" align="middle" height="45" width="180" /></a></p></div><br /><br/>

<!-- Draw a progress bar to show step progress -->
<div class="progress">
	<div class="progress-bar" role="progressbar" aria-valuenow="<?=$completion?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$completion?>%">
	</div>
</div>

<?php

$form = new Form(false);

$form->addGlobal(new Form_Input(
	'stepid',
	null,
	'hidden',
	$stepid
));

$form->addGlobal(new Form_Input(
	'xml',
	null,
	'hidden',
	$xml
));

$section = new Form_Section(fixup_string($title));

if($description) {
	$section->addInput(new Form_StaticText(
		null,
		fixup_string($description)
	));
}

$inputaliases = array();
if ($pkg['step'][$stepid]['fields']['field'] != "") {
	foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {

		$value = $field['value'];
		$name  = $field['name'];

		$name = preg_replace("/\s+/", "", $name);
		$name = strtolower($name);

		if ($field['bindstofield'] != "") {
			$arraynum = "";
			$field_conv = "";
			$field_split = explode("->", $field['bindstofield']);
			// arraynum is used in cases where there is an array of the same field
			// name such as dnsserver (2 of them)
			if ($field['arraynum'] != "") {
				$arraynum = "[" . $field['arraynum'] . "]";
			}

			foreach ($field_split as $f) {
				$field_conv .= "['" . $f . "']";
			}

			if ($field['type'] == "checkbox") {
				$toeval = "if (isset(\$config" . $field_conv . $arraynum . ")) { \$value = \$config" . $field_conv . $arraynum . "; if (empty(\$value)) \$value = true; }";
			} else {
				$toeval = "if (isset(\$config" . $field_conv . $arraynum . ")) \$value = \$config" . $field_conv . $arraynum . ";";
			}

			eval($toeval);
		}


		if(DEBUG) {
			print('Step: ' . $pkg['step'][$stepid]['id'] . ', Field: ' . $field['type'] . ', Name: ' . $name . '<br />');
		}

		switch ($field['type']) {
			case "input":
				if ($field['displayname']) {
					$etitle = $field['displayname'];

				} else if (!$field['dontdisplayname']) {
					$etitle =  fixup_string($field['name']);
				}

				$section->addInput(new Form_Input(
					$name,
					$etitle,
					'text',
					$value
				))->setHelp($field['description'])
				  ->setOnchange(($field['validate']) ? "FieldValidate(this.value, " . $field['validate'] . ", " . $field['message'] . ")":"");

				break;
			case "text":
				$section->addInput(new Form_StaticText(
					null,
					$field['description']
				));

				break;
			case "inputalias":
				if ($field['displayname']) {
					$etitle = $field['displayname'];

				} else if (!$field['dontdisplayname']) {
					$etitle =  fixup_string($field['name']);
				}

				$onchange = "";

				if ($field['validate']) {
					$onchange="FieldValidate(this.value, " . $field['validate'] . ", " . $field['message'] . ")";
				}

				$section->addInput(new Form_Input(
					$name,
					$etitle,
					'text',
					$value
				))->setAttribute('autocomplete', 'off')
				  ->setOnchange($onchange)
				  ->setHelp($field['description']);

				break;
			case "interfaces_selection":
			case "interface_select":

				$name = strtolower($name);
				$options = array();
				$selected = array();

				$etitle = (fixup_string($field['displayname'])) ? $field['displayname'] : $field['name'];

				if (($field['multiple'] != "") && ($field['multiple'] != "0"))
					$multiple = true;
				else
					$multiple = false;

				if ($field['add_to_interfaces_selection'] != "") {
					if ($field['add_to_interfaces_selection'] == $value) {
						array_push($selected, $value);
					}

					$options[$field['add_to_interfaces_selection']] = $field['add_to_interfaces_selection'];
				}

				if ($field['type'] == "interface_select") {
					$interfaces = get_interface_list();
				} else {
					$interfaces = get_configured_interface_with_descr();
				}

				foreach ($interfaces as $ifname => $iface) {
					if ($field['type'] == "interface_select") {
						$iface = $ifname;
						if ($iface['mac']) {
							$iface .= " ({$iface['mac']})";
						}
					}

					if ($value == $ifname)
						array_push($selected, $value);

					$canecho = 0;
					if ($field['interface_filter'] != "") {
						if (stristr($ifname, $field['interface_filter']) == true) {
							$canecho = 1;
						}
					} else {
						$canecho = 1;
					}

					if ($canecho == 1) {
						$options[$ifname] = $iface;
					}
				}

				$section->addInput(new Form_Select(
					$name,
					$etitle,
					($multiple) ? $selected:$selected[0],
					$options,
					$multiple
				))->setHelp($field['description']);

				break;
			case "password":
				if ($field['displayname']) {
					$etitle = $field['displayname'];
				} else if (!$field['dontdisplayname']) {
					$etitle =  fixup_string($field['name']);
				}

				$section->addInput(new Form_Input(
					$name,
					$etitle,
					'password',
					$value
				))->setHelp($field['description'])
				  ->setOnchange(($field['validate']) ? "FieldValidate(this.value, " . $field['validate'] . ", " . $field['message'] .")":"");

				break;
			case "certca_selection":
				$options = array();
				$selected = "";

				$name = strtolower($name);

				$etitle = (fixup_string($field['displayname']) ? $field['displayname'] : $field['name']);

				if ($field['add_to_certca_selection'] != "") {
					if ($field['add_to_certca_selection'] == $value) {
						$selected = $value;
					}

					$options[$field['add_to_certca_selection']] = $field['add_to_certca_selection'];
				}

				foreach ($config['ca'] as $ca) {
					$caname = htmlspecialchars($ca['descr']);

					if ($value == $caname)
						$selected = $value;

					$canecho = 0;
					if ($field['certca_filter'] != "") {
						if (stristr($caname, $field['certca_filter']) == true) {
							$canecho = 1;
						}
					} else {
						$canecho = 1;
					}
					if ($canecho == 1) {
						$options[$ca['refid']] = $caname;
					}
				}

				$section->addInput(new Form_Select(
					$name,
					$etitle,
					$selected,
					$options
				))->setHelp($field['description']);

				break;
			case "cert_selection":
				$options = array();
				$selected = array();

				$multiple = false;
				$name = strtolower($name);

				$etitle = (fixup_string($field['displayname']) ? $field['displayname'] : $field['name']);

				if ($field['add_to_cert_selection'] != "") {
					if ($field['add_to_cert_selection'] == $value) {
						array_push($selected, $value);
					}

					$options[$field['add_to_cert_selection']] = $field['add_to_cert_selection'];
				}

				foreach ($config['cert'] as $ca) {
					if (stristr($ca['descr'], "webconf")) {
						continue;
					}

					$caname = htmlspecialchars($ca['descr']);

					if ($value == $caname) {
						array_push($selected, $value);
					}


					$canecho = 0;
					if ($field['cert_filter'] != "") {
						if (stristr($caname, $field['cert_filter']) == true) {
							$canecho = 1;
						}
					} else {
						$canecho = 1;
					}

					if ($canecho == 1) {
						$options[$ca['refid']] = $caname;
					}
				}

				$section->addInput(new Form_Select(
					$name,
					$etitle,
					($multiple) ? $selected:$selected[0],
					$options,
					$multiple
				))->setHelp($field['description']);

				break;
			case "select":
				if ($field['displayname']) {
					$etitle = $field['displayname'];
				} else if (!$field['dontdisplayname']) {
					$etitle =  fixup_string($field['name']);
				}

				if ($field['size']) {
					$size = " size='" . $field['size'] . "' ";
				}

				$multiple = ($field['multiple'] == "yes");

				$onchange = "";
				foreach ($field['options']['option'] as $opt) {
					if ($opt['enablefields'] != "") {
						$onchange = "Javascript:enableitems(this.selectedIndex);";
					}
				}

				$options = array();
				$selected = array();

				foreach ($field['options']['option'] as $opt) {
					if ($value == $opt['value']) {
						array_push($selected, $value);
					}

					if ($opt['displayname']) {
						$options[$opt['value']] = $opt['displayname'];
					} else {
						$options[$opt['value']] = $opt['name'];
					}

				}

				$section->addInput(new Form_Select(
					$name,
					$etitle,
					($multiple) ? $selected:$selected[0],
					$options,
					$multiple
				))->setHelp($field['description'])->setOnchange($onchange);

				break;
			case "textarea":
				if ($field['displayname']) {
					$etitle = $field['displayname'];
				} else if (!$field['dontdisplayname']) {
					$etitle =  fixup_string($field['name']);
				}

				$section->addInput(new Form_Textarea(
					$name,
					$etitle,
					$value
				))->setHelp($field['description'])
				  ->setAttribute('rows', $field['rows'])
				  ->setOnchange(($field['validate']) ? "FieldValidate(this.value, " . $field['validate'] . ", " . $field['message'] . ")":"");

				break;
			case "submit":
				$form->addGlobal(new Form_Button(
					$name,
					$field['name']
				));

				break;
			case "listtopic":
				$form->add($section);
				$section = new Form_Section($field['name']);

				break;
			case "subnet_select":
				if ($field['displayname']) {
					$etitle = $field['displayname'];
				} else /* if (!$field['dontdisplayname']) */ {
					$etitle =  fixup_string($field['name']);
				}

				$section->addInput(new Form_Select(
					$name,
					$etitle,
					$value,
					array_combine(range(32, 1, -1), range(32, 1, -1))
				))->setHelp($field['description']);

				break;
			case "timezone_select":
				$timezonelist = system_get_timezone_list();

				/* kill carriage returns */
				for ($x = 0; $x < count($timezonelist); $x++) {
					$timezonelist[$x] = str_replace("\n", "", $timezonelist[$x]);
				}

				if ($field['displayname']) {
					$etitle = $field['displayname'];
				} else if (!$field['dontdisplayname']) {
					$etitle =  fixup_string($field['name']);
				}

				if (!$field['dontcombinecells']) {
					//echo "<td class=\"vtable\">";
				}

				$section->addInput(new Form_Select(
					$name,
					$etitle,
					($value == "") ? $g['default_timezone'] : $value,
					array_combine($timezonelist, $timezonelist)
				))->setHelp($field['description']);

				break;
			case "checkbox":
				if ($field['displayname']) {
					$etitle = $field['displayname'];

				} else if (!$field['dontdisplayname']) {
					$etitle =  fixup_string($field['name']);
				}

				if (isset($field['enablefields']) or isset($field['checkenablefields'])) {
					$onclick = "enablechange()";
				} else if (isset($field['disablefields']) or isset($field['checkdisablefields'])) {
					$onclick = "disablechange()";
				}

				$section->addInput(new Form_Checkbox(
					$name,
					$etitle,
					$field['typehint'],
					($value != ""),
					'on'
				))->setHelp($field['description'])
				  ->setOnclick($onclick);

				break;
		} // e-o-switch
	} // e-o-foreach(package)
} // e-o- if(we have fields)

$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[

		if (typeof ext_change != 'undefined') {
			ext_change();
		}
		if (typeof proto_change != 'undefined') {
			ext_change();
		}
		if (typeof proto_change != 'undefined') {
			proto_change();
		}

	<?php
		$isfirst = 0;
		$aliases = "";
		$addrisfirst = 0;
		$aliasesaddr = "";
		if ($config['aliases']['alias'] != "" and is_array($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias_name) {
				if ($isfirst == 1) {
					$aliases .= ",";
				}
				$aliases .= "'" . $alias_name['name'] . "'";
				$isfirst = 1;
			}
		}
	?>

		var customarray=new Array(<?=$aliases; ?>);

		window.onload = function () {

<?php
		$counter = 0;
		foreach ($inputaliases as $alias) {
?>
			$('#' + '<?php echo $alias; ?>').autocomplete({
				source: customarray
			});
<?php
		}
?>
	}

//]]>
</script>

<?php

$fieldnames_array = Array();
if ($pkg['step'][$stepid]['disableallfieldsbydefault'] != "") {
	// create a fieldname loop that can be used with javascript
	// hide and enable features.
	echo "\n<script type=\"text/javascript\">\n";
	echo "//<![CDATA[\n";
	echo "function disableall() {\n";
	foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
		if ($field['type'] != "submit" and $field['type'] != "listtopic") {
			if (!$field['donotdisable'] != "") {
				array_push($fieldnames_array, $field['name']);
				$fieldname = preg_replace("/\s+/", "", $field['name']);
				$fieldname = strtolower($fieldname);
				echo "\tdocument.forms[0]." . $fieldname . ".disabled = 1;\n";
			}
		}
	}
	echo "}\ndisableall();\n";
	echo "function enableitems(selectedindex) {\n";
	echo "disableall();\n";
	$idcounter = 0;
	if ($pkg['step'][$stepid]['fields']['field'] != "") {
		echo "\tswitch (selectedindex) {\n";
		foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
			if ($field['options']['option'] != "") {
				foreach ($field['options']['option'] as $opt) {
					if ($opt['enablefields'] != "") {
						echo "\t\tcase " . $idcounter . ":\n";
						$enablefields_split = explode(",", $opt['enablefields']);
						foreach ($enablefields_split as $efs) {
							$fieldname = preg_replace("/\s+/", "", $efs);
							$fieldname = strtolower($fieldname);
							if ($fieldname != "") {
								$onchange = "\t\t\tdocument.forms[0]." . $fieldname . ".disabled = 0; \n";
								echo $onchange;
							}
						}
						echo "\t\t\tbreak;\n";
					}
					$idcounter = $idcounter + 1;
				}
			}
		}
		echo "\t}\n";
	}
	echo "}\n";
	echo "//]]>\n";
	echo "</script>\n\n";
}
?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	enablechange();
	disablechange();
	showchange();
});
//]]>
</script>

<?php
if ($pkg['step'][$stepid]['stepafterformdisplay'] != "") {
	// handle after form display event.
	eval($pkg['step'][$stepid]['stepafterformdisplay']);
}

if ($pkg['step'][$stepid]['javascriptafterformdisplay'] != "") {
	// handle after form display event.
	echo "\n<script type=\"text/javascript\">\n";
	echo "//<![CDATA[\n";
	echo $pkg['step'][$stepid]['javascriptafterformdisplay'] . "\n";
	echo "//]]>\n";
	echo "</script>\n\n";
}

include("foot.inc");
