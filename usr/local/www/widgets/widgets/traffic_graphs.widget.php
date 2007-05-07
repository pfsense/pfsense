<?php $i = 0; $ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
					for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
						$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
					}?>

	<?php 
					//set variables for traffic graph
					$width = "347";
					$height = "150";
		
					 if (get_cpu_speed() >= 500) 
						$firstgraphshown = false;
					else
						$firstgraphshown = true;
					foreach ($ifdescrs as $ifdescr => $ifname){
						$ifinfo = get_interface_info($ifdescr);					
						$ifnum = convert_friendly_interface_to_real_interface_name($ifname);
						
					 if ($ifinfo['status'] != "down"){ 					
					?>
					<div id="<?=$ifname;?>trafficdiv" style="padding: 5px">
						<div id="<?=$ifname;?>topic" class="widgetsubheader">
							<div style="float:left;width:49%">
								<a href="/status_graph.php?if=<?=$ifdescr;?>"><span style="color:#000000">Current <?=$ifname;?> Traffic</span></a>
							</div>
							<div align="right" style="float:right;width:49%">
							<?php 										
						 	if(!$firstgraphshown){?>
								<div id="<?=$ifname;?>graphdiv-min" onclick='return minimizeDiv("<?php echo $ifname;?>graphdiv",true)' style="display:inline; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_minus.gif" alt="Minimize <?=$ifname;?> traffic graph" /></div>
								<?php $firstgraphshown = true;?>
								<div id="<?=$ifname;?>graphdiv-open" onclick='return showDiv("<?php echo $ifname;?>graphdiv",true)' style="display:none; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_open.gif" alt="Show <?=$ifname;?> traffic graph" /></div>												
							</div><div style="clear:both;"></div>
						</div>
						
						<div id="<?=$ifname;?>graphdiv">
							<embed id="graph" src="graph.php?ifnum=<?=$ifnum;?>&ifname=<?=rawurlencode($ifname);?>" type="image/svg+xml" width="<? echo $width; ?>" height="<? echo $height; ?>" pluginspage="http://www.adobe.com/svg/viewer/install/auto" />
						</div>					
						<? } else { ?>
								<div id="<?=$ifname;?>graphdiv-open" onclick='return showDiv("<?php echo $ifname;?>graphdiv",true)' style="display:inline; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_open.gif" alt="Show <?=$ifname;?> traffic graph" /></div>												
								<div id="<?=$ifname;?>graphdiv-min" onclick='return minimizeDiv("<?php echo $ifname;?>graphdiv",true)' style="display:none; cursor:pointer"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_minus.gif" alt="Minimize <?=$ifname;?> traffic graph" /></div>
							</div><div style="clear:both;"></div>
						</div>
						<div id="<?=$ifname;?>graphdiv" style="display: none">
								<embed id="graph" src="graph.php?ifnum=<?=$ifnum;?>&ifname=<?=rawurlencode($ifname);?>" type="image/svg+xml" width="<? echo $width; ?>" height="<? echo $height; ?>" pluginspage="http://www.adobe.com/svg/viewer/install/auto" />
						</div>
				<? } ?>
			</div>											 
	 <? } 
	} ?>
