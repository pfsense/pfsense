<?php
/*
	firewall_virtual_ip.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005 Bill Marquette <bill.marquette@gmail.com>
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-firewall-virtualipaddresses
##|*NAME=Firewall: Virtual IP Addresses
##|*DESCR=Allow access to the 'Firewall: Virtual IP Addresses' page.
##|*MATCH=firewall_virtual_ip.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['virtualip']['vip'])) {
	$config['virtualip']['vip'] = array();
}

$a_vip = &$config['virtualip']['vip'];

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$check_carp = false;
		if (file_exists("{$g['tmp_path']}/.firewall_virtual_ip.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply"));
			foreach ($toapplylist as $vid => $ovip) {
				if (!empty($ovip)) {
					interface_vip_bring_down($ovip);
				}
				if ($a_vip[$vid]) {
					switch ($a_vip[$vid]['mode']) {
						case "ipalias":
							interface_ipalias_configure($a_vip[$vid]);
							break;
						case "proxyarp":
							interface_proxyarp_configure($a_vip[$vid]['interface']);
							break;
						case "carp":
							$check_carp = true;
							interface_carp_configure($a_vip[$vid]);
							break;
						default:
							break;
					}
				}
			}
			@unlink("{$g['tmp_path']}/.firewall_virtual_ip.apply");
		}
		/* Before changing check #4633 */
		if ($check_carp === true && !get_carp_status()) {
			set_single_sysctl("net.inet.carp.allow", "1");
		}

		$retval = 0;
		$retval |= filter_configure();
		$savemsg = get_std_save_message($retval);

		clear_subsystem_dirty('vip');
	}
}

if ($_GET['act'] == "del") {
	if ($a_vip[$_GET['id']]) {
		/* make sure no inbound NAT mappings reference this entry */
		if (is_array($config['nat']['rule'])) {
			foreach ($config['nat']['rule'] as $rule) {
				if ($rule['destination']['address'] != "") {
					if ($rule['destination']['address'] == $a_vip[$_GET['id']]['subnet']) {
						$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one NAT mapping.");
						break;
					}
				}
			}
		}

		/* make sure no OpenVPN server or client references this entry */
		$openvpn_types_a = array("openvpn-server" => gettext("server"), "openvpn-client" => gettext("client"));
		foreach ($openvpn_types_a as $openvpn_type => $openvpn_type_text) {
			if (is_array($config['openvpn'][$openvpn_type])) {
				foreach ($config['openvpn'][$openvpn_type] as $openvpn) {
					if ($openvpn['ipaddr'] <> "") {
						if ($openvpn['ipaddr'] == $a_vip[$_GET['id']]['subnet']) {
							if (strlen($openvpn['description'])) {
								$openvpn_desc = $openvpn['description'];
							} else {
								$openvpn_desc = $openvpn['ipaddr'] . ":" . $openvpn['local_port'];
							}
							$input_errors[] = sprintf(gettext("This entry cannot be deleted because it is still referenced by OpenVPN %s %s."), $openvpn_type_text, $openvpn_desc);
							break;
						}
					}
				}
			}
		}

		if (is_ipaddrv6($a_vip[$_GET['id']]['subnet'])) {
			$is_ipv6 = true;
			$subnet = gen_subnetv6($a_vip[$_GET['id']]['subnet'], $a_vip[$_GET['id']]['subnet_bits']);
			$if_subnet_bits = get_interface_subnetv6($a_vip[$_GET['id']]['interface']);
			$if_subnet = gen_subnetv6(get_interface_ipv6($a_vip[$_GET['id']]['interface']), $if_subnet_bits);
		} else {
			$is_ipv6 = false;
			$subnet = gen_subnet($a_vip[$_GET['id']]['subnet'], $a_vip[$_GET['id']]['subnet_bits']);
			$if_subnet_bits = get_interface_subnet($a_vip[$_GET['id']]['interface']);
			$if_subnet = gen_subnet(get_interface_ip($a_vip[$_GET['id']]['interface']), $if_subnet_bits);
		}

		$subnet .= "/" . $a_vip[$_GET['id']]['subnet_bits'];
		$if_subnet .= "/" . $if_subnet_bits;

		if (is_array($config['gateways']['gateway_item'])) {
			foreach ($config['gateways']['gateway_item'] as $gateway) {
				if ($a_vip[$_GET['id']]['interface'] != $gateway['interface']) {
					continue;
				}
				if ($is_ipv6 && $gateway['ipprotocol'] == 'inet') {
					continue;
				}
				if (!$is_ipv6 && $gateway['ipprotocol'] == 'inet6') {
					continue;
				}
				if (ip_in_subnet($gateway['gateway'], $if_subnet)) {
					continue;
				}


				if (ip_in_subnet($gateway['gateway'], $subnet)) {
					$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one Gateway.");
					break;
				}
			}
		}

		if ($a_vip[$_GET['id']]['mode'] == "ipalias") {
			$subnet = gen_subnet($a_vip[$_GET['id']]['subnet'], $a_vip[$_GET['id']]['subnet_bits']) . "/" . $a_vip[$_GET['id']]['subnet_bits'];
			$found_if = false;
			$found_carp = false;
			$found_other_alias = false;

			if ($subnet == $if_subnet) {
				$found_if = true;
			}

			$vipiface = $a_vip[$_GET['id']]['interface'];

			foreach ($a_vip as $vip_id => $vip) {
				if ($vip_id == $_GET['id']) {
					continue;
				}

				if ($vip['interface'] == $vipiface && ip_in_subnet($vip['subnet'], $subnet)) {
					if ($vip['mode'] == "carp") {
						$found_carp = true;
					} else if ($vip['mode'] == "ipalias") {
						$found_other_alias = true;
					}
				}
			}

			if ($found_carp === true && $found_other_alias === false && $found_if === false) {
				$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by a CARP IP with the description") . " {$vip['descr']}.";
			}
		} else if ($a_vip[$_GET['id']]['mode'] == "carp") {
			$vipiface = "{$a_vip[$_GET['id']]['interface']}_vip{$a_vip[$_GET['id']]['vhid']}";
			foreach ($a_vip as $vip) {
				if ($vipiface == $vip['interface'] && $vip['mode'] == "ipalias") {
					$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by an IP alias entry with the description") . " {$vip['descr']}.";
				}
			}
		}

		if (!$input_errors) {
			if (!session_id()) {
				session_start();
			}
			$user = getUserEntry($_SESSION['Username']);

			if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
				header("Location: firewall_virtual_ip.php");
				exit;
			}

			session_commit();

			// Special case since every proxyarp vip is handled by the same daemon.
			if ($a_vip[$_GET['id']]['mode'] == "proxyarp") {
				$viface = $a_vip[$_GET['id']]['interface'];
				unset($a_vip[$_GET['id']]);
				interface_proxyarp_configure($viface);
			} else {
				interface_vip_bring_down($a_vip[$_GET['id']]);
				unset($a_vip[$_GET['id']]);
			}
			if (count($config['virtualip']['vip']) == 0) {
				unset($config['virtualip']['vip']);
			}
			write_config();
			header("Location: firewall_virtual_ip.php");
			exit;
		}
	}
} else if ($_GET['changes'] == "mods" && is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}

