<?php
/* $Id$ */
/*
	diag_ipsec.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved. 
 *  Copyright (c)  2004, 2005 Scott Ullrich
 *
 *  Redistribution and use in source and binary forms, with or without modification, 
 *  are permitted provided that the following conditions are met: 
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution. 
 *
 *  3. All advertising materials mentioning features or use of this software 
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/). 
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
  *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

/*
	pfSense_MODULE: ipsec
*/

##|+PRIV
##|*IDENT=page-status-ipsec
##|*NAME=Status: IPsec page
##|*DESCR=Allow access to the 'Status: IPsec' page.
##|*MATCH=diag_ipsec.php*
##|-PRIV


global $g;

$pgtitle = array(gettext("Status"), gettext("IPsec"));
$shortcut_section = "ipsec";

require("guiconfig.inc");
include("head.inc");
require_once("ipsec.inc");

if ($_GET['act'] == 'connect') {
	if (ctype_digit($_GET['ikeid'])) {
		$ph1ent = ipsec_get_phase1($_GET['ikeid']);
		if (!empty($ph1ent)) {
			if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == 'ikev1') {
				$ph2entries = ipsec_get_number_of_phase2($_GET['ikeid']);
				for ($i = 0; $i < $ph2entries; $i++) {
					$connid = escapeshellarg("con{$_GET['ikeid']}00{$i}");
					mwexec("/usr/local/sbin/ipsec down {$connid}");
					mwexec("/usr/local/sbin/ipsec up {$connid}");
				}
			} else {
				mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']));
				mwexec("/usr/local/sbin/ipsec up con" . escapeshellarg($_GET['ikeid']));
			}
		}
	}
} else if ($_GET['act'] == 'ikedisconnect') {
	if (ctype_digit($_GET['ikeid'])) {
		if (!empty($_GET['ikesaid']) && ctype_digit($_GET['ikesaid'])) {
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']) . "[" . escapeshellarg($_GET['ikesaid']) . "]");
		} else {
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']));
		}
	}
} else if ($_GET['act'] == 'childdisconnect') {
	if (ctype_digit($_GET['ikeid'])) {
		if (!empty($_GET['ikesaid']) && ctype_digit($_GET['ikesaid'])) {
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']) . "{" . escapeshellarg($_GET['ikesaid']) . "}");
		}
	}
}

if (!is_array($config['ipsec']['phase1'])) {
	$config['ipsec']['phase1'] = array();
}

$a_phase1 = &$config['ipsec']['phase1'];

$status = ipsec_smp_dump_status();

$tab_array = array();
$tab_array[] = array(gettext("Overview"), true, "diag_ipsec.php");
$tab_array[] = array(gettext("Leases"), false, "diag_ipsec_leases.php");
$tab_array[] = array(gettext("SAD"), false, "diag_ipsec_sad.php");
$tab_array[] = array(gettext("SPD"), false, "diag_ipsec_spd.php");
$tab_array[] = array(gettext("Logs"), false, "diag_logs.php?logfile=ipsec");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading">IPSec status</div>
	<div class="panel-body table responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Local ID")?></th>
					<th><?=gettext("Local IP")?></th>
					<th><?=gettext("Remote ID")?></th>
					<th><?=gettext("Remote IP")?></th>
					<th><?=gettext("Role")?></th>
					<th><?=gettext("Reauth")?></th>
					<th><?=gettext("Algo")?></th>
					<th><?=gettext("Status")?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php
$ipsecconnected = array();

if (is_array($status['query']) && is_array($status['query']['ikesalist']) && is_array($status['query']['ikesalist']['ikesa'])):
	foreach ($status['query']['ikesalist']['ikesa'] as $ikeid => $ikesa):
		$con_id = substr($ikesa['peerconfig'], 3);
		
		if ($ikesa['version'] == 1) {
			$ph1idx = substr($con_id, 0, strrpos(substr($con_id, 0, -1), '00'));
			$ipsecconnected[$ph1idx] = $ph1idx;
		} else {
			$ipsecconnected[$con_id] = $ph1idx = $con_id;
		}

		if (ipsec_phase1_status($status['query']['ikesalist']['ikesa'], $ikesa['id']))
			$icon = "pass";
		elseif (!isset($config['ipsec']['enable']))
			$icon = "block";
		else
			$icon = "reject";
?>
				<tr>
					<td>
						<?=htmlspecialchars(ipsec_get_descr($ph1idx))?>
					</td>
					<td>
<?php
			if (!is_array($ikesa['local']))
				echo gettext("Unknown");
			else {
				if (!empty($ikesa['local']['identification'])) {
					if ($ikesa['local']['identification'] == '%any')
						print(gettext('Any identifier'));
					else
						print(htmlspecialchars($ikesa['local']['identification']));
				} else
					print(gettext("Unknown"));
			}

			if (ipsec_phase1_status($status['query']['ikesalist']['ikesa'], $ikesa['id'])) {
				$icon = "pass";
			} elseif (!isset($config['ipsec']['enable'])) {
				$icon = "block";
			} else {
				$icon = "reject";
			}
