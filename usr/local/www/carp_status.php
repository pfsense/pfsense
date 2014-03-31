<?php
/*
    carp_status.php
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

##|+PRIV
##|*IDENT=page-status-carp
##|*NAME=Status: CARP page
##|*DESCR=Allow access to the 'Status: CARP' page.
##|*MATCH=carp_status.php*
##|-PRIV

/*
	pfSense_BUILDER_BINARIES:	/sbin/sysctl	
	pfSense_MODULE:	carp
*/

require_once("guiconfig.inc");
require_once("globals.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

unset($interface_arr_cache);
unset($carp_interface_count_cache);
unset($interface_ip_arr_cache);

$status = get_carp_status();
if($_POST['carp_maintenancemode'] <> "") {
	interfaces_carp_set_maintenancemode(!isset($config["virtualip_carp_maintenancemode"]));
}
if($_POST['disablecarp'] <> "") {
	if($status == true) {
		mwexec("/sbin/sysctl net.inet.carp.allow=0");
		if(is_array($config['virtualip']['vip'])) {
			$viparr = &$config['virtualip']['vip'];
                	foreach ($viparr as $vip) {
                               	switch ($vip['mode']) {
                                       	case "carp":
                                       		interface_vip_bring_down($vip);
                                       		sleep(1);
                                       	break;
                               	}
                	}
        	}
		$savemsg = sprintf(gettext("%s IPs have been disabled. Please note that disabling does not survive a reboot."), $carp_counter);
	} else {
		$savemsg = gettext("CARP has been enabled.");
		if(is_array($config['virtualip']['vip'])) {
                        $viparr = &$config['virtualip']['vip'];
                        foreach ($viparr as $vip) {
				switch ($vip['mode']) {
					case "carp":
						interface_carp_configure($vip);
						sleep(1);
					break;
                                }
                        }
                }
		interfaces_carp_setup();
		mwexec("/sbin/sysctl net.inet.carp.allow=1");
	}
}

$status = get_carp_status();

$pgtitle = array(gettext("Status"),gettext("CARP"));
$shortcut_section = "carp";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="carp_status.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<div id="mainlevel">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td>
<?php
			$carpcount = 0;
			if(is_array($config['virtualip']['vip'])) {
				foreach($config['virtualip']['vip'] as $carp) {
					if ($carp['mode'] == "carp") {
						$carpcount++;
						break;
					}
				}
			}
			if($carpcount > 0) {
				if($status == false) {
					$carp_enabled = false;
					echo "<input type=\"submit\" name=\"disablecarp\" id=\"disablecarp\" value=\"" . gettext("Enable Carp") . "\">";
				} else {
					$carp_enabled = true;
					echo "<input type=\"submit\" name=\"disablecarp\" id=\"disablecarp\" value=\"" . gettext("Disable Carptemporarily") . "\">";
				}
				if(isset($config["virtualip_carp_maintenancemode"])) {
					echo "<input type=\"submit\" name=\"carp_maintenancemode\" id=\"carp_maintenancemode\" value=\"" . gettext("Leave Carp maintenance mode now and on reboot") . "\">";
				} else {
					echo "<input type=\"submit\" name=\"carp_maintenancemode\" id=\"carp_maintenancemode\" value=\"" . gettext("Enter Carp maintenance mode now and on reboot") . "\">";
				}
			}
?>

			<p>
			<table class="tabcont sortable" width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td class="listhdrr"><b><center><?=gettext("CARP Interface"); ?></center></b></td>
					<td class="listhdrr"><b><center><?=gettext("Virtual IP"); ?></center></b></td>
					<td class="listhdrr"><b><center><?=gettext("Status"); ?></center></b></td>
				</tr>
<?php
				if ($carpcount == 0) {
					echo "</td></tr></table></table></div><center><br />" . gettext("Could not locate any defined CARP interfaces.");
					echo "</center>";

					include("fend.inc");
					echo "</body></html>";
					exit;
				}

				if(is_array($config['virtualip']['vip'])) {
					foreach($config['virtualip']['vip'] as $carp) {
						if ($carp['mode'] != "carp")
							continue;
						$ipaddress = $carp['subnet'];
						$password = $carp['password'];
						$netmask = $carp['subnet_bits'];
						$vhid = $carp['vhid'];
						$advskew = $carp['advskew'];
						$advbase = $carp['advbase'];
						$status = get_carp_interface_status("{$carp['interface']}_vip{$carp['vhid']}");
						echo "<tr>";
						$align = "valign='middle'";
						if($carp_enabled == false) {
							$icon = "<img {$align} src='/themes/".$g['theme']."/images/icons/icon_block.gif'>";
							$status = "DISABLED";
						} else {
							if($status == "MASTER") {
								$icon = "<img {$align} src='/themes/".$g['theme']."/images/icons/icon_pass.gif'>";
							} else if($status == "BACKUP") {
								$icon = "<img {$align} src='/themes/".$g['theme']."/images/icons/icon_pass_d.gif'>";
							} else if($status == "INIT") {
								$icon = "<img {$align} src='/themes/".$g['theme']."/images/icons/icon_log.gif'>";
							}
						}
						echo "<td class=\"listlr\"><center>" . convert_friendly_interface_to_friendly_descr($carp['interface']) . "@{$vhid} &nbsp;</td>";
						echo "<td class=\"listlr\"><center>" . $ipaddress . "&nbsp;</td>";
						echo "<td class=\"listlr\"><center>{$icon}&nbsp;&nbsp;" . $status . "&nbsp;</td>";
						echo "</tr>";
					}
				}
?>
			</table>
			</td>
		</tr>
	</table>
</div>

<p/>

<span class="vexpl">
<span class="red"><strong><?=gettext("Note"); ?>:</strong></span>
<br />
<?=gettext("You can configure high availability sync settings"); ?> <a href="system_hasync.php"><?=gettext("here"); ?></a>.
</span>

<p/>

<?php
	echo "<br />" . gettext("pfSync nodes") . ":<br />";
	echo "<pre>";
	system("/sbin/pfctl -vvss | /usr/bin/grep creator | /usr/bin/cut -d\" \" -f7 | /usr/bin/sort -u");
	echo "</pre>";
?>

<?php include("fend.inc"); ?>

</body>
</html>
