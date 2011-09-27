<?php 
/*
	system_routes_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2010 Scott Ullrich
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
##|*IDENT=page-system-staticroutes-editroute
##|*NAME=System: Static Routes: Edit route page
##|*DESCR=Allow access to the 'System: Static Routes: Edit route' page.
##|*MATCH=system_routes_edit.php*
##|-PRIV

function staticroutecmp($a, $b) {
	return strcmp($a['network'], $b['network']);
}

function staticroutes_sort() {
        global $g, $config;

        if (!is_array($config['staticroutes']['route']))
                return;

        usort($config['staticroutes']['route'], "staticroutecmp");
}

require("guiconfig.inc");

if (!is_array($config['staticroutes']['route']))
	$config['staticroutes']['route'] = array();

$a_routes = &$config['staticroutes']['route'];
$a_gateways = return_gateways_array(true);

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
}

if (isset($id) && $a_routes[$id]) {
	list($pconfig['network'],$pconfig['network_subnet']) = 
		explode('/', $a_routes[$id]['network']);
	$pconfig['gateway'] = $a_routes[$id]['gateway'];
	$pconfig['descr'] = $a_routes[$id]['descr'];
}

if (isset($_GET['dup']))
	unset($id);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "network network_subnet gateway");
	$reqdfieldsn = explode(",",
			gettext("Destination network") . "," .
			gettext("Destination network bit count") . "," .
			gettext("Gateway"));		
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['network'] && !is_ipaddr($_POST['network']))) {
		$input_errors[] = gettext("A valid destination network must be specified.");
	}
	if (($_POST['network_subnet'] && !is_numeric($_POST['network_subnet']))) {
		$input_errors[] = gettext("A valid destination network bit count must be specified.");
	}
	if ($_POST['gateway']) {
		if (!isset($a_gateways[$_POST['gateway']]))
			$input_errors[] = gettext("A valid gateway must be specified.");
		if(!validate_address_family($_POST['network'], lookup_gateway_ip_by_name($_POST['gateway'])))
			$input_errors[] = gettext("The gateway '{$a_gateways[$_POST['gateway']]['gateway']}' is a different Address Family as network '{$_POST['network']}'.");
	}

	/* check for overlaps */
	if(is_ipaddrv6($_POST['network'])) {
		$osn = Net_IPv6::compress(gen_subnetv6($_POST['network'], $_POST['network_subnet'])) . "/" . $_POST['network_subnet'];
	}
	if(is_ipaddrv4($_POST['network'])) {
		if($_POST['network_subnet'] > 32)
			$input_errors[] = gettext("A IPv4 subnet can not be over 32 bits.");
		else
			$osn = gen_subnet($_POST['network'], $_POST['network_subnet']) . "/" . $_POST['network_subnet'];
	}
	foreach ($a_routes as $route) {
		if (isset($id) && ($a_routes[$id]) && ($a_routes[$id] === $route))
			continue;

		if ($route['network'] == $osn) {
			$input_errors[] = gettext("A route to this destination network already exists.");
			break;
		}
	}

	if (!$input_errors) {
		$route = array();
		$route['network'] = $osn;
		$route['gateway'] = $_POST['gateway'];
		$route['descr'] = $_POST['descr'];

		if (!isset($id))
                        $id = count($a_routes);
                if (file_exists("{$g['tmp_path']}/.system_routes.apply"))
                        $toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.system_routes.apply"));
                else
                        $toapplylist = array();
                $oroute = $a_routes[$id];

		$a_routes[$id] = $route;

		if (!empty($oroute)) {
			$osn = explode('/', $oroute['network']);
			$sn = explode('/', $route['network']);
			if ($oroute['network'] <> $route['network'])
				$toapplylist[] = "/sbin/route delete {$oroute['network']}"; 
		}
		file_put_contents("{$g['tmp_path']}/.system_routes.apply", serialize($toapplylist));
		staticroutes_sort();
		
		mark_subsystem_dirty('staticroutes');
		
		write_config();
		
		header("Location: system_routes.php");
		exit;
	}
}

