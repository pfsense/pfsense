<?php
/*
	services_captiveportal_hostname.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-services-captiveportal-allowedhostnames
##|*NAME=Services: Captive portal: Allowed Hostnames
##|*DESCR=Allow access to the 'Services: Captive portal: Allowed Hostnames' page.
##|*MATCH=services_captiveportal_hostname.php*
##|-PRIV

$directionicons = array('to' => '&#x2192;', 'from' => '&#x2190;', 'both' => '&#x21c4;');

$notestr =
	gettext('Adding new hostnames will allow a DNS hostname access to/from the captive portal without being taken to the portal page.' .
	'This can be used for a web server serving images for the portal page, or a DNS server on another network, for example. ' .
	'By specifying <em>from</em> addresses, it may be used to always allow pass-through access from a client behind the captive portal.');

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}

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

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), "Zone " . $a_cp[$cpzone]['zone'], gettext("Allowed Hostnames"));
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
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), true, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);
?>
<div class="table-responsive">
	<table class="table table-hover table-striped table-condensed">
		<thead>
			<tr>
			  <th><?=gettext("Hostname"); ?></th>
			  <th><?=gettext("Description"); ?></th>
			  <th><!-- Buttons --></th>
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
	<?=print_info_box($notestr)?>
</div>

<?php

include("foot.inc");
