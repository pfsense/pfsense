<?php
/*
 * services_ntpd_acls.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Dagorlad
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
##|*IDENT=page-services-ntpd-acls
##|*NAME=Services: NTP ACL Settings
##|*DESCR=Allow access to the 'Services: NTP ACL Settings' page.
##|*MATCH=services_ntpd_acls.php*
##|-PRIV

define('NUMACLS', 50); // The maximum number of configurable ACLs

require_once("guiconfig.inc");
require_once('rrd.inc');
require_once("shaper.inc");

config_init_path('ntpd');

if (is_array(config_get_path('ntpd/restrictions/row'))) {
	$networkacl = config_get_path('ntpd/restrictions/row');
} else {
	$networkacl = array('0' => array('acl_network' => '', 'mask' => ''));
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	for ($x=0, $numacls=0; $x < NUMACLS; $x++) {
		if (array_key_exists("acl_network{$x}", $_POST)) {
			$numacls++;
		}
	}

	for ($x = 0; $x < NUMACLS; $x++) {
		if (isset($pconfig["acl_network{$x}"])) {
			$networkacl[$x] = array();
			$networkacl[$x]['acl_network'] = $pconfig["acl_network{$x}"];
			$networkacl[$x]['mask'] = $pconfig["mask{$x}"];

			/* ACL Flags */
			if (array_key_exists("kod{$x}", $pconfig)) {
				$networkacl[$x]['kod'] = "yes";
			} elseif (isset($networkacl[$x]['kod'])) {
				unset($networkacl[$x]['kod']);
			}

			if (array_key_exists("nomodify{$x}", $pconfig)) {
				$networkacl[$x]['nomodify'] = "yes";
			} elseif (isset($networkacl[$x]['nomodify'])) {
				unset($networkacl[$x]['nomodify']);
			}

			if (array_key_exists("noquery{$x}", $pconfig)) {
				$networkacl[$x]['noquery'] = "yes";
			} elseif (isset($networkacl[$x]['noquery'])) {
				unset($networkacl[$x]['noquery']);
			}

			if (array_key_exists("noserve{$x}", $pconfig)) {
				$networkacl[$x]['noserve'] = "yes";
			} elseif (isset($networkacl[$x]['noserve'])) {
				unset($networkacl[$x]['noserve']);
			}

			if (array_key_exists("nopeer{$x}", $pconfig)) {
				$networkacl[$x]['nopeer'] = "yes";
			} elseif (isset($networkacl[$x]['nopeer'])) {
				unset($networkacl[$x]['nopeer']);
			}

			if (array_key_exists("notrap{$x}", $pconfig)) {
				$networkacl[$x]['notrap'] = "yes";
			} elseif (isset($networkacl[$x]['notrap'])) {
				unset($networkacl[$x]['notrap']);
			}
			/* End ACL Flags */

			if (isset($networkacl[$x]['notrap']) || isset($networkacl[$x]['kod']) || isset($networkacl[$x]['nomodify'])
			   || isset($networkacl[$x]['noquery']) || isset($networkacl[$x]['nopeer']) || isset($networkacl[$x]['noserve'])) {
				if (!is_ipaddr($networkacl[$x]['acl_network'])) {
					$input_errors[] = sprintf(gettext("A valid IP address must be entered for row %s under Networks."), $networkacl[$x]['acl_network']);
				} else {
					if (is_ipaddrv4($networkacl[$x]['acl_network'])) {
						if (!is_subnetv4($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask'])) {
							$input_errors[] = sprintf(gettext("A valid IPv4 netmask must be entered for IPv4 row %s under Networks."), $networkacl[$x]['acl_network']);
						}
					} else if (!is_subnetv6($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask'])) {
						$input_errors[] = sprintf(gettext("A valid IPv6 netmask must be entered for IPv6 row %s under Networks."), $networkacl[$x]['acl_network']);
					}
				}
			} else if ((strlen($networkacl[$x]['acl_network']) == 0) && ($numacls > 1)) {
				unset($networkacl[$x]);
			}
		} else if (isset($networkacl[$x])) {
			unset($networkacl[$x]);
		}
	}

	if (!$input_errors) {
		/* Default Access Restrictions */
		if (empty($_POST['kod'])) {
			config_set_path('ntpd/kod', 'on');
		} elseif (config_path_enabled('ntpd', 'kod')) {
			config_del_path('ntpd/kod');
		}

		if (empty($_POST['nomodify'])) {
			config_set_path('ntpd/nomodify', 'on');
		} elseif (config_path_enabled('ntpd', 'nomodify')) {
			config_del_path('ntpd/nomodify');
		}

		if (!empty($_POST['noquery'])) {
			config_set_path('ntpd/noquery', $_POST['noquery']);
		} elseif (config_path_enabled('ntpd', 'noquery')) {
			config_del_path('ntpd/noquery');
		}

		if (!empty($_POST['noserve'])) {
			config_set_path('ntpd/noserve', $_POST['noserve']);
		} elseif (config_path_enabled('ntpd', 'noserve')) {
			config_del_path('ntpd/noserve');
		}

		if (empty($_POST['nopeer'])) {
			config_set_path('ntpd/nopeer', 'on');
		} elseif (config_path_enabled('ntpd', 'nopeer')) {
			config_del_path('ntpd/nopeer');
		}

		if (empty($_POST['notrap'])) {
			config_set_path('ntpd/notrap', 'on');
		} elseif (config_path_enabled('ntpd', 'notrap')) {
			config_del_path('ntpd/notrap');
		}
		/* End Default Access Restrictions */
		config_set_path('ntpd/restrictions/row', array());
		foreach ($networkacl as $acl) {
			config_set_path('ntpd/restrictions/row/', $acl);
		}

		write_config("Updated NTP ACL Settings");

		$changes_applied = true;
		$retval = 0;
		$retval |= system_ntp_configure();
	}
}

