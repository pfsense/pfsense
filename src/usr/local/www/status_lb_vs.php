<?php
/* $Id$ */
/*
	status_lb_vs.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/relayctl
	pfSense_MODULE: routing
*/

##|+PRIV
##|*IDENT=page-status-loadbalancer-virtualserver
##|*NAME=Status: Load Balancer: Virtual Server page
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

$pgtitle = array(gettext("Status"), gettext("Load Balancer"), gettext("Virtual Server"));
include("head.inc");

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), false, "status_lb_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), true, "status_lb_vs.php");
display_top_tabs($tab_array);

if(empty($a_vs))
	print('<div class="alert alert-danger">No load balancers have been configured!</div>');
else {
?>
	<div class="table-responsive"></div>
		<table class="table table-striped table-hover table-condensed">
			<tr>
				<td><?=gettext("Name"); ?></td>
				<td><?=gettext("Address"); ?></td>
				<td><?=gettext("Servers"); ?></td>
				<td><?=gettext("Status"); ?></td>
				<td><?=gettext("Description"); ?></td>
			</tr>
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
					  $rdr_a[$vsent['name']]['status'] = "Active";
					  break;
					case 'down':
					  $bgcolor = LIGHTCORAL;
					  $rdr_a[$vsent['name']]['status'] = "Down";
					  break;
					default:
					  $bgcolor = LIGHTGRAY;
					  $rdr_a[$vsent['name']]['status'] = 'Unknown - relayd not running?';
				  }

				if(!COLOR)
					$bgcolor = WHITE;
?>
				<td bgcolor="<?=$bgcolor?>">
					<?=$rdr_a[$vsent['name']]['status']?>
				</td>
				<td>
<?php
					if (!empty($rdr_a[$vsent['name']]['total'])) { ?>
						Total Sessions: <?=$rdr_a[$vsent['name']]['total']?><br>/><?php
					}
					if (!empty($rdr_a[$vsent['name']]['last'])) { ?>
						Last: <?=$rdr_a[$vsent['name']]['last']?><br>/><?php
					}
					if (!empty($rdr_a[$vsent['name']]['average'])) { ?>
						Average: <?=$rdr_a[$vsent['name']]['average']?><?php
					} ?>
				</td>
				<td>
					<?=htmlspecialchars($vsent['descr'])?>
				</td>
			</tr>

<?php		$i++; endforeach; ?>
		 </table>
	</div>

<?php }

include("foot.inc"); ?>