<?php
/*
	load_balancer_monitor_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005-2008  Bill Marquette <bill.marquette@gmail.com>
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
##|*IDENT=page-services-loadbalancer-monitor-edit
##|*NAME=Services: Load Balancer: Monitor: Edit
##|*DESCR=Allow access to the 'Services: Load Balancer: Monitor: Edit' page.
##|*MATCH=load_balancer_monitor_edit.php*
##|-PRIV

require("guiconfig.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/load_balancer_monitor.php');

if (!is_array($config['load_balancer']['monitor_type'])) {
	$config['load_balancer']['monitor_type'] = array();
}

$a_monitor = &$config['load_balancer']['monitor_type'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}

if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_monitor[$id]) {
	$pconfig['name'] = $a_monitor[$id]['name'];
	$pconfig['type'] = $a_monitor[$id]['type'];
	$pconfig['descr'] = $a_monitor[$id]['descr'];
	$pconfig['options'] = array();
	$pconfig['options'] = $a_monitor[$id]['options'];
} else {
	/* Some sane page defaults */
	$pconfig['options']['path'] = '/';
	$pconfig['options']['code'] = 200;
}

if ($_GET['act'] == "dup") {
	unset($id);
}

$changedesc = gettext("Load Balancer: Monitor:") . " ";
$changecount = 0;

if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	/* turn $_POST['http_options_*'] into $pconfig['options'][*] */
	foreach ($_POST as $key => $val) {
		if (stristr($key, 'options') !== false) {
			if (stristr($key, $pconfig['type'].'_') !== false) {
				$opt = explode('_', $key);
				$pconfig['options'][$opt[2]] = $val;
			}
			unset($pconfig[$key]);
		}
	}

	/* input validation */
	$reqdfields = explode(" ", "name type descr");
	$reqdfieldsn = array(gettext("Name"), gettext("Type"), gettext("Description"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* Ensure that our monitor names are unique */
	for ($i = 0; isset($config['load_balancer']['monitor_type'][$i]); $i++) {
		if (($_POST['name'] == $config['load_balancer']['monitor_type'][$i]['name']) && ($i != $id)) {
			$input_errors[] = gettext("This monitor name has already been used.  Monitor names must be unique.");
		}
	}

	if (preg_match('/[ \/]/', $_POST['name'])) {
		$input_errors[] = gettext("Spaces or slashes cannot be used in the 'name' field.");
	}

	if (strlen($_POST['name']) > 16) {
		$input_errors[] = gettext("The 'name' field must be 16 characters or less.");
	}

	switch ($_POST['type']) {
		case 'icmp': {
			break;
		}
		case 'tcp': {
			break;
		}
		case 'http':
		case 'https': {
			if (is_array($pconfig['options'])) {
				if (isset($pconfig['options']['host']) && $pconfig['options']['host'] != "") {
					if (!is_hostname($pconfig['options']['host'])) {
						$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
					}
				}
				if (isset($pconfig['options']['code']) && $pconfig['options']['code'] != "") {
					// Check code
					if (!is_rfc2616_code($pconfig['options']['code'])) {
						$input_errors[] = gettext("HTTP(s) codes must be from RFC2616.");
					}
				}
				if (!isset($pconfig['options']['path']) || $pconfig['options']['path'] == "") {
					$input_errors[] = gettext("The path to monitor must be set.");
				}
			}
			break;
		}
		case 'send': {
			if (is_array($pconfig['options'])) {
				if (isset($pconfig['options']['send']) && $pconfig['options']['send'] != "") {
					// Check send
				}
				if (isset($pconfig['options']['expect']) && $pconfig['options']['expect'] != "") {
					// Check expect
				}
			}
			break;
		}
	}

	if (!$input_errors) {
		$monent = array();
		if (isset($id) && $a_monitor[$id]) {
			$monent = $a_monitor[$id];
		}
		if ($monent['name'] != "") {
			$changedesc .= " " . sprintf(gettext("modified '%s' monitor:"), $monent['name']);
		}

		update_if_changed("name", $monent['name'], $pconfig['name']);
		update_if_changed("type", $monent['type'], $pconfig['type']);
		update_if_changed("description", $monent['descr'], $pconfig['descr']);
		if ($pconfig['type'] == "http" || $pconfig['type'] == "https") {
			/* log updates, then clear array and reassign - dumb, but easiest way to have a clear array */
			update_if_changed("path", $monent['options']['path'], $pconfig['options']['path']);
			update_if_changed("host", $monent['options']['host'], $pconfig['options']['host']);
			update_if_changed("code", $monent['options']['code'], $pconfig['options']['code']);
			$monent['options'] = array();
			$monent['options']['path'] = $pconfig['options']['path'];
			$monent['options']['host'] = $pconfig['options']['host'];
			$monent['options']['code'] = $pconfig['options']['code'];
		}
		if ($pconfig['type'] == "send") {
			/* log updates, then clear array and reassign - dumb, but easiest way to have a clear array */
			update_if_changed("send", $monent['options']['send'], $pconfig['options']['send']);
			update_if_changed("expect", $monent['options']['expect'], $pconfig['options']['expect']);
			$monent['options'] = array();
			$monent['options']['send'] = $pconfig['options']['send'];
			$monent['options']['expect'] = $pconfig['options']['expect'];
		}
		if ($pconfig['type'] == "tcp" || $pconfig['type'] == "icmp") {
			$monent['options'] = array();
		}

		if (isset($id) && $a_monitor[$id]) {
			/* modify all pools with this name */
			for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
				if ($config['load_balancer']['lbpool'][$i]['monitor'] == $a_monitor[$id]['name']) {
					$config['load_balancer']['lbpool'][$i]['monitor'] = $monent['name'];
				}
			}
			$a_monitor[$id] = $monent;
		} else {
			$a_monitor[] = $monent;
		}

		if ($changecount > 0) {
			/* Mark config dirty */
			mark_subsystem_dirty('loadbalancer');
			write_config($changedesc);
		}

		header("Location: load_balancer_monitor.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Monitors"), gettext("Edit"));
$shortcut_section = "relayd";

include("head.inc");
$types = array("icmp" => gettext("ICMP"), "tcp" => gettext("TCP"), "http" => gettext("HTTP"), "https" => gettext("HTTPS"), "send" => gettext("Send/Expect"));

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Hides all elements of the specified class. This will usually be a section
	function hideClass(s_class, hide) {
		if (hide) {
			$('.' + s_class).hide();
		} else {
			$('.' + s_class).show();
		}
	}

	// Hide all sections except 't'
	function updateType(t) {
		switch (t) {
	<?php
		/* OK, so this is sick using php to generate javascript, but it needed to be done */
		foreach ($types as $key => $val) {
			echo "		case \"{$key}\": {\n";
			$t = $types;
			foreach ($t as $k => $v) {
				if ($k != $key) {
					echo "			hideClass('{$k}', true);\n";
				}
			}
			echo "		}\n";
		}
	?>
		}

		hideClass(t, false);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#type').on('change', function() {
		updateType($('#type').val());
	});

	// ---------- On initial page load ------------------------------------------------------------

	updateType($('#type').val());
});

