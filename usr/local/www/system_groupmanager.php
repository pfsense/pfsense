<?php 
/*
	$Id: system_groupmanager.php 
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2005 Paul Taylor <paultaylor@winn-dixie.com>.
	All rights reserved. 

	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

require("guiconfig.inc");

$pgtitle = array("System", "Group manager");

// Returns an array of pages with their descriptions
function getAdminPageList() {
	global $g;
	global $config;
	
    $tmp = Array();

    if ($dir = opendir($g['www_path'])) {
		while($file = readdir($dir)) {
	    	// Make sure the file exists
	    	if($file != "." && $file != ".." && $file[0] != '.') {
	    		// Is this a .php file?
	    		if (fnmatch('*.php',$file)) {
	    			// Read the description out of the file
		    		$contents = file_get_contents($file);
		    		// Looking for a line like:
		    		// $pgtitle = array("System", "Group manager");
		    		$offset = strpos($contents,'$pgtitle');
		    		$titlepos = strpos($contents,'(',$offset);
		    		$titleendpos = strpos($contents,')',$titlepos);
		    		if (($offset > 0) && ($titlepos > 0) && ($titleendpos > 0)) {
		    			// Title found, extract it
		    			$title = str_replace(',',': ',str_replace(array('"'),'',substr($contents,++$titlepos,($titleendpos - $titlepos))));
		    			$tmp[$file] = trim($title);
		    		}
		    		else {
		    			$tmp[$file] = '';
		    		}
	    		
	    		}
	        }
		}

        closedir($dir);
        
        // Sets Interfaces:Optional page that didn't read in properly with the above method,
        // and pages that don't have descriptions.
        $tmp['interfaces_opt.php'] = "Interfaces: Optional";
        $tmp['graph.php'] = "Diagnostics: Interface Traffic";
        $tmp['graph_cpu.php'] = "Diagnostics: CPU Utilization";
        $tmp['exec.php'] = "Command";
        $tmp['exec_raw.php'] = "Hidden: Exec Raw";
        $tmp['status.php'] = "Hidden: Detailed Status";
        $tmp['uploadconfig.php'] = "Hidden: Upload Configuration";
        $tmp['index.php'] = "*After Login/Dashboard";
        $tmp['system_usermanager.php'] = "*User Password change portal";
        $tmp['diag_logs_settings.php'] = "Diagnostics: Logs: Settings";
        $tmp['diag_logs_vpn.php'] = "Diagnostics: Logs: PPTP VPN";
        $tmp['diag_logs_filter.php'] = "Diagnostics: Logs: Firewall";
        $tmp['diag_logs_portal.php'] = "Diagnostics: Logs: Captive Portal";
        $tmp['diag_logs_dhcp.php'] = "Diagnostics: Logs: DHCP";
        $tmp['diag_logs.php'] = "Diagnostics: Logs: System";

		$tmp['cg2.php'] = "CoreGUI GUI Manager";
        
        unset($tmp['system_groupmanager_edit.php']);
        unset($tmp['firewall_rules_schedule_logic.php']);
        unset($tmp['status_rrd_graph_img.php']);
        unset($tmp['diag_new_states.php']);
        unset($tmp['system_usermanager_edit.php']);
        
        $tmp['pkg.php'] = "{$g['product_name']} Package manager";
        $tmp['pkg_edit.php'] = "{$g['product_name']} Package manager edit";
        $tmp['wizard.php'] = "{$g['product_name']} wizard subsystem";
        $tmp['graphs.php'] = "Graphing subsystem";
        $tmp['headjs.php'] = "*Required for javascript";

		$tmp['ifstats.php'] = ("*Hidden: XMLRPC Interface Stats");
		$tmp['license.php'] = ("*System: License");
		$tmp['progress.php'] = ("*Hidden: No longer included");
		$tmp['diag_logs_filter_dynamic.php'] = ("*Hidden: No longer included"); 
		$tmp['preload.php'] = ("*Hidden: XMLRPC Preloader");
		$tmp['xmlrpc.php'] = ("*Hidden: XMLRPC Library");        
		
		$tmp['functions.inc.php'] = ("Hidden: Ajax Helper 1");
		$tmp['javascript.inc.php'] = ("Hidden: Ajax Helper 2 ");
		$tmp['sajax.class.php'] = ("Hidden: Ajax Helper 3");

		/* custom pkg.php items */
		$tmp['pkg.php?xml=openvpn.xml'] = ("VPN: OpenVPN");
		$tmp['pkg_edit.php?xml=carp_settings.xml&id=0'] = ("Services: CARP Settings: Edit");
		$tmp['pkg_edit.php?xml=olsrd.xml&id=0'] = ("Services: OLSR");
		$tmp['pkg_edit.php?xml=openntpd.xml&id=0'] = ("Services: NTP Server");
		
		$tmp['system_usermanager_settings_test.php'] = ("System: User Manager: Settings: Test LDAP");
		
		/*  unset older openvpn scripts, we have a custom version
		 *  included in CoreGUI */
	 	unset($tmp['vpn_openvpn.php']);
		unset($tmp['vpn_openvpn_crl.php']);
		unset($tmp['vpn_openvpn_ccd.php']);
		unset($tmp['vpn_openvpn_srv.php']);
		unset($tmp['vpn_openvpn_cli.php']);
		unset($tmp['vpn_openvpn_ccd_edit.php']);
		unset($tmp['phpconfig.php']);
		unset($tmp['system_usermanager_settings_ldapacpicker.php']);
		
        unset($tmp['progress.php']);
        unset($tmp['stats.php']);
        unset($tmp['phpinfo.php']);
        unset($tmp['preload.php']);
        
        // Add appropriate descriptions for extensions, if they exist
        if(file_exists("extensions.inc")){
	   	   include("extensions.inc");
		}
		
		/* firewall rule view and edit entries for lan, wan, optX */
		$iflist = get_configured_interface_list(false, true);

		// Firewall Rules
		foreach ($iflist as $ifent => $ifname) {
			$entryname = "firewall_rules.php?if={$ifname}";
	        $tmp[$entryname] = ("Firewall: Rules: " . strtoupper($ifname));
			$entryname = "firewall_rules_edit.php?if={$ifname}";
	        $tmp[$entryname] = ("Firewall: Rules: Edit: " . strtoupper($ifname));
		}

		/* additional firewal rules tab entries */
		$entryname = "firewall_rules_edit.php?if=enc0";
        $tmp[$entryname] = "Firewall: Rules: Edit: IPsec";

		$entryname = "firewall_rules_edit.php?if=pptp";
        $tmp[$entryname] = "Firewall: Rules: Edit: PPTP";

		$entryname = "firewall_rules_edit.php?if=pppoe";
        $tmp[$entryname] = "Firewall: Rules: Edit: PPPoE";

		// User manager
		$entryname = "system_usermanager.php";
		$tmp[$entryname] = "System: Change Password";

		// User manager
		$entryname = "system_usermanager";
		$tmp[$entryname] = "System: User Manager";

		// NAT Items
		foreach ($iflist as $ifent => $ifname) {
			$entryname = "firewall_nat.php?if={$ifname}";
	        $tmp[$entryname] = ("Firewall: NAT: Port Forward " . strtoupper($ifname));
			$entryname = "firewall_nat_edit.php?if={$ifname}";
	        $tmp[$entryname] = ("Firewall: NAT: Port Forward: Edit: " . strtoupper($ifname));
		}
		/* additional nat tab entries */
		$entryname = "firewall_nat_edit.php?if=enc0";
        $tmp[$entryname] = "Firewall: NAT: Port Forward: Edit: IPsec";
        
		$entryname = "firewall_nat_edit.php?if=pptp";
        $tmp[$entryname] = "Firewall: NAT: Port Forward: Edit: PPTP";

		$entryname = "firewall_nat_edit.php?if=pppoe";
        $tmp[$entryname] = "Firewall: NAT: Port Forward: Edit: PPPoE";

        asort($tmp);
        return $tmp;
    }
}

