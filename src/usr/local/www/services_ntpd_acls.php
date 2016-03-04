<?php
/*
	services_ntpd_acls.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2013 Dagorlad
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
##|*IDENT=page-services-ntpd-acls
##|*NAME=Services: NTP ACL Settings
##|*DESCR=Allow access to the 'Services: NTP ACL Settings' page.
##|*MATCH=services_ntpd_acls.php*
##|-PRIV

define('NUMACLS', 50); // The maximum number of configurable ACLs
require("guiconfig.inc");
require_once('rrd.inc');
require_once("shaper.inc");

if (!is_array($config['ntpd'])) {
	$config['ntpd'] = array();
}
if (is_array($config['ntpd']['restrictions']) && is_array($config['ntpd']['restrictions']['row'])) {
	$networkacl = $config['ntpd']['restrictions']['row'];
} else {
	$networkacl = array('0' => array('acl_network' => '', 'mask' => ''));
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	for ($x = 0; $x < NUMACLS; $x++) {
		if (isset($pconfig["acl_network{$x}"])) {
			$networkacl[$x] = array();
			$networkacl[$x]['acl_network'] = $pconfig["acl_network{$x}"];
			$networkacl[$x]['mask'] = $pconfig["mask{$x}"];

			/* ACL Flags */
			if (!empty($pconfig["kod{$x}"])) {
				$networkacl[$x]['kod'] = $pconfig["kod{$x}"];
			} elseif (isset($networkacl[$x]['kod'])) {
				unset($networkacl[$x]['kod']);
			}
			if (!empty($pconfig["nomodify{$x}"])) {
				$networkacl[$x]['nomodify'] = $pconfig["nomodify{$x}"];
			} elseif (isset($networkacl[$x]['nomodify'])) {
				unset($networkacl[$x]['nomodify']);
			}
			if (!empty($pconfig["noquery{$x}"])) {
				$networkacl[$x]['noquery'] = $pconfig["noquery{$x}"];
			} elseif (isset($networkacl[$x]['noquery'])) {
				unset($networkacl[$x]['noquery']);
			}
			if (!empty($pconfig["noserve{$x}"])) {
				$networkacl[$x]['noserve'] = $pconfig["noserve{$x}"];
			} elseif (isset($networkacl[$x]['noserve'])) {
				unset($networkacl[$x]['noserve']);
			}
			if (!empty($pconfig["nopeer{$x}"])) {
				$networkacl[$x]['nopeer'] = $pconfig["nopeer{$x}"];
			} elseif (isset($networkacl[$x]['nopeer'])) {
				unset($networkacl[$x]['nopeer']);
			}
			if (!empty($pconfig["notrap{$x}"])) {
				$networkacl[$x]['notrap'] = $pconfig["notrap{$x}"];
			} elseif (isset($networkacl[$x]['notrap'])) {
				unset($networkacl[$x]['notrap']);
			}
			/* End ACL Flags */

			if (!is_ipaddr($networkacl[$x]['acl_network'])) {
				$input_errors[] = gettext("You must enter a valid IP address for each row under Networks.");
			}
			if (is_ipaddr($networkacl[$x]['acl_network'])) {
				if (!is_subnet($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask'])) {
					$input_errors[] = gettext("You must enter a valid IPv4 netmask for each IPv4 row under Networks.");
				}
			} else if (function_exists("is_ipaddrv6")) {
				if (!is_ipaddrv6($networkacl[$x]['acl_network'])) {
					$input_errors[] = gettext("You must enter a valid IPv6 address for {$networkacl[$x]['acl_network']}.");
				} else if (!is_subnetv6($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask'])) {
					$input_errors[] = gettext("You must enter a valid IPv6 netmask for each IPv6 row under Networks.");
				}
			} else {
				$input_errors[] = gettext("You must enter a valid IP address for each row under Networks.");
			}
		} else if (isset($networkacl[$x])) {
			unset($networkacl[$x]);
		}
	}

	if (!$input_errors) {
		/* Default Access Restrictions */
		if (empty($_POST['kod'])) {
			$config['ntpd']['kod'] = 'on';
		} elseif (isset($config['ntpd']['kod'])) {
			unset($config['ntpd']['kod']);
		}

		if (empty($_POST['nomodify'])) {
			$config['ntpd']['nomodify'] = 'on';
		} elseif (isset($config['ntpd']['nomodify'])) {
			unset($config['ntpd']['nomodify']);
		}

		if (!empty($_POST['noquery'])) {
			$config['ntpd']['noquery'] = $_POST['noquery'];
		} elseif (isset($config['ntpd']['noquery'])) {
			unset($config['ntpd']['noquery']);
		}

		if (!empty($_POST['noserve'])) {
			$config['ntpd']['noserve'] = $_POST['noserve'];
		} elseif (isset($config['ntpd']['noserve'])) {
			unset($config['ntpd']['noserve']);
		}

		if (empty($_POST['nopeer'])) {
			$config['ntpd']['nopeer'] = 'on';
		} elseif (isset($config['ntpd']['nopeer'])) {
			unset($config['ntpd']['nopeer']);
		}

		if (empty($_POST['notrap'])) {
			$config['ntpd']['notrap'] = 'on';
		} elseif (isset($config['ntpd']['notrap'])) {
			unset($config['ntpd']['notrap']);
		}
		/* End Default Access Restrictions */
		$config['ntpd']['restrictions']['row'] = array();
		foreach ($networkacl as $acl) {
			$config['ntpd']['restrictions']['row'][] = $acl;
		}

		write_config("Updated NTP ACL Settings");

		$retval = 0;
		$retval = system_ntp_configure();
		$savemsg = get_std_save_message($retval);
	}
}

$pconfig = &$config['ntpd'];

$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("ACLs"));
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
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

	$group->add(new Form_IpAddress(
		'acl_network' . $counter,
		null,
		$item['acl_network']
	))->addMask('mask' . $counter, $item['mask'])->setWidth(3)->setHelp(($counter == $numrows) ? 'Network/mask':null);

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
		'Delete'
	))->removeClass('btn-primary')->addClass('btn-warning');

	$group->addClass('repeatable');
	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add'
))->removeClass('btn-primary')->addClass('btn-success');

$form->add($section);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
	// If this variable is declared, any help text will not be deleted when rows are added
	// IOW the help text will appear on every row
	retainhelp = true;
</script>

<?php include("foot.inc");
