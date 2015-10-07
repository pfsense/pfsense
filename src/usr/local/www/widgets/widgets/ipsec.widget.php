<?php
/*
	ipsec.widget.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	Copyright 2007 Scott Dale
	Part of pfSense widgets (https://www.pfsense.org)
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
	and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("ipsec.inc");

if (isset($config['ipsec']['phase1'])) {
	$tab_array = array();
	$tab_array[0] = array("Overview", true, "ipsec-Overview");
	$tab_array[1] = array("Tunnels", false, "ipsec-tunnel");
	$tab_array[2] = array("Mobile", false, "ipsec-mobile");

	display_widget_tabs($tab_array);

	$spd = ipsec_dump_spd();
	$sad = ipsec_dump_sad();
	$mobile = ipsec_dump_mobile();
	$ipsec_status = ipsec_smp_dump_status();

	$activecounter = 0;
	$inactivecounter = 0;

	if (!is_array($ipsec_status['query'])) {
		$ipsec_status['query'] = array();
		$ipsec_status['query']['ikesalist'] = array();
		$ipsec_status['query']['ikesalist']['ikesa'] = array();
	} else if (!is_array($ipsec_status['query']['ikesalist'])) {
		$ipsec_status['query']['ikesalist'] = array();
		$ipsec_status['query']['ikesalist']['ikesa'] = array();
	} else if (!is_array($ipsec_status['query']['ikesalist']['ikesa'])) {
		$ipsec_status['query']['ikesalist']['ikesa'] = array();
	}

	$ipsec_detail_array = array();
	$ikenum = array();
	if (isset($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase2'] as $ph2ent) {
			if (!ipsec_lookup_phase1($ph2ent,$ph1ent)) {
				continue;
			}

			if ($ph2ent['remoteid']['type'] == "mobile" || isset($ph1ent['mobile'])) {
				continue;
			}
			if (isset($ph1ent['disabled']) || isset($ph2ent['disabled'])) {
				continue;
			}

			if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == 'ikev1') {
				if (!isset($ikenum[$ph1ent['ikeid']])) {
					$ikenum[$ph1ent['ikeid']] = 0;
				} else {
					$ikenum[$ph1ent['ikeid']]++;
				}
				$ikeid = "con{$ph1ent['ikeid']}00" . $ikenum[$ph1ent['ikeid']];
			} else {
				if (isset($ikenum[$ph1ent['ikeid']])) {
					continue;
				}
				$ikeid = "con{$ph1ent['ikeid']}";
				$ikenum[$ph1ent['ikeid']] = true;
			}

			$found = false;
			foreach ($ipsec_status['query']['ikesalist']['ikesa'] as $ikesa) {
				if (isset($ikesa['childsalist']) && isset($ikesa['childsalist']['childsa'])) {
					foreach ($ikesa['childsalist']['childsa'] as $childsa) {
						if ($ikeid == $childsa['childconfig']) {
							$found = true;
							break;
						}
					}
				} else if ($ikeid == $ikesa['peerconfig']) {
					$found = true;
				}

				if ($found === true) {
					if ($ikesa['status'] == 'established') {
						/* tunnel is up */
						$iconfn = "true";
						$activecounter++;
					} else {
						/* tunnel is down */
						$iconfn = "false";
						$inactivecounter++;
					}
					break;
				}
			}

			if ($found === false) {
				/* tunnel is down */
				$iconfn = "false";
				$inactivecounter++;
			}

			$ipsec_detail_array[] = array('src' => convert_friendly_interface_to_friendly_descr($ph1ent['interface']),
					'dest' => $ph1ent['remote-gateway'],
					'remote-subnet' => ipsec_idinfo_to_text($ph2ent['remoteid']),
					'descr' => $ph2ent['descr'],
					'status' => $iconfn);
		}
	}
	unset($ikenum);
}

if (isset($config['ipsec']['phase2'])): ?>
	<table class="table">
		<thead>
		<tr>
			<th>Active Tunnels</td>
			<th>Inactive Tunnels</td>
			<th>Mobile Users</td>
		</tr>
		</thead>
		<tbody>
		<tr>
			<td><?=$activecounter; ?></td>
			<td><?=$inactivecounter; ?></td>
			<td><?=(is_array($mobile['pool']) ? htmlspecialchars($mobile['pool'][0]['usage']) : '0'); ?></td>
		</tr>
		</tbody>
	</table>

	<table class="table table-striped table-hover">
	<thead>
		<th>Source</th>
		<th>Destination</th>
		<th>Description</th>
		<th>Status</th>
	</thead>
	<tbody>
	<?php foreach ($ipsec_detail_array as $ipsec) : ?>
		<tr>
			<td><?php echo htmlspecialchars($ipsec['src']);?></td>
			<td><?php echo $ipsec['remote-subnet'];?><br />(<?php echo htmlspecialchars($ipsec['dest']);?>)</td>
			<td><?php echo htmlspecialchars($ipsec['descr']);?></td>
			<td>
				<?php if ($ipsec['status'] == "true"): ?>
					<i class="icon icon-chevron-up"></i>
				<?php else: ?>
					<i class="icon icon-chevron-down"></i>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
	</table>

	<?php if (is_array($mobile['pool'])): ?>
		<table class="table table-striped table-hover">
		<thead>
			<th>User</th>
			<th>IP</th>
			<th>Status</th>
		</thead>
		<tbody>

		<?php foreach ($mobile['pool'] as $pool):
			if (!is_array($pool['lease']))
				continue;

			foreach ($pool['lease'] as $muser) : ?>
				<tr>
					<td><?php echo htmlspecialchars($muser['id']);?></td>
					<td><?php echo htmlspecialchars($muser['host']);?></td>
					<td><?php echo htmlspecialchars($muser['status']);?></td>
				</tr>
		<?php
			endforeach;
		endforeach; ?>
		</tbody>
		</table>
	<?php endif;?>
<?php else: ?>
	<div>
		<h5 style="padding-left:10px;">There are no configured IPsec Tunnels</h5>
		<p  style="padding-left:10px;">You can configure your IPsec <a href="vpn_ipsec.php">here</a>.</p>
	</div>
<?php endif; ?>