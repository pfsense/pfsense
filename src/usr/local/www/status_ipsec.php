<?php
/*
	status_ipsec.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  portions Copyright (c) 2008 Shrew Soft Inc <mgrooms@shrew.net>.
 *
 *  Parts of this code originally based on vpn_ipsec_sad.php from m0n0wall,
 *  Copyright (c) 2003-2004 Manuel Kasper (BSD 2 clause)
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

##|+PRIV
##|*IDENT=page-status-ipsec
##|*NAME=Status: IPsec
##|*DESCR=Allow access to the 'Status: IPsec' page.
##|*MATCH=status_ipsec.php*
##|-PRIV


global $g;

$pgtitle = array(gettext("Status"), gettext("IPsec"), gettext("Overview"));
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

$status = ipsec_list_sa();

$tab_array = array();
$tab_array[] = array(gettext("Overview"), true, "status_ipsec.php");
$tab_array[] = array(gettext("Leases"), false, "status_ipsec_leases.php");
$tab_array[] = array(gettext("SAD"), false, "status_ipsec_sad.php");
$tab_array[] = array(gettext("SPD"), false, "status_ipsec_spd.php");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading">IPsec status</div>
	<div class="panel-body table responsive">
		<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
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

if (is_array($status)) {
	foreach ($status as $ikeid => $ikesa) {
	$con_id = substr($ikeid, 3);

		if ($ikesa['version'] == 1) {
			$ph1idx = substr($con_id, 0, strrpos(substr($con_id, 0, -1), '00'));
			$ipsecconnected[$ph1idx] = $ph1idx;
		} else {
			$ipsecconnected[$con_id] = $ph1idx = $con_id;
		}
?>
				<tr>
					<td>
						<?=htmlspecialchars(ipsec_get_descr($ph1idx))?>
					</td>
					<td>
<?php
		if (!empty($ikesa['local-id'])) {
			if ($ikesa['local-id'] == '%any') {
				print(gettext('Any identifier'));
			} else {
				print(htmlspecialchars($ikesa['local-id']));
			}
		} else {
			print(gettext("Unknown"));
		}

?>
					</td>
					<td>
<?php
		if (!empty($ikesa['local-host'])) {
			print(htmlspecialchars($ikesa['local-host']));
		} else {
			print(gettext("Unknown"));
		}
		/*
		 * XXX: local-nat-t was defined by pfSense
		 * When strongswan team accepted the change, they changed it to
		 * nat-local. Keep both for a while and remove local-nat-t in
		 * the future
		 */
		if (isset($ikesa['local-nat-t']) || isset($ikesa['nat-local'])) {
			print(" NAT-T");
		}
?>
					</td>
					<td>
<?php
		$identity = "";
		if (!empty($ikesa['remote-id'])) {
			if ($ikesa['remote-id'] == '%any') {
				$identity = 'Any identifier';
			} else {
				$identity = htmlspecialchars($ikesa['remote']['identification']);
			}
		}
		if (!empty($ikesa['remote-xauth-id'])) {
			echo htmlspecialchars($ikesa['remote-xauth-id']);
			echo "<br/>{$identity}";
		} elseif (!empty($ikesa['remote-eap-id'])) {
			echo htmlspecialchars($ikesa['remote-eap-id']);
			echo "<br/>{$identity}";
		} else {
			if (empty($identity)) {
				print(gettext("Unknown"));
			} else {
				print($identity);
			}
		}
?>
					</td>
					<td>
<?php
		if (!empty($ikesa['remote-host'])) {
			print(htmlspecialchars($ikesa['remote-host']));
		} else {
			print(gettext("Unknown"));
		}
		/*
		 * XXX: remote-nat-t was defined by pfSense
		 * When strongswan team accepted the change, they changed it to
		 * nat-remote. Keep both for a while and remove remote-nat-t in
		 * the future
		 */
		if (isset($ikesa['remote-nat-t']) || isset($ikesa['nat-remote'])) {
			print(" NAT-T");
		}
