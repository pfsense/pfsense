<?php
/* $Id$ */
/*
	services_router_advertisements.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004, 2005 Scott Ullrich
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
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
/*
	pfSense_BUILDER_BINARIES:	/bin/rm
	pfSense_MODULE: interfaces
*/

##|+PRIV
##|*IDENT=page-services-router-advertisements
##|*NAME=Services: Router advertisementspage
##|*DESCR=Allow access to the 'Services: Router Advertisements' page.
##|*MATCH=services_router_advertisements.php*
##|-PRIV

require("guiconfig.inc");

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

/*	Fix failover DHCP problem
 *	http://article.gmane.org/gmane.comp.security.firewalls.pfsense.support/18749
 */
ini_set("memory_limit", "64M");

$if = $_GET['if'];
if ($_POST['if']) {
	$if = $_POST['if'];
}

/* if OLSRD is enabled, allow WAN to house DHCP. */
if ($config['installedpackages']['olsrd']) {
	foreach ($config['installedpackages']['olsrd']['config'] as $olsrd) {
		if ($olsrd['enable']) {
			$is_olsr_enabled = true;
			break;
		}
	}
}

if (!$_GET['if']) {
	$savemsg = "<p><b>" . gettext("The DHCPv6 Server can only be enabled on interfaces configured with static, non unique local IP addresses") . ".</b></p>" .
		"<p><b>" . gettext("Only interfaces configured with a static IP will be shown") . ".</b></p>";
}

$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))) ||
			(!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6']))))) {
			continue;
		}
		$if = $ifent;
		break;
	}
}

if (is_array($config['dhcpdv6'][$if])) {
	/* RA specific */
	$pconfig['ramode'] = $config['dhcpdv6'][$if]['ramode'];
	$pconfig['rapriority'] = $config['dhcpdv6'][$if]['rapriority'];
	if ($pconfig['rapriority'] == "") {
		$pconfig['rapriority'] = "medium";
	}
	$pconfig['rainterface'] = $config['dhcpdv6'][$if]['rainterface'];
	$pconfig['radomainsearchlist'] = $config['dhcpdv6'][$if]['radomainsearchlist'];
	list($pconfig['radns1'], $pconfig['radns2'], $pconfig['radns3'], $pconfig['radns4']) = $config['dhcpdv6'][$if]['radnsserver'];
	$pconfig['rasamednsasdhcp6'] = isset($config['dhcpdv6'][$if]['rasamednsasdhcp6']);

	$pconfig['subnets'] = $config['dhcpdv6'][$if]['subnets']['item'];
}
if (!is_array($pconfig['subnets'])) {
	$pconfig['subnets'] = array();
}

$advertise_modes = array("disabled" => "Disabled",
	"router" => "Router Only",
	"unmanaged" => "Unmanaged",
	"managed" => "Managed",
	"assist" => "Assisted",
	"stateless_dhcp" => "Stateless DHCP");
$priority_modes = array("low" => "Low",
	"medium" => "Normal",
	"high" => "High");
$carplist = get_configured_carp_interface_list();

$subnets_help = '<span class="help-block">' . gettext("Subnets are specified in CIDR format.  " .
	"Select the CIDR mask that pertains to each entry.	" .
	"/128 specifies a single IPv6 host; /64 specifies a normal IPv6 network; etc.  " .
	"If no subnets are specified here, the Router Advertisement (RA) Daemon will advertise to the subnet to which the router's interface is assigned." .
	'</span>');

