#!/usr/local/bin/php
<?php
/*
    pkg_edit.php
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

 <tabs>
 	<tab>
 		<text>Testing Tab</text>
 		<url>url to go to</url>
 	</tab>
 	<tab>
 		<text>Testing Tab 2</text>
 		<xml>filename of xml</xml>
 	</tab>
 </tabs>

*/

require("guiconfig.inc");
require("xmlparse_pkg.inc");

$pfSense_config = $config; // copy this since we will be parsing
                           // another xml file which will be clobbered.

function gentitle_pkg($pgname) {
	global $pfSense_config;
	return $pfSense_config['system']['hostname'] . "." . $pfSense_config['system']['domain'] . " - " . $pgname;
}

// XXX: Make this input safe.
$xml = $_GET['xml'];
if($_POST['xml']) $xml = $_POST['xml'];

if($xml == "") {
            $xml = "not_defined";
            print_info_box_np("ERROR:  Could not open " . $xml . ".");
            die;
} else {
            $pkg = parse_xml_config_pkg("/usr/local/pkg/" . $xml, "packagegui");
}

$package_name = $pkg['menu'][0]['name'];
$section      = $pkg['menu'][0]['section'];
$config_path  = $pkg['configpath'];
$name         = $pkg['name'];
$title        = $section . ": " . $package_name;

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

// grab the installedpackages->package_name section.
$toeval = "\$a_pkg = &\$config['installedpackages']['" . $name . "']['config'];";
eval($toeval);

$toeval = "if (!is_array(\$config['installedpackages']['" . xml_safe_fieldname($pkg['name']) . "']['config'])) \$config['installedpackages']['" . xml_safe_fieldname($pkg['name']) . "']['config'] = array();";
eval($toeval);

$toeval = "\$a_pkg = &\$config['installedpackages']['" . xml_safe_fieldname($pkg['name']) . "']['config'];";
eval($toeval);

if($pkg['custom_php_command_before_form'] <> "")
  eval($pkg['custom_php_command_before_form']);


