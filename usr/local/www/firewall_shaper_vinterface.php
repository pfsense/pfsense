<?php
/* $Id$ */
/*
	firewall_shaper_vinterface.php
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
##|*IDENT=page-firewall-trafficshaper-limiter
##|*NAME=Firewall: Traffic Shaper: Limiter page
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Limiter' page.
##|*MATCH=firewall_shaper_vinterface.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if($_GET['reset'] <> "") {
	mwexec("/usr/bin/killall -9 pfctl");
	exit;
}

$pgtitle = array(gettext("Firewall"),gettext("Traffic Shaper"), gettext("Limiter"));
$shortcut_section = "trafficshaper-limiters";

read_dummynet_config();
/* 
 * The whole logic in these code maybe can be specified.
 * If you find a better way contact me :).
 */

if ($_GET) {
	if ($_GET['queue'])
        	$qname = htmlspecialchars(trim($_GET['queue']));
        if ($_GET['pipe'])
                $pipe = htmlspecialchars(trim($_GET['pipe']));
        if ($_GET['action'])
                $action = htmlspecialchars($_GET['action']);
}
if ($_POST) {
	if ($_POST['name'])
        	$qname = htmlspecialchars(trim($_POST['name']));
	else if ($_POST['newname'])
        	$qname = htmlspecialchars(trim($_POST['newname']));
        if ($_POST['pipe'])
        	$pipe = htmlspecialchars(trim($_POST['pipe']));
	else
		$pipe = htmlspecialchars(trim($qname));
	if ($_POST['parentqueue'])
		$parentqueue = htmlspecialchars(trim($_POST['parentqueue']));
}

if ($pipe) {
	$dnpipe = $dummynet_pipe_list[$pipe];
	if ($dnpipe) {
		$queue =& $dnpipe->find_queue($pipe, $qname);
	} else $addnewpipe = true;
}

$dontshow = false;
$newqueue = false;
$output_form = "";

