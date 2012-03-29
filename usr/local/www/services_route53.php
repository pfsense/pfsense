<?php
/* $Id$ */
/*
	Copyright (C) 2012 Edson Brandi <ebrandi@fugspbr.org>
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
/*
	pfSense_MODULE:	route53update
*/

##|+PRIV
##|*IDENT=page-services-route53clients
##|*NAME=Services: Route 53 clients page
##|*DESCR=Allow access to the 'Services: Route 53 clients' page.
##|*MATCH=services_route53.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['route53updates']['route53update']))
	$config['route53updates']['route53update'] = array();

$a_route53 = &$config['route53updates']['route53update'];

if ($_GET['act'] == "del") {
		unset($a_route53[$_GET['id']]);

		write_config();

		header("Location: services_route53.php");
		exit;
}

$pgtitle = array(gettext("Services"), gettext("Route 53 clients"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_route53.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("DynDns"), false, "services_dyndns.php");
	$tab_array[] = array(gettext("RFC 2136"), false, "services_rfc2136.php");
	$tab_array[] = array(gettext("Route 53"), true, "services_route53.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
	<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
		  <td width="5%"  class="listhdrr"><?=gettext("Interface");?></td>
                  <td width="25%" class="listhdrr"><?=gettext("Hostname");?></td>
                  <td width="20%" class="listhdrr"><?=gettext("IP in DNS Cache");?></td>
                  <td width="40%" class="listhdr"><?=gettext("Description");?></td>
                  <td width="10%" class="list"></td>
		</tr>
			  <?php $i = 0; foreach ($a_route53 as $route53): ?>
                <tr>
				  <td class="listlr">
				  <?php $iflist = get_configured_interface_with_descr();
				  		foreach ($iflist as $if => $ifdesc):
							if ($route53['interface'] == $if): ?>
								<?=$ifdesc; break;?>
					<?php endif; endforeach; ?>
				  </td>
                  <td class="listr">
					<?=htmlspecialchars($route53['host']);?>.<?=htmlspecialchars($route53['domain']);?>
                  </td>
                  
                  <td class="listlr">
                         <?php
                                $filename = "/conf/route53-{$if}-{$route53['host']}.{$route53['domain']}.ipcheck";
                                $ipaddr = get_interface_ip($if);
                                if(file_exists($filename)) {
                                	$cached_ip_s = split(":", file_get_contents($filename));
                                	$cached_ip = $cached_ip_s[0];
	                        	if(strlen($cached_ip)>1) {
                                		if($ipaddr <> $cached_ip) 
                                  		echo "<font color='red'>";
                                  		else
                                  		echo "<font color='green'>";
                                  		echo htmlspecialchars($cached_ip);
                                  		echo "</font>"; }
                                	else
                                	echo "Waiting data..."; } 
                                else
                                echo "Waiting data...";
                        ?>                 

                  </td>


                  <td class="listbg">
                    <?=htmlspecialchars($route53['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="services_route53_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="services_route53.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this client?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="4">&nbsp;</td>
                  <td class="list"> <a href="services_route53_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
				<tr>
				<td colspan="3" class="list"><p class="vexpl"><span class="red"><strong>
				  <br>
				  </strong></span>
				  
				  </td>
				<td class="list">&nbsp;</td>
				</tr>
              </table>
	      </div>
	</td>
                                <tr>
                                <td colspan="3" class="list"><p class="vexpl"><span class="red"><strong>
                                  <?=gettext("Note:");?><br>
                                  </strong></span>
                                  <?=gettext("Green IP addresses means that your IP are synchronized with DNS record.");?>
                                  </td>
                                <td class="list">&nbsp;</td>
                                </tr>

	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
