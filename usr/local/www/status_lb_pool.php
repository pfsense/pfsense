<?php
/* $Id$ */
/*
	status_lb_pool.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-status-loadbalancer-pool
##|*NAME=Status: Load Balancer: Pool page
##|*DESCR=Allow access to the 'Status: Load Balancer: Pool' page.
##|*MATCH=status_lb_pool.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("vslb.inc");

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
$a_pool = &$config['load_balancer']['lbpool'];

$lb_logfile = "{$g['varlog_path']}/relayd.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

$now = time();
$year = date("Y");

$pgtitle = array(gettext("Status"),gettext("Load Balancer"),gettext("Pool"));
$shortcut_section = "relayd";
include("head.inc");

$relay_hosts = get_lb_summary();

if ($_POST) {
	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$retval |= relayd_configure();
		$savemsg = get_std_save_message($retval);
		clear_subsystem_dirty('loadbalancer');
	} else {
		/* Keep a list of servers we find in POST variables */
		$newservers = array();
		foreach ($_POST as $name => $value) {
			/* Look through the POST vars to find the pool data */
			if (strpos($name, '|') !== false){
				list($poolname, $ip) = explode("|", $name);
				$ip = str_replace('_', '.', $ip);
				$newservers[$poolname][] = $ip;
			} elseif (is_ipaddr($value)) {
				$newservers[$name][] = $value;
			}
		}
		foreach ($a_pool as & $pool) {
			if (is_array($pool['servers']) && is_array($pool['serversdisabled'])) {
				$oldservers = array_merge($pool['servers'], $pool['serversdisabled']);
			} elseif (is_array($pool['servers'])) {
				$oldservers = $pool['servers'];
			} elseif (is_array($pool['serversdisabled'])) {
				$oldservers = $pool['serversdisabled'];
			} else {
				$oldservers = array();
			}
			if (is_array($newservers[$pool['name']])) {
				$pool['servers'] = $newservers[$pool['name']];
				$pool['serversdisabled'] = array_diff($oldservers, $newservers[$pool['name']]);
			}
		}
		mark_subsystem_dirty('loadbalancer');
		write_config("Updated load balancer pools via status screen.");
	}
}

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="status_lb_pool.php" method="post">
<?php if (is_subsystem_dirty('loadbalancer')): ?><p>
<?php print_info_box_np(sprintf(gettext("The load balancer configuration has been changed%sYou must apply the changes in order for them to take effect."), "<br />"));?><br />
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
	<?php
	/* active tabs */
	$tab_array = array();
	$tab_array[] = array(gettext("Pools"), true, "status_lb_pool.php");
	$tab_array[] = array(gettext("Virtual Servers"), false, "status_lb_vs.php");
	display_top_tabs($tab_array);
	?>
	</td></tr>
	<tr>
	<td>
	<div id="mainarea">
		<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont sortable" name="sortabletable" id="sortabletable">
		<tr>
		<td width="10%" class="listhdrr"><?=gettext("Name");?></td>
		<td width="10%" class="listhdrr"><?=gettext("Mode");?></td>
		<td width="20%" class="listhdrr"><?=gettext("Servers");?></td>
		<td width="10%" class="listhdrr"><?=gettext("Monitor");?></td>
		<td width="30%" class="listhdr"><?=gettext("Description");?></td>
		</tr>
		<?php foreach ($a_pool as & $pool): ?>
		<tr>
		<td class="listlr">
			<?=$pool['name'];?>
		</td>
		<td class="listr" align="center" >
		<?php
		switch($pool['mode']) {
			case "loadbalance":
				echo "Load balancing";
				break;
			case "failover":
				echo "Manual failover";
				break;
			default:
				echo "(default)";
		}
		?>
		</td>
		<td class="listr" align="center">
		<table border="0" cellpadding="2" cellspacing="0">
		<?php
		$pool_hosts=array();
		foreach ((array) $pool['servers'] as $server) {
			$svr['ip']['addr']=$server;
			$svr['ip']['state']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['state'];
			$svr['ip']['avail']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['avail'];
			$pool_hosts[]=$svr;
		}
		foreach ((array) $pool['serversdisabled'] as $server) {
			$svr['ip']['addr']="$server";
			$svr['ip']['state']='disabled';
			$svr['ip']['avail']='disabled';
			$pool_hosts[]=$svr;
		}
		asort($pool_hosts);

		foreach ((array) $pool_hosts as $server) {
			if($server['ip']['addr']!="") {
				switch ($server['ip']['state']) {
					case 'up':
						$bgcolor = "#90EE90";  // lightgreen
						$checked = "checked";
						break;
					case 'disabled':
						$bgcolor = "white";
						$checked = "";
						break;
					default:
						$bgcolor = "#F08080";  // lightcoral
						$checked = "checked";
				}
				echo "<tr>";
				switch ($pool['mode']) {
					case 'loadbalance':
						echo "<td><input type='checkbox' name='{$pool['name']}|".str_replace('.', '_', $server['ip']['addr'])."' {$checked}></td>\n";
						break;
					case 'failover':
						echo "<td><input type='radio' name='{$pool['name']}' value='{$server['ip']['addr']}' {$checked}></td>\n";
						break;
				}
				echo "<td bgcolor={$bgcolor}>&nbsp;{$server['ip']['addr']}:{$pool['port']}&nbsp;</td><td bgcolor={$bgcolor}>&nbsp;";
#				echo "<td bgcolor={$bgcolor}>&nbsp;{$server['ip']['addr']}:{$pool['port']} ";
				if($server['ip']['avail'])
				  echo " ({$server['ip']['avail']}) ";
				echo "&nbsp;</td></tr>";
			}
		}
		?>
		</table>
		</td>
		<td class="listr" >
			<?php echo $pool['monitor']; ?>
		</td>
		<td class="listbg" >
			<?=$pool['descr'];?>
		</td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<td colspan="5">
			<input name="Submit" type="submit" class="formbtn" value="<?= gettext("Save"); ?>">
			<input name="Reset"  type="reset"  class="formbtn" value="<?= gettext("Reset"); ?>">
			</td>
		</tr>
		</table>
	</div>
	</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
