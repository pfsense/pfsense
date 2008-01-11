<?php
/* $Id$ */
/*
	firewall_shaper.php
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

if (!is_array($config['shaper']['queue'])) {
	$config['shaper']['queue'] = array();
}

read_altq_config();
	
$tree = "<ul class=\"tree\" >";
if (is_array($altq_list_queues)) {
	foreach ($altq_list_queues as $altq) {
        	$tree .= $altq->build_tree();
        }
$tree .=  get_interface_list_to_show();
}
$tree .= "</ul>";

/* 
 * The whole logic in these code maybe can be specified.
 * If you find a better way contact me :).
 */

if ($_GET) {
	if ($_GET['queue'])
        	$qname = trim($_GET['queue']);
        if ($_GET['interface'])
                $interface = trim($_GET['interface']);
        if ($_GET['action'])
                $action = $_GET['action'];
}
if ($_POST) {
	if ($_POST['name'])
        	$qname = trim($_POST['name']);
        if ($_POST['interface'])
                $interface = trim($_POST['interface']);
	if ($_POST['parentqueue'])
		$parentqueue = trim($_POST['parentqueue']);
}

if ($interface) {
	$altq = $altq_list_queues[$interface];
	if ($altq) {
		$queue =& $altq->find_queue($interface, $qname);
		if ($queue) {
			if ($queue->GetEnabled())
				$can_enable = true;
			else
				$can_enable = false;
			if ($queue->CanHaveChilds() && $can_enable)
				$can_add = true;
			else
				$can_add = false;
		}
	} else $addnewaltq = true;
}

$output = "<div id=\"shaperarea\" style=\"position:relative\">";
if ($queue) {
$output .= "<tr><td valign=\"top\" class=\"vncellreq\"><br>";
$output .= "Enable/Disable";
$output .= "</td><td class=\"vncellreq\">";
$output .= " <input type=\"checkbox\" id=\"enabled\" name=\"enabled\"";
if ($can_enable)
        $output .=  " CHECKED";
$output .= " ><span class=\"vexpl\"> Enable/Disable queue and its childs</span>";
$output .= "</td></tr>";
}
$dontshow = false;
$newqueue = false;

if ($_GET) {
	switch ($action) {
	case "delete":
			if ($queue) {
				$queue->delete_queue();
				write_config();
				touch($d_shaperconfdirty_path);
			}
			header("Location: firewall_shaper.php");
			exit;
		break;
	case "add":
			/* XXX: Find better way because we shouldn't know about this */
		if ($altq) {
	                switch ($altq->GetScheduler()) {
         	        case "PRIQ":
                	        $q = new priq_queue();
                        	break;
                        case "HFSC":
                         	$q = new hfsc_queue();
                        	break;
                        case "CBQ":
                                $q = new cbq_queue();
                        	break;
                        default:
                                /* XXX: Happens when sched==NONE?! */
				$q = new altq_root_queue();
                        	break;
        		}
		} else if ($addnewaltq) {
			$q = new altq_root_queue();
		} else 
			$input_errors[] = "Could not create new queue/discipline!";

			if ($q) {
				$q->SetInterface($interface);
				$output .= $q->build_form();
				$output .= "<input type=\"hidden\" name=\"parentqueue\" id=\"parentqueue\"";
				$output .= " value=\"".$qname."\">";
                unset($q);
				$newqueue = true;
			}
		break;
		case "show":
			if ($queue)  
                        $output .= $queue->build_form();
			else
					$input_errors[] = "Queue not found!";
		break;
		case "enable":
			if ($queue) {
					$queue->SetEnabled("on");
					$output .= $queue->build_form();
					write_config();
					touch($d_shaperconfdirty_path);
			} else
					$input_errors[] = "Queue not found!";
		break;
		case "disable":
			if ($queue) {
					$queue->SetEnabled("");
					$output .= $queue->build_form();
					write_config();
					touch($d_shaperconfdirty_path);
			} else
					$input_errors[] = "Queue not found!";
		break;
		default:
			$output .= "<p class=\"pgtitle\">" . $default_shaper_msg."</p>";
			$dontshow = true;
			break;
	}
} else if ($_POST) {
	unset($input_errors);

	if ($addnewaltq) {
		$altq =& new altq_root_queue();
		$altq->SetInterface($interface);
		$altq->ReadConfig($_POST);
		unset($tmppath);
		$tmppath[] = $altq->GetInterface();
		$altq->SetLink(&$tmppath);	
		$altq->wconfig();
		$output .= $altq->build_form();
		write_config();
  		touch($d_shaperconfdirty_path);
	} else if ($parentqueue) { /* Add a new queue */
		$qtmp =& $altq->find_queue($interface, $parentqueue);
		if ($qtmp) {
			$tmppath =& $qtmp->GetLink();
			array_push($tmppath, $qname);
			$tmp =& $qtmp->add_queue($interface, $_POST, &$tmppath);
			array_pop($tmppath);
			$output .= $tmp->build_form();
			$tmp->wconfig();
			$can_enable = true;
			if ($tmp->CanHaveChilds() && $can_enable)
				$can_add = true;
			else
				$can_add = false;
			write_config();
       		touch($d_shaperconfdirty_path);
		} else
			$input_errors[] = "Could not add new queue.";
	} else if ($_POST['apply']) {
                        write_config();

                        $retval = 0;
                        $savemsg = get_std_save_message($retval);
                        /* Setup pf rules since the user may have changed the optimizat
ion value */
                        config_lock();
                        $retval = filter_configure();
                        config_unlock();
                        if (stristr($retval, "error") <> true)
                                $savemsg = get_std_save_message($retval);
                        else
                                $savemsg = $retval;

			enable_rrd_graphing();

            unlink($d_shaperconfdirty_path);
			if ($queue)
				$output .= $queue->build_form();
			else
				$output .= $default_shaper_message;

			$dontshow = true;
//                       header("Location: firewall_shaper.php");
 //                      exit;
	} else if ($queue) {
                $queue->validate_input($_POST, &$input_errors);
                if (!$input_errors) {
                                $queue->update_altq_queue_data($_POST);
                                $queue->wconfig();
                        	$output .= $queue->build_form();
							write_config();
				       		touch($d_shaperconfdirty_path);
                } else 
					$input_errors[] = "Could not complete the request.";
	} else 
		$input_errors[] = "Ummmm nothing to do?!";
} else {
	$output .= "<p class=\"pgtitle\">" . $default_shaper_msg."</p>";
	$dontshow = true;

}
	

	