if ($_POST) {
	if($_POST['act'] == "del") {
		if($pkg['custom_delete_php_command']) {
		    eval($pkg['custom_delete_php_command']);
		}
		write_config();
		// resync the configuration file code if defined.
		if($pkg['custom_php_resync_config_command'] <> "") {
		    eval($pkg['custom_php_resync_config_command']);
		}
	} else {
		if($pkg['custom_add_php_command']) {
			if($pkg['donotsave'] <> "") {
				?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle_pkg($title);?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");

?>
<p class="pgtitle"><?=$title?></p>
				<?php
			}
			if($pkg['preoutput']) echo "<pre>";
			eval($pkg['custom_add_php_command']);
			if($pkg['preoutput']) echo "</pre>";
		}
	}

	// donotsave is enabled.  lets simply exit.
	if($pkg['donotsave'] <> "") exit;

	$firstfield = "";
	$rows = 0;

	// store values in xml configration file.
	if (!$input_errors) {
		$pkgarr = array();
		foreach ($pkg['fields']['field'] as $fields) {
			if($fields['type'] == "rowhelper") {
				// save rowhelper items.
				for($x=0; $x<99; $x++) { // XXX: this really should be passed from the form.
				                         // XXX: this really is not helping embedded platforms.
					foreach($fields['rowhelper']['rowhelperfield'] as $rowhelperfield) {
						if($firstfield == "")  {
						  $firstfield = $rowhelperfield['fieldname'];
						} else {
						  if($firstfield == $rowhelperfield['fieldname']) $rows++;
						}
						$comd = "\$value = \$_POST['" . $rowhelperfield['fieldname'] . $x . "'];";
						//echo($comd . "<br>");
						eval($comd);
						if($value <> "") {
							$comd = "\$pkgarr['row'][" . $x . "]['" . $rowhelperfield['fieldname'] . "'] = \"" . $value . "\";";
							//echo($comd . "<br>");
							eval($comd);
						}
					}
				}
			} else {
				// simply loop through all field names looking for posted
				// values matching the fieldnames.  if found, save to package
				// configuration area.

				$fieldname  = $fields['fieldname'];
				$fieldvalue = $_POST[$fieldname];
				$toeval = "\$pkgarr['" . $fieldname . "'] 	= \"" . $fieldvalue . "\";";
				eval($toeval);
			}
		}

		if (isset($id) && $a_pkg[$id])
			$a_pkg[$id] = $pkgarr;
		else
			$a_pkg[] = $pkgarr;

		write_config();

		// late running code
		if($pkg['custom_add_php_command_late'] <> "") {
		    eval($pkg['custom_add_php_command_late']);
		}

		// resync the configuration file code if defined.
		if($pkg['custom_php_resync_config_command'] <> "") {
		    eval($pkg['custom_php_resync_config_command']);
		}

		parse_package_templates();

		/* if start_command is defined, restart w/ this */
		if($pkg['start_command'] <> "")
		    exec($pkg['start_command'] . ">/dev/null 2&>1");

		/* if restart_command is defined, restart w/ this */
		if($pkg['restart_command'] <> "")
		    exec($pkg['restart_command'] . ">/dev/null 2&>1");

		if($pkg['aftersaveredirect'] <> "") {
		    header("Location:  " . $pkg['aftersaveredirect']);
		} else {
		    header("Location:  pkg.php?xml=" . $xml);
		}
		exit;
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle_pkg($title);?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script type="text/javascript" language="javascript" src="row_helper_dynamic.js">
</script>

<?php
$config_tmp = $config;
$config = $pfSense_config;
include("fbegin.inc");
$config = $config_tmp;
?>
<p class="pgtitle"><?=$title?></p>
<form action="pkg_edit.php" method="post">
<input type="hidden" name="xml" value="<?= $xml ?>">
<?php if ($savemsg) print_info_box($savemsg); ?>

&nbsp;<br>

<?php
if ($pkg['tabs'] <> "") {
    echo "<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">";
    echo "<tr><td>";
    echo "  <ul id=\"tabnav\">";
    foreach($pkg['tabs']['tab'] as $tab) {
	$active = "tabinact";
	if(isset($tab['active'])) $active = "tabact";
	$url = "";
	if($tab['url'] <> "") $url = $tab['url'];
	if($tab['xml'] <> "") $url = "pkg_edit.php?xml=" . $tab['xml'];
	echo "<li class=\"{$active}\"><a href=\"{$url}\">Server NAT</a></li>";
    }
    echo "  </ul>";
    echo "</td></tr>";
    echo "<tr>";
    echo "<td class=\"tabcont\">";
}
?>

<table width="100%" border="0" cellpadding="6" cellspacing="0">
  <?php
  $cols = 0;
  $savevalue = "Save";
  if($pkg['savetext'] <> "") $savevalue = $pkg['savetext'];
  foreach ($pkg['fields']['field'] as $pkga) { ?>

	  <?php if(!$pkga['combinefieldsend']) echo "<tr valign=\"top\">"; ?>

	  <?php
	  if(!$pkga['dontdisplayname']) {
		echo "<td width=\"22%\" class=\"vncellreq\">";
		echo fixup_string($pkga['fielddescr']);
		echo "</td>";
	  }

	  if(!$pkga['dontcombinecells'])
		echo "<td class=\"vtable\">";

		// if user is editing a record, load in the data.
		if (isset($id) && $a_pkg[$id]) {
			$fieldname = $pkga['fieldname'];
			$toeval = "\$value = \$a_pkg[" . $id . "]['" . $fieldname . "'];";
			echo "<!-- eval: " . $toeval . "-->\n";
			eval($toeval);
		}

	      if($pkga['type'] == "input") {
			if($pkga['size']) $size = " size='" . $pkga['size'] . "' ";
			echo "<input " . $size . " name='" . $pkga['fieldname'] . "' value='" . $value . "'>\n";
			echo "<br>" . fixup_string($pkga['description']) . "\n";
	      } else if($pkga['type'] == "password") {
			echo "<input type='password' " . $size . " name='" . $pkga['fieldname'] . "' value='" . $value . "'>\n";
			echo "<br>" . fixup_string($pkga['description']) . "\n";
	      } else if($pkga['type'] == "select") {
		  // XXX: TODO: set $selected
                  if($pkga['size']) $size = " size='" . $pkga['size'] . "' ";
		  if($pkga['multiple'] == "yes") $multiple = "MULTIPLE ";
		    echo "<select " . $multiple . $size . "id='" . $pkga['fieldname'] . "' name='" . $pkga['fieldname'] . "'>\n";
		    foreach ($pkga['options']['option'] as $opt) {
			  $selected = "";
			  if($opt['value'] == $value) $selected = " SELECTED";
			  echo "\t<option name='" . $opt['name'] . "' value='" . $opt['value'] . "'" . $selected . ">" . $opt['name'] . "</option>\n";
		    }
		    echo "</select>\n";
		    echo "<br>" . fixup_string($pkga['description']) . "\n";
	      } else if($pkga['type'] == "vpn_selection") {
		    echo "<select name='" . $vpn['name'] . "'>\n";
		    foreach ($config['ipsec']['tunnel'] as $vpn) {
			echo "\t<option value=\"" . $vpn['descr'] . "\">" . $vpn['descr'] . "</option>\n";
		    }
		    echo "</select>\n";
		    echo "<br>" . fixup_string($pkga['description']) . "\n";
	      } else if($pkga['type'] == "checkbox") {
			$checkboxchecked = "";
			if($value == "on") $checkboxchecked = " CHECKED";
			echo "<input type='checkbox' name='" . $pkga['fieldname'] . "'" . $checkboxchecked . ">\n";
			echo "<br>" . fixup_string($pkga['description']) . "\n";
	      } else if($pkga['type'] == "textarea") {
		  if($pkga['rows']) $rows = " rows='" . $pkga['rows'] . "' ";
		  if($pkga['cols']) $cols = " cols='" . $pkga['cols'] . "' ";
			echo "<textarea " . $rows . $cols . " name='" . $pkga['fieldname'] . "'>" . $value . "</textarea>\n";
			echo "<br>" . fixup_string($pkga['description']) . "\n";
		  } else if($pkga['type'] == "interfaces_selection") {
			$size = "";
			$multiple = "";
			$fieldname = $pkga['fieldname'];
			if($pkga['size'] <> "") $size = " size=\"" . $pkga['size'] . "\"";
			if($pkga['multiple'] <> "" and $pkga['multiple'] <> "0") {
			  $multiple = " multiple=\"multiple\"";
			  $fieldname .= "[]";
			}
			echo "<select name='" . $fieldname . "'" . $size . $multiple . ">\n";
			foreach ($config['interfaces'] as $ifname => $iface) {
			  if ($iface['descr'])
				  $ifdescr = $iface['descr'];
			  else
				  $ifdescr = strtoupper($ifname);
			  $ifname = $iface['if'];
			  $SELECTED = "";
			  if($value == $ifname) $SELECTED = " SELECTED";
			  echo "<option value='" . $ifname . "'" . $SELECTED . ">" . $ifdescr . "</option>\n";
			}
			echo "</select>\n";
			echo "<br>" . fixup_string($pkga['description']) . "\n";
	      } else if($pkga['type'] == "radio") {
			echo "<input type='radio' name='" . $pkga['fieldname'] . "' value='" . $value . "'>";
	      } else if($pkga['type'] == "rowhelper") {
		?>
			<script type="text/javascript" language='javascript'>
			<!--

			<?php
				$rowcounter = 0;
				$fieldcounter = 0;
				foreach($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
					echo "rowname[" . $fieldcounter . "] = \"" . $rowhelper['fieldname'] . "\";\n";
					echo "rowtype[" . $fieldcounter . "] = \"" . $rowhelper['type'] . "\";\n";
					$fieldcounter++;
				}
			?>

			-->
			</script>

			<table name="maintable" id="maintable">
			<tr>
			<?php
				foreach($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
				  echo "<td><b>" . fixup_string($rowhelper['fielddescr']) . "</td>\n";
				}
				echo "</tr>";
				echo "<tbody>";

				echo "<tr>";
				  // XXX: traverse saved fields, add back needed rows.
				echo "</tr>";

				echo "<tr>\n";
				$rowcounter = 0;
				$trc = 0;
				if(isset($a_pkg[$id]['row'])) {
					foreach($a_pkg[$id]['row'] as $row) {
					/*
					 * loop through saved data for record if it exists, populating rowhelper
					 */
						foreach($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
							if($rowhelper['value'] <> "") $value = $rowhelper['value'];
							$fieldname = $rowhelper['fieldname'];
							// if user is editing a record, load in the data.
							if (isset($id) && $a_pkg[$id]) {
								$toeval = "\$value = \$row['" . $fieldname . "'];";
								echo "<!-- eval: " . $toeval . "-->\n";
								eval($toeval);
								echo "<!-- value: " . $value . "-->\n";
							}
							$options = "";
							$type = $rowhelper['type'];
							$fieldname = $rowhelper['fieldname'];
							if($type == "option") $options = &$rowhelper['options']['option'];
							$size = "8";
							if($rowhelper['size'] <> "") $size = $rowhelper['size'];
							display_row($rowcounter, $value, $fieldname, $type, $rowhelper, $size);
							// javascript helpers for row_helper_dynamic.js
							echo "</td>\n";
							echo "<script language=\"JavaScript\">\n";
							echo "<!--\n";
							echo "newrow[" . $trc . "] = \"" . $text . "\";\n";
							echo "-->\n";
							echo "</script>\n";
							$text = "";
							$trc++;
						}

						$rowcounter++;
						echo "<td>";
						echo "<input type=\"image\" src=\"/x.gif\" onclick=\"removeRow(this); return false;\" value=\"Delete\">";
						echo "</td>\n";
						echo "</tr>\n";
					}
				}
				if($trc == 0) {
					/*
					 *  no records loaded.
                                         *  just show a generic line non-populated with saved data
                                         */
                                        foreach($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
						if($rowhelper['value'] <> "") $value = $rowhelper['value'];
						$fieldname = $rowhelper['fieldname'];
						$options = "";
						$type = $rowhelper['type'];
						$fieldname = $rowhelper['fieldname'];
						if($type == "option") $options = &$rowhelper['options']['option'];
						$size = "8";
						if($rowhelper['size'] <> "") $size = $rowhelper['size'];
						display_row($rowcounter, $value, $fieldname, $type, $rowhelper, $size);
						// javascript helpers for row_helper_dynamic.js
						echo "</td>\n";
						echo "<script language=\"JavaScript\">\n";
						echo "<!--\n";
						echo "newrow[" . $trc . "] = \"" . $text . "\";\n";
						echo "-->\n";
						echo "</script>\n";
						$text = "";
						$trc++;
					}

					$rowcounter++;
				}
			?>

			  </tbody>
			</table>

		<br><a onClick="javascript:addRowTo('maintable'); return false;" href="#"><img border="0" src="/plus.gif"></a>
		<script language="JavaScript">
		<!--
		field_counter_js = <?= $fieldcounter ?>;
		rows = <?= $rowcounter ?>;
		totalrows = <?php echo $rowcounter; ?>;
		loaded = <?php echo $rowcounter; ?>;
		//typesel_change();
		//-->
		</script>

		<?php
	      }
	      if($pkga['typehint']) echo " " . $pkga['typehint'];
	     ?>

      <?php
	  if(!$pkga['combinefieldsbegin']) echo "</td></tr>";
      $i++;
  }
 ?>
  <tr>
	<td>&nbsp;</td>
  </tr>
  <tr>
    <td width="22%" valign="top">&nbsp;</td>
    <td width="78%">
      <input name="Submit" type="submit" class="formbtn" value="<?= $savevalue ?>">
      <?php if (isset($id) && $a_pkg[$id]): ?>
      <input name="id" type="hidden" value="<?=$id;?>">
      <?php endif; ?>
    </td>
  </tr>
</table>

<?php
if ($pkg['tabs'] <> "") {
    echo "</td></tr></table>";
}
?>

</form>
<?php include("fend.inc"); ?>
</body>
</html>

<?php

/*
 * ROW Helpers function
 */
function display_row($trc, $value, $fieldname, $type, $rowhelper, $size) {
	global $text;
	echo "<td>\n";
	if($type == "input") {
		echo "<input size='" . $size . "' name='" . $fieldname . $trc . "' value='" . $value . "'>\n";
	} else if($type == "password") {
		echo "<input size='" . $size . "' type='password' name='" . $fieldname . $trc . "' value='" . $value . "'>\n";
	} else if($type == "textarea") {
		echo "<textarea rows='2' cols='12' name='" . $fieldname . $trc . "'>" . $value . "</textarea>\n";
	} else if($type == "select") {
		echo "<select name='" . $fieldname . $trc . "'>\n";
		foreach($rowhelper['options']['option'] as $rowopt) {
			$selected = "";
			if($rowopt['value'] == $value) $selected = " SELECTED";
			$text .= "<option value='" . $rowopt['value'] . "'" . $selected . ">" . $rowopt['name'] . "</option>";
			echo "<option value='" . $rowopt['value'] . "'" . $selected . ">" . $rowopt['name'] . "</option>\n";
		}
		echo "</select>\n";
	}
}

function fixup_string($string) {
	global $config;
	// fixup #1: $myurl -> http[s]://ip_address:port/
	$https = "";
	$port = "";
	$urlport = "";
	$port = $config['system']['webguiport'];
	if($port <> "443" and $port <> "80") $urlport = ":" . $port;
	if($config['system']['webguiproto'] == "https") $https = "s";
	$myurl = "http" . $https . "://" . getenv("HTTP_HOST") . $urlportport;
	$newstring = str_replace("\$myurl", $myurl, $string);
	$string = $newstring;
	// fixup #2: $wanip
	$curwanip = get_current_wan_address();
	$newstring = str_replace("\$wanip", $curwanip, $string);
	$string = $newstring;
	// fixup #3: $lanip
	$lancfg = $config['interfaces']['lan'];
	$lanip = $lancfg['ipaddr'];
	$newstring = str_replace("\$lanip", $lanip, $string);
	$string = $newstring;
	// fixup #4: fix'r'up here.
	return $newstring;
}

/*
 *  Parse templates if they are defined
 */
function parse_package_templates() {
	global $pkg, $config;
	if($pkg['templates']['template'] <> "")
	    foreach($pkg['templates']['template'] as $pkg_template_row) {
		$filename = $pkg_template_row['filename'];
		$template_text = $pkg_template_row['templatecontents'];

		/* calculate total row helpers count */
		foreach ($pkg['fields']['field'] as $fields) {
			if($fields['type'] == "rowhelper") {
				// save rowhelper items.
                                $row_helper_total_rows = 0;
				for($x=0; $x<99; $x++) { // XXX: this really should be passed from the form.
					foreach($fields['rowhelper']['rowhelperfield'] as $rowhelperfield) {
						if($firstfield == "")  {
						  $firstfield = $rowhelperfield['fieldname'];
						} else {
						  if($firstfield == $rowhelperfield['fieldname']) $rows++;
						}
						$comd = "\$value = \$_POST['" . $rowhelperfield['fieldname'] . $x . "'];";
						eval($comd);
						if($value <> "") {
						    //$template_text = str_replace($fieldname . "_fieldvalue", $fieldvalue, $template_text);
						} else {
						    $row_helper_total_rows = $rows;
						    break;
						}
					}
				}
			}
		}

		/* replace $domain_total_rows with total rows */
		$template_text = str_replace("$domain_total_rows", $row_helper_total_rows, $template_text);

		/* change fields defined as fieldname_fieldvalue to their value */
		foreach ($pkg['fields']['field'] as $fields) {
			if($fields['type'] == "rowhelper") {
				// save rowhelper items.
				for($x=0; $x<99; $x++) { // XXX: this really should be passed from the form.
					$row_helper_data = "";
					$isfirst = 0;
					foreach($fields['rowhelper']['rowhelperfield'] as $rowhelperfield) {
						if($firstfield == "")  {
						  $firstfield = $rowhelperfield['fieldname'];
						} else {
						  if($firstfield == $rowhelperfield['fieldname']) $rows++;
						}
						$comd = "\$value = \$_POST['" . $rowhelperfield['fieldname'] . $x . "'];";
						eval($comd);
						if($value <> "") {
						    if($isfirst == 1) $row_helper_data .= "  " ;
						    $row_helper_data .= $value;
						    $isfirst = 1;
						}
						ereg($rowhelperfield['fieldname'] . "_fieldvalue\[(.*)\]", $template_text, $sep);
						foreach ($sep as $se) $seperator = $se;
						if($seperator <> "") {
						    $row_helper_data = ereg_replace("  ", $seperator, $row_helper_data);
						    $template_text = ereg_replace("\[" . $seperator . "\]", "", $template_text);
						}
						$template_text = str_replace($rowhelperfield['fieldname'] . "_fieldvalue", $row_helper_data, $template_text);
					}
				}
			} else {
				$fieldname  = $fields['fieldname'];
				$fieldvalue = $_POST[$fieldname];
				$template_text = str_replace($fieldname . "_fieldvalue", $fieldvalue, $template_text);
			}
		}

		/* replace cr's */
		$template_text = str_replace("\\n", "\n", $template_text);

		/* write out new template file */
		$fout = fopen($filename,"w");
		fwrite($fout, $template_text);
		fclose($fout);
	    }
}

?>