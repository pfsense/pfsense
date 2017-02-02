<?php
/*
	services_checkip.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Rubicon Communications, LLC (Netgate). All rights reserved.
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
##|*IDENT=page-services-checkipservices
##|*NAME=Services: Check IP Service
##|*DESCR=Allow access to the 'Services: Check IP Service' page.
##|*MATCH=services_checkip.php*
##|-PRIV

require_once("guiconfig.inc");

if (!is_array($config['checkipservices']['checkipservice'])) {
	$config['checkipservices']['checkipservice'] = array();
}

$a_checkipservice = &$config['checkipservices']['checkipservice'];

$dirty = false;
if ($_GET['act'] == "del") {
	unset($a_checkipservice[$_GET['id']]);
	$dirty = true;
} else if ($_GET['act'] == "toggle") {
	if ($a_checkipservice[$_GET['id']]) {
		if (isset($a_checkipservice[$_GET['id']]['enable'])) {
			unset($a_checkipservice[$_GET['id']]['enable']);
		} else {
			$a_checkipservice[$_GET['id']]['enable'] = true;
		}
		$dirty = true;
	} else if ($_GET['id'] == count($a_checkipservice)) {
		if (isset($config['checkipservices']['disable_factory_default'])) {
			unset($config['checkipservices']['disable_factory_default']);
		} else {
			$config['checkipservices']['disable_factory_default'] = true;
		}
		$dirty = true;
	}
}
if ($dirty) {
	write_config();

	header("Location: services_checkip.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS"), gettext("Check IP Services"));
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
							<th><?=gettext("Verify SSL Peer")?></th>
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
							<a	class="fa fa-ban" title="<?=gettext('Disable service')?>" href="?act=toggle&amp;id=<?=$i?>"></a>
						<?php } else {
						?>
							<a class="fa fa-check-square-o" title="<?=gettext('Enable service')?>" href="?act=toggle&amp;id=<?=$i?>"></a>
						<?php }
						?>
							<a class="fa fa-trash <?=$visibility?>" title="<?=gettext('Delete service')?>" href="services_checkip.php?act=del&amp;id=<?=$i?>"></a>
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
