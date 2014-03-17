<?php
/* $Id$ */
/*
	firewall_shaper.php
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
##|*IDENT=page-firewall-trafficshaper
##|*NAME=Firewall: Traffic Shaper page
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper' page.
##|*MATCH=firewall_shaper.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rrd.inc");

if($_GET['reset'] <> "") {
	/* XXX: Huh, why are we killing php? */
	mwexec("killall -9 pfctl php");
	exit;
}

$pgtitle = array(gettext("Firewall"),gettext("Traffic Shaper"));
$shortcut_section = "trafficshaper";

$shaperIFlist = get_configured_interface_with_descr();
read_altq_config();
/* 
 * The whole logic in these code maybe can be specified.
 * If you find a better way contact me :).
 */

if ($_GET) {
	if ($_GET['queue'])
        	$qname = trim($_GET['queue']);
        if ($_GET['interface'])
                $interface = htmlspecialchars(trim($_GET['interface']));
        if ($_GET['action'])
                $action = htmlspecialchars($_GET['action']);
}
if ($_POST) {
	if ($_POST['name'])
        	$qname = htmlspecialchars(trim($_POST['name']));
        if ($_POST['interface'])
                $interface = htmlspecialchars(trim($_POST['interface']));
	if ($_POST['parentqueue'])
		$parentqueue = htmlspecialchars(trim($_POST['parentqueue']));
}

if ($interface) {
	$altq = $altq_list_queues[$interface];
	if ($altq) {
		$queue =& $altq->find_queue($interface, $qname);
	} else $addnewaltq = true;
}

$dontshow = false;
$newqueue = false;
$output_form = "";

