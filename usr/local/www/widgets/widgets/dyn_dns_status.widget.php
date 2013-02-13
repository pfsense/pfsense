<?php
/*
    Original status page code from: services_dyndns.php
    Copyright (C) 2008 Ermal Lu\xe7i
    Edits to convert it to a widget: dyn_dns_status.php
    Copyright (C) 2013 Stanley P. Miller \ stan-qaz
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

/* added the dyn_dns_status.inc file to the .inc directory, 
copied from dyn_dns_status.inc and edited it to work with this file */

require_once("/usr/local/www/widgets/include/dyn_dns_status.inc");


if (!is_array($config['dyndnses']['dyndns']))
	$config['dyndnses']['dyndns'] = array();

$a_dyndns = &$config['dyndnses']['dyndns'];

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
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td>
      <tr>
        <td width="5%"  class="listhdrr"><?=gettext("Int.");?></td>
        <td width="15%" class="listhdrr"><?=gettext("Service");?></td>
        <td width="20%" class="listhdrr"><?=gettext("Hostname");?></td>
        <td width="20%" class="listhdrr"><?=gettext("Cached IP");?></td>
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
              for ($j = 0; $j < count($vals); $j++) 
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
              $filename = "{$g['conf_path']}/dyndns_{$if}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . "{$dyndns['id']}.cache";
              $ipaddr = dyndnsCheckIP($if);
              if(file_exists($filename)) {
                $cached_ip_s = split(":", file_get_contents($filename));
                $cached_ip = $cached_ip_s[0];
                if($ipaddr <> $cached_ip) echo "<font color='red'>";
                  else echo "<font color='green'>";
                echo htmlspecialchars($cached_ip);
                echo "</font>";
              } else echo "N/A";
            ?>
          </td>
        </tr>
      <?php $i++; endforeach; ?>
    </td>
  </tr>
</table>
