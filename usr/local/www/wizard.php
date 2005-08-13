#!/usr/local/bin/php
<?php
/* $Id$ */
/*
    wizard.php
    Copyright (C) 2004 Scott Ullrich
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

require("guiconfig.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

$stepid = $_GET['stepid'];
if (isset($_POST['stepid']))
    $stepid = $_POST['stepid'];
if (!$stepid) $stepid = "0";

// XXX: Make this input safe.
$xml = $_GET['xml'];
if($_POST['xml']) $xml = $_POST['xml'];

if($xml == "") {
	$xml = "not_defined";
	print_info_box_np("ERROR:  Could not open " . $xml . ".");
	die;
} else {
	if (file_exists("{$g['www_path']}/wizards/{$xml}"))
		$pkg = parse_xml_config_pkg("{$g['www_path']}/wizards/" . $xml, "pfsensewizard");
	else {
		print_info_box_np("ERROR:  Could not open " . $xml . ".");
		die;
	}
}

$title          = $pkg['step'][$stepid]['title'];
$description    = $pkg['step'][$stepid]['description'];
$totalsteps     = $pkg['totalsteps'];

exec('/usr/bin/tar -tzf /usr/share/zoneinfo.tgz', $timezonelist);
$timezonelist = array_filter($timezonelist, 'is_timezone');
sort($timezonelist);

if($pkg['step'][$stepid]['stepsubmitbeforesave']) {
		eval($pkg['step'][$stepid]['stepsubmitbeforesave']);
}

if ($_POST) {
    foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
        if($field['bindstofield'] <> "" and $field['type'] <> "submit") {
		$fieldname = $field['name'];
		$unset_fields = "";
		$fieldname = ereg_replace(" ", "", $fieldname);
		$fieldname = strtolower($fieldname);
		// update field with posted values.
                if($field['unsetfield'] <> "") $unset_fields = "yes";
		if($field['arraynum'] <> "") $arraynum = $field['arraynum'];
		if($field['bindstofield'])
			update_config_field( $field['bindstofield'], $_POST[$fieldname], $unset_fields, $arraynum, $field['type']);
        }

    }
    // run custom php code embedded in xml config.
    if($pkg['step'][$stepid]['stepsubmitphpaction'] <> "") {
		eval($pkg['step'][$stepid]['stepsubmitphpaction']);
    }
	write_config();
    $stepid++;
    if($stepid > $totalsteps) $stepid = $totalsteps;
}

$title          = $pkg['step'][$stepid]['title'];
$description    = $pkg['step'][$stepid]['description'];

function update_config_field($field, $updatetext, $unset, $arraynum, $field_type) {
	global $config;
	$field_split = split("->",$field);
	foreach ($field_split as $f) $field_conv .= "['" . $f . "']";
	if($field_conv == "") return;
	if($field_type == "checkbox" and $updatetext <> "on") {
		/*
		    item is a checkbox, it should have the value "on"
		    if it was checked
                */
		$text = "unset(\$config" . $field_conv . ");";
		eval($text);
		return;
	}
	
	if($field_type == "interfaces_selection") {
		$text = "unset(\$config" . $field_conv . ");";
		eval($text);
		$text = "\$config" . $field_conv . " = \"" . $updatetext . "\";";
		eval($text);
		return;
	}
	
	if($unset <> "") {
		$text = "unset(\$config" . $field_conv . ");";
		eval($text);
		$text = "\$config" . $field_conv . "[" . $arraynum . "] = \"" . $updatetext . "\";";
		eval($text);
	} else {
		if($arraynum <> "") {
			$text = "\$config" . $field_conv . "[" . $arraynum . "] = \"" . $updatetext . "\";";
		} else {
			$text = "\$config" . $field_conv . " = \"" . $updatetext . "\";";
		}
		eval($text);
	}
}

if($pkg['step'][$stepid]['stepbeforeformdisplay'] <> "") {
	// handle before form display event.
        // good for modifying posted values, etc.
	eval($pkg['step'][$stepid]['stepbeforeformdisplay']);
}

