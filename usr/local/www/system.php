<?php
/* $Id$ */
/*
	system.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
/*
	pfSense_BUILDER_BINARIES:	/bin/kill	/usr/bin/tar
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-generalsetup
##|*NAME=System: General Setup page
##|*DESCR=Allow access to the 'System: General Setup' page.
##|*MATCH=system.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pconfig['hostname'] = $config['system']['hostname'];
$pconfig['domain'] = $config['system']['domain'];
list($pconfig['dns1'],$pconfig['dns2'],$pconfig['dns3'],$pconfig['dns4']) = $config['system']['dnsserver'];

$arr_gateways = return_gateways_array();

$pconfig['dns1gw'] = $config['system']['dns1gw'];
$pconfig['dns2gw'] = $config['system']['dns2gw'];
$pconfig['dns3gw'] = $config['system']['dns3gw'];
$pconfig['dns4gw'] = $config['system']['dns4gw'];

$pconfig['dnsallowoverride'] = isset($config['system']['dnsallowoverride']);
$pconfig['timezone'] = $config['system']['timezone'];
$pconfig['timeupdateinterval'] = $config['system']['time-update-interval'];
$pconfig['timeservers'] = $config['system']['timeservers'];
$pconfig['theme'] = $config['system']['theme'];
$pconfig['language'] = $config['system']['language'];

$pconfig['dnslocalhost'] = isset($config['system']['dnslocalhost']);

if (!isset($pconfig['timeupdateinterval']))
	$pconfig['timeupdateinterval'] = 300;
if (!$pconfig['timezone'])
	$pconfig['timezone'] = "Etc/UTC";
if (!$pconfig['timeservers'])
	$pconfig['timeservers'] = "pool.ntp.org";

$changedesc = gettext("System") . ": ";
$changecount = 0;

function is_timezone($elt) {
	return !preg_match("/\/$/", $elt);
}

if($pconfig['timezone'] <> $_POST['timezone']) {
	filter_pflog_start(true);
}

exec('/usr/bin/tar -tzf /usr/share/zoneinfo.tgz', $timezonelist);
$timezonelist = array_filter($timezonelist, 'is_timezone');
sort($timezonelist);

$multiwan = false;
$interfaces = get_configured_interface_list();
foreach($interfaces as $interface) {
	if(interface_has_gateway($interface)) {
		$multiwan = true;
	}
}

if ($_POST) {

	$changecount++;
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "hostname domain");
	$reqdfieldsn = array(gettext("Hostname"),gettext("Domain"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['hostname'] && !is_hostname($_POST['hostname'])) {
		$input_errors[] = gettext("The hostname may only contain the characters a-z, 0-9 and '-'.");
	}
	if ($_POST['domain'] && !is_domain($_POST['domain'])) {
		$input_errors[] = gettext("The domain may only contain the characters a-z, 0-9, '-' and '.'.");
	}

	$ignore_posted_dnsgw = array();

	for ($dnscounter=1; $dnscounter<5; $dnscounter++){
		$dnsname="dns{$dnscounter}";
		$dnsgwname="dns{$dnscounter}gw";
		if (($_POST[$dnsname] && !is_ipaddr($_POST[$dnsname]))) {
			$input_errors[] = gettext("A valid IP address must be specified for DNS server $dnscounter.");
		} else {
			if(($_POST[$dnsgwname] <> "") && ($_POST[$dnsgwname] <> "none")) {
				// A real gateway has been selected.
				if (is_ipaddr($_POST[$dnsname])) {
					if ((is_ipaddrv4($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false )) {
						$input_errors[] = gettext("You can not specify IPv6 gateway '{$_POST[$dnsgwname]}' for IPv4 DNS server '{$_POST[$dnsname]}'");
					}
					if ((is_ipaddrv6($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false )) {
						$input_errors[] = gettext("You can not specify IPv4 gateway '{$_POST[$dnsgwname]}' for IPv6 DNS server '{$_POST[$dnsname]}'");
					}
				} else {
					// The user selected a gateway but did not provide a DNS address. Be nice and set the gateway back to "none".
					$ignore_posted_dnsgw[$dnsgwname] = true;
				}
			}
		}
	}

	if ($_POST['webguiport'] && (!is_numericint($_POST['webguiport']) ||
			($_POST['webguiport'] < 1) || ($_POST['webguiport'] > 65535))) {
		$input_errors[] = gettext("A valid TCP/IP port must be specified for the webConfigurator port.");
	}

	$direct_networks_list = explode(" ", filter_get_direct_networks_list());
	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		$dnsitem = "dns{$dnscounter}";
		$dnsgwitem = "dns{$dnscounter}gw";
		if ($_POST[$dnsgwitem]) {
			if(interface_has_gateway($_POST[$dnsgwitem])) {
				foreach($direct_networks_list as $direct_network) {
					if(ip_in_subnet($_POST[$dnsitem], $direct_network)) {
						$input_errors[] = sprintf(gettext("You can not assign a gateway to DNS '%s' server which is on a directly connected network."),$_POST[$dnsitem]);
					}
				}
			}
		}
	}

	$t = (int)$_POST['timeupdateinterval'];
	if (($t < 0) || (($t > 0) && ($t < 6)) || ($t > 1440)) {
		$input_errors[] = gettext("The time update interval must be either 0 (disabled) or between 6 and 1440.");
	}
	# it's easy to have a little too much whitespace in the field, clean it up for the user before processing.
	$_POST['timeservers'] = preg_replace('/[[:blank:]]+/', ' ', $_POST['timeservers']);
	$_POST['timeservers'] = trim($_POST['timeservers']);
	foreach (explode(' ', $_POST['timeservers']) as $ts) {
		if (!is_domain($ts)) {
			$input_errors[] = gettext("A NTP Time Server name may only contain the characters a-z, 0-9, '-' and '.'.");
		}
	}

	if (!$input_errors) {
		update_if_changed("hostname", $config['system']['hostname'], strtolower($_POST['hostname']));
		update_if_changed("domain", $config['system']['domain'], strtolower($_POST['domain']));

		update_if_changed("timezone", $config['system']['timezone'], $_POST['timezone']);
		update_if_changed("NTP servers", $config['system']['timeservers'], strtolower($_POST['timeservers']));
		update_if_changed("NTP update interval", $config['system']['time-update-interval'], $_POST['timeupdateinterval']);

		if($_POST['language'] && $_POST['language'] != $config['system']['language']) {
			$config['system']['language'] = $_POST['language'];
			set_language($config['system']['language']);
		}

		/* pfSense themes */
		if (! $g['disablethemeselection']) {
			update_if_changed("System Theme", $config['theme'], $_POST['theme']);	
		}

		/* XXX - billm: these still need updating after figuring out how to check if they actually changed */
		$olddnsservers = $config['system']['dnsserver'];
		unset($config['system']['dnsserver']);
		if ($_POST['dns1'])
			$config['system']['dnsserver'][] = $_POST['dns1'];
		if ($_POST['dns2'])
			$config['system']['dnsserver'][] = $_POST['dns2'];
		if ($_POST['dns3'])
			$config['system']['dnsserver'][] = $_POST['dns3'];
		if ($_POST['dns4'])
			$config['system']['dnsserver'][] = $_POST['dns4'];

		$olddnsallowoverride = $config['system']['dnsallowoverride'];

		unset($config['system']['dnsallowoverride']);
		$config['system']['dnsallowoverride'] = $_POST['dnsallowoverride'] ? true : false;

		if($_POST['dnslocalhost'] == "yes")
			$config['system']['dnslocalhost'] = true;
		else
			unset($config['system']['dnslocalhost']);

		/* which interface should the dns servers resolve through? */
		$outdnscounter = 0;
		for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
			$dnsname="dns{$dnscounter}";
			$dnsgwname="dns{$dnscounter}gw";
			$olddnsgwname = $config['system'][$dnsgwname];

			if ($ignore_posted_dnsgw[$dnsgwname])
				$thisdnsgwname = "none";
			else
				$thisdnsgwname = $pconfig[$dnsgwname];

			// "Blank" out the settings for this index, then we set them below using the "outdnscounter" index.
			$config['system'][$dnsgwname] = "none";
			$pconfig[$dnsgwname] = "none";
			$pconfig[$dnsname] = "";

			if ($_POST[$dnsname]) {
				// Only the non-blank DNS servers were put into the config above.
				// So we similarly only add the corresponding gateways sequentially to the config (and to pconfig), as we find non-blank DNS servers.
				// This keeps the DNS server IP and corresponding gateway "lined up" when the user blanks out a DNS server IP in the middle of the list.
				$outdnscounter++;
				$outdnsname="dns{$outdnscounter}";
				$outdnsgwname="dns{$outdnscounter}gw";
				$pconfig[$outdnsname] = $_POST[$dnsname];
				if($_POST[$dnsgwname]) {
					$config['system'][$outdnsgwname] = $thisdnsgwname;
					$pconfig[$outdnsgwname] = $thisdnsgwname;
				} else {
					// Note: when no DNS GW name is chosen, the entry is set to "none", so actually this case never happens.
					unset($config['system'][$outdnsgwname]);
					$pconfig[$outdnsgwname] = "";
				}
			}
			if (($olddnsgwname != "") && ($olddnsgwname != "none") && (($olddnsgwname != $thisdnsgwname) || ($olddnsservers[$dnscounter-1] != $_POST[$dnsname]))) {
				// A previous DNS GW name was specified. It has now gone or changed, or the DNS server address has changed.
				// Remove the route. Later calls will add the correct new route if needed.
				if (is_ipaddrv4($olddnsservers[$dnscounter-1]))
					mwexec("/sbin/route delete " . escapeshellarg($olddnsservers[$dnscounter-1]));
				else
					if (is_ipaddrv6($olddnsservers[$dnscounter-1]))
						mwexec("/sbin/route delete -inet6 " . escapeshellarg($olddnsservers[$dnscounter-1]));
			}
		}

		if ($changecount > 0)
			write_config($changedesc);

		$retval = 0;
		$retval = system_hostname_configure();
		$retval |= system_hosts_generate();
		$retval |= system_resolvconf_generate();
		$retval |= services_dnsmasq_configure();
		$retval |= system_timezone_configure();
		$retval |= system_ntp_configure();

		if ($olddnsallowoverride != $config['system']['dnsallowoverride'])
			$retval |= send_event("service reload dns");

		// Reload the filter - plugins might need to be run.
		$retval |= filter_configure();
		
		$savemsg = get_std_save_message($retval);
	}

	unset($ignore_posted_dnsgw);
}