$types = array('proxyarp' => 'Proxy ARP',
			   'carp' => 'CARP',
			   'other' => 'Other',
			   'ipalias' => 'IP Alias'
			   );

$pgtitle = array(gettext("Firewall"), gettext("Virtual IP Addresses"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
} else if ($savemsg) {
	print_info_box($savemsg, 'success');
} else if (is_subsystem_dirty('vip')) {
	print_info_box_np(gettext("The VIP configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));
}

/* active tabs
$tab_array = array();
$tab_array[] = array(gettext("Virtual IPs"), true, "firewall_virtual_ip.php");
 $tab_array[] = array(gettext("CARP Settings"), false, "system_hasync.php");
display_top_tabs($tab_array);
*/
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Virtual IP Address')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("Virtual IP address")?></th>
					<th><?=gettext("Interface")?></th>
					<th><?=gettext("Type")?></th>
					<th><?=gettext("Description")?></th>
					<th><!--Buttons--></th>
				</tr>
			</thead>
			<tbody>
<?php
$interfaces = get_configured_interface_with_descr(false, true);
$carplist = get_configured_carp_interface_list();

foreach ($carplist as $cif => $carpip) {
	$interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
}

$interfaces['lo0'] = "Localhost";

$i = 0;
foreach ($a_vip as $vipent):
	if ($vipent['subnet'] != "" or $vipent['range'] != "" or
		$vipent['subnet_bits'] != "" or (isset($vipent['range']['from']) && $vipent['range']['from'] != "")):
?>
				<tr>
					<td>
<?php
	if (($vipent['type'] == "single") || ($vipent['type'] == "network")) {
		if ($vipent['subnet_bits']) {
			print("{$vipent['subnet']}/{$vipent['subnet_bits']}");
		}
	}

	if ($vipent['type'] == "range") {
		print("{$vipent['range']['from']}-{$vipent['range']['to']}");
	}

	if ($vipent['mode'] == "carp") {
		print(" (vhid: {$vipent['vhid']})");
	}
?>
					</td>
					<td>
						<?=htmlspecialchars($interfaces[$vipent['interface']])?>&nbsp;
					</td>
					<td>
						<?=$types[$vipent['mode']]?>
					</td>
					<td>
						<?=htmlspecialchars($vipent['descr'])?>
					</td>
					<td>
						<a class="fa fa-pencil" title="<?=gettext("Edit virtual ip"); ?>" href="firewall_virtual_ip_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext("Delete virtual ip")?>" href="firewall_virtual_ip.php?act=del&amp;id=<?=$i?>"></a>
					</td>
				</tr>
<?php
	endif;
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="firewall_virtual_ip_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="infoblock">
	<?=print_info_box(gettext('The virtual IP addresses defined on this page may be used in ') . '<a href="firewall_nat.php">' . gettext('NAT') . '</a>' . gettext(' mappings.') . '<br />' .
			   gettext('You can check the status of your CARP Virtual IPs and interfaces ') . '<a href="status_carp.php">' . gettext('here') . '</a>', 'info')?>
</div>

<?php
include("foot.inc");