if ($_POST) {
	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */

	$pconfig['subnets'] = array();
	for ($x = 0; $x < 5000; $x += 1) {
		$address = trim($_POST['subnet_address' . $x]);
		if ($address === "") {
			continue;
		}

		$bits = trim($_POST['subnet_bits' . $x]);
		if ($bits === "") {
			$bits = "128";
		}

		if (is_alias($address)) {
			$pconfig['subnets'][] = $address;
		} else {
			$pconfig['subnets'][] = $address . "/" . $bits;
			if (!is_ipaddrv6($address)) {
				$input_errors[] = sprintf(gettext("An invalid subnet or alias was specified. [%s/%s]"), $address, $bits);
			}
		}
	}

	if (($_POST['radns1'] && !is_ipaddrv6($_POST['radns1'])) || ($_POST['radns2'] && !is_ipaddrv6($_POST['radns2'])) || ($_POST['radns3'] && !is_ipaddrv6($_POST['radns3'])) || ($_POST['radns4'] && !is_ipaddrv6($_POST['radns4']))) {
		$input_errors[] = gettext("A valid IPv6 address must be specified for each of the DNS servers.");
	}
	if ($_POST['radomainsearchlist']) {
		$domain_array=preg_split("/[ ;]+/", $_POST['radomainsearchlist']);
		foreach ($domain_array as $curdomain) {
			if (!is_domain($curdomain)) {
				$input_errors[] = gettext("A valid domain search list must be specified.");
				break;
			}
		}
	}

	if (!$input_errors) {
		if (!is_array($config['dhcpdv6'][$if])) {
			$config['dhcpdv6'][$if] = array();
		}

		$config['dhcpdv6'][$if]['ramode'] = $_POST['ramode'];
		$config['dhcpdv6'][$if]['rapriority'] = $_POST['rapriority'];
		$config['dhcpdv6'][$if]['rainterface'] = $_POST['rainterface'];

		$config['dhcpdv6'][$if]['radomainsearchlist'] = $_POST['radomainsearchlist'];
		unset($config['dhcpdv6'][$if]['radnsserver']);
		if ($_POST['radns1']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns1'];
		}
		if ($_POST['radns2']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns2'];
		}
		if ($_POST['radns3']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns3'];
		}
		if ($_POST['radns4']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns4'];
		}

		$config['dhcpdv6'][$if]['rasamednsasdhcp6'] = ($_POST['rasamednsasdhcp6']) ? true : false;

		if (count($pconfig['subnets'])) {
			$config['dhcpdv6'][$if]['subnets']['item'] = $pconfig['subnets'];
		} else {
			unset($config['dhcpdv6'][$if]['subnets']);
		}

		write_config();
		$retval = services_radvd_configure();
		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = array(gettext("Services"), gettext("Router advertisements"));

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;
foreach ($iflist as $ifent => $ifname) {
	$oc = $config['interfaces'][$ifent];
	// We need at least one interface configured with a NON-LOCAL IPv6 static address. fd80:8dba:82e1::/64 fits the bill
	if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))) ||
		(!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6']))))) {
		continue;
	}

	if ($ifent == $if) {
		$active = true;
	} else {
		$active = false;
	}

	$tab_array[] = array($ifname, $active, "services_dhcpv6.php?if={$ifent}");
	$tabscounter++;
}

if ($tabscounter == 0) {
	include("foot.inc");
	exit;
}

display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("DHCPv6 Server"),		 false, "services_dhcpv6.php?if={$if}");
$tab_array[] = array(gettext("Router Advertisements"), true,  "services_router_advertisements.php?if={$if}");
display_top_tabs($tab_array);

require_once('classes/Form.class.php');

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Advertisements');

$section->addInput(new Form_Select(
	'ramode',
	'Router mode',
	$pconfig['ramode'],
	$advertise_modes
))->setHelp('Select the Operating Mode for the Router Advertisement (RA) Daemon. Use:' . '<br />' .
			'&nbsp;<strong>Router Only</strong> to only advertise this router' . '<br />' .
			'&nbsp;<strong>Unmanaged</strong> for Router Advertising with Stateless Autoconfig' . '<br />' .
			'&nbsp;<strong>Managed</strong> for assignment through a DHCPv6 Server' . '<br />' .
			'&nbsp;<strong>Assisted</strong> for DHCPv6 Server assignment combined with Stateless Autoconfig.' .
			'It is not required to activate this DHCPv6 server when set to "Managed", this can be another host on the network');

