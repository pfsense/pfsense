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
require_once("/usr/local/www/widgets/include/dyn_dns_status.inc");

if (!is_array($config['dyndnses']['dyndns']))
	$config['dyndnses']['dyndns'] = array();

$a_dyndns = &$config['dyndnses']['dyndns'];

?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td width="5%"  class="listhdrr"><?=gettext("Int.");?></td>
		<td width="15%" class="listhdrr"><?=gettext("Service");?></td>
		<td width="20%" class="listhdrr"><?=gettext("Hostname");?></td>
		<td width="20%" class="listhdrr"><?=gettext("Cached IP");?></td>
	</tr>
	<?php $i = 0; foreach ($a_dyndns as $dyndns): ?>
	<tr ondblclick="document.location='services_dyndns_edit.php?id=<?=$i;?>'">
		<td class="listlr">
		<?php $iflist = get_configured_interface_with_descr();
		foreach ($iflist as $if => $ifdesc) {
			if ($dyndns['interface'] == $if) {
				if (!isset($dyndns['enable']))
					echo "<span class=\"gray\">{$ifdesc}</span>";
				else
					echo "{$ifdesc}";
				break;
			}
		}
		$groupslist = return_gateway_groups_array();
		foreach ($groupslist as $if => $group) {
			if ($dyndns['interface'] == $if) {
				if (!isset($dyndns['enable']))
					echo "<span class=\"gray\">{$if}</span>";
				else
					echo "{$if}";
				break;
			}
		}
		?>
		</td>
		<td class="listlr">
		<?php
		$types = explode(",", DYNDNS_PROVIDER_DESCRIPTIONS);
		$vals = explode(" ", DYNDNS_PROVIDER_VALUES);
		for ($j = 0; $j < count($vals); $j++) 
			if ($vals[$j] == $dyndns['type']) { 
				if (!isset($dyndns['enable']))
					echo "<span class=\"gray\">".htmlspecialchars($types[$j])."</span>";
				else
					echo htmlspecialchars($types[$j]);
				break;
			}
		?>
		</td>
		<td class="listr">
		<?php
		if (!isset($dyndns['enable']))
			echo "<span class=\"gray\">".htmlspecialchars($dyndns['host'])."</span>";
		else
			echo htmlspecialchars($dyndns['host']);
		?>
		</td>
		<td class="listlr">
		<?php
		$filename = "{$g['conf_path']}/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . "{$dyndns['id']}.cache";
		if (file_exists($filename)) {
			$ipaddr = dyndnsCheckIP($dyndns['interface']);
			$cached_ip_s = split(":", file_get_contents($filename));
			$cached_ip = $cached_ip_s[0];
			if ($ipaddr <> $cached_ip)
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
	</tr>
	<?php $i++; endforeach; ?>
</table>
