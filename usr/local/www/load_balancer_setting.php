<?php
/* $Id$ */
/*
	load_balancer_setting.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2008 Bill Marquette <bill.marquette@gmail.com>.
	Copyright (C) 2012 Pierre POMES <pierre.pomes@gmail.com>.
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
##|*IDENT=page-services-loadbalancer-setting
##|*NAME=Services: Load Balancer: setting page
##|*DESCR=Allow access to the 'Settings: Load Balancer: Settings' page.
##|*MATCH=load_balancer_setting.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("util.inc");

if (!is_array($config['load_balancer']['setting'])) {
	$config['load_balancer']['setting'] = array();
}
$lbsetting = &$config['load_balancer']['setting'];

if ($_POST) {

        if ($_POST['apply']) {
                $retval = 0;
                $retval |= filter_configure();
                $retval |= relayd_configure();
  
                $savemsg = get_std_save_message($retval);
                clear_subsystem_dirty('loadbalancer');
        } else {
		unset($input_errors);
		$pconfig = $_POST;
	
		/* input validation */
		if ($_POST['timeout'] && !is_numeric($_POST['timeout'])) {
			$input_errors[] = gettext("Timeout must be a numeric value");
		}

                if ($_POST['interval'] && !is_numeric($_POST['interval'])) {
			$input_errors[] = gettext("Interval must be a numeric value");
                }

		if ($_POST['prefork']) { 
			if (!is_numeric($_POST['prefork'])) {
				$input_errors[] = gettext("Prefork must be a numeric value");
			} else {
				if (($_POST['prefork']<=0) || ($_POST['prefork']>32)) {
					$input_errors[] = gettext("Prefork value must be between 1 and 32");
				}
			}
		}

		/* update config if user entry is valid */
		if (!$input_errors) {
			$lbsetting['timeout'] = $_POST['timeout'];
			$lbsetting['interval'] = $_POST['interval'];
			$lbsetting['prefork'] = $_POST['prefork'];

        		write_config();
        		mark_subsystem_dirty('loadbalancer');
		}
	}
}

$pgtitle = array(gettext("Services"),gettext("Load Balancer"),gettext("Settings"));
$shortcut_section = "relayd";

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="load_balancer_setting.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('loadbalancer')): ?><br/>
<?php print_info_box_np(gettext("The load balancer configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="load balancer settings">
   <tr>
      <td class="tabnavtbl">
       <?php
        /* active tabs */
        $tab_array = array();
        $tab_array[] = array(gettext("Pools"), false, "load_balancer_pool.php");
        $tab_array[] = array(gettext("Virtual Servers"), false, "load_balancer_virtual_server.php");
        $tab_array[] = array(gettext("Monitors"), false, "load_balancer_monitor.php");
        $tab_array[] = array(gettext("Settings"), true, "load_balancer_setting.php");
        display_top_tabs($tab_array);
       ?>
      </td>
   </tr>
   <tr>
      <td id="mainarea">
         <div class="tabcont">
            <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
              <tr>
                 <td colspan="2" valign="top" class="listtopic"><?=gettext("Relayd global settings"); ?></td>
              </tr>
	      <tr>
	         <td width="22%" valign="top" class="vncell"><?=gettext("timeout") ; ?></td>
                 <td width="78%" class="vtable">
                   <input name="timeout" id="timeout" value="<?php if ($lbsetting['timeout'] <> "") echo $lbsetting['timeout']; ?>" class="formfld unknown" />
                   <br />
                   <?=gettext("Set the global timeout in milliseconds for checks. Leave blank to use the default value of 1000 ms "); ?>
                 </td>
              </tr>
	      <tr>
	         <td width="22%" valign="top" class="vncell"><?=gettext("interval") ; ?></td>
                 <td width="78%" class="vtable">
                   <input name="interval" id="interval" value="<?php if ($lbsetting['interval'] <> "") echo $lbsetting['interval']; ?>" class="formfld unknown" />
                   <br />
                   <?=gettext("Set the interval in seconds at which the member of a pool will be checked. Leave blank to use the default interval of 10 seconds"); ?>
                </td>
             </tr>
              <tr>
                 <td width="22%" valign="top" class="vncell"><?=gettext("prefork") ; ?></td>
                 <td width="78%" class="vtable">
                   <input name="prefork" id="prefork" value="<?php if ($lbsetting['prefork'] <> "") echo $lbsetting['prefork']; ?>" class="formfld unknown" />
                   <br />
                   <?=gettext("Number of processes used by relayd for dns protocol. Leave blank to use the default value of 5 processes"); ?>
                </td>
             </tr>
             <tr>
                 <td width="22%" valign="top">&nbsp;</td>
                 <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
                 </td>
            </tr>
           </table>
        </div>
      </td>
  </tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