// Get a list of all admin pages & Descriptions
$pages = getAdminPageList();

if (!is_array($config['system']['group'])) {
	$config['system']['group'] = array();
}
admin_groups_sort();
$a_group = &$config['system']['group'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];
	
if ($_GET['act'] == "del") {
	if ($a_group[$_GET['id']]) {
		del_local_group($a_group[$_GET['id']]);
   		unset($a_group[$_GET['id']]);
       	write_config();
	    header("Location: system_groupmanager.php");
	    exit;
	}
}	

if($_GET['act']=="edit"){
	if (isset($id) && $a_group[$id]) {
		$pconfig['name'] = $a_group[$id]['name'];
		$pconfig['description'] = $a_group[$id]['description'];
		if (is_array($a_group[$id]['pages']))
			$pconfig['pages'] = $a_group[$id]['pages'];
		else
			$pconfig['pages'] = array();
	}
}
	
if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "groupname");
	$reqdfieldsn = explode(",", "Group Name");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (preg_match("/[^a-zA-Z0-9\.\-_ ]/", $_POST['groupname']))
		$input_errors[] = "The group name contains invalid characters.";
		
	if (!$input_errors && !(isset($id) && $a_group[$id])) {
		/* make sure there are no dupes */
		foreach ($a_group as $group) {
			if ($group['name'] == $_POST['groupname']) {
				$input_errors[] = "Another entry with the same group name already exists.";
				break;
			}
		}
	}
	
	if (!$input_errors) {
		$group = array();
		if (isset($id) && $a_group[$id])
			$group = $a_group[$id];
		
		$group['name'] = $_POST['groupname'];
		$group['description'] = $_POST['description'];

		unset($group['pages']);
		foreach ($pages as $fname => $title) {
			$identifier = str_replace('.php','XXXUMXXX',$fname);
			$identifier = str_replace('.','XXXDOTXXX',$identifier);
			if ($_POST[$identifier] == 'yes') {
				$group['pages'][] = $fname;
			}
		}

		if (isset($id) && $a_group[$id])
			$a_group[$id] = $group;
		else {
			$group['gid'] = $config['system']['nextgid']++;
			$a_group[] = $group;
		}

		set_local_group($group);
		write_config();
		
		header("Location: system_groupmanager.php");
		exit;
	}
}