if ($_GET) {
	switch ($action) {
	case "delete":
		if ($queue) {
			if (is_array($config['filter']['rule'])) {
				foreach ($config['filter']['rule'] as $rule) {
					if ($rule['dnpipe'] == $queue->GetQname() || $rule['pdnpipe'] == $queue->GetQname())
						$input_errors[] = gettext("This pipe/queue is referenced in filter rules, please remove references from there before deleting.");
				}
			}
			if (!$input_errors) {
				$queue->delete_queue();
				if (write_config())
					mark_subsystem_dirty('shaper');
				header("Location: firewall_shaper_vinterface.php");
				exit;
			}
			$output_form .= $queue->build_form();
		} else {
			$input_errors[] = sprintf(gettext("No queue with name %s was found!"),$qname);
			$output_form .= $dn_default_shaper_msg;
			$dontshow = true;
		}
		break;
	case "resetall":
		foreach ($dummynet_pipe_list as $dn)
			$dn->delete_queue();
		unset($dummynet_pipe_list);
		$dummynet_pipe_list = array();
		unset($config['dnshaper']['queue']);
		unset($queue);
		unset($pipe);
		$can_add = false;
		$can_enable = false;
		$dontshow = true;
		foreach ($config['filter']['rule'] as $key => $rule) {
			if (isset($rule['dnpipe']))
				unset($config['filter']['rule'][$key]['dnpipe']);
			if (isset($rule['pdnpipe']))
				unset($config['filter']['rule'][$key]['pdnpipe']);
		}
		if (write_config()) {
			$retval = 0;
			$retval = filter_configure();
			$savemsg = get_std_save_message($retval);

			if (stristr($retval, "error") <> true)
				$savemsg = get_std_save_message($retval);
			else
				$savemsg = $retval;
		} else
			$savemsg = gettext("Unable to write config.xml (Access Denied?)");
		$output_form = $dn_default_shaper_message;

		break;
	case "add":
		if ($dnpipe) {
			$q = new dnqueue_class();
			$q->SetPipe($pipe);
			$output_form .= "<input type=\"hidden\" name=\"parentqueue\" id=\"parentqueue\"";
			$output_form .= " value=\"".$pipe."\" />";
		} else if ($addnewpipe) {
			$q = new dnpipe_class();
			$q->SetQname($pipe);
		} else 
			$input_errors[] = gettext("Could not create new queue/discipline!");

		if ($q) {
			$output_form .= $q->build_form();
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
			$queue->wconfig();
			if (write_config())
				mark_subsystem_dirty('shaper');
		} else
			$input_errors[] = gettext("Queue not found!");
		break;
	case "disable":
		if ($queue) {
			$queue->SetEnabled("");
			$output_form .= $queue->build_form();
			$queue->wconfig();
			if (write_config())
				mark_subsystem_dirty('shaper');
		} else
			$input_errors[] = gettext("Queue not found!");
		break;
	default:
		$output_form .= $dn_default_shaper_msg;
		$dontshow = true;
		break;
	}
} else if ($_POST) {
	unset($input_errors);

	if ($addnewpipe) {
		if (!empty($dummynet_pipe_list[$qname]))
			$input_errors[] = gettext("You cannot name a child queue with the same name as a parent limiter");
		else {
			$dnpipe =& new dnpipe_class();
			
			$dnpipe->ReadConfig($_POST);
			$dnpipe->validate_input($_POST, $input_errors);
			if (!$input_errors) {
				$number = dnpipe_find_nextnumber();
				$dnpipe->SetNumber($number);
				unset($tmppath);
				$tmppath[] = $dnpipe->GetQname();
				$dnpipe->SetLink($tmppath);	
				$dnpipe->wconfig();
				if (write_config())
					mark_subsystem_dirty('shaper');
				$can_enable = true;
				$can_add = true;
			}

			read_dummynet_config();
			$output_form .= $dnpipe->build_form();
			$newjavascript = $dnpipe->build_javascript();
		}
	} else if ($parentqueue) { /* Add a new queue */
		if (!empty($dummynet_pipe_list[$qname]))
			$input_errors[] = gettext("You cannot name a child queue with the same name as a parent limiter");
		else if ($dnpipe) {
			$tmppath =& $dnpipe->GetLink();
			array_push($tmppath, $qname);
			$tmp =& $dnpipe->add_queue($pipe, $_POST, $tmppath, $input_errors);
			if (!$input_errors) {
				array_pop($tmppath);
				$tmp->wconfig();
				if (write_config()) {
					$can_enable = true;
					$can_add = false;
					mark_subsystem_dirty('shaper');
				}
			}
			read_dummynet_config();
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

 		/* XXX: TODO Make dummynet pretty graphs */ 
		//	enable_rrd_graphing();

			clear_subsystem_dirty('shaper');
			
			if ($queue) {
				$output_form .= $queue->build_form();
				$dontshow = false;
			}
			else {
				$output_form .= $dn_default_shaper_message;
				$dontshow = true;
			}

	} else if ($queue) {
                $queue->validate_input($_POST, $input_errors);
                if (!$input_errors) {
			$queue->update_dn_data($_POST);
			$queue->wconfig();
			if (write_config())
				mark_subsystem_dirty('shaper');
			$dontshow = false;
                } 
		read_dummynet_config();
		$output_form .= $queue->build_form();
	} else  {
		$output_form .= $dn_default_shaper_msg;
		$dontshow = true;
	}
} else {
	$output_form .= $dn_default_shaper_msg;
	$dontshow = true;
}

if ($queue) {
                        if ($queue->GetEnabled())
                                $can_enable = true;
                        else
                                $can_enable = false;
                        if ($queue->CanHaveChildren()) { 
                       		$can_add = true;
                        } else
                                $can_add = false;
}

$tree = "<ul class=\"tree\" >";
if (is_array($dummynet_pipe_list)) {
        foreach ($dummynet_pipe_list as $tmpdn) {
                $tree .= $tmpdn->build_tree();
        }
}
$tree .= "</ul>";

if (!$dontshow || $newqueue) {

$output_form .= "<tr><td width=\"22%\" valign=\"top\" class=\"vncellreq\">";
$output_form .= gettext("Queue Actions");
$output_form .= "</td><td valign=\"top\" class=\"vncellreq\" width=\"78%\">";

$output_form .= "<input type=\"submit\" name=\"Submit\" value=\"" . gettext("Save") . "\" class=\"formbtn\" />";
if ($can_add || $addnewaltq) {
	$output_form .= "<a href=\"firewall_shaper_vinterface.php?pipe=";
	$output_form .= $pipe; 
	if ($queue) {
		$output_form .= "&amp;queue=" . $queue->GetQname();
	}
	$output_form .= "&amp;action=add\">";
	$output_form .= "<input type=\"button\" class=\"formbtn\" name=\"add\" value=\"" . gettext("Add new queue") ."\" />";
	$output_form .= "</a>";
}
$output_form .= "<a href=\"firewall_shaper_vinterface.php?pipe=";
$output_form .= $pipe;
if ($queue) {
	$output_form .= "&amp;queue=" . $queue->GetQname();
}
$output_form .= "&amp;action=delete\">";
$output_form .= "<input type=\"button\" class=\"formbtn\" name=\"delete\"";
if ($queue)
	$output_form .= " value=\"" . gettext("Delete this queue") ."\" />";
else
	$output_form .= " value=\"" . gettext("Delete Limiter") ."\" />";
$output_form .= "</a>";  
$output_form .= "</td></tr>";
$output_form .= "</table>";
} 
else 
	$output_form .= "</table>";

$output = "<table summary=\"output form\">";
$output .= $output_form;
$closehead = false;
include("head.inc");
?>
<link rel="stylesheet" type="text/css" media="all" href="./tree/tree.css" />
<script type="text/javascript" src="./tree/tree.js"></script>
<script type="text/javascript">
//<![CDATA[
function show_source_port_range() {
        document.getElementById("sprtable").style.display = '';
	document.getElementById("sprtable1").style.display = '';
	document.getElementById("sprtable2").style.display = '';
	document.getElementById("sprtable5").style.display = '';
	document.getElementById("sprtable4").style.display = 'none';
	document.getElementById("showadvancedboxspr").innerHTML='';
}
//]]>
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php
if ($queue)
	echo $queue->build_javascript();
else
	echo $newjavascript;

include("fbegin.inc"); 
?>
<div id="inputerrors"></div>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="firewall_shaper_vinterface.php" method="post" id="iform" name="iform">

<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('shaper')): ?><p>
<?php print_info_box_np(gettext("The traffic shaper configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));?><br /></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="traffic shaper limiter">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("By Interface"), false, "firewall_shaper.php");
	$tab_array[1] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
	$tab_array[2] = array(gettext("Limiter"), true, "firewall_shaper_vinterface.php");
	$tab_array[3] = array(gettext("Layer7"), false, "firewall_shaper_layer7.php");
	$tab_array[4] = array(gettext("Wizards"), false, "firewall_shaper_wizards.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
<?php if (count($dummynet_pipe_list) > 0): ?>
                        <tr class="tabcont"><td width="25%" align="left">
                        </td><td width="75%"> </td></tr>
<?php endif; ?>
			<tr>
			<td width="25%" valign="top" align="left">
			<?php
				echo $tree; 
			?>
			<br /><br />
			<a href="firewall_shaper_vinterface.php?pipe=new&amp;action=add">
			<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("Create new limiter");?>" width="17" height="17" border="0" alt="add" />&nbsp;<?=gettext("Create new limiter");?>
			</a><br />
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
<script type='text/javascript'>
//<![CDATA[
<?php
	$totalrows = 0;
	if (is_array($config['dnshaper']) && is_array($config['dnshaper']['queue'])) 
		$totalrows = count($config['dnshaper']['queue']);
	echo "totalrows = {$totalrows}";
?>
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