?>
					</td>
					<td>
<?php
			if (!is_array($ikesa['local']))
				print(gettext("Unknown"));
			else {
				if (!empty($ikesa['local']['address']))
					print(htmlspecialchars($ikesa['local']['address']) . '<br/>' .	gettext('Port: ') . htmlspecialchars($ikesa['local']['port']));
				else
					print(gettext("Unknown"));
				if ($ikesa['local']['port'] == '4500')
					print(" NAT-T");
			}
?>
					</td>	
					<td>
<?php
			if (!is_array($ikesa['remote']))
				print(gettext("Unknown"));
			else {
				$identity = "";
				if (!empty($ikesa['remote']['identification'])) {
					if ($ikesa['remote']['identification'] == '%any')
						$identity = 'Any identifier';
					else
						$identity = htmlspecialchars($ikesa['remote']['identification']);
				}

				if (is_array($ikesa['remote']['auth']) && !empty($ikesa['remote']['auth'][0]['identity'])) {
					print(htmlspecialchars($ikesa['remote']['auth'][0]['identity']));
					print('<br/>' . $identity);
				} else {
					if (empty($identity))
						print(gettext("Unknown"));
					else
						print($identity);
				}
			}
?>
					</td>
					<td>
<?php
			if (!is_array($ikesa['remote']))
				print(gettext("Unknown"));
			else {
				if (!empty($ikesa['remote']['address']))
					print(htmlspecialchars($ikesa['remote']['address']) . '<br/>' . gettext('Port: ') . htmlspecialchars($ikesa['remote']['port']));
				else
					print(gettext("Unknown"));
				if ($ikesa['remote']['port'] == '4500')
					print(" NAT-T");
			}
?>
					</td>
					<td>
						IKEv<?=htmlspecialchars($ikesa['version'])?>
						<br/>
						<?=htmlspecialchars($ikesa['role'])?>
					</td>
					<td>
						<?=htmlspecialchars($ikesa['reauth']);?>
					</td>
					<td>
						<?=htmlspecialchars($ikesa['encalg'])?>
						<br/>
						<?=htmlspecialchars($ikesa['intalg'])?>
						<br/>
						<?=htmlspecialchars($ikesa['prfalg'])?>
						<br/>
						<?=htmlspecialchars($ikesa['dhgroup'])?>
					</td>
					<td>
<?php
			if ($ikesa['status'] == 'established')
				print('<span style="color:green">');
			else
				print('<span>');
?>
						<?=ucfirst(htmlspecialchars($ikesa['status']))?>
						<br/><?=htmlspecialchars($ikesa['established'])?>
						</span>
					</td>
					<td >
<?php
				if ($icon != "pass"):
?>
					<a href="diag_ipsec.php?act=connect&amp;ikeid=<?=$con_id; ?>" class="btn btn-xs btn-success" data-toggle="tooltip" title="Connect VPN" >
							<?=gettext("Connect VPN")?>
						</a>
<?php
				else:
?>
						<a href="diag_ipsec.php?act=ikedisconnect&amp;ikeid=<?=$con_id; ?>" class="btn btn-xs btn-danger" data-toggle="tooltip" title="Disconnect VPN">
							<?=gettext("Disconnect")?>
						</a><br />
						<a href="diag_ipsec.php?act=ikedisconnect&amp;ikeid=<?=$con_id; ?>&amp;ikesaid=<?=$ikesa['id']; ?>" class="btn btn-xs btn-warning" data-toggle="tooltip" title="Disconnect VPN connection">
							<?=gettext("Disconnect")?>
						</a>
<?php
				endif;
?>
					</td>
				</tr>
				<tr>
					<td colspan = 10>
<?php
		    if (is_array($ikesa['childsalist'])):
?>
						<div id="btnchildsa-<?=$ikeid?>">
							<a type="button" onclick="show_childsa('childsa-<?=$ikeid?>','btnchildsa-<?=$ikeid?>');" class="btn btn-sm btn-default" />
								<?=gettext('Show child SA entries')?>
							</a>
						</div>

						<table class="table table-hover table-condensed" id="childsa-<?=$ikeid?>" style="display:none">
							<thead>
								<tr class="info">
									<th><?=gettext("Local subnets")?></th>
									<th><?=gettext("Local SPI(s)")?></th>
									<th><?=gettext("Remote subnets")?></th>
									<th><?=gettext("Times")?></th>
									<th><?=gettext("Algo")?></th>
									<th><?=gettext("Stats")?></th>
									<th><!-- Buttons --></th>
								</tr>
							</thead>
							<tbody>
<?php
			if (is_array($ikesa['childsalist']['childsa'])):
				foreach ($ikesa['childsalist']['childsa'] as $childsa):
?>
								<tr>
									<td>
<?php
				if (is_array($childsa['local']) &&
				    is_array($childsa['local']['networks']) &&
				    is_array($childsa['local']['networks']['network']))
					foreach ($childsa['local']['networks']['network'] as $lnets)
						print(htmlspecialchars(ipsec_fixup_network($lnets)) . "<br />");
				else
					print(gettext("Unknown"));
?>
									</td>
									<td>