include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php
	include("fbegin.inc");
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td class="tabnavtbl">
			<ul id="tabnav">
			<?php 
				$tab_array = array();
				$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
				$tab_array[] = array(gettext("Group"), true, "system_groupmanager.php");
				$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
				display_top_tabs($tab_array);
			?>
			</ul>
		</td>
	</tr>    
	<tr>
		<td class="tabcont">

			<?php if($_GET['act']=="new" || $_GET['act']=="edit"): ?>

			<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
			<script type="text/javascript">
				function checkall() {
					var el = document.getElementById('iform');
					for (var i = 0; i < el.elements.length; i++)
						el.elements[i].checked = true;
				}
				function checknone() {
					var el = document.getElementById('iform');
					for (var i = 0; i < el.elements.length; i++)
						el.elements[i].checked = false;
				}
			</script>
			<form action="system_groupmanager.php" method="post" name="iform" id="iform">
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr> 
						<td width="22%" valign="top" class="vncellreq">Group name</td>
						<td width="78%" class="vtable"> 
							<input name="groupname" type="text" class="formfld" id="groupname" size="20" value="<?=htmlspecialchars($pconfig['name']);?>"> 
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell">Description</td>
						<td width="78%" class="vtable"> 
							<input name="description" type="text" class="formfld" id="description" size="20" value="<?=htmlspecialchars($pconfig['description']);?>">
							<br>
							Group description, for your own information only
						</td>
					</tr>
					<tr>
						<td colspan="4">
							<br>
							Select that pages that this group may access.
							Members of this group will be able to perform
							all actions that are possible from each
							individual web page. Ensure you set access
							levels appropriately.<br>
							<br>
							<span class="vexpl">
								<span class="red">
									<strong>&nbsp;Note:</strong>
								</span>
								Pages marked with an * are strongly recommended
								for every group.
							</span>
						</td>
					</tr>
					<tr>
						<td colspan="4">
							<input type="button" name="types[]" value="Check All" onClick="checkall(); return false;"> 
							<input type="button" name="types[]" value="Check None" onClick="checknone(); return false;">
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<table width="100%" border="0" cellpadding="0" cellspacing="0">
								<tr>
									<td class="listhdrr">&nbsp;</td>
									<td class="listhdrr">Page Description</td>
									<td class="listhdr">Filename</td>
								</tr>
								<?php 
									foreach ($pages as $fname => $title):
										$identifier = str_replace('.php','XXXUMXXX',$fname);
										$identifier = str_replace('.','XXXDOTXXX',$identifier);
										$checked = "";
										if (in_array($fname,$pconfig['pages']))
											$checked = "checked";
								?>
								<tr>
									<td class="listlr">
										<input class="check" name="<?=$identifier?>" type="checkbox" id="<?=$identifier?>" value="yes" <?=$checked;?>>
									</td>
									<td class="listr"><?=$title?></td>
									<td class="listr"><?=$fname?></td>
								</tr>
								<?php endforeach; ?>
							</table>
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%"> 
							<input name="save" type="submit" class="formbtn" value="Save"> 
							<?php if (isset($id) && $a_group[$id]): ?>
							<input name="id" type="hidden" value="<?=$id;?>">
							<?php endif; ?>                
						</td>
					</tr>
				</table>
			</form>

			<?php else: ?>

			<table width="100%" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td width="25%" class="listhdrr">Group name</td>
					<td width="25%" class="listhdrr">Description</td>
					<td width="15%" class="listhdrr">Member Count</td>
					<td width="15%" class="listhdrr">Pages Accessible</td>
					<td width="10%" class="list"></td>
				</tr>
				<?php
					$i = 0;
					foreach($a_group as $group):
				?>
				<tr>
					<td class="listlr">
						<?=htmlspecialchars($group['name']); ?>&nbsp;
					</td>
					<td class="listr">
						<?=htmlspecialchars($group['description']);?>&nbsp;
					</td>
					<td class="listr">
						<?=count($group['member'])?>
					</td>
					<td class="listbg">
						<font color="white">
							<?=count($group['pages']);?>
						</font>
					</td>
					<td valign="middle" nowrap class="list">
						<a href="system_groupmanager.php?act=edit&id=<?=$i;?>">
							<img src="./themes/<?=$g['theme'];?>/images/icons/icon_e.gif" title="edit group" width="17" height="17" border="0">
						</a>
						&nbsp;
						<a href="system_groupmanager.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this group?')">
							<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" title="delete group" width="17" height="17" border="0">
						</a>
					</td>
				</tr>
				<?php
					$i++;
					endforeach;
				?>
				<tr> 
					<td class="list" colspan="4"></td>
					<td class="list">
						<a href="system_groupmanager.php?act=new"><img src="./themes/<?=$g['theme'];?>/images/icons/icon_plus.gif" title="add group" width="17" height="17" border="0">
						</a>
					</td>
				</tr>
				<tr>
					<td colspan="4">
						Additional webGui admin groups can be added here.
						Each group can be restricted to specific portions of the webGUI.
						Individually select the desired web pages each group may access.
						For example, a troubleshooting group could be created which has
						access only to selected Status and Diagnostics pages.
					</td>
				</tr>
			</table>
			
			<? endif; ?>
     
		</td>
	</tr>
</table>
</body>
<?php include("fend.inc"); ?>
