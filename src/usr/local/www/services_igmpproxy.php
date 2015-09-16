<?php
/* $Id$ */
/*
	services_igmpproxy.php

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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
	pfSense_MODULE:	igmpproxy
*/

##|+PRIV
##|*IDENT=page-services-igmpproxy
##|*NAME=Services: Igmpproxy page
##|*DESCR=Allow access to the 'Services: Igmpproxy' page.
##|*MATCH=services_igmpproxy.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['igmpproxy']['igmpentry'])) {
	$config['igmpproxy']['igmpentry'] = array();
}

//igmpproxy_sort();
$a_igmpproxy = &$config['igmpproxy']['igmpentry'];

if ($_POST) {
	$pconfig = $_POST;

	$retval = 0;
	/* reload all components that use igmpproxy */
	$retval = services_igmpproxy_configure();

	if (stristr($retval, "error") <> true) {
		$savemsg = get_std_save_message($retval);
	} else {
		$savemsg = $retval;
	}

	clear_subsystem_dirty('igmpproxy');
}

if ($_GET['act'] == "del") {
	if ($a_igmpproxy[$_GET['id']]) {
		unset($a_igmpproxy[$_GET['id']]);
		write_config();
		mark_subsystem_dirty('igmpproxy');
		header("Location: services_igmpproxy.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("IGMP Proxy"));
include("head.inc");

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('igmpproxy'))
	print_info_box_np(gettext('The IGMP entry list has been changed.' . '<br />' . 'You must apply the changes in order for them to take effect.'));
?>

<form action="services_igmpproxy.php" method="post">
	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("Name")?></th>
					<th><?=gettext("Type")?></th>
					<th><?=gettext("Values")?></th>
					<th><?=gettext("Description")?></th>
					<th</th>
				</tr>
			</thead>
			<tbody>
<?php
$i = 0;
foreach ($a_igmpproxy as $igmpentry):
?>
				<tr>
					<td>
						<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($igmpentry['ifname']))?>
					</td>
					<td>
						<?=htmlspecialchars($igmpentry['type'])?>
					</td>
					<td>
<?php
	$addresses = implode(", ", array_slice(explode(" ", $igmpentry['address']), 0, 10));
	print($addresses);

	if(count($addresses) < 10) {
		print(' ');
	} else {
		print('...');
	}
?>
					</td>
					<td>
						<?=htmlspecialchars($igmpentry['descr'])?>&nbsp;
					</td>
					<td>
						<a href="services_igmpproxy_edit.php?id=<?=$i?>" class="btn btn-info btn-xs"><?=gettext('Edit')?></a>
						<a href="services_igmpproxy.php?act=del&amp;id=<?=$i?>" class="btn btn-danger btn-xs"><?=gettext('Delete')?></a>
					</td>
				</tr>
<?php
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>

	<nav class="action-buttons">
		<input id="submit" name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save")?>" />
		<a href="services_igmpproxy_edit.php" class="btn btn-success"><?=gettext('Add')?></a>
	</nav>

</form>

<?php

print_info_box(gettext('Please add the interface for upstream, the allowed subnets, and the downstream interfaces you would like the proxy to allow. ' .
					   'Only one "upstream" interface can be configured.'));

include("foot.inc");