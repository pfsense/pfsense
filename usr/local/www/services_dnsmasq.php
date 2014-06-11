<?php
/* $Id$ */
/*
	services_dnsmasq.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Bob Zoller <bob@kludgebox.com> and Manuel Kasper <mk@neon1.net>.
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
	pfSense_MODULE:	dnsforwarder
*/

##|+PRIV
##|*IDENT=page-services-dnsforwarder
##|*NAME=Services: DNS Forwarder page
##|*DESCR=Allow access to the 'Services: DNS Forwarder' page.
##|*MATCH=services_dnsmasq.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

/* check for old or missing config */
if (!isset($config['dnsmasq']['instances']))
	$config['dnsmasq']['instances'] = array('instance0' => $config['dnsmasq']);

$a_instances = &$config['dnsmasq']['instances'];

// find correct instance
if (is_numericint($_GET['instance']))
	$idx = $_GET['instance'];
if (isset($_POST['instance']) && is_numericint($_POST['instance']))
	$idx = $_POST['instance'];

// redirect to overview or determine next index when index is not in range
if (!isset($idx) || $idx < 0) {
	if (isset($config['dnsmasq']['allow_multi']) && ($idx < 0 || $_SERVER['REQUEST_METHOD'] != 'POST')) {
		header("Location: services_dnsmasq_instances.php");
		exit;
	}
	$idx = 0;
}

$N = count($a_instances);
if ($N > $idx || $_SERVER['REQUEST_METHOD'] == 'POST') {
	if ($N > $idx) {
		$keys = array_keys($a_instances);
		$key = $keys[$idx];
	} else {
		$key = "instance{$N}";
	}

	$instance = &$a_instances[$key];
	$instanceIndex = $idx;
	unset($keys, $key);
}
else {
	$isNewInstance = true;
	$instance = array();
	$instanceIndex = $N;
}
unset($idx, $N);

$pconfig['allow_multi'] = isset($config['dnsmasq']['allow_multi']);
$pconfig['enable'] = isset($instance['enable']);
$pconfig['regdhcp'] = isset($instance['regdhcp']);
$pconfig['regdhcpstatic'] = isset($instance['regdhcpstatic']);
$pconfig['regdhcpfilter'] = isset($instance['regdhcpfilter']);
$pconfig['dhcpfirst'] = isset($instance['dhcpfirst']);
$pconfig['strict_order'] = isset($instance['strict_order']);
$pconfig['domain_needed'] = isset($instance['domain_needed']);
$pconfig['no_private_reverse'] = isset($instance['no_private_reverse']);
$pconfig['port'] = $instance['port'];
$pconfig['custom_options'] = $instance['custom_options'];
$pconfig['descr'] = $instance['descr'];

$pconfig['strictbind'] = isset($instance['strictbind']);
if (!empty($instance['interface']))
	$pconfig['interface'] = explode(",", $instance['interface']);
else
	$pconfig['interface'] = array();

if (!is_array($instance['hosts']))
	$instance['hosts'] = array();

if (!is_array($instance['domainoverrides']))
	$instance['domainoverrides'] = array();


$a_hosts = &$instance['hosts'];
$a_domainOverrides = &$instance['domainoverrides'];

$showMultiInstanceOptions = isset($_REQUEST['instance']);
$allowMultiInstance = $instanceIndex == 0 || isset($config['dnsmasq']['allow_multi']);

