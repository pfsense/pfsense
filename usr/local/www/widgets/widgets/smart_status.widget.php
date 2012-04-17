<?php
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
$devs = array();
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<?
		echo '<td class="widgetsubheader"><b><center>' . gettext("Drive") . '</center></b></td>';
		echo '<td class="widgetsubheader"><b><center>' . gettext("Ident") . '</center></b></td>';
		echo '<td class="widgetsubheader"><b><center>' . gettext("SMART Status") . '</center></b></td>';
		?>
	</tr>

<?php

// Get all AD* and DA* (IDE and SCSI) devices currently installed and st$
exec("ls /dev | grep '^[ad][da][0-9]\{1,2\}$'", $devs);

if(count($devs) > 0)  {
	foreach($devs as $dev)  {
		$dev_state =  exec("smartctl -a /dev/$dev | grep result: | awk '{print $6}'");
		$dev_ident =  exec("diskinfo -v /dev/$dev | grep ident | awk '{print $1}'");
		##erste Spalte: Drives ausgeben
		echo '<tr><td class="listlr">';
		echo '/dev/'. $dev ;
		echo '</td>' . "\n";

		##zweite Spalte
		echo '<td class="listr"><center>' . "\n";
		echo $dev_ident ;
		echo '</td>' . "\n";

		##dritte Spalte: smartstatus ausgeben
		echo '<td class="listr">'; #tabellenspalte

		if($dev_state == "PASSED")
			echo "<span style=\"background-color:#00FF00\">$dev_state</span><br>"; ##gruener Hintergrund bei PASSED
		else
			echo "<span style=\"background-color:#FF0000\">$dev_state</span><br>"; ##roter Hintergrund sonst
		echo '</td></tr>' . "\n"; #tabellenspalte
	}
}
?>
</table>
<center><a href="diag_smart.php" class="navlink">SMART Status</a></center>