$section->addInput(new Form_Select(
	'rapriority',
	'Router priority',
	$pconfig['rapriority'],
	$priority_modes
))->setHelp('Select the Priority for the Router Advertisement (RA) Daemon.');

$carplistif = array();
if (count($carplist) > 0) {
	foreach ($carplist as $ifname => $vip) {
		if ((preg_match("/^{$if}_/", $ifname)) && (is_ipaddrv6($vip))) {
			$carplistif[$ifname] = $vip;
		}
	}
}

if (count($carplistif) > 0) {
	$list = array();

	foreach ($carplistif as $ifname => $vip) {
		$list['interface'] = strtoupper($if);
		$list[$ifname] = $ifname . ' - ' . $vip;
	}

	$section->addInput(new Form_Select(
		'rainterface',
		'RA Interface',
		$pconfig['rainterface'],
		$list
	))->setHelp('Select the Interface for the Router Advertisement (RA) Daemon.');
}

$section->addInput(new Form_StaticText(
	'RA Subnets',
	$subnets_help
));


if(empty($pconfig['subnets']))
	$pconfig['subnets'] = array('0' => '/128');

$counter = 0;
$numrows = count($pconfig['subnets']) - 1;

foreach ($pconfig['subnets'] as $subnet) {
	$address_name = "subnet_address" . $counter;
	$bits_name = "subnet_bits" . $counter;
	list($address, $subnet) = explode("/", $subnet);

	$group = new Form_Group($counter == 0 ? 'Subnets':'');

	$group->add(new Form_IpAddress(
		$address_name,
		null,
		$address
	))->addMask($bits_name, $subnet);

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

$section = new Form_Section('DNS Configuration');

for($idx=1; $idx=<4; $idx++) {
	$section->addInput(new Form_IpAddress(
		'radns' . $idx,
		'Server ' . $idx,
		$pconfig['radns' . $idx]
	))->setPattern('[0-9, a-z, A-Z and .')->setHelp(($idx < 4) ? '':'Leave blank to use the system default DNS servers - this interface\'s IP if DNS Forwarder or Resolver is enabled, otherwise the servers configured on the General page');
}

$section->addInput(new Form_Input(
	'radomainsearchlist',
	'Domain search list',
	'text',
	$pconfig['radomainsearchlist']
))->setHelp('The RA server can optionally provide a domain search list. Use the semicolon character as separator ');

$section->addInput(new Form_Checkbox(
	'rasamednsasdhcp6',
	'Settings',
	'Use same settings as DHCPv6 server',
	$pconfig['rasamednsasdhcp6']
));

$section->addInput(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));


$form->add($section);
print($form);
?>