if ($_GET) {
	switch ($action) {
	case "delete":
			if ($queue) {
				$queue->delete_queue();
				if (write_config())
					mark_subsystem_dirty('shaper');
			}
			header("Location: firewall_shaper.php");
			exit;
		break;
	case "resetall":
			foreach ($altq_list_queues as $altq)
				$altq->delete_all();
			unset($altq_list_queues);
			$altq_list_queues = array();
			$tree = "<ul class=\"tree\" >";
			$tree .= get_interface_list_to_show();
			$tree .= "</ul>";
			unset($config['shaper']['queue']);
			unset($queue);
			unset($altq);
			$can_add = false;
			$can_enable = false;
			$dontshow = true;
			foreach ($config['filter']['rule'] as $key => $rule) {
				if (isset($rule['wizard']) && $rule['wizard'] == "yes")
					unset($config['filter']['rule'][$key]);
			}
			if (write_config()) {
				$retval = 0;
				$retval |= filter_configure();
				$savemsg = get_std_save_message($retval);

				if (stristr($retval, "error") <> true)
					$savemsg = get_std_save_message($retval);
				else
					$savemsg = $retval;
			} else {
				$savemsg = gettext("Unable to write config.xml (Access Denied?)");
			}
			$output_form = $default_shaper_message;

		break;
	case "add":
			/* XXX: Find better way because we shouldn't know about this */
		if ($altq) {
	                switch ($altq->GetScheduler()) {
         	        case "PRIQ":
                	        $q = new priq_queue();
                        	break;
			case "FAIRQ":
				$q = new fairq_queue();
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
			$input_errors[] = gettext("Could not create new queue/discipline!");

			if ($q) {
				$q->SetInterface($interface);
				$output_form .= $q->build_form();
				$output_form .= "<input type=\"hidden\" name=\"parentqueue\" id=\"parentqueue\"";
				$output_form .= " value=\"".$qname."\" />";
				$newjavascript = $q->build_javascript();
                unset($q);
				$newqueue = true;
			}
		break;
		case "show":
			if ($queue)  
                        $output_form .= $queue->build_form();
			else
					$input_errors[] = gettext("Queue not found!");
		break;
		case "enable":
			if ($queue) {
					$queue->SetEnabled("on");
					$output_form .= $queue->build_form();
					if (write_config())
						mark_subsystem_dirty('shaper');
			} else
					$input_errors[] = gettext("Queue not found!");
		break;
		case "disable":
			if ($queue) {
					$queue->SetEnabled("");
					$output_form .= $queue->build_form();
					if (write_config())
						mark_subsystem_dirty('shaper');
			} else
					$input_errors[] = gettext("Queue not found!");
		break;
		default:
			$output_form .= $default_shaper_msg;
			$dontshow = true;
			break;
	}
} else if ($_POST) {
	unset($input_errors);

	if ($addnewaltq) {
		$altq =& new altq_root_queue();
		$altq->SetInterface($interface);
		
		switch ($altq->GetBwscale()) {
				case "Mb":
					$factor = 1000 * 1000;
					brak;
				case "Kb":
					$factor = 1000;
					break;
				case "b":
					$factor = 1;
					break;
				case "Gb":
					$factor = 1000 * 1000 * 1000;
					break;
				case "%": /* We don't use it for root_XXX queues. */
				default: /* XXX assume Kb by default. */
					$factor = 1000;
					break;
			} 
		$altq->SetAvailableBandwidth($altq->GetBandwidth() * $factor);
		$altq->ReadConfig($_POST);
		$altq->validate_input($_POST, $input_errors);
		if (!$input_errors) {
			unset($tmppath);
			$tmppath[] = $altq->GetInterface();
			$altq->SetLink($tmppath);	
			$altq->wconfig();
			if (write_config())
				mark_subsystem_dirty('shaper');
			$can_enable = true;
                        $can_add = true;
		}
		read_altq_config();
		$output_form .= $altq->build_form();

	} else if ($parentqueue) { /* Add a new queue */
		$qtmp =& $altq->find_queue($interface, $parentqueue);
		if ($qtmp) {
			$tmppath =& $qtmp->GetLink();
			array_push($tmppath, $qname);
			$tmp =& $qtmp->add_queue($interface, $_POST, $tmppath, $input_errors);
			if (!$input_errors) {
				array_pop($tmppath);
				$tmp->wconfig();
				$can_enable = true;
				if ($tmp->CanHaveChildren() && $can_enable) {
					if ($tmp->GetDefault() <> "")
                             			$can_add = false;
                        		else
                             			$can_add = true;
				} else
					$can_add = false;
				if (write_config())
					mark_subsystem_dirty('shaper');
				$can_enable = true;
				if ($altq->GetScheduler() != "PRIQ") /* XXX */
					if ($tmp->GetDefault() <> "")
                                                $can_add = false;
                                        else
                                                $can_add = true;
			}
			read_altq_config();
			$output_form .= $tmp->build_form();			
		} else
			$input_errors[] = gettext("Could not add new queue.");
	} else if ($_POST['apply']) {
			write_config();

			$retval = 0;
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
			
			if ($queue) {
				$output_form .= $queue->build_form();
				$dontshow = false;
			}
			else {
				$output_form .= $default_shaper_message;
				$dontshow = true;
			}

	} else if ($queue) {
                $queue->validate_input($_POST, $input_errors);
                if (!$input_errors) {
                            $queue->update_altq_queue_data($_POST);
                            $queue->wconfig();
				if (write_config())
					mark_subsystem_dirty('shaper');
				$dontshow = false;
                } 
		read_altq_config();
		$output_form .= $queue->build_form();
	} else  {
		$output_form .= $default_shaper_msg;
		$dontshow = true;
	}
	mwexec("killall qstats");
} else {
	$output_form .= $default_shaper_msg;
	$dontshow = true;
}

if ($queue) {
                        if ($queue->GetEnabled())
                                $can_enable = true;
                        else
                                $can_enable = false;
                        if ($queue->CanHaveChildren() && $can_enable) { 
                                if ($altq->GetQname() <> $queue->GetQname() && $queue->GetDefault() <> "")
                                        $can_add = false;
                                else
                                        $can_add = true;
                        } else
                                $can_add = false;
}

$tree = "<ul class=\"tree\" >";
if (is_array($altq_list_queues)) {
        foreach ($altq_list_queues as $tmpaltq) {
                $tree .= $tmpaltq->build_tree();
        }
$tree .=  get_interface_list_to_show();
}
$tree .= "</ul>";

if (!$dontshow || $newqueue) {

$output_form .= "<tr><td width=\"22%\" valign=\"middle\" class=\"vncellreq\">";
$output_form .= "<br />" . gettext("Queue Actions") . "<br />";
$output_form .= "</td><td valign=\"middle\" class=\"vncellreq\" width=\"78%\"><br />";

$output_form .= "<input type=\"submit\" name=\"Submit\" value=\"" . gettext("Save") . "\" class=\"formbtn\" />";
if ($can_add || $addnewaltq) {
	$output_form .= "<a href=\"firewall_shaper.php?interface=";
	$output_form .= $interface; 
	if ($queue) {
		$output_form .= "&amp;queue=" . $queue->GetQname();
	}
	$output_form .= "&amp;action=add\">";
	$output_form .= "<input type=\"button\" class=\"formbtn\" name=\"add\" value=\"" . gettext("Add new queue") . "\" />";
	$output_form .= "</a>";
}
$output_form .= "<a href=\"firewall_shaper.php?interface=";
$output_form .= $interface . "&amp;queue=";
if ($queue) {
	$output_form .= "&amp;queue=" . $queue->GetQname();
}
$output_form .= "&amp;action=delete\">";
$output_form .= "<input type=\"button\" class=\"formbtn\" name=\"delete\"";
if ($queue)
	$output_form .= " value=\"" . gettext("Delete this queue") . "\" />";
else
	$output_form .= " value=\"" . gettext("Disable shaper on interface") . "\" />";
$output_form .= "</a>";
$output_form .= "<br /></td></tr>";
$output_form .= "</table>";
}
else 
	$output_form .= "</table>";

$output = "<table  summary=\"output form\">";
$output .= $output_form;

//$pgtitle = "Firewall: Shaper: By Interface View";
$closehead = false;
include("head.inc");
?>
<link rel="stylesheet" type="text/css" media="all" href="./tree/tree.css" />
<script type="text/javascript" src="./tree/tree.js"></script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >
<?php
if ($queue)
        echo $queue->build_javascript();
echo $newjavascript;

include("fbegin.inc"); 
?>
<div id="inputerrors"></div>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="firewall_shaper.php" method="post" id="iform" name="iform">

<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('shaper')): ?><p>
<?php print_info_box_np(gettext("The traffic shaper configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));?><br /></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="traffic shaper">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("By Interface"), true, "firewall_shaper.php");
	$tab_array[1] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
	$tab_array[2] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
	$tab_array[3] = array(gettext("Layer7"), false, "firewall_shaper_layer7.php");
	$tab_array[4] = array(gettext("Wizards"), false, "firewall_shaper_wizards.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
<?php if (count($altq_list_queues) > 0): ?>
                        <tr class="tabcont"><td width="25%" align="left">
                                <a href="firewall_shaper.php?action=resetall" >
                                        <input type="button" value="<?=gettext("Remove Shaper")?>" class="formbtn" />
                                </a>
                        </td><td width="75%"> </td></tr>
<?php endif; ?>
			<tr>
			<td width="25%" valign="top" align="left">
			<?php
				echo $tree; 
			?>
			</td>
			<td width="75%" valign="top" align="center">
			<div id="shaperarea" style="position:relative">
			<?php
				echo $output;
			?>	
			</div>

		      </td></tr>
                    </table>
		</div>
	  </td>
	</tr>
</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
