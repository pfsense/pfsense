<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (www.pfsense.com)
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

	if (isset($config['ipsec']['phase1'])){?>
	<div>&nbsp;</div>
	<?php	
	$tab_array = array();
	$tab_array[0] = array("Overview", true, "ipsec-Overview");
	$tab_array[1] = array("Tunnel Status", false, "ipsec-tunnel");
	display_widget_tabs($tab_array);

	$spd = ipsec_dump_spd();
	$sad = ipsec_dump_sad();

	$activecounter = 0;
	$inactivecounter = 0;
	
	$ipsec_detail_array = array();
		foreach ($config['ipsec']['phase2'] as $ph2ent){ 
			ipsec_lookup_phase1($ph2ent,$ph1ent);
			$ipsecstatus = false;
			
			$tun_disabled = "false";
			$foundsrc = false;
			$founddst = false; 
	
			if (isset($ph1ent['disabled']) || isset($ph2ent['disabled'])) {
				$tun_disabled = "true";
				continue;
			}
			
			if(ipsec_phase2_status($spd,$sad,$ph1ent,$ph2ent)) {
				/* tunnel is up */
				$iconfn = "true";
				$activecounter++;
			} else {
				/* tunnel is down */
				$iconfn = "false";
				$inactivecounter++;
			}
			
			$ipsec_detail_array[] = array('src' => $ph1ent['interface'],
						'dest' => $ph1ent['remote-gateway'],
						'remote-subnet' => ipsec_idinfo_to_text($ph2ent['remoteid']),
						'descr' => $ph2ent['descr'],
						'status' => $iconfn,
						'disabled' => $tun_disabled);
		}
	}
	
	if (isset($config['ipsec']['phase2'])){ ?>

<div id="ipsec-Overview" style="display:block;background-color:#EEEEEE;">
	<div>
	  <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">	
		  <tr>
		        <td nowrap class="listhdrr">Active Tunnels</td>
		        <td nowrap class="listhdrr">Inactive Tunnels</td>
			</tr>
			<tr>
				<td class="listlr"><?=$activecounter;?></td>
				<td class="listr"><?=$inactivecounter;?></td>
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
	<?php
	foreach ($ipsec_detail_array as $ipsec) :
		
		if ($ipsec['disabled'] == "true"){
			$spans = "<span class=\"gray\">";
			$spane = "</span>";
		} 
		else {
			$spans = $spane = "";
		}		

		?>
	
		<div style="display:table-row;">
			<div class="listlr" style="display:table-cell;width:39px">
				<?=$spans;?>
					<?=htmlspecialchars($ipsec['src']);?>						
				<?=$spane;?>
			</div>
			<div class="listr"  style="display:table-cell;width:100px"><?=$spans;?>			
				<?=$ipsec['remote-subnet'];?>
				<br/>
				(<?=htmlspecialchars($ipsec['dest']);?>)<?=$spane;?>
			</div>
			<div class="listr"  style="display:table-cell;width:90px"><?=$spans;?><?=htmlspecialchars($ipsec['descr']);?><?=$spane;?></div>
			<div class="listr"  style="display:table-cell;width:37px"><?=$spans;?><center>
			<?php 
			
			if($ipsec['status'] == "true") { 
				/* tunnel is up */
				$iconfn = "interface_up";
			} else {
				/* tunnel is down */
				$iconfn = "interface_down";
			}
			
			echo "<img src ='/themes/{$g['theme']}/images/icons/icon_{$iconfn}.gif'>";
						
			?></center><?=$spane;?></div>
		</div>
	<?php endforeach; ?>
	</div>
 </div>
</div><?php //end ipsec tunnel
}//end if tunnels are configured, else show code below
else { ?>
<div style="display:block">
	 <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
	  <tr>
	    <td colspan="4">
			  <p>
	        <span class="vexpl">
	          <span class="red">
	            <strong>
	              Note: There are no configured IPsec Tunnels<br />
	            </strong>
	          </span>
	          You can configure your IPsec 
	          <a href="vpn_ipsec.php">here</a>.
	        </span>
	      </p>
		</td>
	  </tr>
	</table>
</div>
<? } ?>


