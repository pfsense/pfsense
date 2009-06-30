<?php 
/* $Id$ */
/*
	system_routes_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

##|+PRIV
##|*IDENT=page-system-staticroutes-editroute
##|*NAME=System: Static Routes: Edit route page
##|*DESCR=Allow access to the 'System: Static Routes: Edit route' page.
##|*MATCH=system_routes_edit.php*
##|-PRIV

function staticroutes_sort() {
        global $g, $config;

        if (!is_array($config['staticroutes']['route']))
                return;

        function staticroutecmp($a, $b) {
                return strcmp($a['network'], $b['network']);
        }

        usort($config['staticroutes']['route'], "staticroutecmp");
}

require("guiconfig.inc");

if (!is_array($config['staticroutes']['route']))
	$config['staticroutes']['route'] = array();
if (!is_array($config['gateways']['gateway_item']))
	$config['gateways']['gateway_item'] = array();

staticroutes_sort();
$a_routes = &$config['staticroutes']['route'];
$a_gateways = &$config['gateways']['gateway_item'];

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
	$reqdfieldsn = explode(",", "Destination network,Destination network bit count,Gateway");		
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['network'] && !is_ipaddr($_POST['network']))) {
		$input_errors[] = "A valid destination network must be specified.";
	}
	if (($_POST['network_subnet'] && !is_numeric($_POST['network_subnet']))) {
		$input_errors[] = "A valid destination network bit count must be specified.";
	}
	if ($_POST['gateway']) {
		$match = false;
		foreach($a_gateways as $gateway) {
			if(in_array($_POST['gateway'], $gateway)) {
				$match = true;
			}
		}
		if(!$match)
			$input_errors[] = "A valid gateway must be specified.";
	}

	/* check for overlaps */
	$osn = gen_subnet($_POST['network'], $_POST['network_subnet']) . "/" . $_POST['network_subnet'];
	foreach ($a_routes as $route) {
		if (isset($id) && ($a_routes[$id]) && ($a_routes[$id] === $route))
			continue;

		if ($route['network'] == $osn) {
			$input_errors[] = "A route to this destination network already exists.";
			break;
		}
	}

	if (!$input_errors) {
		$route = array();
		$route['network'] = $osn;
		$route['gateway'] = $_POST['gateway'];
		$route['descr'] = $_POST['descr'];

		staticroutes_sort();
		if (isset($id) && $a_routes[$id])
			$a_routes[$id] = $route;
		else
			$a_routes[] = $route;
		
		mark_subsystem_dirty('staticroutes');
		
		write_config();
		
		header("Location: system_routes.php");
		exit;
	}
}

$pgtitle = array("System","Static Routes","Edit route");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="system_routes_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic">Edit route entry</td>
				</tr>	
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Destination network</td>
                  <td width="78%" class="vtable"> 
                    <input name="network" type="text" class="formfld unknown" id="network" size="20" value="<?=htmlspecialchars($pconfig['network']);?>"> 
				  / 
                    <select name="network_subnet" class="formselect" id="network_subnet">
                      <?php for ($i = 32; $i >= 1; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['network_subnet']) echo "selected"; ?>>
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select>
                    <br> <span class="vexpl">Destination network for this static route</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Gateway</td>
                  <td width="78%" class="vtable">
			<select name="gateway" class="formselect">
			<?php
				foreach ($a_gateways as $gateway): ?>
	                      <option value="<?=$gateway['name'];?>" <?php if ($gateway['name'] == $pconfig['gateway']) echo "selected"; ?>> 
	                      <?=htmlspecialchars($gateway['name']);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    <span class="vexpl">Choose which gateway this route applies to.</span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here
                    for your reference (not parsed).</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" value="Cancel" class="formbtn"  onclick="history.back()">
                    <?php if (isset($id) && $a_routes[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
<script language="JavaScript">
	enable_change();
</script>
</body>
</html>
