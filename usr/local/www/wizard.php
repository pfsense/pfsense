#!/usr/local/bin/php
<?php
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
require("xmlparse_pkg.inc");

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
            $pkg = parse_xml_config_pkg("/usr/local/www/wizards/" . $xml, "pfsensewizard");
}

$title          = $pkg['step'][$stepid]['title'];
$description    = $pkg['step'][$stepid]['description'];

if ($_POST) {
    foreach ($pkg['step'][$stepid]['fields'] as $field) {
        if($field['bindtofield'] <> "") {
            // update field with posted values.

        }
        if($pkg['step'][$stepid]['stepsubmitphpaction']) {
            eval($pkg['step'][$stepid]['stepsubmitphpaction']);
        }
    }
}

function update_config_field($field, $updatetext) {
    $config[$field] = $updatetext;
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
<?php
$config_tmp = $config;
$config = $pfSense_config;
include("fbegin.inc");
$config = $config_tmp;
?>
<p class="pgtitle"><?=$title?></p>
<form action="wizard.php" method="post">
<input type="hidden" name="xml" value="<?= $xml ?>">
<input type="hidden" name="stepid" value="<?= $stepid+1 ?>">
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="1" cellpadding="6" cellspacing="0">
  <tr>
    <td bgcolor="#990000">
        <font color="white"><center><b><?= $title ?></b></center></font>
    </td>
   </tr>
   <tr>
    <td><center>
        <table>
            <!-- wizard goes here -->
            <tr><td>&nbsp;</td></tr>
            <tr><td colspan='2'><center><b><?= $description ?></b></center></td></tr><tr><td>&nbsp;</td></tr>
            <?php
                foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {

                    $value = $field['value'];
		    $name  = $field['name'];

		    $name = ereg_replace(" ", "", $name);
		    $name = strtolower($name);

                    if ($field['type'] == "input") {
			echo "<tr><td align='right'>";
			echo $field['name'];
			echo ":</td><td>";
                        echo "<input name='" . $name . "' value='" . $value . "'>\n";
                    } else if ($field['type'] == "password") {
			echo "<tr><td align='right'>";
			echo $field['name'];
			echo ":</td><td>";
                        echo "<input name='" . $name . "' value='" . $value . "' type='password'>\n";
                    } else if ($field['type'] == "select") {
			echo "<tr><td align='right'>";
			echo $field['name'];
			echo ":</td><td>";
                        // XXX: TODO: set $selected
                        if($field['size']) $size = " size='" . $field['size'] . "' ";
                        if($field['multiple'] == "yes") $multiple = "MULTIPLE ";
                        echo "<select " . $multiple . $size . "id='" . $field['fieldname'] . "' name='" . $name . "'>\n";
                        foreach ($field['options']['option'] as $opt) {
                            echo "\t<option name='" . $opt['name'] . "' value='" . $opt['value'] . "'>" . $opt['name'] . "</option>\n";
                        }
                        echo "</select>\n";
                    } else if ($field['type'] == "textarea") {
			echo "<tr><td align='right'>";
			echo $field['name'];
			echo ":</td><td>";
                        echo "<textarea name='" . $name . ">" . $value . "</textarea>\n";
                    } else if ($field['type'] == "submit") {
			echo "<tr><td>&nbsp;</td></tr>";
			echo "<tr><td colspan='2'><center>";
			echo "<input type='submit' name='" . $name . "' value='" . $field['name'] . "'>\n";
			echo "</td><td>";
		    }

                    echo "</td></tr>";
                }
            ?>
        </table>
	  &nbsp;<br>&nbsp;
    </td>
  </tr>
</table>

</form>
<?php include("fend.inc"); ?>
</body>
</html>