$serviceUrl = "services_dnsmasq.php";
if ($showMultiInstanceOptions)
	$serviceUrl .= "?instance={$instanceIndex}";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$pconfig = $_POST;
	unset($input_errors);

	$instance['enable'] = ($_POST['enable']) ? true : false;
	$instance['regdhcp'] = ($_POST['regdhcp']) ? true : false;
	$instance['regdhcpstatic'] = ($_POST['regdhcpstatic']) ? true : false;
	$instance['regdhcpfilter'] = ($_POST['regdhcpfilter']) ? true : false;
	$instance['dhcpfirst'] = ($_POST['dhcpfirst']) ? true : false;
	$instance['strict_order'] = ($_POST['strict_order']) ? true : false;
	$instance['domain_needed'] = ($_POST['domain_needed']) ? true : false;
	$instance['no_private_reverse'] = ($_POST['no_private_reverse']) ? true : false;
	$instance['custom_options'] = str_replace("\r\n", "\n", $_POST['custom_options']);
	
	if (!isset($_REQUEST['instance'])) {
		if (!$_POST['allow_multi']) {
			$first = true;
			foreach ($a_instances as &$other) {
				if ($first) {
					$first = false;
					continue;
				}
				unset($other['enable']);
			}
			unset($first, $config['dnsmasq']['allow_multi']);
		} else {
			$config['dnsmasq']['allow_multi'] = true;
		}

		$allowMultiInstance = $instanceIndex == 0 || isset($config['dnsmasq']['allow_multi']);
		$showMultiInstanceOptions = $showMultiInstanceOptions ?: isset($config['dnsmasq']['allow_multi']);
	}
	
	if (!$instance['enable']) {	
		if (!isset($config['dnsmasq']['allow_multi']))
			unset($config['dnsmasq']['enable']);
		else {
			foreach ($a_instances as &$other) {
				if (isset($other['enable'])) {
					$config['dnsmasq']['enable'] = true;
					break;
				}
			}
		}
	}
	else $config['dnsmasq']['enable'] = true;

	$instance['strictbind'] = ($_POST['strictbind']) ? true : false;

	$instance['descr'] = $_POST['descr']; 

	if ($_POST['port'])
		if(is_port($_POST['port']))
			$instance['port'] = $_POST['port'];
		else
			$input_errors[] = gettext("You must specify a valid port number");
	else if (isset($instance['port']))
		unset($instance['port']);

	if (is_array($_POST['interface']))
		$instance['interface'] = implode(",", $_POST['interface']);
	elseif (isset($instance['interface']))
		unset($instance['interface']);

	// check whether the combination of ports and interfaces are valid
	if (isset($config['dnsmasq']['allow_multi']) && count($a_instances) > 1) {
		// interface + port must be unique w.r.t. other instances
		$a_interfaces = $_POST['interface'];
		if (!is_array($a_interfaces))
			$a_interfaces = array(NULL);

		$portBindError = false;
		foreach ($a_interfaces as $interface) {
			$k = 0;
			foreach ($a_instances as $other) {				
				if ($k == $instanceIndex || !isset($other['enable']))
					continue;

				if ($other['port'] == $instance['port']) {
					if (!isset($other['strictbind']) && (!$instance['strictbind'] || $interface == NULL)) {
						$portBindError = true;
						break 2;
					}
					
					if (isset($other['interface'])) {
						$a_interfacesOther = explode(",", $other['interface']);
						if (in_array($interface, $a_interfacesOther))
							$portBindError = true;
							break 2;
						}
					elseif ($interface === NULL) {
						$portBindError = true;
						break 2;
					}
				}
				++$k;
			}
		}

		if ($portBindError)
			$input_errors[] = gettext("Cannot bind to the same combination of interface and port on multiple instances.");
	}

	if ($instance['custom_options']) {
		$args = '';
		foreach (preg_split('/\s+/', $instance['custom_options']) as $c)
			$args .= "--$c ";
		exec("/usr/local/sbin/dnsmasq --test $args", $output, $rc);
		if ($rc != 0)
			$input_errors[] = gettext("Invalid custom options");
	}

	if (!$input_errors) {
		write_config();

		if (!isset($config['dnsmasq']['allow_multi'])) {
			// reload dnsmasq
			$retval = 0;
			$retval = services_dnsmasq_configure();
			$savemsg = get_std_save_message($retval);
			if (isset($config['dnsmasq']['allow_multi']))
				$savemsg .= " You can also go to the <a href=\"services_dnsmasq_instances.php\">overview</a>.";

			// Reload filter (we might need to sync to CARP hosts)
			filter_configure();
			/* Update resolv.conf in case the interface bindings exclude localhost. */
			system_resolvconf_generate();

			if ($retval == 0)
				clear_subsystem_dirty('hosts');
		} else {
			// do not reload dnsmasq, but show warning
			mark_subsystem_dirty('hosts');
			header("Location: {$serviceUrl}");
			exit;
		}
	}
}

