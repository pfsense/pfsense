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

if (isset($config['ipsec']['phase1'])) { ?>
	<div>&nbsp;</div>
	<?php
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
	} else if (!is_array($ipsec_status['query']['ikesalist']['ikesa']))
		$ipsec_status['query']['ikesalist']['ikesa'] = array();

	$ipsec_detail_array = array();
	$ikev1num = array();
	if (isset($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase2'] as $ph2ent) {
			if ($ph2ent['remoteid']['type'] == "mobile")
				continue;
			if (!ipsec_lookup_phase1($ph2ent,$ph1ent))
				continue;

			if (isset($ph1ent['disabled']) || isset($ph2ent['disabled']))
				continue;

			if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == 'ikev1') {
				if (!isset($ikev1num[$ph1ent['ikeid']]))
					$ikev1num[$ph1ent['ikeid']] = 0;
				else
					$ikev1num[$ph1ent['ikeid']]++;
				$ikeid = "con{$ph1ent['ikeid']}00" . $ikev1num[$ph1ent['ikeid']];
			} else
				$ikeid = "con{$ph1ent['ikeid']}";

			$found = false;
			foreach ($ipsec_status['query']['ikesalist']['ikesa'] as $ikesa) {
				if ($ikeid == $ikesa['peerconfig']) {
					$found = true;
					$ph2ikeid = $ikesa['id'];
					if (ipsec_phase1_status($ipsec_status['query']['ikesalist']['ikesa'], $ph2ikeid)) {
						/* tunnel is up */
						$iconfn = "true";
						$activecounter++;
					} else {
						/* tunnel is down */
						$iconfn = "false";
						$inactivecounter++;
					}
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
	unset($ikev1num);
}

	if (isset($config['ipsec']['phase2'])) { ?>

<div id="ipsec-Overview" style="display:block;background-color:#EEEEEE;">
	<div>
	<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="heading">
	<tr>
		<td class="listhdrr nowrap">Active Tunnels</td>
		<td class="listhdrr nowrap">Inactive Tunnels</td>
		<td class="listhdrr nowrap">Mobile Users</td>
	</tr>
	<tr>
		<td class="listlr"><?php echo $activecounter; ?></td>
		<td class="listr"><?php echo $inactivecounter; ?></td>
		<td class="listr"><?php if (is_array($mobile['pool'])) echo htmlspecialchars($mobile['pool'][0]['usage']); else echo 0; ?></td>
	</tr>
	</table>
	</div>
</div>

<div id="ipsec-tunnel" style="display:none;background-color:#EEEEEE;">
	<div style="padding: 10px">
		<div style="display:table-row;">
			<div class="widgetsubheader" style="display:table-cell;width:40px">Source</div>
			<div class="widgetsubheader" style="display:table-cell;width:100px">Destination</div>
			<div class="widgetsubheader" style="display:table-cell;width:90px">Description</div>
			<div class="widgetsubheader" style="display:table-cell;width:30px">Status</div>
		</div>
		<div style="max-height:105px;overflow:auto;">

	<?php foreach ($ipsec_detail_array as $ipsec) : ?>
	
		<div style="display:table-row;">
			<div class="listlr" style="display:table-cell;width:39px">
				<?php echo htmlspecialchars($ipsec['src']);?>
			</div>
			<div class="listr"  style="display:table-cell;width:100px">
				<?php echo $ipsec['remote-subnet'];?>
				<br />
				(<?php echo htmlspecialchars($ipsec['dest']);?>)
			</div>
			<div class="listr"  style="display:table-cell;width:90px">
				<?php echo htmlspecialchars($ipsec['descr']);?>
			</div>
			<div class="listr"  style="display:table-cell;width:37px" align="center">
			<?php

			if ($ipsec['status'] == "true") {
				/* tunnel is up */
				$iconfn = "interface_up";
			} else {
				/* tunnel is down */
				$iconfn = "interface_down";
			}

			echo "<img src ='/themes/{$g['theme']}/images/icons/icon_{$iconfn}.gif' alt='Tunnel status' width='11' height='11' />";
			?>
			</div>
		</div>
	<?php endforeach; ?>
	</div>
 </div>
</div>
<div id="ipsec-mobile" style="display:none;background-color:#EEEEEE;">
	<div style="padding: 10px">
		<div style="display:table-row;">
			<div class="widgetsubheader" style="display:table-cell;width:140px">User</div>
			<div class="widgetsubheader" style="display:table-cell;width:130px">IP</div>
			<div class="widgetsubheader" style="display:table-cell;width:30px">Status</div>
		</div>
		<div style="max-height:105px;overflow:auto;">
<?php
	if (is_array($mobile['pool'])):
	foreach ($mobile['pool'] as $pool):
		if (is_array($pool['lease'])): 
			foreach ($pool['lease'] as $muser) : ?>
		<div style="display:table-row;">
			<div class="listlr" style="display:table-cell;width:139px">
				<?php echo htmlspecialchars($muser['id']);?><br />
			</div>
			<div class="listr"  style="display:table-cell;width:130px">
				<?php echo htmlspecialchars($muser['host']);?><br />
			</div>
			<div class="listr"  style="display:table-cell;width:30px">
				<?php echo htmlspecialchars($muser['status']);?><br/>
			</div>
		</div>
<?php
			endforeach;
		endif;
	endforeach;
	endif;
?>
		</div>
	</div>
</div>
<?php //end ipsec tunnel
}//end if tunnels are configured, else show code below
else { ?>
<div style="display:block">
	 <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="note">
	  <tr>
	    <td colspan="4">
	        <span class="vexpl">
	          <span class="red">
	            <strong>
	              Note: There are no configured IPsec Tunnels<br />
	            </strong>
	          </span>
	          You can configure your IPsec
	          <a href="vpn_ipsec.php">here</a>.
	        </span>
		</td>
	  </tr>
	</table>
</div>
<?php } ?>