?>
					</td>
					<td>
						IKEv<?=htmlspecialchars($ikesa['version'])?>
						<br/>
<?php
		if ($ikesa['initiator'] == 'yes') {
			print("initiator");
		} else {
			print("responder");
		}
?>
					</td>
					<td>
						<?=htmlspecialchars($ikesa['reauth-time']) . gettext(" seconds (") . convert_seconds_to_hms($ikesa['reauth-time']) . ")";?>
					</td>
					<td>
						<?=htmlspecialchars($ikesa['encr-alg'])?>
						<br/>
						<?=htmlspecialchars($ikesa['integ-alg'])?>
						<br/>
						<?=htmlspecialchars($ikesa['prf-alg'])?>
						<br/>
						<?=htmlspecialchars($ikesa['dh-group'])?>
					</td>
					<td>
<?php
		if ($ikesa['state'] == 'ESTABLISHED') {
			print('<span style="color:green">');
		} else {
			print('<span>');
		}
?>
						<?=ucfirst(htmlspecialchars($ikesa['state']))?>
						<br/><?=htmlspecialchars($ikesa['established']) . gettext(" seconds (" . convert_seconds_to_hms($ikesa['established']) . ") ago")?>
						</span>
					</td>
					<td >
<?php
		if ($ikesa['state'] != 'ESTABLISHED') {
?>
					<a href="status_ipsec.php?act=connect&amp;ikeid=<?=$con_id; ?>" class="btn btn-xs btn-success" data-toggle="tooltip" title="Connect VPN" >
							<?=gettext("Connect VPN")?>
						</a>
<?php
		} else {
?>
						<a href="status_ipsec.php?act=ikedisconnect&amp;ikeid=<?=$con_id; ?>" class="btn btn-xs btn-danger" data-toggle="tooltip" title="Disconnect VPN">
							<?=gettext("Disconnect")?>
						</a><br />
<?php
		}
?>
					</td>
				</tr>
				<tr>
					<td colspan = 10>
