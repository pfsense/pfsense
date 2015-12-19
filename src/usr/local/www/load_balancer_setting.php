<?php
/*
	load_balancer_setting.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2008 Bill Marquette <bill.marquette@gmail.com>.
 *	Copyright (c)  2012 Pierre POMES <pierre.pomes@gmail.com>.
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
##|*IDENT=page-services-loadbalancer-setting
##|*NAME=Services: Load Balancer: Settings
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

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Settings"));
$shortcut_section = "relayd";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('loadbalancer')) {
	print_info_box_np(gettext("The load balancer configuration has been changed") . ' ' .
					  gettext("You must apply the changes in order for them to take effect."), 'Apply', null, false, 'danger');
}

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), false, "load_balancer_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), false, "load_balancer_virtual_server.php");
$tab_array[] = array(gettext("Monitors"), false, "load_balancer_monitor.php");
$tab_array[] = array(gettext("Settings"), true, "load_balancer_setting.php");
display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section('Relayd Global Settings');

$section->addInput(new Form_Input(
	'timeout',
	'Timeout',
	'text',
	$pconfig['timeout']
))->setHelp('Set the global timeout in milliseconds for checks. Leave blank to use the default value of 1000 ms');

$section->addInput(new Form_Input(
	'interval',
	'Interval',
	'text',
	$pconfig['interval']
))->setHelp('Set the interval in seconds at which the member of a pool will be checked. Leave blank to use the default interval of 10 seconds');

$section->addInput(new Form_Input(
	'prefork',
	'Prefork',
	'text',
	$pconfig['prefork']
))->setHelp('Number of processes forked in advance by relayd. Leave blank to use the default value of 5 processes');

$form->add($section);
print($form);

include("foot.inc");