//]]>
</script>

<?php
if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();
$form->setAction("load_balancer_monitor_edit.php");

$section = new Form_Section('Edit Load Balancer - Monitor Entry');

$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
));

$section->addInput(new Form_Select(
	'type',
	'Type',
	$pconfig['type'],
	$types
));

$form->add($section);

$section = new Form_Section('HTTP Options');
$section->addClass('http');

$section->addInput(new Form_Input(
	'http_options_path',
	'Path',
	'text',
	$pconfig['options']['path']
));

$section->addInput(new Form_Input(
	'http_options_host',
	'Host',
	'text',
	$pconfig['options']['host']
))->setHelp('Hostname for Host: header if needed.');

$section->addInput(new Form_Select(
	'http_options_code',
	'HTTP Code',
	$pconfig['options']['code'],
	$rfc2616
));

$form->add($section);

$section = new Form_Section('HTTPS Options');
$section->addClass('https');

$section->addInput(new Form_Input(
	'https_options_path',
	'Path',
	'text',
	$pconfig['options']['path']
));

$section->addInput(new Form_Input(
	'https_options_host',
	'Host',
	'text',
	$pconfig['options']['host']
))->setHelp('Hostname for Host: header if needed.');

$section->addInput(new Form_Select(
	'https_options_code',
	'HTTPS Code',
	$pconfig['options']['code'],
	$rfc2616
));

$form->add($section);

$section = new Form_Section('Send/Expect Options');
$section->addClass('send');

$section->addInput(new Form_Input(
	'send_options_send',
	'Send',
	'text',
	$pconfig['options']['send']
));

$section->addInput(new Form_Input(
	'send_options_expect',
	'Expect',
	'text',
	$pconfig['options']['expect']
));

if (isset($id) && $a_monitor[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);

print($form);

include("foot.inc");
