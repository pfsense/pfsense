<?php
/*
	status_lb_vs.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-status-loadbalancer-virtualserver
##|*NAME=Status: Load Balancer: Virtual Server
##|*DESCR=Allow access to the 'Status: Load Balancer: Virtual Server' page.
##|*MATCH=status_lb_vs.php*
##|-PRIV

define('COLOR', true);
define('LIGHTGREEN', '#90EE90');
define('LIGHTCORAL', '#F08080');
define('KHAKI',		 '#F0E68C');
define('LIGHTGRAY',	 '#D3D3D3');
define('WHITE',		 '#FFFFFF');

require_once("guiconfig.inc");
require_once("vslb.inc");

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
if (!is_array($config['load_balancer']['virtual_server'])) {
	$config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];
$a_pool = &$config['load_balancer']['lbpool'];
$rdr_a = get_lb_redirects();

$pgtitle = array(gettext("Status"), gettext("Load Balancer"), gettext("Virtual Servers"));
include("head.inc");

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), false, "status_lb_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), true, "status_lb_vs.php");
display_top_tabs($tab_array);

if (empty($a_vs)) {
	print('<div class="alert alert-danger">' . gettext("No load balancers have been configured!") . '</div>');
} else {
?>
<div class="table-responsive"></div>
	<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?=gettext("Name"); ?></th>
				<th><?=gettext("Address"); ?></th>
				<th><?=gettext("Servers"); ?></th>
				<th><?=gettext("Status"); ?></th>
				<th><?=gettext("Description"); ?></th>
			</tr>
		</thead>
		<tbody>
<?php
			$i = 0;
			foreach ($a_vs as $vsent): ?>
			<tr>
				<td>
					<?=$vsent['name']?>
				</td>
				<td>
					<?=$vsent['ipaddr']." : ".$vsent['port']?><br />
				</td>
				<td>

					<?php
					foreach ($a_pool as $vipent) {
						if ($vipent['name'] == $vsent['poolname']) {
							foreach ((array) $vipent['servers'] as $server) { ?>
								<?=$server?> <br />
<?php
							}
						}
					}
?>
				</td>
				<?php
				switch (trim($rdr_a[$vsent['name']]['status'])) {
					case 'active':
					  $bgcolor = LIGHTGREEN;
					  $rdr_a[$vsent['name']]['status'] = gettext("Active");
					  break;
					case 'down':
					  $bgcolor = LIGHTCORAL;
					  $rdr_a[$vsent['name']]['status'] = gettext("Down");
					  break;
					default:
					  $bgcolor = LIGHTGRAY;
					  $rdr_a[$vsent['name']]['status'] = gettext('Unknown - relayd not running?');
				  }

				if (!COLOR) {
					$bgcolor = WHITE;
				}
?>
				<td style="background-color:<?=$bgcolor?>">
					<?=$rdr_a[$vsent['name']]['status']?>

<?php
					if (!empty($rdr_a[$vsent['name']]['total'])) {
						echo sprintf(gettext("Total Sessions: %s"), $rdr_a[$vsent['name']]['total'] . "<br />");
					}
					if (!empty($rdr_a[$vsent['name']]['last'])) {
						echo sprintf(gettext("Last: %s"), $rdr_a[$vsent['name']]['last'] . "<br />");
					}
					if (!empty($rdr_a[$vsent['name']]['average'])) {
						echo sprintf(gettext("Average: %s"), $rdr_a[$vsent['name']]['average']);
					}
?>
				</td>
				<td>
					<?=htmlspecialchars($vsent['descr'])?>
				</td>
			</tr>

<?php		$i++; endforeach; ?>
		</tbody>
	</table>
</div>

<?php }

include("foot.inc"); ?>