if (!$dontshow || $newqueue) {

$output .= "<tr><td width=\"22%\" valign=\"top\" class=\"vncellreq\">";
$output .= "Queue Actions";
$output .= "</td><td valign=\"top\" class=\"vncellreq\" width=\"78%\">";

$output .= "<input type=\"image\" src=\"";
$output .= "./themes/".$g['theme']."/images/icons/icon_up.gif\"";
$output .= " width=\"17\" height=\"17\" border=\"0\" title=\"Submit\" >";

if ($can_add || $addnewaltq) {
	$output .= "<a href=\"firewall_shaper.php?interface=";
	$output .= $altq->GetInterface() . "&queue=";
	$output .= $queue->GetQname() . "&action=add\">";
	$output .= "<img src=\"";
	$output .= "./themes/".$g['theme']."/images/icons/icon_plus.gif\"";
	$output .= " width=\"17\" height=\"17\" border=\"0\" title=\"Add queue\">";
	$output .= "</a>";
	$output .= "<a href=\"firewall_shaper.php?interface=";
	$output .= $altq->GetInterface() . "&queue=";
	$output .= $queue->GetQname() . "&action=delete\">";
	$output .= "<img src=\"";
	$output .= "./themes/".$g['theme']."/images/icons/icon_minus.gif\"";
	$output .= " width=\"17\" height=\"17\" border=\"0\" title=\"Delete a queue\">";
	$output .= "</a>";  
}
$output .= "</td></tr>";
$output .= "</div>";
} 
else 
	$output .= "</div>";

$pgtitle = "Firewall: Shaper: By Interface View";

include("head.inc");
if ($queue) {
	echo "<script type=\"text/javascript\">";
	echo $queue->build_javascript();
	echo "</script>";
}
?>
<link rel="stylesheet" type="text/css" media="all" href="./tree/tree.css" />
<script type="text/javascript" src="./tree/tree.js"></script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<div id="inputerrors"></div>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="firewall_shaper.php" method="post" name="form" id="form">

<script type="text/javascript" language="javascript" src="row_toggle.js"></script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_shaperconfdirty_path)): ?><p>
<?php print_info_box_np("The traffic shaper configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Shaper", true, "firewall_shaper.php");
	//$tab_array[1] = array("Level 2", false, "");
	$tab_array[1] = array("EZ Shaper wizard", false, "wizard.php?xml=traffic_shaper_wizard.xml");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
			<td width="25%" valign="top" algin="left">
		<?	$tab_ar = array();
        $tab_ar[0] = array("Interfaces", true, "firewall_shaper.php");
        $tab_ar[1] = array("Queues", false, "firewall_shaper_queues.php");
	display_top_tabs($tab_ar);
        // customize later display_top_bar($tab_ar, "#A0000F", "#578100"); ?>
			<?php
				echo $tree; 
			?>
			</td>
			<td width="75%" valign="top" align="center">
			<table>
			<?
				echo $output;
			?>	
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
