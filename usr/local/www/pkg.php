<?php
/* $Id$ */
/*
    pkg.php
    Copyright (C) 2004-2012 Scott Ullrich <sullrich@gmail.com>
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
	pfSense_MODULE:	pkgs
*/

##|+PRIV
##|*IDENT=page-package-settings
##|*NAME=Package: Settings page
##|*DESCR=Allow access to the 'Package: Settings' page.
##|*MATCH=pkg.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

function domTT_title($title_msg){
	print "onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\" onmouseover=\"domTT_activate(this, event, 'content', '".gettext($title_msg)."', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\"";
}

$xml = $_REQUEST['xml'];

if($xml == "") {
	print_info_box_np(gettext("ERROR: No package defined."));
	exit;
} else {
	if(file_exists("/usr/local/pkg/" . $xml))
		$pkg = parse_xml_config_pkg("/usr/local/pkg/" . $xml, "packagegui");
	else {
		echo "File not found " . htmlspecialchars($xml);
		exit;
	}
}

if($pkg['donotsave'] <> "") {
	Header("Location: pkg_edit.php?xml=" . $xml);
	exit;
}

if ($pkg['include_file'] != "") {
	require_once($pkg['include_file']);
}

$package_name = $pkg['menu'][0]['name'];
$section      = $pkg['menu'][0]['section'];
$config_path  = $pkg['configpath'];
$title	      = $pkg['title'];

if($_REQUEST['startdisplayingat']) 
	$startdisplayingat = $_REQUEST['startdisplayingat'];

if($_REQUEST['display_maximum_rows']) 
	if($_REQUEST['display_maximum_rows'])
		$display_maximum_rows = $_REQUEST['display_maximum_rows'];

$evaledvar = $config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'];

if ($_GET['act'] == "update") {
		
		if(is_array($config['installedpackages'][$pkg['name']]) && $pkg['name'] != "" && $_REQUEST['ids'] !=""){
			#get current values
			$current_values=$config['installedpackages'][$pkg['name']]['config'];
			#get updated ids
			parse_str($_REQUEST['ids'], $update_list);
			#sort ids to know what to change
			#usefull to do not loose data when using sorting and paging
			$sort_list=$update_list['ids'];
			sort($sort_list);
			#apply updates
			foreach($update_list['ids'] as $key=> $value){
				$config['installedpackages'][$pkg['name']]['config'][$sort_list[$key]]=$current_values[$update_list['ids'][$key]];
				}
			#save current config
			write_config();
			#sync package
			eval ("{$pkg['custom_php_resync_config_command']}");
			}
		#function called via jquery, no need to continue after save changes.
		exit;
}
if ($_GET['act'] == "del") {
		// loop through our fieldnames and automatically setup the fieldnames
		// in the environment.  ie: a fieldname of username with a value of
		// testuser would automatically eval $username = "testuser";
		foreach ($evaledvar as $ip) {
			if($pkg['adddeleteeditpagefields']['columnitem'])
			  foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
				  ${xml_safe_fieldname($column['fielddescr'])} = $ip[xml_safe_fieldname($column['fieldname'])];
			  }
		}

		$a_pkg = &$config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'];

		if ($a_pkg[$_GET['id']]) {
			unset($a_pkg[$_GET['id']]);
			write_config();
			if($pkg['custom_delete_php_command'] <> "") {
			    if($pkg['custom_php_command_before_form'] <> "")
					eval($pkg['custom_php_command_before_form']);
		    		eval($pkg['custom_delete_php_command']);
			}
			header("Location:  pkg.php?xml=" . $xml);
			exit;
	    }
}

ob_start();

$iflist = get_configured_interface_with_descr(false, true);
$evaledvar = $config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'];

if($pkg['custom_php_global_functions'] <> "")
	eval($pkg['custom_php_global_functions']);

if($pkg['custom_php_command_before_form'] <> "")
	eval($pkg['custom_php_command_before_form']);

