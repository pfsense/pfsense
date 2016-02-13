<?php
/*
	services_wol.php
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
##|*IDENT=page-services-wakeonlan
##|*NAME=Services: Wake-on-LAN
##|*DESCR=Allow access to the 'Services: Wake-on-LAN' page.
##|*MATCH=services_wol.php*
##|-PRIV

require("guiconfig.inc");
if (!is_array($config['wol']['wolentry'])) {
	$config['wol']['wolentry'] = array();
}
$a_wol = &$config['wol']['wolentry'];

if ($_GET['wakeall'] != "") {
	$i = 0;
	$savemsg = "";
	foreach ($a_wol as $wolent) {
		$mac = $wolent['mac'];
		$if = $wolent['interface'];
		$description = $wolent['descr'];
		$ipaddr = get_interface_ip($if);
		if (!is_ipaddr($ipaddr)) {
			continue;
		}
		$bcip = gen_subnet_max($ipaddr, get_interface_subnet($if));
		/* Execute wol command and check return code. */
		if (!mwexec("/usr/local/bin/wol -i {$bcip} {$mac}")) {
			$savemsg .= sprintf(gettext('Sent magic packet to %1$s (%2$s).'), $mac, $description) . "<br />";
			$class = 'success';
		} else {
			$savemsg .= sprintf(gettext('Please check the %1$ssystem log%2$s, the wol command for %3$s (%4$s) did not complete successfully.'), '<a href="/status_logs.php">', '</a>', $description, $mac) . "<br />";
			$class = 'warning';
		}
	}
}

if ($_POST || $_GET['mac']) {
	unset($input_errors);

	if ($_GET['mac']) {
		/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
		$_GET['mac'] = strtolower(str_replace("-", ":", $_GET['mac']));
		$mac = $_GET['mac'];
		$if = $_GET['if'];
	} else {
		/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
		$_POST['mac'] = strtolower(str_replace("-", ":", $_POST['mac']));
		$mac = $_POST['mac'];
		$if = $_POST['interface'];
	}

	/* input validation */
	if (!$mac || !is_macaddr($mac)) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}
	if (!$if) {
		$input_errors[] = gettext("A valid interface must be specified.");
	}

	if (!$input_errors) {
		/* determine broadcast address */
		$ipaddr = get_interface_ip($if);
		if (!is_ipaddr($ipaddr)) {
			$input_errors[] = gettext("A valid ip could not be found!");
		} else {
			$bcip = gen_subnet_max($ipaddr, get_interface_subnet($if));
			/* Execute wol command and check return code. */
			if (!mwexec("/usr/local/bin/wol -i {$bcip} " . escapeshellarg($mac))) {
				$savemsg .= sprintf(gettext("Sent magic packet to %s."), $mac);
				$class = 'success';
			} else {
				$savemsg .= sprintf(gettext('Please check the %1$ssystem log%2$s, the wol command for %3$s did not complete successfully.'), '<a href="/status_logs.php">', '</a>', $mac) . "<br />";
				$class = 'warning';
			}
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_wol[$_GET['id']]) {
		unset($a_wol[$_GET['id']]);
		write_config();
		header("Location: services_wol.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Wake-on-LAN"));
include("head.inc");
?>
<div class="infoblock blockopen">
<?php
print_info_box(gettext('This service can be used to wake up (power on) computers by sending special "Magic Packets".') . '<br />' .
			   gettext('The NIC in the computer that is to be woken up must support Wake-on-LAN and must be properly configured (WOL cable, BIOS settings).'),
			   'info', false);

?>
</div>
<?php

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, $class);
}

$form = new Form('Send');

$section = new Form_Section('Wake-on-LAN');

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	(link_interface_to_bridge($if) ? null : $if),
	get_configured_interface_with_descr()
))->setHelp('Choose which interface the host to be woken up is connected to.');

$section->addInput(new Form_Input(
	'mac',
	'MAC address',
	'text',
	$mac
))->setHelp(gettext('Enter a MAC address in the following format: xx:xx:xx:xx:xx:xx'));

$form->add($section);
print $form;
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Wake-on-LAN Devices");?></h2>
	</div>

	<div class="panel-body">
		<p><?=gettext("Click the MAC address to wake up an individual device.")?></p>
		<div class="table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("MAC address")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($a_wol as $i => $wolent): ?>
						<tr>
							<td>
								<?=convert_friendly_interface_to_friendly_descr($wolent['interface']);?>
							</td>
							<td>
								<a href="?mac=<?=$wolent['mac'];?>&amp;if=<?=$wolent['interface'];?>"><?=strtolower($wolent['mac']);?></a>
							</td>
							<td>
								<?=htmlspecialchars($wolent['descr']);?>
							</td>
							<td>
								<a class="fa fa-pencil"	title="<?=gettext('Edit device')?>"	href="services_wol_edit.php?id=<?=$i?>"></a>
								<a class="fa fa-trash"	title="<?=gettext('Delete device')?>" href="services_wol.php?act=del&amp;id=<?=$i?>"></a>
							</td>
						</tr>
					<?php endforeach?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="panel-footer">
		<a class="btn btn-success" href="services_wol_edit.php">
			<?=gettext("Add");?>
		</a>

		<a href="services_wol.php?wakeall=true" role="button" class="btn btn-primary">
			<?=gettext("Wake all devices")?>
		</a>
	</div>
</div>

<?php

include("foot.inc");
