<?php
/*
 * services_captiveportal_hostname.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
##|*IDENT=page-services-captiveportal-allowedhostnames
##|*NAME=Services: Captive portal: Allowed Hostnames
##|*DESCR=Allow access to the 'Services: Captive portal: Allowed Hostnames' page.
##|*MATCH=services_captiveportal_hostname.php*
##|-PRIV

$directionicons = array('to' => '&#x2192;', 'from' => '&#x2190;', 'both' => '&#x21c4;');

$notestr =
	gettext('Adding new hostnames will allow a DNS hostname access to/from the captive portal without being taken to the portal page. ' .
	'This can be used for a web server serving images for the portal page, or a DNS server on another network, for example. ' .
	'By specifying <em>from</em> addresses, it may be used to always allow pass-through access from a client behind the captive portal.');

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}
$cpzone = strtolower($cpzone);

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

if (isset($cpzone) && !empty($cpzone) && isset($a_cp[$cpzone]['zoneid'])) {
	$cpzoneid = $a_cp[$cpzone]['zoneid'];
}

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), $a_cp[$cpzone]['zone'], gettext("Allowed Hostnames"));
$shortcut_section = "captiveportal";

if ($_GET['act'] == "del" && !empty($cpzone) && isset($cpzoneid)) {
	$a_allowedhostnames =& $a_cp[$cpzone]['allowedhostname'];
	if ($a_allowedhostnames[$_GET['id']]) {
		$ipent = $a_allowedhostnames[$_GET['id']];

		if (isset($a_cp[$cpzone]['enable'])) {
			if (is_ipaddr($ipent['hostname'])) {
				$ip = $ipent['hostname'];
			} else {
				$ip = gethostbyname($ipent['hostname']);
			}
			$sn = (is_ipaddrv6($ip)) ? 128 : 32;
			if (is_ipaddr($ip)) {
				$ipfw = pfSense_ipfw_getTablestats($cpzoneid, IP_FW_TABLE_XLISTENTRY, 3, $ip);
				if (is_array($ipfw)) {
					captiveportal_free_dn_ruleno($ipfw['dnpipe']);
					pfSense_pipe_action("pipe delete {$ipfw['dnpipe']}");
					pfSense_pipe_action("pipe delete " . ($ipfw['dnpipe']+1));
				}

				pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XDEL, 3, $ip, $sn);
				pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XDEL, 4, $ip, $sn);
			}
		}

		unset($a_allowedhostnames[$_GET['id']]);
		write_config();
		captiveportal_allowedhostname_configure();
		header("Location: services_captiveportal_hostname.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MACs"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), true, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);
?>
<div class="table-responsive">
	<table class="table table-hover table-striped table-condensed table-rowdblclickedit">
		<thead>
			<tr>
				<th><?=gettext("Hostname"); ?></th>
				<th><?=gettext("Description"); ?></th>
				<th><?=gettext("Actions"); ?></th>
			</tr>
		</thead>

<?php
if (is_array($a_cp[$cpzone]['allowedhostname'])): ?>
		<tbody>
<?php
$i = 0;
foreach ($a_cp[$cpzone]['allowedhostname'] as $ip): ?>
			<tr>
				<td>
					<?=$directionicons[$ip['dir']]?>&nbsp;<?=strtolower($ip['hostname'])?>
				</td>
				<td >
					<?=htmlspecialchars($ip['descr'])?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext("Edit hostname"); ?>" href="services_captiveportal_hostname_edit.php?zone=<?=$cpzone?>&amp;id=<?=$i?>"></a>
					<a class="fa fa-trash"	title="<?=gettext("Delete hostname")?>" href="services_captiveportal_hostname.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i?>"></a>
				</td>
			</tr>
<?php
$i++;
endforeach; ?>
		<tbody>
	</table>
	<?=$directionicons['to'] . ' = ' . sprintf(gettext('All connections %sto%s the hostname are allowed'), '<u>', '</u>') . ', '?>
	<?=$directionicons['from'] . ' = ' . sprintf(gettext('All connections %sfrom%s the hostname are allowed'), '<u>', '</u>') . ', '?>
	<?=$directionicons['both'] . ' = ' . sprintf(gettext('All connections %sto or from%s are allowed'), '<u>', '</u>')?>
<?php
else:
?>
		</tbody>
	</table>
<?php
endif;
?>
</div>

<nav class="action-buttons">
	<a href="services_captiveportal_hostname_edit.php?zone=<?=$cpzone?>&amp;act=add" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box($notestr, 'info', false); ?>
</div>

<?php

include("foot.inc");
