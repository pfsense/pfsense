<?php
/* $Id$ */
/*
	firewall_shaper_queues.php
	Copyright (C) 2004, 2005 Scott Ullrich
	Copyright (C) 2008 Ermal Luçi
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
	pfSense_BUILDER_BINARIES:	/usr/bin/killall
	pfSense_MODULE:	shaper
*/

##|+PRIV
##|*IDENT=page-firewall-trafficshaper-queues
##|*NAME=Firewall: Traffic Shaper: Queues page
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Queues' page.
##|*MATCH=firewall_shaper_queues.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rrd.inc");

if($_GET['reset'] <> "") {
	mwexec("killall -9 pfctl");
	exit;
}

$shaperIFlist = get_configured_interface_with_descr();
read_altq_config();
$qlist =& get_unique_queue_list();

if (!is_array($qlist)) 
	$qlist = array();

$tree = "<ul class=\"tree\" >";
foreach ($qlist as $queue => $qkey) {
	$tree .= "<li><a href=\"firewall_shaper_queues.php?queue={$queue}&amp;action=show\" >";
	if (isset($shaperIFlist[$queue]))
		$tree .= $shaperIFlist[$queue] . "</a></li>";
	else	
		$tree .= $queue . "</a></li>";
}
$tree .= "</ul>";

if ($_GET) {
	if ($_GET['queue'])
        	$qname = htmlspecialchars(trim($_GET['queue']));
        if ($_GET['interface'])
                $interface = htmlspecialchars(trim($_GET['interface']));
        if ($_GET['action'])
                $action = htmlspecialchars($_GET['action']);

	switch ($action) {
	case "delete":
			$altq =& $altq_list_queues[$interface];
			$qtmp =& $altq->find_queue("", $qname);
			if ($qtmp) {
				$qtmp->delete_queue(); 
				if (write_config())
					mark_subsystem_dirty('shaper');
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
                                        $copycfg = array();
                                        $qtmp->copy_queue($interface, $copycfg);
                                        $aq =& $altq_list_queues[$interface];
					if ($qname == $qtmp->GetInterface()) {
                                                $config['shaper']['queue'][] = $copycfg;
                                        } else if ($aq) {
                                                $tmp1 =& $qtmp->find_parentqueue($interface, $qname);
                                                if ($tmp1) 
                                                        $tmp =& $aq->find_queue($interface, $tmp1->GetQname());

                                                if ($tmp)
                                                        $link =& get_reference_to_me_in_config($tmp->GetLink());
                                                else
                                                        $link =& get_reference_to_me_in_config($aq->GetLink());
                                                $link['queue'][] = $copycfg;
                                        } else {
                                                $newroot = array();
                                                $newroot['name'] = $interface;
                                                $newroot['interface'] = $interface;
                                                $newroot['scheduler'] = $altq->GetScheduler();
                                                $newroot['queue'] = array();
                                                $newroot['queue'][] = $copycfg;
                                                $config['shaper']['queue'][] = $newroot;
                                        }
					if (write_config())
						mark_subsystem_dirty('shaper');
                                        break;
                                }
                        }

			header("Location: firewall_shaper_queues.php?queue=".$qname."&action=show");
			exit;
		break;
	case "show":
                        foreach ($config['interfaces'] as $if => $ifdesc) {
                                $altq = $altq_list_queues[$if];
                                if ($altq) {
                                        $qtmp =& $altq->find_queue("", $qname);
                                        if ($qtmp)
                                                $output .= $qtmp->build_shortform();
                                        else
                                                $output .= build_iface_without_this_queue($if, $qname);
                                } else {
                                        if (!is_altq_capable($ifdesc['if']))
                                                continue;
                                        if (!isset($ifdesc['enable']) && $if != "lan" && $if != "wan")
                                                continue;
                                        $output .= build_iface_without_this_queue($if, $qname);
                                }
                        }
		break;
	}
}

if ($_POST['apply']) {
          write_config();

	$retval = 0;
        /* Setup pf rules since the user may have changed the optimization value */
	$retval = filter_configure();
	$savemsg = get_std_save_message($retval);
                        if (stristr($retval, "error") <> true)
                                $savemsg = get_std_save_message($retval);
                        else
                                $savemsg = $retval;

 		/* reset rrd queues */
                system("rm -f /var/db/rrd/*queuedrops.rrd");
                system("rm -f /var/db/rrd/*queues.rrd");
			enable_rrd_graphing();

		clear_subsystem_dirty('shaper');
}

$pgtitle = gettext("Firewall: Shaper: By Queues View");
$shortcut_section = "trafficshaper";
$closehead = false;
include("head.inc");
?>
<link rel="stylesheet" type="text/css" media="all" href="./tree/tree.css" />
<script type="text/javascript" src="./tree/tree.js"></script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<div id="inputerrors"></div>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="firewall_shaper_queues.php" method="post" name="iform" id="iform">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('shaper')): ?><p>
<?php print_info_box_np(gettext("The traffic shaper configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br /></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="traffic shaper queues">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("By Interface"), false, "firewall_shaper.php");
	$tab_array[1] = array(gettext("By Queue"), true, "firewall_shaper_queues.php");
	$tab_array[2] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
	$tab_array[3] = array(gettext("Layer7"), false, "firewall_shaper_layer7.php");
	$tab_array[4] = array(gettext("Wizards"), false, "firewall_shaper_wizards.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr> 
    <td valign="top">
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
		<tr>
		<td width="30%" valign="top" align="left">
                <?php      echo $tree; ?>
		</td>
		<td width="70%" valign="top" align="center">
			<?php 
				if ($qname)
        				echo "<p class=\"pgtitle\">" . $qname . "</p><br />";
				echo "<table align=\"center\" class=\"tabcont\" width=\"80%\" border=\"0\" cellpadding=\"4\" cellspacing=\"0\" summary=\"output form\">";
				echo $output;
				echo "<tr><td>&nbsp;</td></tr>";
				echo "</table>";
			?>	
			</td></tr>
			</table><!-- table:main area -->

		</div><!-- div:main area -->
	  </td>
	</tr>
</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
