#!/usr/local/bin/php
<?php
/*
    pkg_mgr.php
    Copyright (C) 2004, 2005 Scott Ullrich
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

$a_out = &$pkg_config['packages'];

if ($_POST) {

    $pconfig = $_POST;

    $retval = 0;

    if (!file_exists($d_sysrebootreqd_path)) {
		config_lock();
        $retval |= filter_configure();
		config_unlock();
    }
    $savemsg = get_std_save_message($retval);

    if ($retval == 0) {
        if (file_exists($d_natconfdirty_path))
            unlink($d_natconfdirty_path);
        if (file_exists($d_filterconfdirty_path))
            unlink($d_filterconfdirty_path);
    }
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Package Manager");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle">System: Package Manager</p>
<form action="firewall_nat_out_load_balancing.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php

// Allow package location to be overriden
$config_location = "http://www.pfsense.com/packages/pkg_config.xml";
if($config['package_location'])
	    $config_location = $config['package_location'];

if(!file_exists("/tmp/pkg_config.xml")) {
            mwexec("cd {$g['tmp_path']} && /usr/bin/fetch \"" . $config_location . "\" >/dev/null 2>&1 ");
            if(!file_exists("{$g['tmp_path']}/pkg_config.xml")) {
                        print_info_box_np("Could not download pkg_config.xml from pfSense.com.  Check your DNS settings.");
                        die;
            }
}

$pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");

if(!$pkg_config['packages']) {
            print_info_box_np("Could not find any packages in pkg_config.xml");
}
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
  <ul id="tabnav">
    <li class="tabact">Available Packages</a></li>
    <li class="tabinact"><a href="pkg_mgr_installed.php">Installed Packages</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="10%" class="listhdrr">Package Name</td>
                  <td width="25%" class="listhdrr">Category</td>
				  <td width="5%" class="listhdrr">Status</td>
                  <td width="50%" class="listhdr">Description</td>
                </tr>

		<?php
		 $i = 0;
		    foreach ($pkg_config['packages']['package'] as $pkg) {
			$pkgname = "";
			$pkgname = $pkg['name'];
                        if($config['installedpackages']['package']) {
                            foreach ($config['installedpackages']['package'] as $installed) {
                                        if($installed['name'] == $pkg['name'])
                                                    $pkgname = "";
                            }
                        }
                        if($pkgname <> "") {
                            ?>
                            <tr valign="top">
                                <td class="listlr">
                                    <A target="_new" href="<?= $pkg['website'] ?>"><?= $pkg['name'] ?></a>
                                </td>
                                <td class="listlr">
                                    <?= $pkg['category'] ?>
    							</td>
                                <td class="listlr">
									<?= $pkg['status'] ?>
									<br>
									<?= $pkg['version'] ?>
                                </td>
                                <td class="listbg">
                                    <font color="#FFFFFFF">
                                    <?= $pkg['descr'] ?>
                                </td>
                                <td valign="middle" class="list" nowrap>
                                    <a onclick="return confirm('Do you really want to install this package?')" href="pkg_mgr_install.php?id=<?=$i;?>"><img src="plus.gif" width="17" height="17" border="0"></a>
                                </td>
                            </tr>
                            <?php
                        }
			$i++;
		    }
                    if($i == 0) {
                        echo "<tr><td colspan=\"3\"><center>There are currently no available packages for installation.</td></tr>";
                    }
		?>
        </table>
    </td>
  </tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>

<?php system("rm /tmp/pkg*"); ?>