<?php
		if (is_array($ikesa['child-sas']) && (count($ikesa['child-sas']) > 0)) {
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
			foreach ($ikesa['child-sas'] as $childid => $childsa) {
?>
								<tr>
									<td>
<?php
				if (is_array($childsa['local-ts'])) {
					foreach ($childsa['local-ts'] as $lnets) {
						print(htmlspecialchars(ipsec_fixup_network($lnets)) . "<br />");
					}
				} else {
					print(gettext("Unknown"));
				}
?>
									</td>
									<td>
<?php
				if (isset($childsa['spi-in'])) {
					print(gettext("Local: ") . htmlspecialchars($childsa['spi-in']));
				}

				if (isset($childsa['spi-out'])) {
					print('<br/>' . gettext('Remote: ') . htmlspecialchars($childsa['spi-out']));
				}
?>
									</td>
									<td>
<?php
				if (is_array($childsa['remote-ts'])) {
					foreach ($childsa['remote-ts'] as $rnets) {
						print(htmlspecialchars(ipsec_fixup_network($rnets)) . '<br />');
					}
				} else {
					print(gettext("Unknown"));
				}
?>
									</td>
									<td>
<?php
				print(gettext("Rekey: ") . htmlspecialchars($childsa['rekey-time']) . gettext(" seconds (") . convert_seconds_to_hms($childsa['rekey-time']) . ")");
				print('<br/>' . gettext('Life: ') . htmlspecialchars($childsa['life-time']) . gettext(" seconds (") . convert_seconds_to_hms($childsa['life-time']) . ")");
				print('<br/>' . gettext('Install: ') .htmlspecialchars($childsa['install-time']) . gettext(" seconds (") . convert_seconds_to_hms($childsa['install-time']) . ")");

?>
									</td>
									<td>
<?php
				print(htmlspecialchars($childsa['encr-alg']) . '<br/>');
				print(htmlspecialchars($childsa['integ-alg']) . '<br/>');

				if (!empty($childsa['prf-alg'])) {
					print(htmlspecialchars($childsa['prf-alg']) . '<br/>');
				}
				if (!empty($childsa['dh-group'])) {
					print(htmlspecialchars($childsa['dh-group']) . '<br/>');
				}
				if (!empty($childsa['esn'])) {
					print(htmlspecialchars($childsa['esn']) . '<br/>');
				}

				print(gettext("IPComp: "));
				if (!empty($childsa['cpi-in']) || !empty($childsa['cpi-out'])) {
					print(htmlspecialchars($childsa['cpi-in']) . " " . htmlspecialchars($childsa['cpi-out']));
				} else {
					print(gettext('none'));
				}
?>
									</td>
									<td>
<?php
				print(gettext("Bytes-In: ") . htmlspecialchars(number_format($childsa['bytes-in'])) . ' (' . htmlspecialchars(format_bytes($childsa['bytes-in'])) . ')<br/>');
				print(gettext("Packets-In: ") . htmlspecialchars(number_format($childsa['packets-in'])) . '<br/>');
				print(gettext("Bytes-Out: ") . htmlspecialchars(number_format($childsa['bytes-out'])) . ' (' . htmlspecialchars(format_bytes($childsa['bytes-out'])) . ')<br/>');
				print(gettext("Packets-Out: ") . htmlspecialchars(number_format($childsa['packets-out'])) . '<br/>');
?>
									</td>
									<td>
										<a href="status_ipsec.php?act=childdisconnect&amp;ikeid=<?=$con_id; ?>&amp;ikesaid=<?=$childsa['uniqueid']; ?>" class="btn btn-xs btn-warning" data-toggle="tooltip" title="<?=gettext('Disconnect Child SA')?>">
											<?=gettext("Disconnect")?>
										</a>
									</td>
								</tr>
<?php
			}
?>

							</tbody>
						</table>
					</td>
				</tr>
<?php
		}

		unset($con_id);
	}

}

$rgmap = array();
foreach ($a_phase1 as $ph1ent) {
	if (isset($ph1ent['disabled'])) {
		continue;
	}

	$rgmap[$ph1ent['remote-gateway']] = $ph1ent['remote-gateway'];

	if ($ipsecconnected[$ph1ent['ikeid']]) {
		continue;
	}
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
	if (empty($myid_data)) {
		print(gettext("Unknown"));
	} else {
		print(htmlspecialchars($myid_data));
	}
?>
					</td>
					<td>
<?php
	$ph1src = ipsec_get_phase1_src($ph1ent);

	if (empty($ph1src)) {
		print(gettext("Unknown"));
	} else {
		print(htmlspecialchars($ph1src));
	}
?>
					</td>
					<td>
<?php
	list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, "peer", $rgmap);
	if (empty($peerid_data)) {
		print(gettext("Unknown"));
	} else {
		print(htmlspecialchars($peerid_data));
	}
?>
					</td>
					<td>
<?php
	$ph1src = ipsec_get_phase1_dst($ph1ent);
	if (empty($ph1src)) {
		print(gettext("Unknown"));
	} else {
		print(htmlspecialchars($ph1src));
	}
?>
					</td>
					<td>
					</td>
					<td>
					</td>
					<td>
					</td>
<?php
	if (isset($ph1ent['mobile'])) {
?>
					<td>
						<?=gettext("Awaiting connections")?>
					</td>
					<td>
					</td>
<?php
	} else {
?>
					<td>
						<?=gettext("Disconnected")?>
					</td>
					<td >
						<a href="status_ipsec.php?act=connect&amp;ikeid=<?=$ph1ent['ikeid']; ?>" class="btn btn-xs btn-success">
							<?=gettext("Connect VPN")?>
						</a>
					</td>
<?php
	}
?>
				</tr>
<?php
}
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
