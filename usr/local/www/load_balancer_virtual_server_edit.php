<?php
/* $Id$ */
/*
        load_balancer_virtual_server_edit.php
        part of pfSense (https://www.pfsense.org/)

        Copyright (C) 2005-2008 Bill Marquette <bill.marquette@gmail.com>.
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
##|*IDENT=page-loadbalancer-virtualserver-edit
##|*NAME=Load Balancer: Virtual Server: Edit page
##|*DESCR=Allow access to the 'Load Balancer: Virtual Server: Edit' page.
##|*MATCH=load_balancer_virtual_server_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['load_balancer']['virtual_server'])) {
        $config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_vs[$id]) {
  $pconfig = $a_vs[$id];
} else {
  // Sane defaults
  $pconfig['mode'] = 'redirect';
}

$changedesc = gettext("Load Balancer: Virtual Server:") . " ";
$changecount = 0;

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
  switch($pconfig['mode']) {
    case "redirect": {
    	$reqdfields = explode(" ", "ipaddr name mode");
    	$reqdfieldsn = array(gettext("IP Address"),gettext("Name"),gettext("Mode"));
    	break;
    }
    case "relay": {
    	$reqdfields = explode(" ", "ipaddr name mode relay_protocol");
    	$reqdfieldsn = array(gettext("IP Address"),gettext("Name"),gettext("Relay Protocol"));
      break;
    }
  }

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	for ($i=0; isset($config['load_balancer']['virtual_server'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['virtual_server'][$i]['name']) && ($i != $id))
			$input_errors[] = gettext("This virtual server name has already been used.  Virtual server names must be unique.");

	if (preg_match('/[ \/]/', $_POST['name']))
		$input_errors[] = gettext("You cannot use spaces or slashes in the 'name' field.");

	if ($_POST['port'] != "" && !is_portoralias($_POST['port']))
		$input_errors[] = gettext("The port must be an integer between 1 and 65535, a port alias, or left blank.");

	if (!is_ipaddroralias($_POST['ipaddr']) && !is_subnetv4($_POST['ipaddr']))
		$input_errors[] = sprintf(gettext("%s is not a valid IP address, IPv4 subnet, or alias."), $_POST['ipaddr']);
	else if (is_subnetv4($_POST['ipaddr']) && subnet_size($_POST['ipaddr']) > 64)
		$input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses."), $_POST['ipaddr']);

	if ((strtolower($_POST['relay_protocol']) == "dns") && !empty($_POST['sitedown']))
		$input_errors[] = gettext("You cannot select a Fall Back Pool when using the DNS relay protocol.");

	if (!$input_errors) {
		$vsent = array();
		if(isset($id) && $a_vs[$id])
			$vsent = $a_vs[$id];
		if($vsent['name'] != "")
			$changedesc .= " " . sprintf(gettext("modified '%s' vs:"), $vsent['name']);
		else
			$changedesc .= " " . sprintf(gettext("created '%s' vs:"), $_POST['name']);

		update_if_changed("name", $vsent['name'], $_POST['name']);
		update_if_changed("descr", $vsent['descr'], $_POST['descr']);
		update_if_changed("poolname", $vsent['poolname'], $_POST['poolname']);
		update_if_changed("port", $vsent['port'], $_POST['port']);
		update_if_changed("sitedown", $vsent['sitedown'], $_POST['sitedown']);
		update_if_changed("ipaddr", $vsent['ipaddr'], $_POST['ipaddr']);
		update_if_changed("mode", $vsent['mode'], $_POST['mode']);
		update_if_changed("relay protocol", $vsent['relay_protocol'], $_POST['relay_protocol']);

		if($_POST['sitedown'] == "")
			unset($vsent['sitedown']);

		if (isset($id) && $a_vs[$id]) {
			if ($a_vs[$id]['name'] != $_POST['name']) {
				/* Because the VS name changed, mark the old name for cleanup. */
				cleanup_lb_mark_anchor($a_vs[$id]['name']);
			}
			$a_vs[$id] = $vsent;
		} else
			$a_vs[] = $vsent;

		if ($changecount > 0) {
			/* Mark virtual server dirty */
			mark_subsystem_dirty('loadbalancer');
			write_config($changedesc);
		}

		header("Location: load_balancer_virtual_server.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("Load Balancer"),gettext("Virtual Server"),gettext("Edit"));
$shortcut_section = "relayd-virtualservers";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" src="/javascript/autosuggest.js"></script>
<script type="text/javascript" src="/javascript/suggestions.js"></script>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="load_balancer_virtual_server_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="3" valign="top" class="listtopic"><?=gettext("Edit Load Balancer - Virtual Server entry"); ?></td>
				</tr>
                <tr align="left">
		  			<td width="22%" valign="top" class="vncellreq"><?=gettext("Name"); ?></td>
                  <td width="78%" class="vtable" colspan="2">
                    <input name="name" type="text" <?if(isset($pconfig['name'])) echo "value=\"" . htmlspecialchars($pconfig['name']) . "\"";?>size="32" maxlength="32">
                  </td>
			</tr>
                <tr align="left">
		  			<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable" colspan="2">
                    <input name="descr" type="text" <?if(isset($pconfig['descr'])) echo "value=\"" . htmlspecialchars($pconfig['descr']) . "\"";?>size="64">
                  </td>
			</tr>
                <tr align="left">
		  			<td width="22%" valign="top" class="vncellreq"><?=gettext("IP Address"); ?></td>
                  <td width="78%" class="vtable" colspan="2">
                    <input class="formfldalias" id="ipaddr" name="ipaddr" type="text" <?if(isset($pconfig['ipaddr'])) echo "value=\"" . htmlspecialchars($pconfig['ipaddr']) . "\"";?> size="39" maxlength="39">
					<br /><?=gettext("This is normally the WAN IP address that you would like the server to listen on.  All connections to this IP and port will be forwarded to the pool cluster."); ?>
					<br /><?=gettext("You may also specify a host alias listed in Firewall -&gt; Aliases here."); ?>
					<script type="text/javascript">
					//<![CDATA[
						var host_aliases = <?= json_encode(get_alias_list(array("host", "network", "url", "urltable"))) ?>;
						var oTextbox1 = new AutoSuggestControl(document.getElementById("ipaddr"), new StateSuggestions(host_aliases));
					//]]>
					</script>
                  </td>
			</tr>
                <tr align="left">
		  			<td width="22%" valign="top" class="vncell"><?=gettext("Port"); ?></td>
                  <td width="78%" class="vtable" colspan="2">
                    <input class="formfldalias" name="port" id="port" type="text" <?if(isset($pconfig['port'])) echo "value=\"" . htmlspecialchars($pconfig['port']) . "\"";?> size="16" maxlength="16">
					<br /><?=gettext("This is the port that the clients will connect to.  All connections to this port will be forwarded to the pool cluster."); ?>
					<br /><?=gettext("If left blank, listening ports from the pool will be used."); ?>
					<br /><?=gettext("You may also specify a port alias listed in Firewall -&gt; Aliases here."); ?>
					<script type="text/javascript">
					//<![CDATA[
						var port_aliases = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;
						var oTextbox2 = new AutoSuggestControl(document.getElementById("port"), new StateSuggestions(port_aliases));
					//]]>
					</script>
                  </td>
			</tr>
                <tr align="left">
		  			<td width="22%" valign="top" class="vncellreq"><?=gettext("Virtual Server Pool"); ?></td>
					<td width="78%" class="vtable" colspan="2">
			<?php if(count($config['load_balancer']['lbpool']) == 0): ?>
				<b><?=gettext("NOTE:"); ?></b> <?=gettext("Please add a pool on the Pools tab to use this feature."); ?>
			<?php else: ?>
				<select id="poolname" name="poolname">
			<?php
				for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
					$selected = "";
					if ( $config['load_balancer']['lbpool'][$i]['name'] == $pconfig['poolname'] )
						$selected = " SELECTED";
					echo "<option value=\"" . htmlspecialchars($config['load_balancer']['lbpool'][$i]['name']) . "\"{$selected}>{$config['load_balancer']['lbpool'][$i]['name']}</option>";
				}
			?>
			<?php endif; ?>
				</select>
				</td>
			</tr>
                <tr align="left">
		  			<td width="22%" valign="top" class="vncellreq"><?=gettext("Fall Back Pool"); ?></td>
					<td width="78%" class="vtable" colspan="2">
					<?php if(count($config['load_balancer']['lbpool']) == 0): ?>
						<b><?=gettext("NOTE:"); ?></b> <?=gettext("Please add a pool on the Pools tab to use this feature."); ?>
					<?php else: ?>
						<select id="sitedown" name="sitedown">
							<option value=""<?=htmlspecialchars($pconfig['sitedown']) == '' ? ' selected' : ''?>><?=gettext("none"); ?></option>
            			<?php
            				for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
            					$selected = "";
            					if ( $config['load_balancer']['lbpool'][$i]['name'] == $pconfig['sitedown'] )
            						$selected = " SELECTED";
						echo "<option value=\"" . htmlspecialchars($config['load_balancer']['lbpool'][$i]['name']) . "\"{$selected}>{$config['load_balancer']['lbpool'][$i]['name']}</option>";
            				}
            			?>
            			</select>
				<br /><?=gettext("The server pool to which clients will be redirected if *ALL* servers in the Virtual Server Pool are offline."); ?>
				<br /><?=gettext("This option is NOT compatible with the DNS relay protocol."); ?>
				  <?php endif; ?>
                  </td>
				</tr>
				<input type="hidden" name="mode" value="redirect_mode">