$pgtitle = array(gettext("System"),gettext("Static Routes"),gettext("Edit route"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="system_routes_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit route entry"); ?></td>
				</tr>	
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Destination network"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="network" type="text" class="formfld unknown" id="network" size="20" value="<?=htmlspecialchars($pconfig['network']);?>"> 
				  / 
                    <select name="network_subnet" class="formselect" id="network_subnet"
                      <?php
			if(is_ipaddrv4($pconfig['network'])) {
				$size = 32;
			} else {
				$size = 128;
			}
			for ($i = $size; $i >= 1; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['network_subnet']) echo "selected"; ?>>
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select>
                    <br> <span class="vexpl"><?=gettext("Destination network for this static route"); ?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Gateway"); ?></td>
                  <td width="78%" class="vtable">
			<select name="gateway" id="gateway" class="formselect">
			<?php
				foreach ($a_gateways as $gateway) {
	                      		echo "<option value='{$gateway['name']}' ";
					if ($gateway['name'] == $pconfig['gateway'])
						echo "selected";
	                      		echo ">" . htmlspecialchars($gateway['name']) . " - " . htmlspecialchars($gateway['gateway']) . "</option>\n";
				}
			?>
                    </select> <br />
			<div id='addgwbox'>
				<?=gettext("Choose which gateway this route applies to or"); ?> <a OnClick="show_add_gateway();" href="#"><?=gettext("add a new one.");?></a>
								</div>
								<div id='notebox'>
								</div>
								<div style="display:none" name ="status" id="status">
								</div>								
								<div style="display:none" id="addgateway" name="addgateway">
									<p> 
									<table border="1" style="background:#990000; border-style: none none none none; width:225px;"><tr><td>
										<table bgcolor="#990000" cellpadding="1" cellspacing="1">
											<tr><td>&nbsp;</td>
											<tr>
												<td colspan="2"><center><b><font color="white"><?=gettext("Add new gateway:"); ?></b></center></td>
											</tr>
											<tr><td>&nbsp;</td>
											<tr>
												<td width="45%" align="right"><font color="white"><?=gettext("Default gateway:"); ?></td><td><input type="checkbox" id="defaultgw" name="defaultgw"></td>
											</tr>												
											<tr>
												<td width="45%" align="right"><font color="white"><?=gettext("Interface:"); ?></td>
												<td><select name="addinterfacegw" id="addinterfacegw">
												<?php $gwifs = get_configured_interface_with_descr();
													foreach($gwifs as $fif => $dif)
														echo "<option value=\"{$fif}\">{$dif}</option>\n";
												?>
												</select></td>
											</tr>
											<tr>
												<td align="right"><font color="white"><?=gettext("Gateway Name:"); ?></td><td><input id="name" name="name" value="GW"></td>
											</tr>
											<tr>
												<td align="right"><font color="white"><?=gettext("Gateway IP:"); ?></td><td><input id="gatewayip" name="gatewayip"></td>
											</tr>
											<tr>
												<td align="right"><font color="white"><?=gettext("Description:"); ?></td><td><input id="gatewaydescr" name="gatewaydescr"></td>
											</tr>
											<tr><td>&nbsp;</td>
											<tr>
												<td colspan="2">
													<center>
														<div id='savebuttondiv'>
															<input type="hidden" name="addrtype" id="addrtype" value="IPv4" />
															<input id="gwsave" type="Button" value="<?=gettext("Save Gateway"); ?>" onClick='hide_add_gatewaysave();'> 
															<input id="gwcancel" type="Button" value="<?=gettext("Cancel"); ?>" onClick='hide_add_gateway();'>
														</div>
													</center>
												</td>
											</tr>
											<tr><td>&nbsp;</td>
										</table>
										</td></tr></table>
									<p/>
								</div>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed)."); ?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input id="save" name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>"> <input id="cancel" type="button" value="<?=gettext("Cancel"); ?>" class="formbtn"  onclick="history.back()">
                    <?php if (isset($id) && $a_routes[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<script type="text/javascript">
					var gatewayip;
					var name;
					function show_add_gateway() {
						document.getElementById("addgateway").style.display = '';
						document.getElementById("addgwbox").style.display = 'none';
						document.getElementById("gateway").style.display = 'none';
						document.getElementById("save").style.display = 'none';
						document.getElementById("cancel").style.display = 'none';
						document.getElementById("gwsave").style.display = '';
						document.getElementById("gwcancel").style.display = '';
						jQuery('#notebox').html("");
					}
					function hide_add_gateway() {
						document.getElementById("addgateway").style.display = 'none';
						document.getElementById("addgwbox").style.display = '';	
						document.getElementById("gateway").style.display = '';
						document.getElementById("save").style.display = '';
						document.getElementById("cancel").style.display = '';
						document.getElementById("gwsave").style.display = '';
						document.getElementById("gwcancel").style.display = '';
					}
					function hide_add_gatewaysave() {
						document.getElementById("addgateway").style.display = 'none';
						jQuery('#status').html('<img src="/themes/metallic/images/misc/loader.gif"> One moment please...');
						var iface = jQuery('#addinterfacegw').val();
						name = jQuery('#name').val();
						var descr = jQuery('#gatewaydescr').val();
						gatewayip = jQuery('#gatewayip').val();
						addrtype = jQuery('#addrtype').val();
						var defaultgw = '';
						if (jQuery('#defaultgw').checked)
							defaultgw = 'yes';
						var url = "system_gateways_edit.php";
						var pars = 'isAjax=true&defaultgw=' + escape(defaultgw) + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip) + '&type=' + escape(addrtype);
						jQuery.ajax(
							url,
							{
								type: 'post',
								data: pars,
								error: report_failure,
								complete: save_callback
							});
					}
					function addOption(selectbox,text,value)
					{
						var optn = document.createElement("OPTION");
						optn.text = text;
						optn.value = value;
						selectbox.append(optn);
						selectbox.prop('selectedIndex',selectbox.children('option').length-1);
						jQuery('#notebox').html("<p/><strong><?=gettext("NOTE:");?></strong> <?php printf(gettext("You can manage Gateways %shere%s."), "<a target='_new' href='system_gateways.php'>", "</a>");?> </strong>");
					}				
					function report_failure() {
						alert("<?=gettext("Sorry, we could not create your gateway at this time."); ?>");
						hide_add_gateway();
					}
					function save_callback(transport) {
						var response = transport.responseText;
						if (response) {
							document.getElementById("addgateway").style.display = 'none';
							hide_add_gateway();
							jQuery('#status').html('');
							addOption(jQuery('#gateway'), name, name);
						} else {
							report_failure();
						}
					}
				</script>
<?php include("fend.inc"); ?>
</body>
</html>