$pgtitle = array($title);
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/javascript" src="javascript/domTT/domLib.js"></script>
<script type="text/javascript" src="javascript/domTT/domTT.js"></script>
<script type="text/javascript" src="javascript/domTT/behaviour.js"></script>
<script type="text/javascript" src="javascript/domTT/fadomatic.js"></script>
<script type="text/javascript">
//<![CDATA[
	function setFilter(filtertext) {
		jQuery('#pkg_filter').val(filtertext);
		document.pkgform.submit();
    }

	<?php
		if($pkg['adddeleteeditpagefields']['movable']){
	?>
			jQuery(document).ready(function(){
				jQuery('#mainarea table tbody').sortable({
					items: 'tr.sortable',
					cursor: 'move',
					distance: 10,
					opacity: 0.8,
					helper: function(e,ui){  
						ui.children().each(function(){  
							jQuery(this).width(jQuery(this).width());  
						});
					return ui;  
					},
				});
			});
			function save_changes_to_xml(xml) {
					var ids=jQuery('#mainarea table tbody').sortable('serialize',{key:"ids[]"});
					var strloading="<img src='/themes/<?= $g['theme']; ?>/images/misc/loader.gif' alt='loader' /> " +  "<?=gettext('Saving changes...');?>";
					if(confirm("<?=gettext("Do you really want to save changes?");?>")){
						jQuery.ajax({
							type: 'get',
							cache: false,
							url: "<?=$_SERVER['SCRIPT_NAME'];?>",
							data: {xml:'<?=$xml?>', act:'update', ids: ids},
							beforeSend: function(){
						        jQuery('#savemsg').empty().html(strloading);
							},
							error: function(data){
        						jQuery('#savemsg').empty().html('Error:' + data);
   							 },
							success: function(data){
        						jQuery('#savemsg').empty().html(data);
   							 }
						});
					}
			}
	<?php 
		}
	?>
//]]>
</script>
<form action="pkg.php" name="pkgform" method="get">
<input type='hidden' name='xml' value='<?=$_REQUEST['xml']?>' />
<?php if($_GET['savemsg'] <> "") $savemsg = htmlspecialchars($_GET['savemsg']); ?>
<div id="savemsg"></div>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="package settings">
<?php
if ($pkg['tabs'] <> "") {
    $tab_array = array();
    foreach($pkg['tabs']['tab'] as $tab) {
        if($tab['tab_level'])
            $tab_level = $tab['tab_level'];
        else
            $tab_level = 1;
        if(isset($tab['active'])) {
            $active = true;
        } else {
            $active = false;
        }
		if(isset($tab['no_drop_down']))
			$no_drop_down = true;
        $urltmp = "";
        if($tab['url'] <> "") $urltmp = $tab['url'];
        if($tab['xml'] <> "") $urltmp = "pkg_edit.php?xml=" . $tab['xml'];

        $addresswithport = getenv("HTTP_HOST");
        $colonpos = strpos($addresswithport, ":");
        if ($colonpos !== False) {
            //my url is actually just the IP address of the pfsense box
            $myurl = substr($addresswithport, 0, $colonpos);
        } else {
            $myurl = $addresswithport;
        }
        // eval url so that above $myurl item can be processed if need be.
        $url = str_replace('$myurl', $myurl, $urltmp);

        $tab_array[$tab_level][] = array(
                        $tab['text'],
                        $active,
                        $url
                    );
    }

    ksort($tab_array);
    foreach($tab_array as $tab) {
		echo '<tr><td>';
		display_top_tabs($tab, $no_drop_down);
        echo '</td></tr>';
    }
}
?>
<tr><td><div id="mainarea"><table width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
	<tr>
		<td class="tabcont">
			<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="tabs">
