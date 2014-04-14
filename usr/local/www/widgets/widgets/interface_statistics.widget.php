<?php
/*
        $Id: interface_statistics.widget.php
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
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/interface_statistics.inc");

		$ifdescrs = get_configured_interface_with_descr();

		$array_in_packets = array();
		$array_out_packets = array();
		$array_in_bytes = array();
		$array_out_bytes = array();
		$array_in_errors = array();
		$array_out_errors = array();
		$array_collisions = array();
		$array_interrupt = array();
		$interfacecounter = 0;

		//build data arrays
		foreach ($ifdescrs as $ifdescr => $ifname){
			$ifinfo = get_interface_info($ifdescr);
			$interfacecounter++;
			if ($ifinfo['status'] != "down"){
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
			}


		}//end for




		?>
              <div id="int_labels" style="float:left;width:32%">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="interfaces statistics">
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

	          <table width="100%" border="0" cellspacing="0" cellpadding="0" summary="the stats">
	              <tr>
		                <?php
		               		$interface_names = array();
		                	foreach ($ifdescrs as $ifdescr => $ifname):
		                	$ifinfo = get_interface_info($ifdescr);
		                	if ($ifinfo['status'] != "down"){ ?>
			                	<td class="widgetsubheader nowrap" style="height:25px">
			                  		<?=htmlspecialchars($ifname);?>
								</td>
							<?php
							//build array of interface names
							$interface_names[] = $ifname;
							}
							endforeach; ?>
		              </tr>

		              <tr>
			                <?php
			                $counter = 1;
			                foreach ($array_in_packets as $data): ?>
							<td class="listr nowrap" id="stat<?php echo $counter?>" style="height:25px">
								<?=htmlspecialchars($data);?>
			                </td>
			                <?php
			                $counter = $counter + 7;
			                endforeach; ?>
	              		</tr>

	              		<tr>
			                <?php
			                $counter = 2;
			                foreach ($array_out_packets as $data): ?>
							<td class="listr nowrap" id="stat<?php echo $counter;?>" style="height:25px">
								<?=htmlspecialchars($data);?>
			                </td>
			                <?php
			                $counter = $counter + 7;
			                endforeach; ?>
	              		</tr>

			            <tr>
			                <?php 
			                $counter = 3;
			                foreach ($array_in_bytes as $data): ?>
							<td class="listr nowrap" id="stat<?php echo $counter;?>" style="height:25px">
								<?=htmlspecialchars($data);?>
			                </td>
			                <?php
			                $counter = $counter + 7;
			                endforeach; ?>
	          			</tr>

	          			<tr>
			                <?php
			                $counter = 4;
			                foreach ($array_out_bytes as $data): ?>
							<td class="listr nowrap" id="stat<?php echo $counter;?>" style="height:25px">
								<?=htmlspecialchars($data);?>
			                </td>
			                <?php
			                $counter = $counter + 7;
			                endforeach; ?>
	            		</tr>

	            		<tr>
			                <?php
			                $counter = 5;
				               foreach ($array_in_errors as $data): ?>
									<td class="listr nowrap" id="stat<?php echo $counter;?>" style="height:25px">
										<?=htmlspecialchars($data);?>
					                </td>
				                <?php
				                $counter = $counter + 7;
				                endforeach; ?>
	              		</tr>

		                <tr>
		                	<?php
		                	$counter = 6;
			               foreach ($array_out_errors as $data): ?>
								<td class="listr nowrap" id="stat<?php echo $counter;?>" style="height:25px">
									<?=htmlspecialchars($data);?>
				                </td>
			                <?php
			                $counter = $counter + 7;
			                endforeach; ?>
	             		</tr>

		                <tr>
		                	<?php
		                	$counter = 7;
				                foreach ($array_collisions as $data): ?>
								<td class="listr nowrap" id="stat<?php echo $counter;?>" style="height:25px">
									<?=htmlspecialchars($data);?>
				                </td>
			                <?php
			                $counter = $counter + 7;
			                endforeach; ?>
						</tr>
	             </table>
			</div>