$pgtitle = $title;
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onLoad="enablechange();">
<?php if($pkg['step'][$stepid]['fields']['field'] <> "") { ?>
<script language="JavaScript">
<!--
function enablechange() {
<?php
        foreach($pkg['step'][$stepid]['fields']['field'] as $field) {
                if(isset($field['enablefields']) or isset($field['checkenablefields'])) {
                        print "\t" . 'if (document.iform.' . strtolower($field['name']) . '.checked == false) {' . "\n";
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
                                        print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 0;' . "\n";
                                }
                        }
                        print "\t" . '} else {' . "\n";
                        if(isset($field['enablefields'])) {
                                foreach($enablefields as $enablefield) {
                                        $enablefield = strtolower($enablefield);
                                        print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 0;' . "\n";
                                }
                        }
                        if(isset($field['checkenablefields'])) {
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
//-->
</script>
<?php } ?>

<form action="wizard.php" method="post" name="iform" id="iform">
<input type="hidden" name="xml" value="<?= $xml ?>">
<input type="hidden" name="stepid" value="<?= $stepid ?>">
<?php if ($savemsg) print_info_box($savemsg); ?>

<center>

&nbsp;<br>
<?php
	if($title == "Reload in progress")
		$ip = "http://{$config['interfaces']['lan']['ipaddr']}";
	else
		$ip = "/";
?>

<a href="<?php echo $ip; ?>"><img border="0" src="./themes/<?= $g['theme']; ?>/images/logo.gif"></a>
<p>

<div style="width:700px;background-color:#ffffff" id="roundme">
<table bgcolor="#ffffff" width="600" cellspacing="0" cellpadding="3">
    <!-- wizard goes here -->
    <tr><td>&nbsp;</td></tr>
    <tr><td colspan='2'><center><b><?= fixup_string($description) ?></b></center></td></tr><tr><td>&nbsp;</td></tr>
    <?php
	if(!$pkg['step'][$stepid]['disableheader'])
		echo "<tr><td colspan=\"2\" class=\"listtopic\">" . fixup_string($title) . "</td></tr>";
    ?>

    <?php
	if($pkg['step'][$stepid]['fields']['field'] <> "") {
		foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {

		    $value = $field['value'];
		    $name  = $field['name'];

		    $name = ereg_replace(" ", "", $name);
		    $name = strtolower($name);

		    if($field['bindstofield'] <> "") {
				$arraynum = "";
				$field_conv = "";
				$field_split = split("->", $field['bindstofield']);
				// arraynum is used in cases where there is an array of the same field
				// name such as dnsserver (2 of them)
				if($field['arraynum'] <> "") $arraynum = "[" . $field['arraynum'] . "]";
				foreach ($field_split as $f) $field_conv .= "['" . $f . "']";
					$toeval = "\$value = \$config" . $field_conv . $arraynum . ";";
					eval($toeval);
					if ($field['type'] == "checkbox") {
						$toeval = "if(isset(\$config" . $field_conv . $arraynum . ")) \$value = \" CHECKED\";";
						eval($toeval);
					}
		    }

		    if(!$field['combinefieldsend'])
			echo "<tr>";

		    if ($field['type'] == "input") {
			if(!$field['dontdisplayname']) {
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['name']);
				echo ":</td>\n";
			}
			if(!$field['dontcombinecells'])
				echo "<td class=\"vtable\">\n";

			echo "<input id='" . $name . "' name='" . $name . "' value='" . $value . "'";
			if($field['validate'])
				echo " onChange='FieldValidate(this.value, \"{$field['validate']}\", \"{$field['message']}\");'";
			echo ">\n";
		    } else if($field['type'] == "interfaces_selection") {
			$size = "";
			$multiple = "";
			$name = strtolower($name);
			echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
			echo fixup_string($field['name']) . "\n";
			echo "</td>";
			echo "<td class=\"vtable\">\n";
			if($field['size'] <> "") $size = " size=\"" . $field['size'] . "\"";
			if($field['multiple'] <> "" and $field['multiple'] <> "0") {
			  $multiple = " multiple=\"multiple\"";
			  $name .= "[]";
			}
			echo "<select name='" . $name . "'" . $size . $multiple . ">\n";
			if($field['add_to_interfaces_selection'] <> "") {
				$SELECTED = "";
				if($field['add_to_interfaces_selection'] == $value) $SELECTED = " SELECTED";
				echo "<option value='" . $field['add_to_interfaces_selection'] . "'" . $SELECTED . ">" . $field['add_to_interfaces_selection'] . "</option>\n";
			}
			$interfaces = &$config['interfaces'];
			if($field['all_interfaces'] <> "") {
				$ints = split(" ", `/sbin/ifconfig -l`);
				$interfaces = array();
				foreach ($ints as $int) {
					$interfaces[]['descr'] = $int;
					$interfaces[] = $int;
				}
			}
			foreach ($interfaces as $ifname => $iface) {
			  if ($iface['descr'])
				  $ifdescr = $iface['descr'];
			  else
				  $ifdescr = strtoupper($ifname);
			  $ifname = $iface['descr'];
			  $ip = "";
			  if($field['all_interfaces'] <> "") {
				$ifdescr = $iface;
				$ip = " " . find_interface_ip($iface);
			  }
			  $SELECTED = "";
			  if($value == $ifdescr) $SELECTED = " SELECTED";
			  $to_echo =  "<option value='" . $ifdescr . "'" . $SELECTED . ">" . $ifdescr . $ip . "</option>\n";
			  $to_echo .= "<!-- {$value} -->";
			  $canecho = 0;
			  if($field['interface_filter'] <> "") {
				if(stristr($iface, $field['interface_filter']) == true)
					$canecho = 1;
			  } else {
				$canecho = 1;
			  }
			  if($canecho == 1) 
				echo $to_echo;
			}
				echo "</select>\n";
			} else if ($field['type'] == "password") {
			if(!$field['dontdisplayname']) {
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['name']);
				echo ":</td>\n";
			}
			if(!$field['dontcombinecells'])
				echo "<td class=\"vtable\">";
			echo "<input id='" . $name . "' name='" . $name . "' value='" . $value . "' type='password'>\n";
		    } else if ($field['type'] == "select") {
			if(!$field['dontdisplayname']) {
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['name']);
				echo ":</td>\n";
			}
			if($field['size']) $size = " size='" . $field['size'] . "' ";
			if($field['multiple'] == "yes") $multiple = "MULTIPLE ";
			if(!$field['dontcombinecells'])
				echo "<td class=\"vtable\">\n";
			$onchange = "";
			foreach ($field['options']['option'] as $opt) {
				if($opt['enablefields'] <> "") {
					$onchange = "onchange=\"enableitems(this.selectedIndex);\" ";
				}
			}
			echo "<select " . $onchange . $multiple . $size . "id='" . $name . "' name='" . $name . "'>\n";
			foreach ($field['options']['option'] as $opt) {
				$selected = "";
				if($value == $opt['value']) $selected = " SELECTED";
			    echo "\t<option name='" . $opt['name'] . "' value='" . $opt['value'] . "'" . $selected . ">" . $opt['name'] . "</option>\n";
			}
			echo "</select>\n";
		    } else if ($field['type'] == "textarea") {
			if(!$field['dontdisplayname']) {
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['name']);
				echo ":</td>";
			}
			if(!$field['dontcombinecells'])
				echo "<td class=\"vtable\">";
			echo "<textarea id='" . $name . "' name='" . $name . ">" . $value . "</textarea>\n";
		    } else if ($field['type'] == "submit") {
			echo "<td>&nbsp;<br></td></tr>";
			echo "<tr><td colspan='2'><center>";
			echo "<input type='submit' name='" . $name . "' value='" . $field['name'] . "'>\n";
		    } else if ($field['type'] == "listtopic") {
			echo "<td>&nbsp;</td><tr>";
			echo "<tr><td colspan=\"2\" class=\"listtopic\">" . $field['name'] . "<br></td>\n";
		    } else if ($field['type'] == "subnet_select") {
			if(!$field['dontdisplayname']) {
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['name']);
				echo ":</td>";
			}
			if(!$field['dontcombinecells'])
				echo "<td class=\"vtable\">";
			echo "<select name='{$name}'>\n";
			for($x=1; $x<33; $x++) {
				$CHECKED = "";
				if($value == $x) $CHECKED = " SELECTED";
				if($x <> 31)
					echo "<option value='{$x}' {$CHECKED}>{$x}</option>\n";
			}
			echo "</select>\n";
		    } else if ($field['type'] == "timezone_select") {
			if(!$field['dontdisplayname']) {
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo fixup_string($field['name']);
				echo ":</td>";
			}
			if(!$field['dontcombinecells'])
				echo "<td class=\"vtable\">";
			echo "<select name='{$name}'>\n";
			foreach ($timezonelist as $tz) {
				$SELECTED = "";
				if ($value == $tz) $SELECTED = " SELECTED";
				echo "<option value='" . htmlspecialchars($tz) . "' {$SELECTED}>";
				echo htmlspecialchars($tz);
				echo "</option>\n";
			}
			echo "</select>\n";
		    } else if ($field['type'] == "checkbox") {
			if(!$field['dontdisplayname']) {
				echo "<td width=\"22%\" align=\"right\" class=\"vncellreq\">\n";
				echo $field['name'];
				echo ":</td>";
			}
			$checked = "";
			if($value <> "") $checked = " CHECKED";
			echo "<td class=\"vtable\"><input type='checkbox' id='" . $name . "' name='" . $name . "' " . $checked;
			if(isset($field['enablefields']) or isset($field['checkenablefields'])) echo " onClick=\"enablechange()\"";
			echo ">\n";
		    }

		    if($field['typehint'] <> "") {
			echo $field['typehint'];
		    }

		    if($field['description'] <> "") {
			echo "<br>" . $field['description'];
			echo "</td>";
		    }

		    if(!$field['combinefieldsbegin'])
			 echo "</tr>\n";

		    if($field['warning'] <> "") {
			echo "<br><b><font color=\"red\">" . $field['warning'] . "</font></b>";
		    }

		}
	}
    ?>