<?php
				/* Handle filtering bar A-Z */				
				$include_filtering_inputbox = false;
				$colspan = 0;
				if($pkg['adddeleteeditpagefields']['columnitem'] <> "") 
					foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column)
						$colspan++;
				if($pkg['fields']['field']) {
					// First find the sorting type field if it exists
					foreach($pkg['fields']['field'] as $field) {
						if($field['type'] == "sorting") {
							if(isset($field['include_filtering_inputbox'])) 
								$include_filtering_inputbox = true;
							if($display_maximum_rows < 1) 
								if($field['display_maximum_rows']) 
									$display_maximum_rows = $field['display_maximum_rows'];
							echo "<tr><td class='listhdrr' colspan='$colspan' align='center'>";
							echo "Filter by: ";
							$isfirst = true;
							for($char = 65; $char < 91; $char++) {
								if(!$isfirst) 
									echo " | ";
								echo "<a href=\"#\" onclick=\"setFilter('" . chr($char) . "');\">" . chr($char) . "</a>";
								$isfirst = false;
							}
							echo "</td></tr>";
							echo "<tr><td class='listhdrr' colspan='$colspan' align='center'>";
							if($field['sortablefields']) {
								echo "Filter field: <select name='pkg_filter_type'>";
								foreach($field['sortablefields']['item'] as $si) {
									if($si['name'] == $_REQUEST['pkg_filter_type']) 
										$SELECTED = "selected=\"selected\"";
									else 
										$SELECTED = "";
									echo "<option value='{$si['name']}' {$SELECTED}>{$si['name']}</option>";
								}
								echo "</select>";
							}
							if($include_filtering_inputbox) 
								echo "&nbsp;&nbsp;Filter text: <input id='pkg_filter' name='pkg_filter' value='" . $_REQUEST['pkg_filter'] . "' /> <input type='submit' value='Filter' />";
							echo "</td></tr><tr><td><font size='-3'>&nbsp;</font></td></tr>";
						}
					}
				}
?>
				<tr>
<?php
				if($display_maximum_rows) {
					$totalpages = ceil(round((count($evaledvar) / $display_maximum_rows),9));
					$page = 1;
					$tmpcount = 0;
					$tmppp = 0;
					if(is_array($evaledvar)) {
						foreach ($evaledvar as $ipa) {
							if($tmpcount == $display_maximum_rows) {
								$page++;
								$tmpcount = 0;
							}
							if($tmppp == $startdisplayingat)
						 		break;
							$tmpcount++;
							$tmppp++;
						}
					}
					echo "<tr><td colspan='" . count($pkg['adddeleteeditpagefields']['columnitem']) . "'>";
					echo "<table width='100%' summary=''>";
					echo "<tr>";
					echo "<td align='left'>Displaying page $page of $totalpages</b></td>";
					echo "<td align='right'>Rows per page: <select onchange='document.pkgform.submit();' name='display_maximum_rows'>";
					for($x=0; $x<250; $x++) {
						if($x == $display_maximum_rows)
							$SELECTED = "selected=\"selected\"";
						else 
							$SELECTED = "";
						echo "<option value='$x' $SELECTED>$x</option>\n";
						$x=$x+4;
					}
					echo "</select></td></tr>";
					echo "</table>";
					echo "</td></tr>";
				}
				$cols = 0;
				if($pkg['adddeleteeditpagefields']['columnitem'] <> "") {
				    foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
						echo "<td class=\"listhdrr\">" . $column['fielddescr'] . "</td>";
						$cols++;
				    }
				}
				echo "</tr>";
				$i=0;
				$pagination_startingrow=0;
				$pagination_counter=0;
			    if($evaledvar)
			    foreach ($evaledvar as $ip) {
				if($startdisplayingat) {
					if($i < $startdisplayingat) {
						$i++;
						continue;
					}
				}
				if($_REQUEST['pkg_filter']) {
					// Handle filterered items
					if($pkg['fields']['field'] && !$filter_regex) {
						// First find the sorting type field if it exists
						foreach($pkg['fields']['field'] as $field) {
							if($field['type'] == "sorting") {
								if($field['sortablefields']['item']) {
									foreach($field['sortablefields']['item'] as $sf) {
										if($sf['name'] == $_REQUEST['pkg_filter_type']) {
											$filter_fieldname = $sf['fieldname'];
											#Use a default regex on sortable fields when none is declared
											if($sf['regex'])
												$filter_regex = str_replace("%FILTERTEXT%", $_REQUEST['pkg_filter'], trim($sf['regex']));
											else
												$filter_regex = "/{$_REQUEST['pkg_filter']}/i";
										}
									}
								}
							}
						}
					}
					// Do we have something to filter on?
					unset($filter_matches);
					if($pkg['adddeleteeditpagefields']['columnitem'] <> "") {
						foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
							$fieldname = $ip[xml_safe_fieldname($column['fieldname'])];
							if($column['fieldname'] == $filter_fieldname) {
								if($filter_regex) {
									//echo "$filter_regex - $fieldname<p/>";
									preg_match($filter_regex, $fieldname, $filter_matches);
									break;
								}
							}
						}
					}
					if(!$filter_matches) {
						$i++;
						continue;
					}
				}
				if($pkg['adddeleteeditpagefields']['movable'])
					echo "<tr valign=\"top\" class=\"sortable\" id=\"id_{$i}\">\n";
				else
					echo "<tr valign=\"top\">\n";
				if($pkg['adddeleteeditpagefields']['columnitem'] <> "")
					foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
						if ($column['fieldname'] == "description")
							$class = "listbg";
						else
							$class = "listlr";
