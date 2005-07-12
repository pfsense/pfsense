</td></tr></table></td>
  </tr>
  <tr>
	<td>&nbsp;</td>
  </tr>
  <tr align="center" valign="top">
    <td id="fend" colspan="2" class="cpline" bgcolor="#990000"><a target="_NEW" href="http://www.pfSense.com">pfSense</a> is &copy; 2004-2005 by Scott Ullrich. All Rights Reserved.
      <br>pfSense is originally based on m0n0wall which is &copy; 2002-2004 by Manuel Kasper.
      All rights reserved.&nbsp; [<a href="/license.php" class="tblnk">view license</a>]</td>
  </tr>
</table>
<script type="text/javascript">
NiftyCheck();
Rounded("div#niftyMenu","top bottom","#FFFFFF","#000000","smooth");
Rounded("div#mainarea","bl br tr","#FFF","#eeeeee","smooth");
Rounded("div#boxarea","bl br tl tr","#FFF","#eeeeee","smooth");
Rounded("tr#fend","bl br tl tr","#FFF","#990000","smooth");
<?php
    /* if we displayed an alert earlier, round the bottom edges */
    if($need_alert_display == true) {
	echo "Rounded(\"div#roundalert\",\"all\",\"#FFF\",\"#000000\",\"smooth\");\n";
	echo "Rounded(\"div#topbox\",\"all\",\"#FFF\",\"#990000\",\"smooth\");\n";
    } else {
	echo "Rounded(\"div#topbox\",\"all\",\"#FFF\",\"#990000\",\"smooth\");\n";
    }
?>
</script>

<?php

if(stristr($HTTP_SERVER_VARS["HTTP_USER_AGENT"],"MSIE") == true) {
    echo "<center>";
    echo "<br>";
    echo "<a href=\"http://getfirefox.com\"><img border=\"0\" src=\"http://sfx-images.mozilla.org/affiliates/Banners/468x60/trust.png\"></a>";
    echo "</center>";
}