</table>
<br>&nbsp;
</div>
</form>

<script type="text/javascript">
NiftyCheck();
Rounded("div#roundme","all","#333333","#FFFFFF","smooth");
</script>

</body>
</html>

<?php

$fieldnames_array = Array();
if($pkg['step'][$stepid]['disableallfieldsbydefault'] <> "") {
	// create a fieldname loop that can be used with javascript
	// hide and enable features.
	echo "\n<script language=\"JavaScript\">\n";
	echo "function disableall() {\n";
	foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
		if($field['type'] <> "submit" and $field['type'] <> "listtopic") {
			if(!$field['donotdisable'] <> "") {
				array_push($fieldnames_array, $field['name']);
				$fieldname = ereg_replace(" ", "", $field['name']);
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
						$enablefields_split = split(",", $opt['enablefields']);
						foreach ($enablefields_split as $efs) {
							$fieldname = ereg_replace(" ", "", $efs);
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
	echo "disableall();\n";
	echo "</script>\n\n";
}


if($pkg['step'][$stepid]['stepafterformdisplay'] <> "") {
	// handle after form display event.
	eval($pkg['step'][$stepid]['stepafterformdisplay']);
}

if($pkg['step'][$stepid]['javascriptafterformdisplay'] <> "") {
	// handle after form display event.
        echo "\n<script language=\"JavaScript\">\n";
	echo $pkg['step'][$stepid]['javascriptafterformdisplay'] . "\n";
	echo "</script>\n\n";
}

/*
 *  HELPER FUNCTIONS
 */

function fixup_string($string) {
	global $config, $myurl;
	$newstring = $string;
	// fixup #1: $myurl -> http[s]://ip_address:port/
	$https = "";
	$port = $config['system']['webguiport'];
	if($port <> "443" and $port <> "80")
		$urlport = ":" . $port;
	else
		$urlport = "";
	if($config['system']['webguiproto'] == "https")
		$https = "s";
    $myurl = "http" . $https . "://" . $config['interfaces']['lan']['ipaddr'] . $urlport;
	$newstring = str_replace("\$myurl", $myurl, $newstring);
	// fixup #2: $wanip
	$curwanip = get_current_wan_address();
	$newstring = str_replace("\$wanip", $curwanip, $newstring);
	// fixup #3: $lanip
	$lanip = $config['interfaces']['lan']['ipaddr'];
	$newstring = str_replace("\$lanip", $lanip, $newstring);
	// fixup #4: fix'r'up here.
	return $newstring;
}

function is_timezone($elt) {
	return !preg_match("/\/$/", $elt);
}

?>