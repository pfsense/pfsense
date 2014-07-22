<?php
/* $Id$ */
/*
	wizard.php
	Copyright (C) 2004 Scott Ullrich
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
##|*IDENT=page-pfsensewizardsubsystem
##|*NAME=pfSense wizard subsystem page
##|*DESCR=Allow access to the 'pfSense wizard subsystem' page.
##|*MATCH=wizard.php*
##|-PRIV


require("globals.inc");
require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require_once("rrd.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

global $g;

$stepid = htmlspecialchars($_GET['stepid']);
if (isset($_POST['stepid']))
	$stepid = htmlspecialchars($_POST['stepid']);
if (!$stepid)
	$stepid = "0";

$xml = htmlspecialchars($_GET['xml']);
if($_POST['xml'])
	$xml = htmlspecialchars($_POST['xml']);

if(empty($xml)) {
	$xml = "not_defined";
	print_info_box_np(sprintf(gettext("ERROR:  Could not open %s."), $xml));
	die;
} else {
	if (file_exists("{$g['www_path']}/wizards/{$xml}"))
		$pkg = parse_xml_config_pkg("{$g['www_path']}/wizards/" . $xml, "pfsensewizard");
	else {
		print_info_box_np(sprintf(gettext("ERROR:  Could not open %s."), $xml));
		die;
	}
}

if (!is_array($pkg)) {
	print_info_box_np(sprintf(gettext("ERROR: Could not parse %s/wizards/%s file."),$g['www_path'],$xml));
	die;
}

$title       = preg_replace("/pfSense/i", $g['product_name'], $pkg['step'][$stepid]['title']);
$description = preg_replace("/pfSense/i", $g['product_name'], $pkg['step'][$stepid]['description']);
$totalsteps  = $pkg['totalsteps'];

if ($pkg['includefile'])
	require_once($pkg['includefile']);

if ($pkg['step'][$stepid]['includefile'])
	require_once($pkg['step'][$stepid]['includefile']);

if($pkg['step'][$stepid]['stepsubmitbeforesave']) {
	eval($pkg['step'][$stepid]['stepsubmitbeforesave']);
}

if ($_POST && !$input_errors) {
	foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
		if(!empty($field['bindstofield']) and $field['type'] <> "submit") {
			$fieldname = $field['name'];
			$fieldname = str_replace(" ", "", $fieldname);
			$fieldname = strtolower($fieldname);
			// update field with posted values.
			if($field['unsetfield'] <> "")
				$unset_fields = "yes";
			else
				$unset_fields = "";
			if($field['arraynum'] <> "")
				$arraynum = $field['arraynum'];
			else
				$arraynum = "";

			update_config_field( $field['bindstofield'], $_POST[$fieldname], $unset_fields, $arraynum, $field['type']);
		}

	}
	// run custom php code embedded in xml config.
	if($pkg['step'][$stepid]['stepsubmitphpaction'] <> "") {
		eval($pkg['step'][$stepid]['stepsubmitphpaction']);
	}
	if (!$input_errors)
		write_config();
	$stepid++;
	if($stepid > $totalsteps)
		$stepid = $totalsteps;
}

function update_config_field($field, $updatetext, $unset, $arraynum, $field_type) {
	global $config;
	$field_split = explode("->",$field);
	foreach ($field_split as $f)
		$field_conv .= "['" . $f . "']";
	if($field_conv == "")
		return;
	if ($arraynum <> "")
		$field_conv .= "[" . $arraynum . "]";
	if(($field_type == "checkbox" and $updatetext <> "on") || $updatetext == "") {
		/*
		 * item is a checkbox, it should have the value "on"
		 * if it was checked
		 */
		$var = "\$config{$field_conv}";
		$text = "if (isset({$var})) unset({$var});";
		eval($text);
		return;
	}

	if($field_type == "interfaces_selection") {
		$var = "\$config{$field_conv}";
		$text = "if (isset({$var})) unset({$var});";
		$text .= "\$config" . $field_conv . " = \"" . $updatetext . "\";";
		eval($text);
		return;
	}

	if($unset == "yes") {
		$text = "unset(\$config" . $field_conv . ");";
		eval($text);
	}
	$text = "\$config" . $field_conv . " = \"" . addslashes($updatetext) . "\";";
	eval($text);
}