?>
						<td class="<?=$class;?>" ondblclick="document.location='pkg_edit.php?xml=<?=$xml?>&amp;act=edit&amp;id=<?=$i;?>';">
							<?php
								$fieldname = $ip[xml_safe_fieldname($column['fieldname'])];
								#Check if columnitem has a type field declared
							    if($column['type'] == "checkbox") {
									if($fieldname == "") {
								    	echo gettext("No");
									} else {
								    	echo gettext("Yes");
									}
							    } else if ($column['type'] == "interface") {
									echo  $column['prefix'] . $iflist[$fieldname] . $column['suffix'];
							    } else {
							    	#Check if columnitem has an encoding field declared
							    	if ($column['encoding'] == "base64")
										echo  $column['prefix'] . base64_decode($fieldname) . $column['suffix'];
									#Check if there is a custom info to show when $fieldname is not empty
									else if($column['listmodeon'] && $fieldname != "")
								   		echo $column['prefix'] . gettext($column['listmodeon']). $column['suffix'];
								   	#Check if there is a custom info to show when $fieldname is empty	
								   	else if($column['listmodeoff'] && $fieldname == "")
								    	echo $column['prefix'] .gettext($column['listmodeoff']). $column['suffix'];
									else
										echo $column['prefix'] . $fieldname ." ". $column['suffix'];
							    }
							?>
						</td>
<?php
					}
?>
				<td valign="middle" class="list nowrap">
				<table border="0" cellspacing="0" cellpadding="1" summary="icons">
				<tr>
				<?php
				#Show custom description to edit button if defined
				$edit_msg=($pkg['adddeleteeditpagefields']['edittext']?$pkg['adddeleteeditpagefields']['edittext']:"Edit this item");?>
				<td valign="middle"><a href="pkg_edit.php?xml=<?=$xml?>&amp;act=edit&amp;id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" <?=domTT_title($edit_msg)?> alt="edit" /></a></td>
				<?php
				#Show custom description to delete button if defined
				$delete_msg=($pkg['adddeleteeditpagefields']['deletetext']?$pkg['adddeleteeditpagefields']['deletetext']:"Delete this item");?>
				<td valign="middle"><a href="pkg.php?xml=<?=$xml?>&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this item?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" <?=domTT_title($delete_msg)?> alt="delete" /></a></td>
				</tr>
				</table>
				</td>
