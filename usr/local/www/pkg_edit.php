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
            $pkg = parse_xml_config_pkg("/usr/local/pkg/" . $xml, "pfSenseWizard");
}

$package_name = $pkg['menu']['name'];
$section      = $pkg['menu']['section'];
$config_path  = $pkg['configpath'];
$title        = $section . ": Edit " . $package_name;

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];
	
$a_pkg = &$config['installedpackages']['package'];

if (isset($id) && $a_pkg[$id]) {
	foreach ($pkg['fields'] as $fields) {
		$fieldname = $pconfig['$fields']['fieldname'];
		$fieldvalue = $_POST[$fieldname];
		$pconfig[$fieldname] = $fieldvalue;
	}
}

if ($_POST) {
    if($_POST['act'] == "del") {
	if($pkg['custom_delete_php_command']) {
	    eval($pkg['custom_delete_php_command']);
	}
	// XXX: unset the xml value to config
    } else {
	if($pkg['custom_add_php_command']) {
	    eval($pkg['custom_add_php_command']);
	    $savemsg = "User has been added.";
	}
	// XXX: add the xml value to config
    }
}

// store values in xml configration file.
if (!$input_errors) {
	$pkgarr = array();
	
	if (isset($id) && $a_pkg[$id])
		$a_pkg[$id] = $pkgarr;
	else
		$a_pkg[] = $pkgarr;
	
	foreach ($pkg['fields'] as $fields) {
		$pkgarr[$fields['fieldname']]  = $fields['fieldname'];
		$pkgarr[$fields['fieldvalue']] = $_POST[$fields['fieldname']]; 
	}
	
	$config['installedpackages']['package']['config'][] = $filterent;
	
	write_config();
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
<form action="pkg_edit.php" method="post">
<input type="hidden" name="xml" value="<?= $xml ?>">
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="6" cellspacing="0">
  <tr>
  <?php
  $cols = 0;
  foreach ($pkg['fields']['field'] as $pkg) { ?>
      </tr>
      <tr valign="top">
       <td width="22%" class="vncellreq">
	  <?= $pkg['fielddescr'] ?>
       </td>
       <td class="vtable">
	  <?php
	      if($pkg['type'] == "input") {
		  // XXX: TODO: set $value
                  if($pkg['size']) $size = " size='" . $pkg['size'] . "' ";
		  echo "<input " . $size . " name='" . $pkg['fieldname'] . "' value='" . $value . "'>\n";
		  echo "<br>" . $pkg['description'] . "\n";
	      } else if($pkg['type'] == "password") {
		  echo "<input type='password' " . $size . " name='" . $pkg['fieldname'] . "' value='" . $value . "'>\n";
		  echo "<br>" . $pkg['description'] . "\n";
	      } else if($pkg['type'] == "select") {
		  // XXX: TODO: set $selected
                  if($pkg['size']) $size = " size='" . $pkg['size'] . "' ";
		  if($pkg['multiple'] == "yes") $multiple = "MULTIPLE ";
		  echo "<select " . $multiple . $size . "id='" . $pkg['fieldname'] . "' name='" . $pkg['fieldname'] . "'>\n";
		  foreach ($pkg['options']['option'] as $opt) {
		      echo "\t<option name='" . $opt['name'] . "' value='" . $opt['value'] . "'>" . $opt['name'] . "</option>\n";
		  }
		  echo "</select>\n";
		  echo "<br>" . $pkg['description'] . "\n";
	      } else if($pkg['type'] == "checkbox") {
		  echo "<input type='checkbox' name='" . $pkg['fieldname'] . "' value='" . $value . "'>\n";
		  echo "<br>" . $pkg['description'] . "\n";
	      } else if($pkg['type'] == "textarea") {
		  if($pkg['rows']) $rows = " rows='" . $pkg['rows'] . "' ";
		  if($pkg['cols']) $cols = " cols='" . $pkg['cols'] . "' ";
		  echo "<textarea " . $rows . $cols . " name='" . $pkg['fieldname'] . "'>" . $value . "</textarea>\n";
		  echo "<br>" . $pkg['description'] . "\n";
	      }
	  ?>
       </td>
      </tr>
      <?php
      $i++;
  }
 ?>
  <tr>
    <td width="22%" valign="top">&nbsp;</td>
    <td width="78%">
      <input name="Submit" type="submit" class="formbtn" value="Save">
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