$pgtitle = array(gettext("System"),gettext("General Setup"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
	include("fbegin.inc");
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
	<form action="system.php" method="post">
		<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="general setup">
                        <tr>
                                <td id="mainarea">
                                        <div class="tabcont">
			<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?=gettext("System"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname"); ?></td>
				<td width="78%" class="vtable"> <input name="hostname" type="text" class="formfld unknown" id="hostname" size="40" value="<?=htmlspecialchars($pconfig['hostname']);?>" />
					<br/>
					<span class="vexpl">
						<?=gettext("Name of the firewall host, without domain part"); ?>
						<br/>
						<?=gettext("e.g."); ?> <em>firewall</em>
					</span>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq"><?=gettext("Domain"); ?></td>
				<td width="78%" class="vtable"> <input name="domain" type="text" class="formfld unknown" id="domain" size="40" value="<?=htmlspecialchars($pconfig['domain']);?>" />
					<br/>
					<span class="vexpl">
						<?=gettext("Do not use 'local' as a domain name. It will cause local hosts running mDNS (avahi, bonjour, etc.) to be unable to resolve local hosts not running mDNS."); ?>
						<br/>
						<?=gettext("e.g."); ?> <em><?=gettext("mycorp.com, home, office, private, etc."); ?></em>
					</span>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?=gettext("DNS servers"); ?></td>
				<td width="78%" class="vtable">
						<br/>
						<table summary="dns servers and gateways">
							<tr>
								<td><b><?=gettext("DNS Server"); ?></b></td>
								<?php if ($multiwan): ?>
								<td><b><?=gettext("Use gateway"); ?></b></td>
								<?php endif; ?>
							</tr>
							<?php
								for ($dnscounter=1; $dnscounter<5; $dnscounter++):
									$fldname="dns{$dnscounter}gw";
							?>
							<tr>
								<td>
									<input name="dns<?php echo $dnscounter;?>" type="text" class="formfld unknown" id="dns<?php echo $dnscounter;?>" size="28" value="<?php echo $pconfig['dns'.$dnscounter];?>" />
								</td>
								<td>
<?php if ($multiwan): ?>
									<select name='<?=$fldname;?>'>
										<?php
											$gwname = "none";
											$dnsgw = "dns{$dnscounter}gw";
											if($pconfig[$dnsgw] == $gwname) {
												$selected = "selected=\"selected\"";
											} else {
												$selected = "";
											}
											echo "<option value='$gwname' $selected>$gwname</option>\n";
											foreach($arr_gateways as $gwname => $gwitem) {
												//echo $pconfig[$dnsgw];
												if((is_ipaddrv4(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && (is_ipaddrv6($gwitem['gateway'])))) {
													continue;
												}
												if((is_ipaddrv6(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && (is_ipaddrv4($gwitem['gateway'])))) {
													continue;
												}
												if($pconfig[$dnsgw] == $gwname) {
													$selected = "selected=\"selected\"";
												} else {
													$selected = "";
												}
												echo "<option value='$gwname' $selected>$gwname - {$gwitem['friendlyiface']} - {$gwitem['gateway']}</option>\n";
											}
										?>
									</select>
<?php endif; ?>
								</td>
							</tr>
							<?php endfor; ?>
						</table>
						<br />
						<span class="vexpl">
							<?=gettext("Enter IP addresses to be used by the system for DNS resolution. " .
							"These are also used for the DHCP service, DNS forwarder and for PPTP VPN clients."); ?>
							<br/>
							<?php if($multiwan): ?>
							<br/>
							<?=gettext("In addition, optionally select the gateway for each DNS server. " .
							"When using multiple WAN connections there should be at least one unique DNS server per gateway."); ?>
							<br/>
							<?php endif; ?>
							<br/>
							<input name="dnsallowoverride" type="checkbox" id="dnsallowoverride" value="yes" <?php if ($pconfig['dnsallowoverride']) echo "checked=\"checked\""; ?> />
							<strong>
								<?=gettext("Allow DNS server list to be overridden by DHCP/PPP on WAN"); ?>
							</strong>
							<br/>
							<?php printf(gettext("If this option is set, %s will " .
							"use DNS servers assigned by a DHCP/PPP server on WAN " .
							"for its own purposes (including the DNS forwarder). " .
							"However, they will not be assigned to DHCP and PPTP " .
							"VPN clients."), $g['product_name']); ?>
							<br />
							<br />
							<input name="dnslocalhost" type="checkbox" id="dnslocalhost" value="yes" <?php if ($pconfig['dnslocalhost']) echo "checked=\"checked\""; ?> />
							<strong>
								<?=gettext("Do not use the DNS Forwarder as a DNS server for the firewall"); ?>
							</strong>
							<br />
							<?=gettext("By default localhost (127.0.0.1) will be used as the first DNS server where the DNS forwarder is enabled, so system can use the DNS forwarder to perform lookups. ".
							"Checking this box omits localhost from the list of DNS servers."); ?>
						</span>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?=gettext("Time zone"); ?></td>
				<td width="78%" class="vtable">
					<select name="timezone" id="timezone">
						<?php foreach ($timezonelist as $value): ?>
						<?php if(strstr($value, "GMT")) continue; ?>
						<option value="<?=htmlspecialchars($value);?>" <?php if ($value == $pconfig['timezone']) echo "selected=\"selected\""; ?>>
							<?=htmlspecialchars($value);?>
						</option>
						<?php endforeach; ?>
					</select>
					<br/>
					<span class="vexpl">
						<?=gettext("Select the location closest to you"); ?>
					</span>
				</td>
			</tr>
<!--
			<tr>
				<td width="22%" valign="top" class="vncell">Time update interval</td>
				<td width="78%" class="vtable">
					<input name="timeupdateinterval" type="text" class="formfld unknown" id="timeupdateinterval" size="4" value="<?=htmlspecialchars($pconfig['timeupdateinterval']);?>" />
					<br/>
					<span class="vexpl">
						Minutes between network time sync. 300 recommended,
						or 0 to disable
					</span>
				</td>
			</tr>
-->
			<tr>
				<td width="22%" valign="top" class="vncell"><?=gettext("NTP time server"); ?></td>
				<td width="78%" class="vtable">
					<input name="timeservers" type="text" class="formfld unknown" id="timeservers" size="40" value="<?=htmlspecialchars($pconfig['timeservers']);?>" />
					<br/>
					<span class="vexpl">
						<?=gettext("Use a space to separate multiple hosts (only one " .
						"required). Remember to set up at least one DNS server " .
						"if you enter a host name here!"); ?>
					</span>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Language");?></td>
				<td width="78%" class="vtable">
					<select name="language">
						<?php
						foreach(get_locale_list() as $lcode => $ldesc) {
							$selected = ' selected="selected"';
							if($lcode != $pconfig['language'])
								$selected = '';
							echo "<option value=\"{$lcode}\"{$selected}>{$ldesc}</option>";
						}
						?>
					</select>
					<strong>
						<?=gettext("Choose a language for the webConfigurator"); ?>
					</strong>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="list" height="12">&nbsp;</td>
			</tr>
			<?php if (! $g['disablethemeselection']): ?>
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?=gettext("Theme"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">&nbsp;</td>
				<td width="78%" class="vtable">
					<select name="theme">
						<?php
							$files = return_dir_as_array("/usr/local/www/themes/");
							foreach($files as $f):
								if ((substr($f, 0, 1) == "_") && !isset($config['system']['developer']))
									continue;
								if ($f == "CVS")
									continue;
								$curtheme = "pfsense";
								if ($config['theme'])
									$curtheme = $config['theme'];
								$selected = "";
								if($f == $curtheme)
									$selected = " selected=\"selected\"";
						?>
						<option <?=$selected;?>><?=$f;?></option>
						<?php endforeach; ?>
					</select>
					<strong>
						<?=gettext("This will change the look and feel of"); ?>
						<?=$g['product_name'];?>.
					</strong>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<td colspan="2" class="list" height="12">&nbsp;</td>
			</tr>			
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%">
					<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
				</td>
			</tr>
		</table>
		</div>
		</td></tr>
		</table>
	</form>
<?php include("fend.inc"); ?>
</body>
</html>
