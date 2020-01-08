<?php
/*
 * services_checkip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-services-checkipservices
##|*NAME=Services: Check IP Service
##|*DESCR=Allow access to the 'Services: Check IP Service' page.
##|*MATCH=services_checkip.php*
##|-PRIV

require_once("guiconfig.inc");

init_config_arr(array('checkipservices', 'checkipservice'));
$a_checkipservice = &$config['checkipservices']['checkipservice'];

$dirty = false;
if ($_POST['act'] == "del") {
	unset($a_checkipservice[$_POST['id']]);
	$wc_msg = gettext('Deleted a check IP service.');
	$dirty = true;
} else if ($_POST['act'] == "toggle") {
	if ($a_checkipservice[$_POST['id']]) {
		if (isset($a_checkipservice[$_POST['id']]['enable'])) {
			unset($a_checkipservice[$_POST['id']]['enable']);
			$wc_msg = gettext('Disabled a check IP service.');
		} else {
			$a_checkipservice[$_POST['id']]['enable'] = true;
			$wc_msg = gettext('Enabled a check IP service.');
		}
		$dirty = true;
	} else if ($_POST['id'] == count($a_checkipservice)) {
		if (isset($config['checkipservices']['disable_factory_default'])) {
			unset($config['checkipservices']['disable_factory_default']);
			$wc_msg = gettext('Enabled the default check IP service.');
		} else {
			$config['checkipservices']['disable_factory_default'] = true;
			$wc_msg = gettext('Disabled the default check IP service.');
		}
		$dirty = true;
	}
}
if ($dirty) {
	write_config($wc_msg);

	header("Location: services_checkip.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS"), gettext("Check IP Services"));
$pglinks = array("", "services_dyndns.php", "@self");
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Dynamic DNS Clients"), false, "services_dyndns.php");
$tab_array[] = array(gettext("RFC 2136 Clients"), false, "services_rfc2136.php");
$tab_array[] = array(gettext("Check IP Services"), true, "services_checkip.php");
display_top_tabs($tab_array);

if ($input_errors) {
	print_input_errors($input_errors);
}
?>

<form action="services_checkip.php" method="post" name="iform" id="iform">
	<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Check IP Services')?></h2></div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Name")?></th>
							<th><?=gettext("URL")?></th>
							<th><?=gettext("Verify SSL/TLS Peer")?></th>
							<th><?=gettext("Description")?></th>
							<th><?=gettext("Actions")?></th>
						</tr>
					</thead>
					<tbody>
<?php
// Is the factory default check IP service disabled?
if (isset($config['checkipservices']['disable_factory_default'])) {
	unset($factory_default_checkipservice['enable']);
}

// Append the factory default check IP service to the list.
$a_checkipservice[] = $factory_default_checkipservice;
$factory_default = count($a_checkipservice) - 1;

$i = 0;
foreach ($a_checkipservice as $checkipservice):

	// Hide edit and delete controls on the factory default check IP service entry (last one; id = count-1), and retain layout positioning.
	if ($i == $factory_default) {
		$visibility = 'invisible';
	} else {
		$visibility = 'visible';
	}
?>
						<tr<?=(isset($checkipservice['enable']) ? '' : ' class="disabled"')?>>
						<td>
							<?=htmlspecialchars($checkipservice['name'])?>
						</td>
						<td>
							<?=htmlspecialchars($checkipservice['url'])?>
						</td>
						<td class="text-center">
							<i<?=(isset($checkipservice['verifysslpeer'])) ? ' class="fa fa-check"' : '';?>></i>
						</td>
						<td>
							<?=htmlspecialchars($checkipservice['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil <?=$visibility?>" title="<?=gettext('Edit service')?>" href="services_checkip_edit.php?id=<?=$i?>"></a>
						<?php if (isset($checkipservice['enable'])) {
						?>
							<a	class="fa fa-ban" title="<?=gettext('Disable service')?>" href="?act=toggle&amp;id=<?=$i?>" usepost></a>
						<?php } else {
						?>
							<a class="fa fa-check-square-o" title="<?=gettext('Enable service')?>" href="?act=toggle&amp;id=<?=$i?>" usepost></a>
						<?php }
						?>
							<a class="fa fa-trash <?=$visibility?>" title="<?=gettext('Delete service')?>" href="services_checkip.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
	$i++;
endforeach; ?>

					</tbody>
				</table>
			</div>
		</div>
	</div>
</form>

<nav class="action-buttons">
	<a href="services_checkip_edit.php" class="btn btn-sm btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(gettext('The server must return the client IP address ' .
	'as a string in the following format: ') .
	'<pre>Current IP Address: x.x.x.x</pre>' .
	gettext(
	'The first (highest in list) enabled check ip service will be used to ' .
	'check IP addresses for Dynamic DNS services, and ' .
	'RFC 2136 entries that have the "Use public IP" option enabled.') .
	'<br/><br/>'
	, 'info', false);

	print_info_box(gettext('Sample Server Configurations') .
	'<br/>' .
	gettext('nginx with LUA') . ':' .
	'<pre> location = /ip {
	default_type text/html;
	content_by_lua \'
		ngx.say("' . htmlspecialchars('<html><head><title>Current IP Check</title></head><body>') . 'Current IP Address: ")
		ngx.say(ngx.var.remote_addr)
		ngx.say("' . htmlspecialchars('</body></html>') . '")
	\';
	}</pre>' .
	gettext('PHP') .
	'<pre>' .
	htmlspecialchars('<html><head><title>Current IP Check</title></head><body>Current IP Address: <?=$_SERVER[\'REMOTE_ADDR\']?></body></html>') .
	'</pre>'
	, 'info', false); ?>
</div>

<?php include("foot.inc");