config_init_path('ntpd');
$pconfig = config_get_path('ntpd');

$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("ACLs"));
$pglinks = array("", "services_ntpd.php", "@self");
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "services_ntpd.php");
$tab_array[] = array(gettext("ACLs"), true, "services_ntpd_acls.php");
$tab_array[] = array(gettext("Serial GPS"), false, "services_ntpd_gps.php");
$tab_array[] = array(gettext("PPS"), false, "services_ntpd_pps.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('Default Access Restrictions');

$section->addInput(new Form_Checkbox(
	'kod',
	'Kiss-o\'-death',
	'Enable KOD packets.',
	!$pconfig['kod']
));

$section->addInput(new Form_Checkbox(
	'nomodify',
	"Modifications",
	'Deny run-time Configuration (nomodify) by ntpq and ntpdc.',
	!$pconfig['nomodify']
));

$section->addInput(new Form_Checkbox(
	'noquery',
	'Queries',
	'Disable ntpq and ntpdc queries (noquery).',
	$pconfig['noquery']
));

$section->addInput(new Form_Checkbox(
	'noserve',
	'Service',
	'Disable all except ntpq and ntpdc queries (noserve).',
	$pconfig['noserve']
));

$section->addInput(new Form_Checkbox(
	'nopeer',
	'Peer Association',
	'Deny packets that attempt a peer association (nopeer).',
	!$pconfig['nopeer']
));

$section->addInput(new Form_Checkbox(
	'notrap',
	'Trap Service',
	'Deny mode 6 control message trap service (notrap).',
	!$pconfig['notrap']
));

/* End Default Restrictions*/
$form->add($section);

$section = new Form_Section('Custom Access Restrictions');

$numrows = count($networkacl) - 1;
$counter = 0;

foreach ($networkacl as $item) {
	$group = new Form_Group($counter == 0 ? 'Networks':'');

	$helptext = ($counter == $numrows) ? gettext('Network/mask'):"";

	$group->add(new Form_IpAddress(
		'acl_network' . $counter,
		null,
		$item['acl_network']
	))->addMask('mask' . $counter, $item['mask'])->setWidth(3)->setHelp($helptext);

	$group->add(new Form_Checkbox(
		'kod' . $counter,
		null,
		null,
		$item['kod']
	))->setHelp('KOD');

	$group->add(new Form_Checkbox(
		'nomodify' . $counter,
		null,
		null,
		$item['nomodify']
	))->setHelp('nomodify');

	$group->add(new Form_Checkbox(
		'noquery' . $counter,
		null,
		null,
		$item['noquery']
	))->setHelp('noquery');

	$group->add(new Form_Checkbox(
		'noserve' . $counter,
		null,
		null,
		$item['noserve']
	))->setHelp('noserve');

	$group->add(new Form_Checkbox(
		'nopeer' . $counter,
		null,
		null,
		$item['nopeer']
	))->setHelp('nopeer');

	$group->add(new Form_Checkbox(
		'notrap' . $counter,
		null,
		null,
		$item['notrap']
	))->setHelp('notrap');

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-solid fa-trash-can'
	))->addClass('btn-warning btn-xs')->addClass("nowarn");

	$group->addClass('repeatable');

	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add',
	null,
	'fa-solid fa-plus'
))->addClass('btn-success');

$form->add($section);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	retainhelp = false;
});
//]]>
</script>

<?php include("foot.inc");
