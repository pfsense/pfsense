
              <?php $i = 0; $ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
		for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
			$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
		}
		
		$array_in_packets = array();
		$array_out_packets = array();
		$array_in_bytes = array();
		$array_out_bytes = array();
		$array_in_errors = array();
		$array_out_errors = array();
		$array_collisions = array();
		$array_interrupt = array();
		
		//build data arrays
		foreach ($ifdescrs as $ifdescr => $ifname){
			$ifinfo = get_interface_info($ifdescr);
			
			$array_in_packets[] = $ifinfo['inpkts'];
			$array_out_packets[] = $ifinfo['outpkts'];
			$array_in_bytes[] = format_bytes($ifinfo['inbytes']);
			$array_out_bytes[] = format_bytes($ifinfo['outbytes']);
			if (isset($ifinfo['inerrs'])){
				$array_in_errors[] = $ifinfo['inerrs'];
				$array_out_errors[] = $ifinfo['outerrs'];
			}
			else{
				$array_in_errors[] = "n/a";
				$array_out_errors[] = "n/a";
			}
			if (isset($ifinfo['collisions']))
				$array_collisions[] = htmlspecialchars($ifinfo['collisions']);
			else
				$array_collisions[] = "n/a";
		
					
			
		}//end for
		
	
		
		
		?>
		<div style="padding: 5px">			
              <div id="int_labels" style="float:left;width:32%">
				<table width="100%" border="0" cellspacing="0" cellpadding="0">
					<tr><td class="widgetsubheader" style="height:25px">&nbsp;&nbsp;&nbsp;</td></tr>	
					<tr>
	                	<td class="vncellt" style="height:25px">Packets In</td>
					</tr>	              
		            <tr>
		                <td class="vncellt" style="height:25px">Packets Out</td>
		           </tr>	              
	               <tr>
	                <td class="vncellt" style="height:25px">Bytes In</td>
				    </tr>	              
	              <tr>
	                <td class="vncellt" style="height:25px">Bytes Out</td>
				  </tr>	              
	              <tr>
	                <td class="vncellt" style="height:25px">Errors In</td>
				 </tr>	              
	              <tr>
	                <td class="vncellt" style="height:25px">Errors Out</td>
				</tr>              
	              <tr>
	                <td class="vncellt" style="height:25px">Collisions</td>
				 </tr>	              
	              </table>
	          </div>
	          <div id="interfacestats" style="float:right;overflow: auto; width:68%">
	          <table width="100%" border="0" cellspacing="0" cellpadding="0">
	              <tr>
		                <tr>
		                <?php 
		                	foreach ($ifdescrs as $ifdescr => $ifname): ?>
			                	<td class="widgetsubheader" nowrap  style="height:25px">
			                  		<?=htmlspecialchars($ifname);?>
								</td>
							<?php endforeach; ?>
		              </tr>
		              
		              <tr>
			                <?php foreach ($array_in_packets as $data): ?>
							<td class="listr" nowrap style="height:25px">
								<?=htmlspecialchars($data);?>
			                </td>
			                <?php endforeach; ?>
	              		</tr>
	              		
	              		<tr>
			                <?php foreach ($array_out_packets as $data): ?>
							<td class="listr" nowrap style="height:25px">
								<?=htmlspecialchars($data);?>
			                </td>
			                <?php endforeach; ?>
	              		</tr>
			               
			            <tr>
			                <?php foreach ($array_in_bytes as $data): ?>
							<td class="listr" nowrap style="height:25px">
								<?=htmlspecialchars($data);?>
			                </td>
			                <?php endforeach; ?>
	          			</tr>
	          			
	          			<tr>
			                <?php foreach ($array_out_bytes as $data): ?>
							<td class="listr" nowrap style="height:25px">
								<?=htmlspecialchars($data);?>
			                </td>
			                <?php endforeach; ?>
	            		</tr>
	            		
	            		<tr>
			                <?php
				               foreach ($array_in_errors as $data): ?>
									<td class="listr" nowrap style="height:25px">
										<?=htmlspecialchars($data);?>
					                </td>
				                <?php endforeach; ?>
	              		</tr>
		                	
		                <tr>
		                	<?php 
			               foreach ($array_out_errors as $data): ?>
								<td class="listr" nowrap style="height:25px">
									<?=htmlspecialchars($data);?>
				                </td>
			                <?php endforeach; ?>
	             		</tr>
		                	
		                <tr>	
		                	<?php  
				                foreach ($array_collisions as $data): ?>
								<td class="listr" nowrap style="height:25px">
									<?=htmlspecialchars($data);?>
				                </td>
			                <?php endforeach; ?>
						</tr>
	             </table>
			</div>
	</div>