<?php
/* $Id$ */
/*
	firewall_shaper_queues.php
	Copyright (C) 2004, 2005 Scott Ullrich
	Copyright (C) 2008 Ermal Luçi
	All rights reserved.

	Originally part of m0n0wall (http://m0n0.ch/wall)
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

require("guiconfig.inc");

if($_GET['reset'] <> "") {
	mwexec("killall -9 pfctl php");
	exit;
}

if (!is_array($config['shaper']['queue'])) {
	$config['shaper']['queue'] = array();
}

/* on HEAD it has to use vc_* api on variable_cache.inc */
if (!is_array($GLOBALS['allqueue_list'])) {
        $GLOBALS['allqueue_list'] = array();
}

/* XXX: NOTE: When dummynet is implemented these will be moved from here */
read_altq_config();

$tree = "<ul class=\"tree\" >";
if (is_array($altq_list_queues)) {
	foreach ($GLOBALS['allqueue_list'] as $queue) {
        	$tree .= "<li><a href=\"firewall_shaper_queues.php?queue={$queue}&action=show\" >{$queue}</a></li>";
        }
}
$tree .= "</ul>";

if ($_GET) {
	if ($_GET['queue'])
        	$qname = trim($_GET['queue']);
        if ($_GET['interface'])
                $interface = trim($_GET['interface']);
        if ($_GET['action'])
                $action = $_GET['action'];

	switch ($action) {
	case "delete":
			$altq =& $altq_list_queues[$interface];
			$qtmp =& $altq->find_queue("", $qname);
			if ($qtmp) {
				$qtmp->delete_queue(); 
				write_config();
				touch($d_shaperconfdirty_path);
			}
			header("Location: firewall_shaper_queues.php");
			exit;
		break;
	case "add":
			/* 
			 * XXX: WARNING: This returns the first it finds.
			 * Maybe the user expects something else?!
			 */
			foreach ($altq_list_queues as $altq) {
				$qtmp =& $altq->find_queue("", $qname);
				if ($qtmp) {
					$aq =& $altq_list_queues[$interface];
					if ($aq) {
						//$link =& get_reference_to_me_in_config(&$link);
						$aq->copy_queue($interface, &$qtmp);
						write_config();
                        			touch($d_shaperconfdirty_path);
						break;
					}
				}
			}
			header("Location: firewall_shaper_queues.php?queue=".$qname."&action=show");
			exit;
		break;
	case "show":
			$iflist = get_interface_list();
			foreach ($iflist as $if) {
				$altq = $altq_list_queues[$if['friendly']];
				if ($altq) {
					$qtmp =& $altq->find_queue("", $qname);
					if ($qtmp) 
						$output .= $qtmp->build_shortform();
					else
						$output .= build_iface_without_this_queue($if['friendly'], $qname);
				} else 
					$output .= build_iface_without_this_queue($if['friendly'], $qname);
			}	
		break;
	}
}

if ($_POST['apply']) {
          write_config();

          $retval = 0;
         $savemsg = get_std_save_message($retval);
        /* Setup pf rules since the user may have changed the optimization value */

                        config_lock();
                        $retval = filter_configure();
                        config_unlock();
                        if (stristr($retval, "error") <> true)
                                $savemsg = get_std_save_message($retval);
                        else
                                $savemsg = $retval;

			enable_rrd_graphing();

            unlink($d_shaperconfdirty_path);
}

$pgtitle = "Firewall: Shaper: By Queues View";

include("head.inc");
?>
<link rel="stylesheet" type="text/css" media="all" href="./tree/tree.css" />
<script type="text/javascript" src="./tree/tree.js"></script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<div id="inputerrors"></div>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="firewall_shaper_queues.php" method="post" name="iform" id="iform">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_shaperconfdirty_path)): ?><p>
<?php print_info_box_np("The traffic shaper configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Shaper", false, "firewall_shaper.php");
	//$tab_array[1] = array("Level 2", true, "");
	$tab_array[1] = array("EZ Shaper wizard", false, "wizard.php?xml=traffic_shaper_wizard.xml");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
			<td width="30%" valign="top" algin="left">
		<?	$tab_ar = array();
        $tab_ar[0] = array("By Interface", false, "firewall_shaper.php");
        $tab_ar[1] = array("By Queues", true, "firewall_shaper_queues.php");
        display_top_tabs($tab_ar); 
                                echo $tree;
                        ?>
			</td></tr>
		</table>
			<td width="70%" valign="top" align="center">
			<table class=\"tabcont\" width=\"80%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
		<tr><td>
			<? 
				if ($qname)
        				echo "<pr class=\"pgtitle\">" . $qname . "</pr><br />";
				echo "<table align=\"top\" class=\"tabcont\" width=\"80%\" border=\"0\" cellpadding=\"4\" cellspacing=\"0\">";
				echo $output;
				echo "</table>";
			?>	
			</td></tr>
			</table>

		      </td></tr>
                    </table>
		</div>
	  </td>
	</tr>
</table>
            </form>
<?php include("fend.inc"); 
?>
</body>
</html>
