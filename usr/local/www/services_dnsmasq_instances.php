<?php
/* $Id$ */
/*
	services_dnsmasq_instances.php

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
	pfSense_MODULE:	dnsforwarder
*/

##|+PRIV
##|*IDENT=page-services-dnsforwarder
##|*NAME=Services: DNS Forwarder page
##|*DESCR=Allow access to the 'Services: DNS Forwarder' page.
##|*MATCH=services_dnsmasq.php*
##|-PRIV

require("guiconfig.inc");
require_once("dnsmasq.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

/* check for old or missing config */
if (!isset($config['dnsmasq']['instances']))
	$config['dnsmasq']['instances'] = array('instance0' => $config['dnsmasq']);

$a_instances = &$config['dnsmasq']['instances'];
$nextInstanceId = count($a_instances);

$pconfig['allow_multi'] = isset($config['dnsmasq']['allow_multi']);
$pconfig['enable'] = isset($config['dnsmasq']['enable']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$pconfig = $_POST;
	unset($input_errors);
	
	$pconfig['enable'] = ($_POST['enable']) ? true : false;
	$pconfig['allow_multi'] = ($_POST['allow_multi']) ? true : false;

	if (!$pconfig['allow_multi']) {
		foreach ($a_instances as &$other)
			unset($other['enable']);
		
		// only update the status of the first instance
		foreach ($a_instances as &$other) {
			$other['enable'] = $pconfig['enable'];
			$config['dnsmasq']['enable'] = $pconfig['enable'];
			break;
		}
		
		$config['dnsmasq']['allow_multi'] = false;
	} else {
		if (!$pconfig['enable']) {
			$config['dnsmasq']['enable'] = false;
			//foreach ($a_instances as &$other)
			//	unset($other['enable']);
		}
		$config['dnsmasq']['allow_multi'] = true;
	}
	
	if (!$input_errors) {
		write_config();

		$retval = services_dnsmasq_configure();
		$savemsg = get_std_save_message($retval);

		// Reload filter (we might need to sync to CARP hosts)
		filter_configure();

		if ($retval == 0)
			clear_subsystem_dirty('hosts');
	}
}

if ($_GET['act'] == "del") {
	// find correct instance
	if (is_numericint($_GET['instance']))
		$instanceIndex = $_GET['instance'];
	if (isset($_POST['instance']) && is_numericint($_POST['instance']))
		$instanceIndex = $_POST['instance'];

	$N = count($a_instances);
	if (!empty($instanceIndex) && $instanceIndex >= 0 && $instanceIndex < $N) {
		// update ids
		$i = 0; $j = 0;
		$new_instances = array();
		foreach ($a_instances as &$instance) {
			if ($i != $instanceIndex)
				$new_instances['instance'.$j++] = $instance;
			++$i;
		}

		$config['dnsmasq']['instances'] = $new_instances;

		write_config();
		mark_subsystem_dirty('hosts');
		header("Location: services_dnsmasq_instances.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("DNS forwarder"));
$shortcut_section = "resolver";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>
<form action="services_dnsmasq_instances.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('hosts')): ?><p>
<?php print_info_box_np(gettext("The DNS forwarder configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
<?php endif; ?>
<table width="100%" border="0" cellpadding="6" cellspacing="0">
<tr>
	<td colspan="2" valign="top" class="listtopic"><?=gettext("General DNS Forwarder Options");?></td>
</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Enable");?></td>
		<td width="78%" class="vtable"><p>
			<input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\"";?> onclick="enable_change(false)"/>
			<strong><?=gettext("Enable DNS forwarder");?><br />
			</strong></p></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?=gettext("Multiple instances");?></td>
		<td width="78%" class="vtable"><p>
			<input name="allow_multi" type="checkbox" id="allow_multi" value="yes" <?php if ($pconfig['allow_multi']) echo "checked=\"checked\"";?> onclick="enable_change(false)"/>
			<strong><?= gettext("Allow multiple instances"); ?></strong>
			<br />
			<?= gettext("If disabled, only the first instance determines the configuration of this service."); ?>
			<br /><br />
			<?= gettext("NOTE: Only one instance can bind to a interface and port combination. Use strict interface binding to listen to specific interfaces."); ?>
			</p>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<input name="submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onclick="enable_change(true)"/>
		</td>
	</tr>
</table>
<div id="boxarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont sortable">
	<thead>
	<tr>
		<td width="10%" class="listhdrr"><?=gettext("Instance");?></td>
		<td width="15%" class="listhdrr"><?=gettext("Interfaces");?></td>
		<td width="10%" class="listhdrr" align="center"><?=gettext("Port");?></td>
		<td width="40%" class="listhdrr"><?=gettext("Description");?></td>
		<td width="10%" class="listhdr" align="center"><?=gettext("Status");?></td>
		<td width="10%" class="list">
			<table width="100%" border="0" cellspacing="0">
				<tr>
					<td align="right" height="17"><a style="position:relative;left:1px" href="services_dnsmasq.php?instance=<?=$nextInstanceId;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" width="17" height="17" border="0"/></a></td>
				</tr>
			</table>
		</td>
	</tr>
	</thead>
	<tfoot>
	<tr>
		<td class="list" colspan="5"></td>
		<td class="list">
		<table width="100%" border="0" cellspacing="0" cellpadding="1">
			<tr>
				<td align="right" height="17"><a style="position:relative;left:1px" href="services_dnsmasq.php?instance=<?=$nextInstanceId;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" width="17" height="17" border="0"/></a></td>
			</tr>
		</table>
		</td>
	</tr>
	</tfoot>
	<tbody>
	<?php

	$services = get_services(true);
	$dnsmasqServices = array();
	foreach($services as $service) {
		if (($service['name'] == 'dnsmasq'))
			$dnsmasqServices[$service['instance'] ?: 0] = $service;
	}

	uasort($dnsmasqServices, "service_name_compare");
	$defaultValue = "<span style=\"font-style:italic\">". gettext('default') ."</span>";

	$i = 0; foreach ($a_instances as $instance): $service = $dnsmasqServices[$i]; ?>
	<tr>
		<td class="listlr">
			<?=$i;?>&nbsp;
		</td>
		<td class="listr">
			<?= !empty($instance['interface']) ? $instance['interface'] : $defaultValue ;?>&nbsp;
		</td>
		<td class="listr" align="center">
			<?= !empty($instance['port']) ? $instance['port'] : $defaultValue ;?>&nbsp;
		</td>
		<td class="listr">
			<?=htmlspecialchars($instance['descr']);?>&nbsp;
		</td>
		<td class="listr">
			<?php if (!empty($service)) echo get_service_status_icon($service, true, true); ?>&nbsp;
		</td>
		<td valign="middle" nowrap="nowrap" class="list" align="right">
			<?= get_service_control_links($service); ?>
			<a href="services_dnsmasq.php?instance=<?=$i;?>"><img style="vertical-align:middle" src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" alt="" width="17" height="17" border="0"/></a>
			<a href="services_dnsmasq_instances.php?act=del&amp;instance=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this domain override?");?>')"><img style="vertical-align:middle" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" alt="" width="17" height="17" border="0"/></a>
		</td>
	</tr>
	<?php $i++; endforeach;
		if ($i == 0) echo "<tr><td></td></tr>"; ?>
	</tbody>
</table>
</div>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
