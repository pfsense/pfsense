#!/usr/local/bin/php
<?php
/* $Id$ */
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

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

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


$pgtitle = "System: Packages";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle">System: Package Manager</p>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php

$pkg_info = get_pkg_info('all', array('name', 'category', 'website', 'version', 'status', 'descr'));
$pkg_sizes = get_pkg_sizes();

?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Available Packages", true, "pkg_mgr.php");
	$tab_array[1] = array("Installed Packages", false, "pkg_mgr_installed.php");
	display_top_tabs($tab_array);
?> 
  </td></tr>
  <tr>
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="10%" class="listhdrr">Package Name</td>
                  <td width="25%" class="listhdrr">Category</td>
		  <td width="10%" class="listhdrr">Size</td>
		  <td width="5%" class="listhdrr">Status</td>
                  <td width="50%" class="listhdr">Description</td>
                </tr>

		<?php
		 $pkgs = array();
		 $instpkgs = array();
		    if($config['installedpackages']['package'] != "")
			foreach($config['installedpackages']['package'] as $instpkg) $instpkgs[] = $instpkg['name'];
		    $pkg_names = array_keys($pkg_info);
		    $pkg_keys = array();
		    foreach($pkg_names as $name) {
			if(!in_array($name, $instpkgs)) $pkg_keys[] = $name;
		    }
		    sort($pkg_keys);
		    if(count($pkg_keys) != 0) {
		    	foreach($pkg_keys as $key) {
			    $index = &$pkg_info[$key];
			    if(in_array($index['name'], $instpkgs)) continue;
                            ?>
                            <tr valign="top">
                                <td class="listlr">
                                    <A target="_new" href="<?= $index['website'] ?>"><?= $index['name'] ?></a>
                                </td>
                                <td class="listlr">
                                    <?= $index['category'] ?>
    				</td>
				<?php
					$size = get_package_install_size($index['name'], $pkg_sizes);
                               		$size = squash_from_bytes($size[$index['name']], 1);
				?>
				<td class="listlr">
					<?= $size ?>
				</td>
				<td class="listlr">
									<?= $index['status'] ?>
									<br>
									<?= $index['version'] ?>
                                </td>
                                <td class="listbg">
                                    <font color="#FFFFFFF">
                                    <?= $index['descr'] ?>
                                </td>
                                <td valign="middle" class="list" nowrap>
                                    <a onclick="return confirm('Do you really want to install this package?')" href="pkg_mgr_install.php?id=<?=$index['name'];?>"><img src="plus.gif" width="17" height="17" border="0"></a>
                                </td>
                            </tr>
                            <?php
                        }
		    } else {
			echo "<tr><td colspan=\"5\"><center>There are currently no available packages for installation.</td></tr>";
		    }
		?>
        </table>
    </td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
