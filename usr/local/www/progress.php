#!/usr/local/bin/php

<?php

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

<style type='text/css'> td {font-size: 10pt } td.uplmtr {font-size:6pt; height:12px}</style>

</HEAD>
<BODY BGCOLOR="#FFFFFF">

<table height="100%" width="100%" cellPadding="4" cellSpacing="4" style="border:1px solid #000066;">
<tr><td>

   <font face="arial"><b><center>Uploading files...</b></center>

   <br>

   <table WIDTH="100%" cellPadding="1" cellSpacing="2" style='border:1px dashed #000066; BORDER-BOTTOM: 1px inset; BORDER-LEFT: 1px inset; BORDER-RIGHT: 1px inset; BORDER-TOP: 1px inset'> <tr><td>
     <table border=0 WIDTH="100%" COLS="34"><tr>

     <?

	for ($i=0; $i<100; $i+=3) {
	   $color = ($i<$meter) ? " bgcolor='#990000' " : '';
	   $width = ($i+3<100)   ? "3" : 100-$i;
	   echo("<td $color class='uplmtr' WIDTH='$width%'>&nbsp;</td>\n");
	}
     ?>

   </tr></table>
   </td></tr></table>

   <br>

   <TABLE WIDTH=100%>
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
   </tr>

</td></tr>
</table>

</BODY>
</HTML>

<?  } } ?>