<!--
                <tr align="left">
		  			<td width="22%" valign="top" class="vncellreq">Mode</td>
                  <td width="78%" class="vtable" colspan="2">
                    <input id="redirect_mode" type="radio" name="mode" value="redirect"<?=htmlspecialchars($pconfig['mode']) == 'redirect' ? ' checked="checked"': ''?>> Redirect
                    <input id="relay_mode" type="radio" name="mode" value="relay"<?=htmlspecialchars($pconfig['mode']) == 'relay' ? ' checked="checked"': ''?>> Relay

                  <br />
                  </td>
				</tr>
-->
		<tr id="relay" align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Relay Protocol"); ?></td>
			<td width="78%" class="vtable" colspan="2">
			<select id="relay_protocol" name="relay_protocol">
			<?php
				$lb_def_protos = array("tcp", "dns");
				foreach ($lb_def_protos as $lb_proto) {
					$selected = "";
					if ( $pconfig['relay_protocol'] == $lb_proto )
						$selected = " SELECTED";
					echo "<option value=\"{$lb_proto}\"{$selected}>{$lb_proto}</option>";
				}
			?>
			</select>
			<br />
			</td>
		</tr>
                <tr align="left">
                  <td align="left" valign="bottom">
					<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Submit"); ?>">
					<input type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()">
			<?php if (isset($id) && $a_vs[$id] && $_GET['act'] != 'dup'): ?>
				<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
			<?php endif; ?>
		  	</td>
			</tr>
		</table>
	</form>
	<br />
	<span class="red"><strong><?=gettext("Note:"); ?></strong></span> <?=gettext("Don't forget to add a firewall rule for the virtual server/pool after you're finished setting it up."); ?>
<?php include("fend.inc"); ?>
</body>
</html>
