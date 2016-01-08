<?php
/*
	services_captiveportal_zones.php
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
##|*IDENT=page-services-captiveportal-zones
##|*NAME=Services: Captive portal Zones
##|*DESCR=Allow access to the 'Services: Captive portal Zones' page.
##|*MATCH=services_captiveportal_zones.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

global $cpzone;
global $cpzoneid;

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp = &$config['captiveportal'];

if ($_GET['act'] == "del" && !empty($_GET['zone'])) {
	$cpzone = htmlspecialchars($_GET['zone']);
	if ($a_cp[$cpzone]) {
		$cpzoneid = $a_cp[$cpzone]['zoneid'];
		unset($a_cp[$cpzone]['enable']);
		captiveportal_configure_zone($a_cp[$cpzone]);
		unset($a_cp[$cpzone]);
		if (isset($config['voucher'][$cpzone])) {
			unset($config['voucher'][$cpzone]);
		}
		write_config();
	}
	header("Location: services_captiveportal_zones.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), gettext("Zones"));
$shortcut_section = "captiveportal";
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('captiveportal')) {
	print_info_box_np(gettext("The Captive Portal entry list has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}
?>
<form action="services_captiveportal_zones.php" method="post">
	<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Captive Portal Zones')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><?=gettext('Zone')?></th>
						<th><?=gettext('Interfaces')?></th>
						<th><?=gettext('Number of users'); ?></th>
						<th><?=gettext('Description'); ?></th>
						<th><?=gettext('Actions'); ?></th>
					</tr>
				</thead>
				<tbody>

<?php
	foreach ($a_cp as $cpzone => $cpitem):
		if (!is_array($cpitem)) {
			continue;
		}
?>
					<tr>
						<td><?=htmlspecialchars($cpitem['zone']);?></td>
						<td>
<?php
		$cpifaces = explode(",", $cpitem['interface']);
		foreach ($cpifaces as $cpiface) {
			echo convert_friendly_interface_to_friendly_descr($cpiface) . " ";
		}
?>
						</td>
						<td><?=count(captiveportal_read_db());?></td>
						<td><?=htmlspecialchars($cpitem['descr']);?>&nbsp;</td>
						<td>
							<a class="fa fa-pencil" title="<?=gettext("Edit zone"); ?>" href="services_captiveportal.php?zone=<?=$cpzone?>"></a>
							<a class="fa fa-trash"  title="<?=gettext("Delete zone")?>" href="services_captiveportal_zones.php?act=del&amp;zone=<?=$cpzone;?>"></a>
						</td>
					</tr>
<?php
	endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<nav class="action-buttons">
	<a href="services_captiveportal_zones_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<?php include("foot.inc"); ?>