<?php
				echo "</tr>\n";
				// Handle pagination and display_maximum_rows
				if($display_maximum_rows) {
					if($pagination_counter == ($display_maximum_rows-1) or 
					   $i ==  (count($evaledvar)-1)) {
						$colcount = count($pkg['adddeleteeditpagefields']['columnitem']);
						$final_footer = "";
						$final_footer .= "<tr><td colspan='$colcount'>";
						$final_footer .=  "<table width='100%' summary=''><tr>";
						$final_footer .=  "<td align='left'>";
						$startingat = $startdisplayingat - $display_maximum_rows;
						if($startingat > -1) {
							$final_footer .=  "<a href='pkg.php?xml=" . $_REQUEST['xml'] . "&amp;startdisplayingat={$startingat}&amp;display_maximum_rows={$display_maximum_rows}'>";
						} else {
							if($startingnat > 1) 
								$final_footer .=  "<a href='pkg.php?xml=" . $_REQUEST['xml'] . "&amp;startdisplayingat=0&amp;display_maximum_rows={$display_maximum_rows}'>";
						}
						$final_footer .=  "<font size='2'><< Previous page</font></a>";
						if($tmppp + $display_maximum_rows > count($evaledvar)) 
							$endingrecord = count($evaledvar);
						else 
							$endingrecord = $tmppp + $display_maximum_rows;
						$final_footer .=  "</td><td align='center'>";
						$tmppp++;
						$final_footer .=  "<font size='2'>Displaying {$tmppp} - {$endingrecord} / " . count($evaledvar) . " records";
						$final_footer .=  "</font></td><td align='right'>&nbsp;";
						if(($i+1) < count($evaledvar))
							$final_footer .=  "<a href='pkg.php?xml=" . $_REQUEST['xml'] . "&amp;startdisplayingat=" . ($startdisplayingat + $display_maximum_rows) . "&amp;display_maximum_rows={$display_maximum_rows}'>";
						$final_footer .=  "<font size='2'>Next page >></font></a>";	
						$final_footer .=  "</td></tr></table></td></tr>";
						$i = count($evaledvar);
						break;
					}
				}
				$i++;
				$pagination_counter++;
			    }
?>
				<tr>
					<td colspan="<?=$cols?>"></td>
					<td>
						<table border="0" cellspacing="0" cellpadding="1" summary="icons">
							<tr>
								<?php
								#Show custom description to add button if defined
								$add_msg=($pkg['adddeleteeditpagefields']['addtext']?$pkg['adddeleteeditpagefields']['addtext']:"Add a new item");?>
								<td valign="middle"><a href="pkg_edit.php?xml=<?=$xml?>&amp;id=<?=$i?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" <?=domTT_title($add_msg)?> alt="add" /></a></td>
								<?php
								#Show description button and info if defined
								if($pkg['adddeleteeditpagefields']['description']){?>
								<td valign="middle"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_info_pkg.gif" width="17" height="17" border="0" <?=domTT_title($pkg['adddeleteeditpagefields']['description'])?> alt="info" /></td>
								<?php }?>
							</tr>
						</table>
					</td>
				</tr>
				<?=$final_footer?>
				<?php
				#Show save button only when movable is defined
				if($pkg['adddeleteeditpagefields']['movable']){?>
				<tr><td><input class="formbtn" type="button" value="Save" name="Submit" onclick="save_changes_to_xml('<?=$xml?>')" /></td></tr>
				<?php }?>
		</table>
	</td>
</tr>
</table>
</div></td></tr></table>

</form>
<?php include("fend.inc"); ?>

<?php
	echo "<!-- filter_fieldname: {$filter_fieldname} -->";
	echo "<!-- filter_regex: {$filter_regex} -->";
?>

</body>
</html>
