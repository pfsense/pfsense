#!/usr/local/bin/php
<?php
/*
    pkg.php
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

$xml = $_GET['xml'];

if($xml == "") {
            $xml = "not_defined";
            print_info_box_np("ERROR:  Could not open " . $xml . ".");
            die;
} else {
            $pkg = parse_xml_config_pkg("/usr/local/pkg/" . $xml, "packagegui");
}

$package_name = $pkg['menu']['name'];
$section      = $pkg['menu']['section'];
$config_path  = $pkg['configpath'];
$title        = $section . ": " . $package_name;

$toeval = "\$evaledvar = \$config['installedpackages']['" . xml_safe_fieldname($pkg['name']) . "']['config'];";
eval($toeval);

if ($_GET['act'] == "del") {
	    // loop through our fieldnames and automatically setup the fieldnames
	    // in the environment.  ie: a fieldname of username with a value of
            // testuser would automatically eval $username = "testuser";
	    foreach ($evaledvar as $ip) {
		    foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
			    $toeval = "\$" . xml_safe_fieldname($column['fielddescr']) . " = " . "\$ip['" . xml_safe_fieldname($column['fieldname']) . "'];";
			    eval($toeval);
		    }
	    }

	    $toeval = "\$a_pkg = &\$config['installedpackages']['" . xml_safe_fieldname($pkg['name']) . "']['config'];";
	    eval($toeval);

	    if ($a_pkg[$_GET['id']]) {
		if($pkg['custom_delete_php_command'] <> "") {
		    eval($pkg['custom_delete_php_command']);
		}

		unset($a_pkg[$_GET['id']]);
		write_config();
		header("Location:  pkg.php?xml=" . $xml);
		exit;
	    }
}

$toeval = "\$evaledvar = \$config['installedpackages']['" . xml_safe_fieldname($pkg['name']) . "']['config'];";
eval($toeval);

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
<form action="firewall_nat_out_load_balancing.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                <?php
                $cols = 0;
                foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
                        echo "<td class=\"listhdrr\">" . $column['fielddescr'] . "</td>";
                        $cols++;
                }
                echo "</tr>";
			$i=0;
			if($evaledvar)
			foreach ($evaledvar as $ip) {
				    echo "<tr valign=\"top\">\n";
				    foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
				       ?>
						<td class="listlr">
							<?php
							    $toeval="echo \$ip['" . xml_safe_fieldname($column['fieldname']) . "'];";
							    eval($toeval);
							?>
						</td>
				       <?php
				    }
				    ?>
				    <td valign="middle" class="list" nowrap>
				    &nbsp;<a href="pkg.php?xml=<?=$xml?>&act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this item?')"><img src="x.gif" width="17" height="17" border="0"></a>
				    </td>
				    <?php
				    echo "</tr>\n";
				    $i++;
			}
				    ?>

               <tr><td colspan="<?=$cols?>"></td><td><a href="pkg_edit.php?xml=<?=$xml?>"><img src="plus.gif" width="17" height="17" border="0"></a></td></tr>
        </table>
    </td>
  </tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>

