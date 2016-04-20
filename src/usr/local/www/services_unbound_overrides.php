<?php
/*
	services_unbound.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2014 Warren Baker (warren@pfsense.org)
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
##|*IDENT=page-services-dnsresolver-overrides
##|*NAME=Services: DNS Resolver Overrides
##|*DESCR=Allow access to the 'Services: DNS Resolver' Overrides page.
##|*MATCH=services_unbound_overrides.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("unbound.inc");
require_once("system.inc");

if (!is_array($config['unbound'])) {
	$config['unbound'] = array();
}

$a_unboundcfg =& $config['unbound'];

if (!is_array($a_unboundcfg['hosts'])) {
	$a_unboundcfg['hosts'] = array();
}

$a_hosts =& $a_unboundcfg['hosts'];

if (!is_array($a_unboundcfg['domainoverrides'])) {
	$a_unboundcfg['domainoverrides'] = array();
}

$a_domainOverrides = &$a_unboundcfg['domainoverrides'];

if ($_POST) {
	if ($_POST['apply']) {
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			clear_subsystem_dirty('unbound');
		}
		/* Update resolv.conf in case the interface bindings exclude localhost. */
		system_resolvconf_generate();
		/* Start or restart dhcpleases when it's necessary */
		system_dhcpleases_configure();
	}
}

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'host') {
		if ($a_hosts[$_GET['id']]) {
			unset($a_hosts[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('unbound');
			header("Location: services_unbound_overrides.php");
			exit;
		}
	} elseif ($_GET['type'] == 'doverride') {
		if ($a_domainOverrides[$_GET['id']]) {
			unset($a_domainOverrides[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('unbound');
			header("Location: services_unbound_overrides.php");
			exit;
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("DNS Resolver"), gettext("Overrides"));
$shortcut_section = "resolver";

include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('unbound')) {
	print_apply_box(gettext("The DNS resolver configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}


?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Host Overrides")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Host")?></th>
					<th><?=gettext("Domain")?></th>
					<th><?=gettext("IP")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
$i = 0;
foreach ($a_hosts as $hostent):
?>
				<tr>
					<td>
						<?=$hostent['host']?>
					</td>
					<td>
						<?=$hostent['domain']?>
					</td>
					<td>
						<?=$hostent['ip']?>
					</td>
					<td>
						<?=htmlspecialchars($hostent['descr'])?>
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit host override')?>" href="services_unbound_host_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete host override')?>" href="services_unbound_overrides.php?type=host&amp;act=del&amp;id=<?=$i?>"></a>
					</td>
				</tr>

<?php
	if ($hostent['aliases']['item'] && is_array($hostent['aliases']['item'])):
		foreach ($hostent['aliases']['item'] as $alias):
?>
				<tr>
					<td>
						<?=$alias['host']?>
					</td>
					<td>
						<?=$alias['domain']?>
					</td>
					<td>
						<?=gettext("Alias for ");?><?=$hostent['host'] ? $hostent['host'] . '.' . $hostent['domain'] : $hostent['domain']?>
					</td>
					<td>
						<i class="fa fa-angle-double-right text-info"></i>
						<?=htmlspecialchars($alias['description'])?>
					</td>
					<td>
						<a a class="fa fa-pencil"	title="<?=gettext('Edit host override')?>" 	href="services_unbound_host_edit.php?id=<?=$i?>"></a>
					</td>
				</tr>
<?php
		endforeach;
	endif;
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_unbound_host_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Domain Overrides")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Domain")?></th>
					<th><?=gettext("IP")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>

			<tbody>
<?php
$i = 0;
foreach ($a_domainOverrides as $doment):
?>
				<tr>
					<td>
						<?=$doment['domain']?>&nbsp;
					</td>
					<td>
						<?=$doment['ip']?>&nbsp;
					</td>
					<td>
						<?=htmlspecialchars($doment['descr'])?>&nbsp;
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit domain override')?>" href="services_unbound_domainoverride_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete domain override')?>" href="services_unbound_overrides.php?act=del&amp;type=doverride&amp;id=<?=$i?>"></a>
					</td>
				</tr>
<?php
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_unbound_domainoverride_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext("If the DNS Resolver is enabled, the DHCP".
		" service (if enabled) will automatically serve the LAN IP".
		" address as a DNS server to DHCP clients so they will use".
		" the DNS Resolver. If Forwarding is enabled, the DNS Resolver will use the DNS servers".
		" entered in %sSystem: General setup%s".
		" or those obtained via DHCP or PPP on WAN if &quot;Allow".
		" DNS server list to be overridden by DHCP/PPP on WAN&quot;".
		" is checked."), '<a href="system.php">', '</a>'), 'info', false); ?>
</div>

<?php include("foot.inc");
