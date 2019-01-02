<?php
/*
 * load_balancer_setting.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005-2008 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2012 Pierre POMES <pierre.pomes@gmail.com>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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

init_config_arr(array('load_balancer', 'setting'));
$lbsetting = &$config['load_balancer']['setting'];

if ($_POST) {
	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$retval |= relayd_configure();

		clear_subsystem_dirty('loadbalancer');
		$pconfig = $lbsetting;
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
} else {
	$pconfig = $lbsetting;
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Settings"));
$pglinks = array("", "load_balancer_pool.php", "@self");
$shortcut_section = "relayd";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('loadbalancer')) {
	print_apply_box(gettext("The load balancer configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
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
))->setHelp('Set the global timeout in milliseconds for checks. Leave blank to use the default value of 1000 ms.');

$section->addInput(new Form_Input(
	'interval',
	'Interval',
	'text',
	$pconfig['interval']
))->setHelp('Set the interval in seconds at which the member of a pool will be checked. Leave blank to use the default interval of 10 seconds.');

$section->addInput(new Form_Input(
	'prefork',
	'Prefork',
	'text',
	$pconfig['prefork']
))->setHelp('Number of processes forked in advance by relayd. Leave blank to use the default value of 5 processes.');

$form->add($section);
print($form);

include("foot.inc");
