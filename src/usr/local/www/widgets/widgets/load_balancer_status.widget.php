<?php
/*
	load_balancer_status.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>.
 *	Copyright (c)  2005-2008 Bill Marquette
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("vslb.inc");

$now = time();
$year = date("Y");

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
if (!is_array($config['load_balancer']['virtual_server'])) {
	$config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];
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
						({$server['ip']['avail']})
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