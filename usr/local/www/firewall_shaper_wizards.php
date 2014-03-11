<?php
/* $Id$ */
/*
	firewall_shaper_wizards.php
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
##|*IDENT=page-firewall-trafficshaper-wizard
##|*NAME=Firewall: Traffic Shaper: Wizard page
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Wizard' page.
##|*MATCH=firewall_shaper_wizards.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if($_GET['reset'] <> "") {
	mwexec("/usr/bin/killall -9 pfctl");
	exit;
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

$pgtitle = array(gettext("Firewall"),gettext("Traffic Shaper"),gettext("Wizards"));
$shortcut_section = "trafficshaper";

$wizards = array(gettext("Single Lan multi Wan") => "traffic_shaper_wizard.xml",
                gettext("Single Wan multi Lan") => "traffic_shaper_wizard_multi_lan.xml",
				gettext("Multiple Lan/Wan") => "traffic_shaper_wizard_multi_all.xml",
				gettext("Dedicated Links") => "traffic_shaper_wizard_dedicated.xml",
				);

$closehead = false;
include("head.inc");
?>
<link rel="stylesheet" type="text/css" media="all" href="./tree/tree.css" />
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php include("fbegin.inc");  ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="firewall_shaper_wizards.php" method="post" id="iform" name="iform">

<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('shaper')): ?><p>
<?php print_info_box_np(gettext("The traffic shaper configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));?><br /></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="traffic shaper wizard">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("By Interface"), false, "firewall_shaper.php");
	$tab_array[1] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
	$tab_array[2] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
	$tab_array[3] = array(gettext("Layer7"), false, "firewall_shaper_layer7.php");
	$tab_array[4] = array(gettext("Wizards"), true, "firewall_shaper_wizards.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table  width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
			  <tr>
		  		<td class="listhdrr" width="25%" align="center" ><?=gettext("Wizard function");?></td>
		  		<td class="listhdrr" width="75%" align="center"><?=gettext("Wizard Link");?></td>
			  </tr>
			  <?php	foreach ($wizards as $key => $wizard):  ?>
                        <tr class="tabcont"><td class="listlr" style="background-color: #e0e0e0" width="25%" align="center">
				<?php echo $key;?>
                        </td><td class="listr" style="background-color: #e0e0e0" width="75%" align="center">
				<?php echo "<a href=\"wizard.php?xml=" . $wizard ."\" >" .$wizard . "</a>"; ?>
				</td></tr>
				<?php endforeach; ?>
             </table>
		</div>
	  </td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
