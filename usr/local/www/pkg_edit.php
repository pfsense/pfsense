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
$title        = $section . ": " . $package_name;

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

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

		header("Location:  pkg.php?xml=" . $xml);
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
	   echo $pkga['fielddescr'];
       echo "</td>";
	  }

	  if(!$pkga['dontcombinecells'])
		echo "<td class=\"vtable\">";

	      if($pkga['type'] == "input") {
			if($pkga['size']) $size = " size='" . $pkga['size'] . "' ";
			echo "<input " . $size . " name='" . $pkga['fieldname'] . "' value='" . $value . "'>\n";
			echo "<br>" . $pkga['description'] . "\n";
	      } else if($pkga['type'] == "password") {
			echo "<input type='password' " . $size . " name='" . $pkga['fieldname'] . "' value='" . $value . "'>\n";
			echo "<br>" . $pkga['description'] . "\n";
	      } else if($pkga['type'] == "select") {
		  // XXX: TODO: set $selected
                  if($pkga['size']) $size = " size='" . $pkga['size'] . "' ";
		  if($pkga['multiple'] == "yes") $multiple = "MULTIPLE ";
			echo "<select " . $multiple . $size . "id='" . $pkga['fieldname'] . "' name='" . $pkga['fieldname'] . "'>\n";
		  foreach ($pkga['options']['option'] as $opt) {
		      echo "\t<option name='" . $opt['name'] . "' value='" . $opt['value'] . "'>" . $opt['name'] . "</option>\n";
		  }
		   echo "</select>\n";
		   echo "<br>" . $pkga['description'] . "\n";
	      } else if($pkga['type'] == "checkbox") {
			echo "<input type='checkbox' name='" . $pkga['fieldname'] . "' value='" . $value . "'>\n";
			echo "<br>" . $pkga['description'] . "\n";
	      } else if($pkga['type'] == "textarea") {
		  if($pkga['rows']) $rows = " rows='" . $pkga['rows'] . "' ";
		  if($pkga['cols']) $cols = " cols='" . $pkga['cols'] . "' ";
			echo "<textarea " . $rows . $cols . " name='" . $pkga['fieldname'] . "'>" . $value . "</textarea>\n";
			echo "<br>" . $pkga['description'] . "\n";
		  } else if($pkga['type'] == "interfaces_selection") {
			$size = "";
			if($pkga['size'] <> "") $size = " size=\"" . $pkga['size'] . "\"";
			echo "<select name='" . $pkga['fieldname'] . "'" . $size . ">\n";
			foreach ($config['interfaces'] as $ifname => $iface) {
			  if ($iface['descr'])
				  $ifdescr = $iface['descr'];
			  else
				  $ifdescr = strtoupper($ifname);
				$ifname = $iface['if'];
				echo "<option value='" . $ifname . "'>" . $ifdescr . "</option>\n";
			}
			echo "</select>\n";
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
			  echo "<td><b>" . $rowhelper['fielddescr'] . "</td>\n";
			}
				echo "</tr>";
				echo "<tbody>";

				echo "<tr>";
				  // XXX: traverse saved fields, add back needed rows.
				echo "</tr>";

				if($rowcounter == 0) {
					// nothing saved.  manually add the first row.
                                        echo "<tr>\n";
					$rowcounter = 0;
					$trc = 0;
					foreach($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
						$temp = "";
						$text = "";
						$value = "";
						if($rowhelper['value'] <> "") $value = $rowhelper['value'];
						echo "<td>\n";
						if($rowhelper['type'] == "input") {
							echo "<input size='8' name='" . $rowhelper['fieldname'] . "0" . "' value='" . $value . "'>\n";
						} else if($rowhelper['type'] == "password") {
							echo "<input type='password' name='" . $rowhelper['fieldname'] . "0" . "' value='" . $value . "'>\n";
						} else if($rowhelper['type'] == "textarea") {
							echo "<textarea name='" . $rowhelper['fieldname'] . "0" . "'>" . $value . "</textarea>\n";
						} else if($rowhelper['type'] == "select") {
							echo "<select name='" . $rowhelper['fieldname'] . "0" . "'>\n";
							foreach($rowhelper['options']['option'] as $rowopt) {
								$text .= "<option value='" . $rowopt['value'] . "'>" . $rowopt['name'] . "</option>";
								echo "<option value='" . $rowopt['value'] . "'>" . $rowopt['name'] . "</option>\n";
							}
							echo "</select>\n";
						}
						echo "</td>\n";
						echo "<script language=\"JavaScript\">\n";
						echo "<!--\n";
						echo "newrow[" . $trc . "] = \"" . $text . "\";\n";
						echo "-->\n";
						echo "</script>\n";
						$trc++;
					}
					$rowcounter=0;
					echo "<td>";
					echo "<input type=\"button\" onclick=\"removeRow(this); typesel_change();\" value=\"Delete\">";
					echo "</td>\n";
					echo "</tr>\n";
				}
			?>

			  </tbody>
			</table>

		<br><input type="button" onclick="addRowTo('maintable'); typesel_change();" value="Add">

		<script language="JavaScript">
		<!--
		field_counter_js = <?= $fieldcounter ?>;
		rows = <?= $rowcounter ?>;
		totalrows = <?php echo $rowcounter; ?>;
		loaded = <?php echo $rowcounter; ?>;
		typesel_change();
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

</form>
<?php include("fend.inc"); ?>
</body>
</html>

