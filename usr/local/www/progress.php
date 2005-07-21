#!/usr/local/bin/php
<?php
/* $Id$ */

include("guiconfig.inc");

$url = 'progress.php?UPLOAD_IDENTIFIER='.  $_GET["UPLOAD_IDENTIFIER"] .'&e=1';

function nice_value($x) {
   if ($x < 100)  $x;
   if ($x < 10000)  return sprintf("%.2fKB", $x/1000);
   if ($x < 900000) return sprintf("%dKB", $x/1000);
   return sprintf("%.2fMB", $x/1000/1000);
}


$X = upload_progress_meter_get_info( $_GET["UPLOAD_IDENTIFIER"] );
if (!$X) {

   if ( array_key_exists( "e", $_GET ) ) {
      echo "<HTML><BODY onLoad='window.close();'>Invalid Meter ID! {$_GET["UPLOAD_IDENTIFIER"]}";
      echo ('</BODY></HTML>');
   }else{
      echo ('<HTML><meta HTTP-EQUIV="Refresh" CONTENT="1; url='. $url .'"><BODY></BODY></HTML>');
   }

}else{

   $meter = sprintf("%.2f", $X['bytes_uploaded'] / $X['bytes_total'] * 100);

   $sp = $X['speed_last'];
   if ($sp < 10000) $speed  = sprintf("%.2f", $sp / 1000);
   else $speed  = sprintf("%d", $sp / 1000);

   $eta = sprintf("%02d:%02d", $X['est_sec'] / 60, $X['est_sec'] % 60 );

   $upl   = nice_value($X['bytes_uploaded']);
   $total = nice_value($X['bytes_total']);

   if ($X['bytes_total'] > 1 && $X['bytes_uploaded'] >= $X['bytes_total'] && $X['est_sec'] == 0) {
      echo ('<HTML><BODY onLoad="window.close()"> UPLOAD completed!</BODY></HTML>');
   }else{

?>

<HTML>
<HEAD>

<meta HTTP-EQUIV="Refresh" CONTENT="1; url=<?=$url?>">

<TITLE>Uploading Files... Please wait ...</TITLE>

<style type='text/css'> td {font-size: 10pt }</style>

</HEAD>
<BODY BGCOLOR="#FFFFFF">

<table height="100%" width="100%" cellPadding="4" cellSpacing="4" style="border:1px solid #990000;">
<tr><td>

   <font face="arial"><b><center>Uploading files...</b></center>

   <br>

   <table width="100%" height="15" colspacing="0" cellpadding="0" cellspacing="0" border="0" align="top" nowrap>
	<td width="5" height="15" background="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" align="top"></td>
	<td>
		<table WIDTH="100%" height="15" colspacing="0" cellpadding="0" cellspacing="0" border="0" align="top" nowrap>
			<td background="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif"><?echo("<img src='./themes/".$g['theme']."/images/misc/bar_blue.gif' height='15' WIDTH='$meter%'>");?></td>
		</table>

	</td>
	<td width="5" height="15" background="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" align="top"></td>
   </table>






   <br>

   <TABLE WIDTH="100%">
   <tr>
   <td align="right"><font face="arial"><b>Time Remaining:</td><td><font face="arial"><?=$eta?></td>
   <td align="right"><font face="arial"><b>Speed:</td><td><font face="arial"><font face="arial"><?=$speed?>KB/sec</td>
   </tr>

   <tr>
   <td align="right"><font face="arial"><b>Uploaded:</td><td><font face="arial"><?=$upl?></td>
   <td align="right"><font face="arial"><b>File Size:</td><td><font face="arial"><?=$total?></td>
   </tr>

   <tr>
   <td align="right"><font face="arial"><b>Completed:</td><td><font face="arial"><?=$meter?>%</td>
   <td align="right"><font face="arial"><b></td><td><font face="arial"></td>
   </tr>

</td></tr>
</table>

</BODY>
</HTML>

<?  } } ?>