<?php
				if (is_array($childsa['local']))
					print(gettext("Local: ") . htmlspecialchars($childsa['local']['spi']));
					
				if (is_array($childsa['remote']))
					print('<br/>' . gettext('Remote: ') . htmlspecialchars($childsa['remote']['spi']));
?>
									</td>
									<td>
<?php
				if (is_array($childsa['remote']) &&
				    is_array($childsa['remote']['networks']) &&
				    is_array($childsa['remote']['networks']['network']))
					foreach ($childsa['remote']['networks']['network'] as $rnets)
						print(htmlspecialchars(ipsec_fixup_network($rnets)) . '<br />');
				else
					print(gettext("Unknown"));
?>
									</td>
									<td>
<?php
				print(gettext("Rekey: ") . htmlspecialchars($childsa['rekey']));
				print('<br/>' . gettext('Life: ') . htmlspecialchars($childsa['lifetime']));
				print('<br/>' . gettext('Install: ') .htmlspecialchars($childsa['installtime']));

?>
									</td>
									<td>
<?php
				print(htmlspecialchars($childsa['encalg']) . '<br/>');
				print(htmlspecialchars($childsa['intalg']) . '<br/>');
				
				if (!empty($childsa['prfalg']))
					print(htmlspecialchars($childsa['prfalg']) . '<br/>');
				
				if (!empty($childsa['dhgroup']))
					print(htmlspecialchars($childsa['dhgroup']) . '<br/>');
				
				if (!empty($childsa['esn']))
					print(htmlspecialchars($childsa['esn']) . '<br/>');
				
				print(gettext("IPComp: ") . htmlspecialchars($childsa['ipcomp']));
?>
									</td>
									<td>
<?php
				print(gettext("Bytes-In: ") . htmlspecialchars($childsa['bytesin']) . '<br/>');
				print(gettext("Packets-In: ") . htmlspecialchars($childsa['packetsin']) . '<br/>');
				print(gettext("Bytes-Out: ") . htmlspecialchars($childsa['bytesout']) . '<br/>');
				print(gettext("Packets-Out: ") . htmlspecialchars($childsa['packetsout']) . '<br/>');
?>
									</td>
									<td>
										<a href="diag_ipsec.php?act=childdisconnect&amp;ikeid=<?=$con_id; ?>&amp;ikesaid=<?=$childsa['reqid']; ?>" class="btn btn-xs btn-warning" data-toggle="tooltip" title="<?=gettext('Disconnect Child SA')?>">
											<?=gettext("Disconnect")?>
										</a>
									</td>
								</tr>
<?php
				endforeach;
			endif;
?>

							</tbody>
						</table>
					</td>
				</tr>
<?php
		endif;

		unset($con_id);
	endforeach;
endif;

$rgmap = array();
foreach ($a_phase1 as $ph1ent):
	if (isset($ph1ent['disabled']))
		continue;
		
	$rgmap[$ph1ent['remote-gateway']] = $ph1ent['remote-gateway'];
	
	if ($ipsecconnected[$ph1ent['ikeid']])
		continue;
?>
				<tr>
					<td>
<?php
	print(htmlspecialchars($ph1ent['descr']));
?>
					</td>
					<td>
<?php
	list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, "local");
	if (empty($myid_data))
		print(gettext("Unknown"));
	else
		print(htmlspecialchars($myid_data));
?>
					</td>
					<td>
<?php
	$ph1src = ipsec_get_phase1_src($ph1ent);
	
	if (empty($ph1src))
		print(gettext("Unknown"));
	else
		print(htmlspecialchars($ph1src));
?>
					</td>
					<td>
<?php
	list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, "peer", $rgmap);
	if (empty($peerid_data))
		print(gettext("Unknown"));
	else
		print(htmlspecialchars($peerid_data));
?>
					</td>
					<td>
<?php
	$ph1src = ipsec_get_phase1_dst($ph1ent);
	if (empty($ph1src))
		print(gettext("Unknown"));
	else
		print(htmlspecialchars($ph1src));
?>
					</td>
					<td>
					</td>
					<td>
					</td>
					<td>
					</td>
<?php
	if (isset($ph1ent['mobile'])):
?>
					<td>
						<?=gettext("Awaiting connections")?>
					</td>
					<td>
					</td>
<?php
	else:
?>
					<td>
						<?=gettext("Disconnected")?>
					</td>
					<td >
						<a href="diag_ipsec.php?act=connect&amp;ikeid=<?=$ph1ent['ikeid']; ?>" class="btn btn-xs btn-success">
							<?=gettext("Connect VPN")?>
						</a>
					</td>
<?php
	endif;
?>
					<td>>
					</td>
				</tr>
<?php
endforeach;
unset($ipsecconnected, $phase1, $rgmap);
?>
			</tbody>
		</table>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
function show_childsa(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	aodiv = document.getElementById(id);
	aodiv.style.display = "block";
}
//]]>
</script>

<?php
unset($status);
print_info_box(gettext("You can configure IPsec ") . '<a href="vpn_ipsec.php">Here</a>');
include("foot.inc"); ?>
