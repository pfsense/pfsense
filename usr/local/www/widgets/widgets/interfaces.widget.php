<?php $i = 0; $ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
					for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
						$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
					}?>
			
	         <table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0">
			<?php 
			$counter = 1;
			foreach ($ifdescrs as $ifdescr => $ifname){
					$ifinfo = get_interface_info($ifdescr);
					$iswireless = is_interface_wireless($ifdescr);
				?>
				<tr> 
				<td class="vncellt" width="30%">
				<?php if($iswireless) {
						 if($ifinfo['status'] == "associated") { ?> 
		                  <img src="./themes/<?= $g['theme']; ?>/images/icons/icon_wlan.gif" />
		                  <? } else { ?>
		                  	<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_wlan_d.gif" />
		                  	<? } ?>
		              <? } else { ?>
		                  <img src="./themes/<?= $g['theme']; ?>/images/icons/icon_cablenic.gif"/>
		                  <? } ?>&nbsp; 
				<strong>
				<?php
				if ($ifname == "WAN") { ?>
					<a href="/interfaces_wan.php">					
				<? } else if ($ifname == "LAN") { ?>
					<a href="/interfaces_lan.php">
				<? } else { ?>				
					<a href="/interfaces_opt.php?index=<?=$counter; ?>">
				<? 
				$counter++;
				} ?>			
				
				
				<span style="color:#000000"><?=htmlspecialchars($ifname);?></span></a></strong>
				</td>
				<td width="70%"  class="listr">
				
				  <?php if ($ifinfo['dhcplink'] != "down" && $ifinfo['pppoelink'] != "down" && $ifinfo['pptplink'] != "down"){ ?>
	                 <?php if($ifinfo['status'] == "up") { ?> 
		                  <img src="./themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif" title="<?=$ifname;?> is up" />&nbsp; 
		                  <? } else if ($ifinfo['status'] == "down") { ?>
		                  <img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block.gif" title="<?=$ifname;?> is down" />&nbsp; 
		                  <? } else if ($ifinfo['status'] == "associated") { ?>
		                  <img src="./themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif" title="<?=$ifname;?> is associated" />&nbsp; 
		                  <? } else { ?><?=htmlspecialchars($ifinfo['status']);
				}?>				
				  <?php if ($ifinfo['ipaddr']){ ?>
	                 <?=htmlspecialchars($ifinfo['ipaddr']);}?>&nbsp;                 
		              <?=htmlspecialchars($ifinfo['media']);?>
	            </tr><?php }
			}
			?> 
			</table>