$title       = preg_replace("/pfSense/i", $g['product_name'], $pkg['step'][$stepid]['title']);
$description = preg_replace("/pfSense/i", $g['product_name'], $pkg['step'][$stepid]['description']);

// handle before form display event.
do {
	$oldstepid = $stepid;
	if($pkg['step'][$stepid]['stepbeforeformdisplay'] <> "")
		eval($pkg['step'][$stepid]['stepbeforeformdisplay']);
} while ($oldstepid != $stepid);

$closehead = false;
$pgtitle = array($title);
include("head.inc");

if(file_exists("/usr/local/www/themes/{$g['theme']}/wizard.css"))
	echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"/themes/{$g['theme']}/wizard.css\" media=\"all\" />\n";
else
	echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"/themes/{$g['theme']}/all.css\" media=\"all\" />";
?>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php if($pkg['step'][$stepid]['fields']['field'] <> "") { ?>
<script type="text/javascript">
//<![CDATA[

function  FieldValidate(userinput,  regexp,  message)
{
	if(!userinput.match(regexp))
		alert(message);
}

function enablechange() {
<?php
	foreach($pkg['step'][$stepid]['fields']['field'] as $field) {
		if(isset($field['enablefields']) or isset($field['checkenablefields'])) {
			print "\t" . 'if (document.iform.' . strtolower($field['name']) . '.checked) {' . "\n";
			if(isset($field['enablefields'])) {
				$enablefields = explode(',', $field['enablefields']);
				foreach($enablefields as $enablefield) {
					$enablefield = strtolower($enablefield);
					print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 0;' . "\n";
				}
			}
			if(isset($field['checkenablefields'])) {
				$checkenablefields = explode(',', $field['checkenablefields']);
				foreach($checkenablefields as $checkenablefield) {
					$checkenablefield = strtolower($checkenablefield);
					print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 0;' . "\n";
				}
			}
			print "\t" . '} else {' . "\n";
			if(isset($field['enablefields'])) {
				$enablefields = explode(',', $field['enablefields']);
				foreach($enablefields as $enablefield) {
					$enablefield = strtolower($enablefield);
					print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 1;' . "\n";
				}
			}
			if(isset($field['checkenablefields'])) {
				$checkenablefields = explode(',', $field['checkenablefields']);
				foreach($checkenablefields as $checkenablefield) {
					$checkenablefield = strtolower($checkenablefield);
					print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 1;' . "\n";
				}
			}
			print "\t" . '}' . "\n";
		}
	}
?>
}

function disablechange() {
<?php
	foreach($pkg['step'][$stepid]['fields']['field'] as $field) {
		if(isset($field['disablefields']) or isset($field['checkdisablefields'])) {
			print "\t" . 'if (document.iform.' . strtolower($field['name']) . '.checked) {' . "\n";
			if(isset($field['disablefields'])) {
				$enablefields = explode(',', $field['disablefields']);
				foreach($enablefields as $enablefield) {
					$enablefield = strtolower($enablefield);
					print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 1;' . "\n";
				}
			}
			if(isset($field['checkdisablefields'])) {
				$checkenablefields = explode(',', $field['checkdisablefields']);
				foreach($checkenablefields as $checkenablefield) {
					$checkenablefield = strtolower($checkenablefield);
					print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 1;' . "\n";
				}
			}
			print "\t" . '} else {' . "\n";
			if(isset($field['disablefields'])) {
				$enablefields = explode(',', $field['disablefields']);
				foreach($enablefields as $enablefield) {
					$enablefield = strtolower($enablefield);
					print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 0;' . "\n";
				}
			}
			if(isset($field['checkdisablefields'])) {
				$checkenablefields = explode(',', $field['checkdisablefields']);
				foreach($checkenablefields as $checkenablefield) {
					$checkenablefield = strtolower($checkenablefield);
					print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 0;' . "\n";
				}
			}
			print "\t" . '}' . "\n";
		}
	}
?>
}

function showchange() {
<?php
	foreach($pkg['step'][$stepid]['fields']['field'] as $field) {
		if(isset($field['showfields'])) {
			print "\t" . 'if (document.iform.' . strtolower($field['name']) . '.checked == false) {' . "\n";
			if(isset($field['showfields'])) {
				$showfields = explode(',', $field['showfields']);
				foreach($showfields as $showfield) {
					$showfield = strtolower($showfield);
					//print "\t\t" . 'document.iform.' . $showfield . ".display =\"none\";\n";
					print "\t\t jQuery('#". $showfield . "').hide();";
				}
			}
			print "\t" . '} else {' . "\n";
			if(isset($field['showfields'])) {
				$showfields = explode(',', $field['showfields']);
				foreach($showfields as $showfield) {
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
<?php } ?>

<form action="wizard.php" method="post" name="iform" id="iform">
<input type="hidden" name="xml" value="<?= htmlspecialchars($xml) ?>" />
<input type="hidden" name="stepid" value="<?= htmlspecialchars($stepid) ?>" />

<center>

&nbsp;<br />

<?php
	if($title == "Reload in progress") {
		$ip = fixup_string("\$myurl");
	} else {
		$ip = "/";
	}
	echo "<a href='$ip'>";
?>
<img border="0" src="./themes/<?= $g['theme']; ?>/images/logo.gif" alt="logo" /></a>
<p>&nbsp;</p>
<div style="width:800px;background-color:#ffffff" id="roundme">
<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
	if ($_GET['message'] != "")
		print_info_box(htmlspecialchars($_GET['message']));
	if ($_POST['message'] != "")
		print_info_box(htmlspecialchars($_POST['message']));
?>
<table bgcolor="#ffffff" width="95%" border="0" cellspacing="0" cellpadding="2" summary="wizard">
	<!-- wizard goes here -->
	<tr><td>&nbsp;</td></tr>
	<tr>
		<td class="tabcont">
			<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">

				<tr><td colspan="2" align="center"><font size="2"><b><?= fixup_string($description) ?></b></font></td></tr><tr><td>&nbsp;</td></tr>
<?php
	if(!$pkg['step'][$stepid]['disableheader'])
		echo "<tr><td colspan=\"2\" class=\"listtopic\">" . fixup_string($title) . "</td></tr>";
?>

<?php
	$inputaliases = array();
	if($pkg['step'][$stepid]['fields']['field'] <> "") {
		foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {

			$value = $field['value'];
			$name  = $field['name'];

			$name = preg_replace("/\s+/", "", $name);
			$name = strtolower($name);

			if($field['bindstofield'] <> "") {
				$arraynum = "";
				$field_conv = "";
				$field_split = explode("->", $field['bindstofield']);
				// arraynum is used in cases where there is an array of the same field
				// name such as dnsserver (2 of them)
				if($field['arraynum'] <> "")
					$arraynum = "[" . $field['arraynum'] . "]";
				foreach ($field_split as $f)
					$field_conv .= "['" . $f . "']";
				if($field['type'] == "checkbox")
					$toeval = "if (isset(\$config" . $field_conv . $arraynum . ")) { \$value = \$config" . $field_conv . $arraynum . "; if (empty(\$value)) \$value = true; }";
				else
					$toeval = "if (isset(\$config" . $field_conv . $arraynum . ")) \$value = \$config" . $field_conv . $arraynum . ";";
				eval($toeval);
			}

			if(!$field['combinefieldsend'])
				echo "<tr>";

			switch ($field['type']) {
			case "input":
				if ($field['displayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['displayname'];
					echo ":</td>\n";
				} else if(!$field['dontdisplayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo fixup_string($field['name']);
					echo ":</td>\n";
				}
				if(!$field['dontcombinecells'])
					echo "<td class=\"vtable\">\n";

				echo "<input class='formfld unknown' id='" . $name . "' name='" . $name . "' value=\"" . htmlspecialchars($value) . "\"";
				if($field['size'])
					echo " size='" . $field['size'] . "' ";
				if($field['validate'])
					echo " onchange='FieldValidate(this.value, \"{$field['validate']}\", \"{$field['message']}\");'";
				echo " />\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}
				break;
			case "text":
				echo "<td colspan=\"2\" align=\"center\" class=\"vncell\">\n";
				if($field['description'] <> "") {
					echo "<center><br /> " . $field['description'] . "</center>";
				}
				break;
			case "inputalias":
				if ($field['displayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['displayname'];
					echo ":</td>\n";
				} else if(!$field['dontdisplayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo fixup_string($field['name']);
					echo ":</td>\n";
				}
				if(!$field['dontcombinecells'])
					echo "<td class=\"vtable\">\n";

				$inputaliases[] = $name;
				echo "<input class='formfldalias' autocomplete='off' id='" . $name . "' name='" . $name . "' value=\"" . htmlspecialchars($value) . "\"";
				if($field['size'])
					echo " size='" . $field['size'] . "' ";
				if($field['validate'])
					echo " onchange='FieldValidate(this.value, \"{$field['validate']}\", \"{$field['message']}\");'";
				echo " />\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}
				break;
			case "interfaces_selection":
			case "interface_select":
				$size = "";
				$multiple = "";
				$name = strtolower($name);
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['displayname'] ? $field['displayname'] : $field['name']) . ":\n";
				echo "</td>";
				echo "<td class=\"vtable\">\n";
				if($field['size'] <> "") $size = "size=\"{$field['size']}\"";
				if($field['multiple'] <> "" and $field['multiple'] <> "0") {
					$multiple = "multiple=\"multiple\"";
					$name .= "[]";
				}
				echo "<select class='formselect' id='{$name}' name='{$name}' {$size} {$multiple}>\n";
				if($field['add_to_interfaces_selection'] <> "") {
					$SELECTED = "";
					if($field['add_to_interfaces_selection'] == $value) $SELECTED = " selected=\"selected\"";
					echo "<option value='" . $field['add_to_interfaces_selection'] . "'" . $SELECTED . ">" . $field['add_to_interfaces_selection'] . "</option>\n";
				}
				if($field['type'] == "interface_select")
					$interfaces = get_interface_list();
				else
					$interfaces = get_configured_interface_with_descr();
				foreach ($interfaces as $ifname => $iface) {
					if ($field['type'] == "interface_select") {
						$iface = $ifname;
						if ($iface['mac'])
							$iface .= " ({$iface['mac']})";
					}
					$SELECTED = "";
					if ($value == $ifname) $SELECTED = " selected=\"selected\"";
					$to_echo = "<option value='" . $ifname . "'" . $SELECTED . ">" . $iface . "</option>\n";
					$to_echo .= "<!-- {$value} -->";
					$canecho = 0;
					if($field['interface_filter'] <> "") {
						if(stristr($ifname, $field['interface_filter']) == true)
							$canecho = 1;
					} else
						$canecho = 1;
					if($canecho == 1)
						echo $to_echo;
				}
				echo "</select>\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}

				break;
			case "password":
				if ($field['displayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['displayname'];
					echo ":</td>\n";
				} else if(!$field['dontdisplayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo fixup_string($field['name']);
					echo ":</td>\n";
				}
				if(!$field['dontcombinecells'])
					echo "<td class=\"vtable\">";
				echo "<input class='formfld pwd' id='" . $name . "' name='" . $name . "' value=\"" . htmlspecialchars($value) . "\" type='password' ";
				if($field['size'])
					echo " size='" . $field['size'] . "' ";
				echo " />\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}

				break;
			case "certca_selection":
				$size = "";
				$multiple = "";
				$name = strtolower($name);
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['displayname'] ? $field['displayname'] : $field['name']) . ":\n";
				echo "</td>";
				echo "<td class=\"vtable\">\n";
				if($field['size'] <> "") $size = "size=\"{$field['size']}\"";
				echo "<select id='{$name}' name='{$name}' {$size}>\n";
				if($field['add_to_certca_selection'] <> "") {
					$SELECTED = "";
					if($field['add_to_certca_selection'] == $value) $SELECTED = " selected=\"selected\"";
					echo "<option value='" . $field['add_to_certca_selection'] . "'" . $SELECTED . ">" . $field['add_to_certca_selection'] . "</option>\n";
				}
				foreach($config['ca'] as $ca) {
					$name = htmlspecialchars($ca['descr']);
					$SELECTED = "";
					if ($value == $name) $SELECTED = " selected=\"selected\"";
					$to_echo = "<option value='" . $ca['refid'] . "'" . $SELECTED . ">" . $name . "</option>\n";
					$to_echo .= "<!-- {$value} -->";
					$canecho = 0;
					if($field['certca_filter'] <> "") {
						if(stristr($name, $field['certca_filter']) == true)
							$canecho = 1;
					} else {
						$canecho = 1;
					}
					if($canecho == 1)
						echo $to_echo;
				}
				echo "</select>\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}

				break;
			case "cert_selection":
				$size = "";
				$multiple = "";
				$name = strtolower($name);
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['displayname'] ? $field['displayname'] : $field['name']) . ":\n";
				echo "</td>";
				echo "<td class=\"vtable\">\n";
				if($field['size'] <> "") $size = "size=\"{$field['size']}\"";
				echo "<select id='{$name}' name='{$name}' {$size}>\n";
				if($field['add_to_cert_selection'] <> "") {
					$SELECTED = "";
					if($field['add_to_cert_selection'] == $value) $SELECTED = " selected=\"selected\"";
					echo "<option value='" . $field['add_to_cert_selection'] . "'" . $SELECTED . ">" . $field['add_to_cert_selection'] . "</option>\n";
				}
				foreach($config['cert'] as $ca) {
					if (stristr($ca['descr'], "webconf"))
						continue;
					$name = htmlspecialchars($ca['descr']);
					$SELECTED = "";
					if ($value == $name) $SELECTED = " selected=\"selected\"";
					$to_echo = "<option value='" . $ca['refid'] . "'" . $SELECTED . ">" . $name . "</option>\n";
					$to_echo .= "<!-- {$value} -->";
					$canecho = 0;
					if($field['cert_filter'] <> "") {
						if(stristr($name, $field['cert_filter']) == true)
							$canecho = 1;
					} else {
						$canecho = 1;
					}
					if($canecho == 1)
						echo $to_echo;
				}
				echo "</select>\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}

				break;
			case "select":
				if ($field['displayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['displayname'];
					echo ":</td>\n";
				} else if(!$field['dontdisplayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo fixup_string($field['name']);
					echo ":</td>\n";
				}
				if($field['size']) $size = " size='" . $field['size'] . "' ";
				if($field['multiple'] == "yes") $multiple = "multiple=\"multiple\" ";
				if(!$field['dontcombinecells'])
					echo "<td class=\"vtable\">\n";
				$onchange = "";
				foreach ($field['options']['option'] as $opt) {
					if($opt['enablefields'] <> "") {
						$onchange = "onchange=\"enableitems(this.selectedIndex);\" ";
					}
				}
				echo "<select class='formselect' " . $onchange . $multiple . $size . "id='" . $name . "' name='" . $name . "'>\n";
				foreach ($field['options']['option'] as $opt) {
					$selected = "";
					if($value == $opt['value'])
						$selected = " selected=\"selected\"";
					echo "\t<option value='" . $opt['value'] . "'" . $selected . ">";
					if ($opt['displayname'])
						echo $opt['displayname'];
					else
						echo $opt['name'];
					echo "</option>\n";
				}
				echo "</select>\n";
				echo "<!-- {$value} -->\n";

				if($field['description'] <> "") {
					echo $field['description'];
				}

				break;
			case "textarea":
				if ($field['displayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['displayname'];
					echo ":</td>\n";
				} else if(!$field['dontdisplayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo fixup_string($field['name']);
					echo ":</td>";
				}
				if(!$field['dontcombinecells'])
					echo "<td class=\"vtable\">";
				echo "<textarea class='formpre' id='" . $name . "' name='" . $name . "'";
				if ($field['rows'])
					echo " rows='" . $field['rows'] . "' ";
				if ($field['cols'])
					echo " cols='" . $field['cols'] . "' ";
				echo ">" . $value . "</textarea>\n";


				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}

				break;
			case "submit":
				echo "<td>&nbsp;<br /></td></tr>";
				echo "<tr><td colspan=\"2\" align=\"center\">";
				echo "<input type='submit' name='" . $name . "' value=\"" . htmlspecialchars($field['name']) . "\" />\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}

				break;
			case "listtopic":
				echo "<td>&nbsp;</td></tr>";
				echo "<tr><td colspan=\"2\" class=\"listtopic\">" . $field['name'] . "<br />\n";

				break;
			case "subnet_select":
				if ($field['displayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['displayname'];
					echo ":</td>\n";
				} else if(!$field['dontdisplayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo fixup_string($field['name']);
					echo ":</td>";
				}
				if(!$field['dontcombinecells'])
					echo "<td class=\"vtable\">";
				echo "<select class='formselect' name='{$name}'>\n";
				for($x=1; $x<33; $x++) {
					$CHECKED = "";
					if($value == $x) $CHECKED = " selected=\"selected\"";
					if($x <> 31)
						echo "<option value='{$x}' {$CHECKED}>{$x}</option>\n";
				}
				echo "</select>\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}

				break;
			case "timezone_select":
				exec('/usr/bin/tar -tzf /usr/share/zoneinfo.tgz', $timezonelist);
				$timezonelist = array_filter($timezonelist, 'is_timezone');
				sort($timezonelist);

				/* kill carriage returns */
				for($x=0; $x<count($timezonelist); $x++)
					$timezonelist[$x] = str_replace("\n", "", $timezonelist[$x]);

				if ($field['displayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['displayname'];
					echo ":</td>\n";
				} else if(!$field['dontdisplayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo fixup_string($field['name']);
					echo ":</td>";
				}
				if(!$field['dontcombinecells'])
					echo "<td class=\"vtable\">";
				echo "<select class='formselect' name='{$name}'>\n";
				foreach ($timezonelist as $tz) {
					if(strstr($tz, "GMT"))
						continue;
					$SELECTED = "";
					if ($value == $tz) $SELECTED = " selected=\"selected\"";
					echo "<option value=\"" . htmlspecialchars($tz) . "\" {$SELECTED}>";
					echo htmlspecialchars($tz);
					echo "</option>\n";
				}
				echo "</select>\n";

				if($field['description'] <> "") {
					echo "<br /> " . $field['description'];
				}

				break;
			case "checkbox":
				if ($field['displayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['displayname'];
					echo ":</td>\n";
				} else if(!$field['dontdisplayname']) {
					echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
					echo $field['name'];
					echo ":</td>";
				}
				$checked = "";
				if($value <> "")
					$checked = " checked=\"checked\"";
				echo "<td class=\"vtable\"><input value=\"on\" type='checkbox' id='" . $name . "' name='" . $name . "' " . $checked;
				if(isset($field['enablefields']) or isset($field['checkenablefields']))
					echo " onclick=\"enablechange()\"";
				else if(isset($field['disablefields']) or isset($field['checkdisablefields']))
					echo " onclick=\"disablechange()\"";
				echo " />\n";

				if($field['description'] <> "") {
					echo $field['description'];
				}

				break;
			}

			if($field['typehint'] <> "") {
				echo $field['typehint'];
			}
			if($field['warning'] <> "") {
				echo "<br /><b><font color=\"red\">" . $field['warning'] . "</font></b>";
			}

			if(!$field['combinefieldsbegin']) {
				if (!$field['dontcombinecells'])
					echo "</td>";

				echo "</tr>\n";
			}

		}
	}
?>
			</table>
		</td>
	</tr>
</table>
<br />&nbsp;
</div>
</center>
</form>
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
	if($config['aliases']['alias'] <> "" and is_array($config['aliases']['alias']))
		foreach($config['aliases']['alias'] as $alias_name) {
				if($isfirst == 1)
					$aliases .= ",";
				$aliases .= "'" . $alias_name['name'] . "'";
				$isfirst = 1;
		}
?>

	var customarray=new Array(<?php echo $aliases; ?>);

	window.onload = function () {

		<?php
			$counter=0;
			foreach($inputaliases as $alias) {
				echo "var oTextbox$counter = new AutoSuggestControl(document.getElementById(\"$alias\"), new StateSuggestions(customarray));\n";
				$counter++;
			}
		?>

	}

//]]>
</script>

<?php

$fieldnames_array = Array();
if($pkg['step'][$stepid]['disableallfieldsbydefault'] <> "") {
	// create a fieldname loop that can be used with javascript
	// hide and enable features.
	echo "\n<script type=\"text/javascript\">\n";
	echo "//<![CDATA[\n";
	echo "function disableall() {\n";
	foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
		if($field['type'] <> "submit" and $field['type'] <> "listtopic") {
			if(!$field['donotdisable'] <> "") {
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
	if($pkg['step'][$stepid]['fields']['field'] <> "") {
		echo "\tswitch(selectedindex) {\n";
		foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
			if($field['options']['option'] <> "") {
				foreach ($field['options']['option'] as $opt) {
					if($opt['enablefields'] <> "") {
						echo "\t\tcase " . $idcounter . ":\n";
						$enablefields_split = explode(",", $opt['enablefields']);
						foreach ($enablefields_split as $efs) {
							$fieldname = preg_replace("/\s+/", "", $efs);
							$fieldname = strtolower($fieldname);
							if($fieldname <> "") {
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
NiftyCheck();
var bgcolor = document.getElementsByTagName("body")[0].style.backgroundColor;
Rounded("div#roundme","all",bgcolor,"#FFFFFF","smooth");
enablechange();
disablechange();
showchange();
//]]>
</script>

<?php
if($pkg['step'][$stepid]['stepafterformdisplay'] <> "") {
	// handle after form display event.
	eval($pkg['step'][$stepid]['stepafterformdisplay']);
}

if($pkg['step'][$stepid]['javascriptafterformdisplay'] <> "") {
	// handle after form display event.
	echo "\n<script type=\"text/javascript\">\n";
	echo "//<![CDATA[\n";
	echo $pkg['step'][$stepid]['javascriptafterformdisplay'] . "\n";
	echo "//]]>\n";
	echo "</script>\n\n";
}

/*
 *  HELPER FUNCTIONS
 */

function fixup_string($string) {
	global $config, $g, $myurl, $title;
	$newstring = $string;
	// fixup #1: $myurl -> http[s]://ip_address:port/
	switch($config['system']['webgui']['protocol']) {
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
	if($port != "") {
		if(($port == "443" and $proto != "https") or ($port == "80" and $proto != "http")) {
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
	if($title == "Reload in progress") {
		if (is_ipaddr($urlhost)) {
			$host_if = find_ip_interface($urlhost);
			if ($host_if) {
				$host_if = convert_real_interface_to_friendly_interface_name($host_if);
				if ($host_if && is_ipaddr($config['interfaces'][$host_if]['ipaddr']))
					$urlhost = $config['interfaces'][$host_if]['ipaddr'];
			}
		} else if ($urlhost == $config['system']['hostname'])
			$urlhost = $config['wizardtemp']['system']['hostname'];
		else if ($urlhost == $config['system']['hostname'] . '.' . $config['system']['domain'])
			$urlhost = $config['wizardtemp']['system']['hostname'] . '.' . $config['wizardtemp']['system']['domain'];
	}
	if($urlhost != $http_host)
		file_put_contents("{$g['tmp_path']}/setupwizard_lastreferrer", $proto . "://" . $http_host . $urlport . $_SERVER['REQUEST_URI']);
	$myurl = $proto . "://" . $urlhost . $urlport . "/";

	if (strstr($newstring, "\$myurl"))
		$newstring = str_replace("\$myurl", $myurl, $newstring);
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

?>

</body>
</html>
