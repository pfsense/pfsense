<?php
/* $Id$ */
/*
	Copyright (C) 2008 Ermal Luçi
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
	pfSense_BUILDER_BINARIES:	/usr/bin/host	
	pfSense_MODULE:	dyndns
*/

##|+PRIV
##|*IDENT=page-services-dynamicdnsclients
##|*NAME=Services: Dynamic DNS clients page
##|*DESCR=Allow access to the 'Services: Dynamic DNS clients' page.
##|*MATCH=services_dyndns.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['dyndnses']['dyndns']))
	$config['dyndnses']['dyndns'] = array();

$a_dyndns = &$config['dyndnses']['dyndns'];

if ($_GET['act'] == "del") {
		unset($a_dyndns[$_GET['id']]);

		write_config();

		header("Location: services_dyndns.php");
		exit;
}

function dyndnsCheckIP($int) {

	$ip_address = get_interface_ip($int);
	if (is_private_ip($ip_address)) {
		$hosttocheck = "checkip.dyndns.org";
		$checkip = gethostbyname($hosttocheck);
		$ip_ch = curl_init("http://{$checkip}");
		curl_setopt($ip_ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ip_ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ip_ch, CURLOPT_INTERFACE, $ip_address);
		$ip_result_page = curl_exec($ip_ch);
		curl_close($ip_ch);
		$ip_result_decoded = urldecode($ip_result_page);
		preg_match('=Current IP Address: (.*)</body>=siU', $ip_result_decoded, $matches);
		$ip_address = trim($matches[1]);
	}

	return $ip_address;
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS clients"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_dyndns.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("DynDns"), true, "services_dyndns.php");
	$tab_array[] = array(gettext("RFC 2136"), false, "services_rfc2136.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
	<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
		  <td width="5%"  class="listhdrr"></td>
		  <td width="15%" class="listhdrr"><?=gettext("Service");?></td>
                  <td width="20%" class="listhdrr"><?=gettext("Hostname");?></td>
                  <td width="20%" class="listhdrr"><?=gettext("Cached IP");?></td>
                  <td width="50%" class="listhdr"><?=gettext("Description");?></td>
                  <td width="10%" class="list"></td>
		</tr>
		  <?php $i = 0; foreach ($a_dyndns as $dyndns): ?>
                <tr  ondblclick="document.location='services_dyndns_edit.php?id=<?=$i;?>'">
		  <td class="listlr">
		  <?php $iflist = get_configured_interface_with_descr();
	  		foreach ($iflist as $if => $ifdesc):
				if ($dyndns['interface'] == $if): ?>
					<?=$ifdesc; break;?>
		  <?php endif; endforeach; ?>
		  </td>
                  <td class="listlr">
				  <?php
					$types = explode(",", "DNS-O-Matic, DynDNS (dynamic),DynDNS (static),DynDNS (custom),DHS,DyNS,easyDNS,No-IP,ODS.org,ZoneEdit,Loopia,freeDNS, DNSexit, OpenDNS, Namecheap, HE.net");
					$vals = explode(" ", "dnsomatic dyndns dyndns-static dyndns-custom dhs dyns easydns noip ods zoneedit loopia freedns dnsexit opendns namecheap he-net");
					$j = 0; for ($j = 0; $j < count($vals); $j++) 
                      			if ($vals[$j] == $dyndns['type']) { 
                      				echo htmlspecialchars($types[$j]);
							break;
					}
				?>
                  </td>
                  <td class="listr">
					<?=htmlspecialchars($dyndns['host']);?>
                  </td>
                  <td class="listlr">
			<?php
				$filename = "{$g['conf_path']}/dyndns_{$if}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . ".cache";
				$ipaddr = dyndnsCheckIP($if);
				if(file_exists($filename)) {
					$cached_ip_s = split(":", file_get_contents($filename));
					$cached_ip = $cached_ip_s[0];
					if($ipaddr <> $cached_ip) 
						echo "<font color='red'>";
					else 
						echo "<font color='green'>";
						echo htmlspecialchars($cached_ip);
						echo "</font>";
				} else {
					echo "N/A";
				}
			?>
		  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($dyndns['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="services_dyndns_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="services_dyndns.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="5"></td>
                  <td class="list"> <a href="services_dyndns_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
				<tr>
				<td colspan="3" class="list"><p class="vexpl"><span class="red"><strong>
				  <?=gettext("Note:");?><br>
				  </strong></span>
				  <?=gettext("IP addresses appearing in green are up to date with Dynamic DNS provider.");?>
				  </td>
				<td class="list">&nbsp;</td>
				</tr>
              </table>
	      </div>
	</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