if ($_GET['act'] == "del") {	
	if ($_GET['type'] == 'host') {
		if ($a_hosts[$_GET['id']]) {
			unset($a_hosts[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('hosts');
			header("Location: {$serviceUrl}");
			exit;
		}
	}
	elseif ($_GET['type'] == 'doverride') {
		if ($a_domainOverrides[$_GET['id']]) {
			unset($a_domainOverrides[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('hosts');
			header("Location: {$serviceUrl}");
			exit;
		}
	}
}

$closehead = false;
$pgtitle = array(gettext("Services"),gettext("DNS forwarder"));
$shortcut_section = "resolver";
include("head.inc");

function getInstanceParam($prefix = '', $suffix = '') {
	global $showMultiInstanceOptions, $instanceIndex;
	
	$s = "";
	if ($showMultiInstanceOptions) {
		$s .= "{$prefix}instance={$instanceIndex}";
		if (!empty($suffix))
			$s .= $suffix;
	}
	return $s;
}

?>

<script type="text/javascript">
//<![CDATA[
function enable_change(enable_over) {
	var endis;
	endis = !(document.iform.enable.checked || enable_over);
	document.iform.regdhcp.disabled = endis;
	document.iform.regdhcpstatic.disabled = endis;
	document.iform.regdhcpfilter.disabled = endis;
	document.iform.dhcpfirst.disabled = endis;
}
function show_advanced_dns() {
	document.getElementById("showadvbox").innerHTML='';
	aodiv = document.getElementById('showadv');
	aodiv.style.display = "block";
}
//]]>
</script>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_dnsmasq.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (!$allowMultiInstance) print_info_box(gettext("Enable multiple instances for dnsmasq to enable this service.")); ?>
<?php //if (isset($config['dnsmasq']['allow_multi']) && !isset($config['dnsmasq']['enable'])) print_info_box(gettext("Please <a href=\"services_dnsmasq_instances.php\">enable the service</a> to run this instance.")); ?>
<?php if (is_subsystem_dirty('hosts')): ?><br/>
<?php print_info_box_np(gettext("The DNS forwarder configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
<?php endif;

if ($showMultiInstanceOptions): ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
	/* active tabs */
	$tab_array = array();
	$i = 0; // count tabs (= instances)
	foreach ($a_instances as &$other) {
		$active = ($i == $instanceIndex);
		$tab_array[] = array(sprintf(gettext("Instance %s"), $i), $active, "services_dnsmasq.php?instance={$i}");
		++$i;
	}
	if ($isNewInstance) {
		$tab_array[] = array(gettext("New instance"), true);
		++$i;
	}
	if ($i == 0) {
		echo "</td></tr></table></form>";
		include("fend.inc");
		echo "</body>";
		echo "</html>";
		exit;
	}
	display_top_tabs($tab_array);
?>
</td></tr>
<tr>
<td>
	<div id="mainarea">
	<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="dns forwarder">
<?php else: ?>
<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="dns forwarder">
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?=gettext("General DNS Forwarder Options");?></td>
	</tr>
<?php endif; ?>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Enable");?></td>
		<td width="78%" class="vtable"><p>
			<input name="enable" type="checkbox" id="enable" value="yes" <?php if (!$allowMultiInstance) echo "disabled=\"disabled\""; elseif ($pconfig['enable'] == "yes") echo "checked=\"checked\"";?> onclick="enable_change(false)" />
			<strong><?php
				if ($showMultiInstanceOptions)
					echo gettext("Enable instance");
				else
					echo gettext("Enable DNS forwarder");
			?><br />
			</strong></p></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("DHCP Registration");?></td>
		<td width="78%" class="vtable"><p>
			<input name="regdhcp" type="checkbox" id="regdhcp" value="yes" <?php if ($pconfig['regdhcp'] == "yes") echo "checked=\"checked\"";?> />
			<strong><?=gettext("Register DHCP leases in DNS forwarder");?><br />
			</strong><?php printf(gettext("If this option is set, then machines that specify".
			" their hostname when requesting a DHCP lease will be registered".
			" in the DNS forwarder, so that their name can be resolved.".
			" You should also set the domain in %sSystem:".
			" General setup%s to the proper value."),'<a href="system.php">','</a>')?></p>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Static DHCP");?></td>
		<td width="78%" class="vtable"><p>
			<input name="regdhcpstatic" type="checkbox" id="regdhcpstatic" value="yes" <?php if ($pconfig['regdhcpstatic'] == "yes") echo "checked=\"checked\"";?> />
			<strong><?=gettext("Register DHCP static mappings in DNS forwarder");?><br />
			</strong><?php printf(gettext("If this option is set, then DHCP static mappings will ".
					"be registered in the DNS forwarder, so that their name can be ".
					"resolved. You should also set the domain in %s".
					"System: General setup%s to the proper value."),'<a href="system.php">','</a>');?></p>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Prefer DHCP");?></td>
		<td width="78%" class="vtable"><p>
			<input name="dhcpfirst" type="checkbox" id="dhcpfirst" value="yes" <?php if ($pconfig['dhcpfirst'] == "yes") echo "checked=\"checked\"";?> />
			<strong><?=gettext("Resolve DHCP mappings first");?><br />
			</strong><?php printf(gettext("If this option is set, then DHCP mappings will ".
					"be resolved before the manual list of names below. This only ".
					"affects the name given for a reverse lookup (PTR)."));?></p>
		</td>
	</tr>
	<?php if ($showMultiInstanceOptions): ?>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Filter DHCP");?></td>
		<td width="78%" class="vtable"><p>
			<input name="regdhcpfilter" type="checkbox" id="regdhcpfilter" value="yes" <?php if ($pconfig['regdhcpfilter'] == "yes") echo "checked=\"checked\"";?>/>
			<strong><?=gettext("Filter DHCP mappings");?><br />
			</strong><?php printf(gettext("If this option is set, then DHCP mappings will ".
					"be filtered so that they must be in at least one subnet defined by ".
					"any of the selected interfaces."));?></p>
		</td>
	</tr>
	<?php endif; ?>
	<tr>
		<td rowspan="3" width="22%" valign="top" class="vncellreq"><?=gettext("DNS Query Forwarding");?></td>
		<td width="78%" class="vtable"><p>
			<input name="strict_order" type="checkbox" id="strict_order" value="yes" <?php if ($pconfig['strict_order'] == "yes") echo "checked=\"checked\"";?> />
			<strong><?=gettext("Query DNS servers sequentially");?><br />
			</strong><?php printf(gettext("If this option is set, %s DNS Forwarder (dnsmasq) will ".
					"query the DNS servers sequentially in the order specified (<i>System - General Setup - DNS Servers</i>), ".
					"rather than all at once in parallel. ".
					""), $g['product_name']); ?></p>
		</td>
	</tr>
	<tr>
		<td width="78%" class="vtable"><p>
			<input name="domain_needed" type="checkbox" id="domain_needed" value="yes" <?php if ($pconfig['domain_needed'] == "yes") echo "checked=\"checked\"";?> />
			<strong><?=gettext("Require domain");?><br />
			</strong><?php printf(gettext("If this option is set, %s DNS Forwarder (dnsmasq) will ".
					"not forward A or AAAA queries for plain names, without dots or domain parts, to upstream name servers.  ".
					"If the name is not known from /etc/hosts or DHCP then a \"not found\" answer is returned. ".
					""), $g['product_name']); ?></p>
		</td>
	</tr>
	<tr>
		<td width="78%" class="vtable"><p>
			<input name="no_private_reverse" type="checkbox" id="no_private_reverse" value="yes" <?php if ($pconfig['no_private_reverse'] == "yes") echo "checked=\"checked\"";?> />
			<strong><?=gettext("Do not forward private reverse lookups");?><br />
			</strong><?php printf(gettext("If this option is set, %s DNS Forwarder (dnsmasq) will ".
					"not forward reverse DNS lookups (PTR) for private addresses (RFC 1918) to upstream name servers.  ".
					"Any entries in the Domain Overrides section forwarding private \"n.n.n.in-addr.arpa\" names to a specific server are still forwarded. ".
					"If the IP to name is not known from /etc/hosts, DHCP or a specific domain override then a \"not found\" answer is immediately returned. ".
					""), $g['product_name']); ?></p>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Listen Port");?></td>
		<td width="78%" class="vtable"><p>
			<input name="port" type="text" id="port" size="6" <?php if ($pconfig['port']) echo "value=\"{$pconfig['port']}\"";?> />
			<br /><br />
			<?=gettext("The port used for responding to DNS queries. It should normally be left blank unless another service needs to bind to TCP/UDP port 53.");?></p>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" rowspan="2" class="vncellreq"><?=gettext("Interfaces"); ?></td>
		<td width="78%" class="vtable">
		<?php
			$interface_addresses = get_possible_listen_ips(true);
			$size=count($interface_addresses)+1;
		?>
			<?=gettext("Interface IPs used by the DNS Forwarder for responding to queries from clients. If an interface has both IPv4 and IPv6 IPs, both are used. Queries to other interface IPs not selected below are discarded. The default behavior is to respond to queries on every available IPv4 and IPv6 address.");?>
			<br /><br />
			<select id="interface" name="interface[]" multiple="multiple" class="formselect" size="<?php echo $size; ?>">
				<option value="" <?php if (empty($pconfig['interface']) || empty($pconfig['interface'][0])) echo 'selected="selected"'; ?>>All</option>
			<?php  foreach ($interface_addresses as $laddr):
					$selected = "";
					if (in_array($laddr['value'], $pconfig['interface']))
						$selected = 'selected="selected"';
			?>
				<option value="<?=$laddr['value'];?>" <?=$selected;?>>
					<?=htmlspecialchars($laddr['name']);?>
				</option>
			<?php endforeach; ?>
			</select>
			<br /><br />
		</td>
	</tr>
	<tr>
		<td width="78%" class="vtable"><p>
			<input name="strictbind" type="checkbox" id="strictbind" value="yes" <?php if ($pconfig['strictbind'] == "yes") echo "checked=\"checked\"";?> />
			<strong><?=gettext("Strict Interface Binding");?></strong>
			<br />
			<?= gettext("If this option is set, the DNS forwarder will only bind to the interfaces containing the IP addresses selected above, rather than binding to all interfaces and discarding queries to other addresses."); ?>
			<br /><br />
			<?= gettext("NOTE: This option does NOT work with IPv6. If set, dnsmasq will not bind to IPv6 addresses."); ?>
			</p>
		</td>
	</tr>
	<?php if (!isset($_REQUEST['instance'])): ?>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Multiple instances");?></td>
		<td width="78%" class="vtable"><p>
			<input name="allow_multi" type="checkbox" id="allow_multi" value="yes" <?php if ($pconfig['allow_multi'] == "yes") echo "checked=\"checked\"";?> onclick="enable_change(false)"/>
			<strong><?= gettext("Allow multiple instances"); ?></strong>
			<br /><br />
			<?= gettext("NOTE: Only one instance can bind to a interface and port combination. Use strict interface binding to listen to specific interfaces."); ?>
			</p>
		</td>
	</tr>
	<?php endif; ?>
	<?php if ($showMultiInstanceOptions): ?>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Description");?></td>
		<td width="78%" class="vtable"><p>
			<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"/>
			<br /> <span class="vexpl"><?=gettext("You may enter a description here".
			" for your reference (not parsed).");?></span>
		</p></td>
	</tr>
	<?php endif; ?>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Advanced");?></td>
		<td width="78%" class="vtable">
			<div id="showadvbox" <?php if ($pconfig['custom_options']) echo "style='display:none'"; ?>>
				<input type="button" onclick="show_advanced_dns()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
			</div>
			<div id="showadv" <?php if (empty($pconfig['custom_options'])) echo "style='display:none'"; ?>>
				<strong><?=gettext("Advanced");?><br /></strong>
				<textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=htmlspecialchars($pconfig['custom_options']);?></textarea><br />
				<?=gettext("Enter any additional options you would like to add to the dnsmasq configuration here, separated by a space or newline"); ?><br />
			</div>
		</td>
	</tr>
	<tr>
		<?php if ($showMultiInstanceOptions): ?>
		<td width="22%" valign="top">&nbsp;</td>
		<td>
		<?php else: ?>
		<td colspan="2">
		<?php endif; ?>
			<input name="submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onclick="enable_change(true)" />
			<?php if (isset($_REQUEST['instance'])): ?>
			<input name="instance" type="hidden" value="<?=htmlspecialchars($instanceIndex);?>"/>
			<?php endif; ?>
		</td>
	</tr>
</table>
<?php if ($showMultiInstanceOptions): ?>
	</div>
</td>
</tr>
</table>
<?php endif; ?>

<p><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br />
</strong></span><?php printf(gettext("If the DNS forwarder is enabled, the DHCP".
" service (if enabled) will automatically serve the LAN IP".
" address as a DNS server to DHCP clients so they will use".
" the forwarder. The DNS forwarder will use the DNS servers".
" entered in %sSystem: General setup%s".
" or those obtained via DHCP or PPP on WAN if the &quot;Allow".
" DNS server list to be overridden by DHCP/PPP on WAN&quot;".
" is checked. If you don't use that option (or if you use".
" a static IP address on WAN), you must manually specify at".
" least one DNS server on the %sSystem:".
"General setup%s page."),'<a href="system.php">','</a>','<a href="system.php">','</a>');?><br />
</span></p>

&nbsp;<br />
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont" summary="host overrides">
<tr>
	<td colspan="5" valign="top" class="listtopic"><?=gettext("Host Overrides");?></td>
</tr>
<tr>
	<td><br />
	<?=gettext("Entries in this section override individual results from the forwarders.");?>
	<?=gettext("Use these for changing DNS results or for adding custom DNS records.");?>
	</td>
</tr>
</table>
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont sortable" summary="results">
	<thead>
	<tr>
		<td width="20%" class="listhdrr"><?=gettext("Host");?></td>
		<td width="25%" class="listhdrr"><?=gettext("Domain");?></td>
		<td width="20%" class="listhdrr"><?=gettext("IP");?></td>
		<td width="25%" class="listhdr"><?=gettext("Description");?></td>
		<td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="icons">
				<tr>
					<td width="17"></td>
					<td valign="middle"><a href="services_dnsmasq_edit.php<?=getInstanceParam('?');?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
				</tr>
			</table>
		</td>
	</tr>
	</thead>
	<tfoot>
	<tr>
		<td class="list" colspan="4"></td>
		<td class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="add">
				<tr>
					<td width="17"></td>
					<td valign="middle"><a href="services_dnsmasq_edit.php<?=getInstanceParam('?');?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
				</tr>
			</table>
		</td>
	</tr>
	</tfoot>
	<tbody>
	<?php $instanceParam = getInstanceParam('','&amp;'); $i = 0; foreach ($a_hosts as $hostent): ?>
	<tr>
		<td class="listlr" ondblclick="document.location='services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>';">
			<?=strtolower($hostent['host']);?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>';">
			<?=strtolower($hostent['domain']);?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>';">
			<?=$hostent['ip'];?>&nbsp;
		</td>
		<td class="listbg" ondblclick="document.location='services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>';">
			<?=htmlspecialchars($hostent['descr']);?>&nbsp;
		</td>
		<td valign="middle" class="list nowrap">
			<table border="0" cellspacing="0" cellpadding="1" summary="icons">
				<tr>
					<td valign="middle"><a href="services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="edit" /></a></td>
					<td><a href="services_dnsmasq.php?<?=$instanceParam;?>type=host&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this host?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="delete" /></a></td>
				</tr>
			</table>
		</td>
	</tr>
	<?php if ($hostent['aliases']['item'] && is_array($hostent['aliases']['item'])): ?>
	<?php foreach ($hostent['aliases']['item'] as $alias): ?>
	<tr>
		<td class="listlr" ondblclick="document.location='services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>';">
			<?=strtolower($alias['host']);?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>';">
			<?=strtolower($alias['domain']);?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>';">
			Alias for <?=$hostent['host'] ? $hostent['host'] . '.' . $hostent['domain'] : $hostent['domain'];?>&nbsp;
		</td>
		<td class="listbg" ondblclick="document.location='services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>';">
			<?=htmlspecialchars($alias['description']);?>&nbsp;
		</td>
		<td valign="middle" class="list nowrap">
			<a href="services_dnsmasq_edit.php?<?=$instanceParam;?>id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="edit" /></a>
		</td>
	</tr>
	<?php endforeach; ?>
	<?php endif; ?>
	<?php $i++; endforeach; ?>
	<tr style="display:none"><td></td></tr>
	</tbody>
</table>
<br />
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont" summary="domain overrides">
<tr>
	<td colspan="5" valign="top" class="listtopic"><?=gettext("Domain Overrides");?></td>
</tr>
<tr>
	<td><p><?=gettext("Entries in this area override an entire domain, and subdomains, by specifying an".
	" authoritative DNS server to be queried for that domain.");?></p></td>
</tr>
</table>
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont sortable" summary="results">
	<thead>
	<tr>
		<td width="35%" class="listhdrr"><?=gettext("Domain");?></td>
		<td width="20%" class="listhdrr"><?=gettext("IP");?></td>
		<td width="35%" class="listhdr"><?=gettext("Description");?></td>
		<td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="add">
				<tr>
					<td width="17" height="17"></td>
					<td><a href="services_dnsmasq_domainoverride_edit.php<?=getInstanceParam('?');?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
				</tr>
			</table>
		</td>
	</tr>
	</thead>
	<tfoot>
	<tr>
		<td class="list" colspan="3"></td>
		<td class="list">
		<table border="0" cellspacing="0" cellpadding="1" summary="add">
			<tr>
				<td width="17" height="17"></td>
				<td><a href="services_dnsmasq_domainoverride_edit.php<?=getInstanceParam('?');?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
			</tr>
		</table>
		</td>
	</tr>
	</tfoot>
	<tbody>
	<?php $i = 0; foreach ($a_domainOverrides as $doment): ?>
	<tr>
		<td class="listlr">
			<?=strtolower($doment['domain']);?>&nbsp;
		</td>
		<td class="listr">
			<?=$doment['ip'];?>&nbsp;
		</td>
		<td class="listbg">
			<?=htmlspecialchars($doment['descr']);?>&nbsp;
		</td>
		<td valign="middle" class="list nowrap"> <a href="services_dnsmasq_domainoverride_edit.php?<?=$instanceParam;?>id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="edit" /></a>
			&nbsp;<a href="services_dnsmasq.php?<?=$instanceParam;?>act=del&amp;type=doverride&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this domain override?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="delete" /></a></td>
	</tr>
	<?php $i++; endforeach; ?>
	<tr style="display:none"><td></td></tr>
	</tbody>
</table>
</form>
<script type="text/javascript">
//<![CDATA[
enable_change(false);
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
