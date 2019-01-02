<?php
/*
 * load_balancer_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>.
 * Copyright (c) 2005-2008 Bill Marquette
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("vslb.inc");

$now = time();
$year = date("Y");

init_config_arr(array('load_balancer', 'virtual_server'));
$a_vs = &$config['load_balancer']['virtual_server'];
init_config_arr(array('load_balancer', 'lbpool'));
$a_pool = &$config['load_balancer']['lbpool'];
$rdr_a = get_lb_redirects();
$relay_hosts = get_lb_summary();

$lb_logfile = "{$g['varlog_path']}/relayd.log";
$nentries = $config['syslog']['nentries'];
if (!$nentries) {
	$nentries = 50;
}

?>

<table class="table">
<thead>
	<tr>
		<th><?=gettext('Server')?></th>
		<th><?=gettext('Pool')?></th>
		<th><?=gettext('Description')?></th>
	</tr>
</thead>
<tbody>
	<?php foreach ($a_vs as $vsent): ?>
	<tr>
		<?php
		switch (trim($rdr_a[$vsent['name']]['status'])) {
			case 'active':
				$bgcolor = "success";
				$rdr_a[$vsent['name']]['status'] = gettext("Active");
				break;
			case 'down':
				$bgcolor = "danger";
				$rdr_a[$vsent['name']]['status'] = gettext("Down");
				break;
			default:
				$bgcolor = "info";
				$rdr_a[$vsent['name']]['status'] = gettext('Unknown - relayd not running?');
		}
		?>
		<td>
			<?=$vsent['name'];?><br />
			<span class="bg-<?=$bgcolor?>" style="display: block"><i><?=$rdr_a[$vsent['name']]['status']?></i></span>
			<?=$vsent['ipaddr'].":".$vsent['port'];?><br />
		</td>
		<td>
			<table>
			<?php
			foreach ($a_pool as $pool) {
				if ($pool['name'] == $vsent['poolname']) {
					$pool_hosts=array();
					foreach ((array) $pool['servers'] as $server) {
						$svr['ip']['addr']=$server;
						$svr['ip']['state']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['state'];
						$svr['ip']['avail']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['avail'];
						$pool_hosts[]=$svr;
					}
					foreach ((array) $pool['serversdisabled'] as $server) {
						$svr['ip']['addr']="$server";
						$svr['ip']['state']='disabled';
						$svr['ip']['avail']='disabled';
						$pool_hosts[]=$svr;
					}
					asort($pool_hosts);
					foreach ((array) $pool_hosts as $server) {
						if (empty($server['ip']['addr'])) {
							continue;
						}

						switch ($server['ip']['state']) {
							case 'up':
								$bgcolor = "success";
								$checked = "checked";
								break;
							case 'disabled':
								$bgcolor = "info";
								$checked = "";
								break;
							default:
								$bgcolor = "danger";
								$checked = "checked";
						}
?>
				<tr class="bg-<?=$bgcolor?>">
					<td><?=$server['ip']['addr']?>:<?=$pool['port']?></td>
					<td>
						<?php if ($server['ip']['avail']): ?>
						&nbsp;(<?= $server['ip']['avail'] ?>)
						<?php endif; ?>
					</td>
				</tr>
<?php
					}
				}
			}
?>
			</table>
		</td>
		<td><?=htmlspecialchars($vsent['descr']);?></td>
	</tr>
	<?php endforeach; ?>
</tbody>
</table>