<script>
//<![CDATA[
events.push(function(){

	function setMasks() {
		// Find all ipaddress masks and make dynamic based on address family of input
		$('span.pfIpMask + select').each(function (idx, select){
			var input = $(select).prevAll('input[type=text]');

			input.on('change', function(e){
				var isV6 = (input.val().indexOf(':') != -1), min = 0, max = 128;
				if (!isV6)
					max = 32;

				if (input.val() == "")
					return;

				while (select.options.length > max)
					select.remove(0);

				if (select.options.length < max)
				{
					for (var i=select.options.length; i<=max; i++)
						select.options.add(new Option(i, i), 0);
				}
			});

			// Fire immediately
			input.change();
		});
	}

	// Complicated function to move all help text associated with this input id to the same id
	// on the row above. That way if you delete the last row, you don't lose the help
	function moveHelpText(id) {
		$('#' + id).parent('div').parent('div').find('input').each(function() {	 // For each <span></span>
			var fromId = this.id;
			var toId = decrStringInt(fromId);
			var helpSpan;

			if(!$(this).hasClass('pfIpMask') && !$(this).hasClass('btn')) {

				helpSpan = $('#' + fromId).parent('div').parent('div').find('span:last').clone();
				if($(helpSpan).hasClass('help-block')) {
					if($('#' + decrStringInt(fromId)).parent('div').hasClass('input-group'))
						$('#' + decrStringInt(fromId)).parent('div').after(helpSpan);
					else
						$('#' + decrStringInt(fromId)).after(helpSpan);
				}
			}
		});
	}

	// Increment the number at the end of the string
	function bumpStringInt( str )	{
	  var data = str.match(/(\D*)(\d+)(\D*)/), newStr = "";

	  if( data )
		newStr = data[ 1 ] + ( Number( data[ 2 ] ) + 1 ) + data[ 3 ];

	  return newStr || str;
	}

	// Decrement the number at the end of the string
	function decrStringInt( str )	{
	  var data = str.match(/(\D*)(\d+)(\D*)/), newStr = "";

	  if( data )
		newStr = data[ 1 ] + ( Number( data[ 2 ] ) - 1 ) + data[ 3 ];

	  return newStr || str;
	}

	// Called after a delete so that there are no gaps in the numbering. Most of the time the config system doesn't care about
	// gaps, but I do :)
	function renumber() {
		var idx = 0;

		$('.repeatable').each(function() {

			$(this).find('input').each(function() {
				$(this).prop("id", this.id.replace(/\d+$/, "") + idx);
				$(this).prop("name", this.name.replace(/\d+$/, "") + idx);
			});

			$(this).find('select').each(function() {
				$(this).prop("id", this.id.replace(/\d+$/, "") + idx);
				$(this).prop("name", this.name.replace(/\d+$/, "") + idx);
			});

			$(this).find('label').attr('for', $(this).find('label').attr('for').replace(/\d+$/, "") + idx);

			idx++;
		});
	}

	function delete_row(row) {
		$('#' + row).parent('div').parent('div').remove();
		renumber();
	}

	function add_row() {
		// Find the lst repeatable group
		var lastRepeatableGroup = $('.repeatable:last');

		// Clone it
		var newGroup = lastRepeatableGroup.clone(true);

		// Increment the suffix number for each input elemnt in the new group
		$(newGroup).find('input').each(function() {
			$(this).prop("id", bumpStringInt(this.id));
			$(this).prop("name", bumpStringInt(this.name));
			if(!$(this).is('[id^=delete]'))
				$(this).val('');
		});

		// Do the same for selectors
		$(newGroup).find('select').each(function() {
			$(this).prop("id", bumpStringInt(this.id));
			$(this).prop("name", bumpStringInt(this.name));
			// If this selector lists mask bits, we need it to be reset to all 128 options
			// and no items selected, so that automatic v4/v6 selection still works
			if($(this).is('[id^=address_subnet]')) {
				$(this).empty();
				for(idx=128; idx>0; idx--) {
					$(this).append($('<option>', {
						value: idx,
						text: idx
					}));
				}
			}
		});

		// And for "for" tags
		$(newGroup).find('label').attr('for', bumpStringInt($(newGroup).find('label').attr('for')));
		$(newGroup).find('label').text(""); // Clear the label. We only want it on the very first row

		// Insert the updated/cloned row
		$(lastRepeatableGroup).after(newGroup);

		// Delete any help text from the group we have cloned
		$(lastRepeatableGroup).find('.help-block').each(function() {
			$(this).remove();
		});

		setMasks();
	}

	// These are action buttons, not submit buttons
	$('[id^=addrow]').prop('type','button');
	$('[id^=delete]').prop('type','button');

	// on click . .
	$('[id^=addrow]').click(function() {
		add_row();
	});

	$('[id^=delete]').click(function(event) {
		if($('.repeatable').length > 1) {
			moveHelpText(event.target.id);
			delete_row(event.target.id);
		}
		else
			alert('<?php echo gettext("You may not delete the last one!")?>');
	});

	// --------- Autocomplete -----------------------------------------------------------------------------------------
	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;

	$('#radns1, #radns2, #radns3, #radns4').autocomplete({
		source: addressarray
	});

});
//]]>
</script>

<?php include("foot.inc");