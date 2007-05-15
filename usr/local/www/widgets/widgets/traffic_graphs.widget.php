<?php $i = 0; $ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
					for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
						$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
					}?>
	<input type="hidden" id="traffic_graphs-config" name="traffic_graphs-config" value="">
	<?php 
					//set variables for traffic graph
					$width = "347";
					$height = "150";
					$showngraphlist = explode(",", $pconfig['traffic_graphs-config']);
					$graphcounter = 0;
					
					 if (get_cpu_speed() >= 500) 
						$firstgraphshown = false;
					else
						$firstgraphshown = true;
					foreach ($ifdescrs as $ifdescr => $ifname){
						$ifinfo = get_interface_info($ifdescr);					
						$ifnum = convert_friendly_interface_to_real_interface_name($ifname);
						$currentgraph = $showngraphlist[$graphcounter];
						$colposition = strpos($currentgraph,":");
						$currentgraph = substr($currentgraph, $colposition+1);
						if($config['widgets']['traffic_graphs-config']){
							if ($currentgraph =="show"){
								$mingraphbutton = "inline";
								$showgraphbutton = "none";
								$graphdisplay = "inline";
								$interfacevalue = "show";
							}
							else
							{
								$mingraphbutton = "none";
								$showgraphbutton = "inline";
								$graphdisplay = "none";
								$interfacevalue = "hide";
							}
						}
						else
						{
							if(!$firstgraphshown){
								$mingraphbutton = "inline";
								$showgraphbutton = "none";
								$graphdisplay = "inline";
								$interfacevalue = "show";
							}
							else
							{
								$mingraphbutton = "none";
								$showgraphbutton = "inline";
								$graphdisplay = "none";
								$interfacevalue = "hide";
							}
						}
						
						
					 if ($ifinfo['status'] != "down"){ 					
					?>
					<div id="<?=$ifname;?>trafficdiv" style="padding: 5px">
					<input type="hidden" id="<?php echo $ifname;?>_graph-config" name="<?php echo $ifname;?>_graph-config" class="graphsettings" value="<?=$interfacevalue;?>">
						<div id="<?=$ifname;?>topic" class="widgetsubheader">
							<div style="float:left;width:49%">
								<a href="/status_graph.php?if=<?=$ifdescr;?>"><span style="color:#000000">Current <?=$ifname;?> Traffic</span></a>
							</div>
							<div align="right" style="float:right;width:49%">
								<div id="<?=$ifname;?>graphdiv-min" onclick='return  trafficminimizeDiv("<?php echo $ifname;?>",true)' style="display:<?php echo $mingraphbutton;?>; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_minus.gif" alt="Minimize <?=$ifname;?> traffic graph" /></div>								
								<div id="<?=$ifname;?>graphdiv-open" onclick='return trafficshowDiv("<?php echo $ifname;?>",true)' style="display:<?php echo $showgraphbutton;?>; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_open.gif" alt="Show <?=$ifname;?> traffic graph" /></div>												
							</div>
							<div style="clear:both;"></div>
						</div>						
						<div id="<?=$ifname;?>graphdiv" style="display:<?php echo $graphdisplay;?>">
							<embed id="graph" src="graph.php?ifnum=<?=$ifnum;?>&ifname=<?=rawurlencode($ifname);?>" type="image/svg+xml" width="<? echo $width; ?>" height="<? echo $height; ?>" pluginspage="http://www.adobe.com/svg/viewer/install/auto" />
						</div>
				<? $firstgraphshown = true; $graphcounter++;} ?>
					</div>											 
	 <?  
	} 
?>
