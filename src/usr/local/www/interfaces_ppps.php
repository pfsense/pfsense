<?php
/*
	interfaces_ppps.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-interfaces-ppps
##|*NAME=Interfaces: PPPs
##|*DESCR=Allow access to the 'Interfaces: PPPs' page.
##|*MATCH=interfaces_ppps.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");

function ppp_inuse($num) {
	global $config, $g;

	$iflist = get_configured_interface_list(false, true);
	if (!is_array($config['ppps']['ppp'])) {
		return false;
	}

	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $config['ppps']['ppp'][$num]['if']) {
			return true;
		}
	}

	return false;
}

if ($_GET['act'] == "del") {
	/* check if still in use */
	if (ppp_inuse($_GET['id'])) {
		$input_errors[] = gettext("This point-to-point link cannot be deleted because it is still being used as an interface.");
	} elseif (is_array($config['ppps']['ppp']) && is_array($config['ppps']['ppp'][$_GET['id']])) {

		unset($config['ppps']['ppp'][$_GET['id']]['pppoe-reset-type']);
		handle_pppoe_reset($config['ppps']['ppp'][$_GET['id']]);
		unset($config['ppps']['ppp'][$_GET['id']]);
		write_config();
		header("Location: interfaces_ppps.php");
		exit;
	}
}

if (!is_array($config['ppps']['ppp'])) {
	$config['ppps']['ppp'] = array();
}
$a_ppps = $config['ppps']['ppp'];

$pgtitle = array(gettext("Interfaces"), gettext("PPPs"));
$shortcut_section = "interfaces";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), true, "interfaces_ppps.php");
$tab_array[] = array(gettext("GRE"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIF"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGG"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
			  <th><?=gettext("Interface"); ?></th>
			  <th><?=gettext("Interface(s)/Port(s)"); ?></th>
			  <th><?=gettext("Description"); ?></th>
			  <th></th>
			</tr>
		</thead>
		<tbody>
<?php

$i = 0;

foreach ($a_ppps as $id => $ppp) {
?>
			<tr>
				<td>
					<?=htmlspecialchars($ppp['if'])?>
				</td>
				<td>
<?php
	$portlist = explode(",", $ppp['ports']);
	foreach ($portlist as $portid => $port) {
		if ($port != get_real_interface($port) && $ppp['type'] != "ppp") {
			$portlist[$portid] = convert_friendly_interface_to_friendly_descr($port);
		}
	}
					echo htmlspecialchars(implode(",", $portlist));
?>
				</td>
				<td>
					<?=htmlspecialchars($ppp['descr'])?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext('Edit PPP interface')?>"	href="interfaces_ppps_edit.php?id=<?=$i?>"></a>
					<a class="fa fa-trash"	title="<?=gettext('Delete PPP interface')?>"	href="interfaces_ppps.php?act=del&amp;id=<?=$i?>"></a>
				</td>
			</tr>
<?php
	$i++;
}
?>
		</tbody>
	</table>

	<nav class="action-buttons">
		<a href="interfaces_ppps_edit.php" class="btn btn-success btn-sm">
	   		<i class="fa fa-plus icon-embed-btn"></i>
	   		<?=gettext("Add")?>
	   	</a>
	</nav>
</div>
<?php
include("foot.inc");

