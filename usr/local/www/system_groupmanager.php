<?php
/*
    $Id$
        part of pfSense (http://www.pfSense.com)
    originally part of part of m0n0wall (http://m0n0.ch/wall)

    Copyright (C) 2006 Scott Ullrich <sullrich@gmail.com>.
    All rights reserved.

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

$_SESSION['NO_AJAX'] = true;

$pgtitle = "System: Group manager";
$treeItemID = 0;

function walkArea($title,
                  $t,
                  $area,
                  $id,
                  &$counter,
                  &$script_tag,
                  $tmpfname,
                  &$group) {
  global $treeItemID;

  foreach($area as $a => $aa) {
    if (is_array($aa) && count($aa) > 0) {
      $title .= "_{$a}";
      echo "<li class=\"closed\"><a id=\"treeitem_{$treeItemID}\" href=\"#\">$a</a><ul>";
      $treeItemID++;
      walkArea($title,
               $a,
               $aa,
               $id,
               $counter,
               $script_tag,
               $tmpfname,
               $group);
      echo "</ul>\n";
    } else {
      $tmp_string = "{$t}";
      $tmp_string .= ": ";
      $tmp_string .= $a;
      $trimmed_title = trim($title);
      $trimmed_a = trim($a);
      $this_id = "{$trimmed_title}_{$trimmed_a}_{$counter}";
      $this_id = str_replace(" ", "", $this_id);
      $this_id = str_replace("/", "", $this_id);
      $stripped_session = str_replace("/tmp/", "", $tmpfname);
      $allowed = false;
      if (is_array($group['pages'][0]['page'])) {
          foreach($group['pages'][0]['page'] as $page) {
              if (stristr($aa, $page))
                  $allowed = true;
              // echo "$page || $aa";
          }
      }
      $allowed ? $checked = " checked=\"checked\"" : $checked = "";
      echo "                        <li id=\"treeitem_{$treeItemID}\" class=\"closed\" title=\"{$aa}\"><a name=\"anchor_{$treeItemID}\" style=\"display: none;\">&nbsp;</a>";
      $idForOnClick = $treeItemID;
      $treeItemID++;
      echo "<input type=\"checkbox\" class=\"formfld\" id=\"treeitem_{$treeItemID}\" ";
      $treeItemID++;
      echo "name=\"treeitem_{$treeItemID}\" title=\"{$area}\" onClick=\"getURL('system_groupmanager.php?id={$id}&amp;toggle={$aa}&amp;item={$idForOnClick}&amp;session={$stripped_session}', after_request_callback); rotate();\" {$checked} />&nbsp;";
      $treeItemID++;
      echo "<a id=\"treeitem_{$treeItemID}\" href=\"#anchor_{$idForOnClick}\" onclick=\"getURL('system_groupmanager.php?id={$id}&amp;toggle={$aa}&amp;item={$idForOnClick}&amp;session={$stripped_session}', after_request_callback); rotate();\">{$a}</a></li>\n";
      $idForScript = $treeItemID;
      $treeItemID++;

//echo "$script_tag <hr />";
      $script_tag .= "var item = document.getElementById('treeitem_{$idForScript}');\n";
      if ($allowed) {
          $script_tag .= "item.style.backgroundImage = \"url('/tree/page-file_play.gif')\";\n";
      } else {
          $script_tag .= "item.style.backgroundImage = \"url('/tree/page-file_x.gif')\";\n";
      }
      $counter++;
    } // end if
  } // end foreach
}

function init_ajax_helper_file($tmpfname)
{
    global $config, $id, $global;
    $a_group = &$config['system']['group'];
    $id = $_GET['id'];
    if (isset($id) && $a_group[$id])
        $group = $a_group[$id];
    else
        $group = array();
    $fd = fopen("/tmp/{$tmpfname}", "w");
    if ($group['pages'][0]['page'])
        foreach($group['pages'][0]['page'] as $page) {
        fwrite($fd, $page . "\n");
    }
    fclose($fd);
    return;
}

if ($_GET['toggle'] <> "") {
    /* AJAX is calling, lets take care of it */
    if (!file_exists("/tmp/" . $_GET['session'])) {
        init_ajax_helper_file($_GET['session']);
    }
    $fc = file_get_contents("/tmp/" . $_GET['session']);
    $file_split = split("\n", $fc);
    $found = -1;
    for($x = 0; $x < count($file_split); $x++) {
        if ($file_split[$x] == $_GET['toggle']) {
            $found = $x;
        }
    }
    if ($found == -1) {
        $file_split[] = $_GET['toggle'];
        $image = "/tree/page-file_play.gif";
    } else {
        unset($file_split[$found]);
        $image = "/tree/page-file_x.gif";
    }
    $fd = fopen("/tmp/{$_GET['session']}", "w");
    if ($file_split)
        foreach($file_split as $fs) {
        if ($fs)
            fwrite($fd, $fs . "\n");
    }
    fclose($fd);
    echo $_GET['item'] . "_a||" . "{$image}";
    exit;
}

function convert_array_to_pgtitle($orig)
{
    $newstring = "";
    foreach($orig as $o) {
        if ($newstring <> "")
            $newstring .= ": ";
        $newstring .= $o;
    }
    return $newstring;
}
// Returns an array of pages with their descriptions
function getAdminPageList()
{
    global $g;

    $tmp = Array();

    if ($dir = opendir($g['www_path'])) {
        while ($file = readdir($dir)) {
            // Make sure the file exists and is not a directory
            if ($file == "." or $file == ".." or $file[0] == '.')
                continue;
            // Is this a .inc.php file? pfSense!
            if (fnmatch('guiconfig.inc', $file))
                continue;
            if (fnmatch('*.inc', $file))
                continue;
            if (fnmatch('*.inc.php', $file))
                continue;
            if (fnmatch('*.php', $file)) {
                // Read the description out of the file
                $contents = file_get_contents($file);
                $contents_split = split("\n", $contents);
                $mlinestr = "";
                foreach($contents_split as $contents) {
                    $pgtitle = "";
                    // Looking for a line like:
                    // $pgtitle = array(gettext("System"), gettext("Group manager")); // - DO NOT REMOVE.
                    if ($mlinestr == "" && stristr($contents, "\$pgtitle") == false)
                        continue;
                    if ($mlinestr == "" && stristr($contents, "=") == false)
                        continue;
                    if (stristr($contents, "<"))
                        continue;
                    if (stristr($contents, ">"))
                        continue;
                    /* at this point its evalable */
                    $contents = trim ($contents);
                    $lastchar = substr($contents, strlen($contents) - 1, strlen($contents));
                    $firstchar = substr($contents, 0, 1);

                    /* check whether pgtitle is on one or multible lines */
                    if ($firstchar <> "/" && $firstchar <> "#" && $lastchar <> ";") {
                        /* remember the partitial pgtitle string for the next loop iteration */
                        $mlinestr .= $contents;
                        continue;
                    } else if ($mlinestr <> "" && $lastchar == ";") {
                        /* this is the final pgtitle part including the semicolon */
                        $mlinestr .= $contents;
                    } else if ($mlinestr == "" && $lastchar == ";") {
                        /* this is a single line pgtitle, hence just
                         * copy its contents into mlinestr
                         */
                        $mlinestr = $contents;
                    } else if ($firstchar == "/" || $firstchar == "#") {
                        /* same applies for comment lines */
                        $mlinestr = $contents;
                    }

                    eval($mlinestr);

                    /* after eval, if not an array, continue */
                    if (!is_array($pgtitle)) {
                        /* reset mlinestr for the next loop iteration */
                        $mlinestr = "";
                        continue;
                    }

                    $tmp[$file] = $pgtitle;

                    /* break out of the for loop, on to next file */
                    break;
                }
            }
        }

        /* loop through and read in wizard information */
        if ($dir = opendir("{$g['www_path']}/wizards")) {
            while ($file = readdir($dir)) {
                // Make sure the file exists and is not directory
                if ($file == "." or $file == ".." or $file[0] == '.')
                    continue;
                // Is this a .xml file? pfSense!
                if (fnmatch('*.xml', $file)) {
                    /* parse package and retrieve the package title */
                    $pkg = parse_xml_config_pkg("{$g['www_path']}/wizards/{$file}", "pfsensewizard");
                    $title = $pkg['title'];
                    if ($title)
                        $tmp[$file] = trim($title);
                }
            }
        }

        /* loop through and read in package information */
        if ($dir = opendir("{$g['pkg_path']}")) {
            while ($file = readdir($dir)) {
                // Make sure the file exists and is not directory
                if ($file == "." or $file == ".." or $file[0] == '.')
                    continue;
                // Is this a .xml file? pfSense!
                if (fnmatch('*.xml', $file)) {
                    /* parse package and retrieve the package title */
                    $pkg = parse_xml_config_pkg("{$g['pkg_path']}/{$file}", "packagegui");
                    $title = $pkg['title'];
                    if ($title)
                        $tmp[$file] = trim($title);
                }
            }
            closedir($dir);
        }

        // Sets Interfaces:Optional page that didn't read in properly with the above method,
        // and pages that don't have descriptions.
        $tmp['interfaces_opt.php'] = ("Interfaces: Optional");
        $tmp['graph.php'] = ("Status: Traffic Graph");
        $tmp['graph_cpu.php'] = ("Diagnostics: CPU Utilization");
        $tmp['exec_raw.php'] = ("Hidden: Exec Raw");
        $tmp['uploadconfig.php'] = ("Hidden: Upload Configuration");
        $tmp['index.php'] = ("Status: System");
        $tmp['system_usermanager.php'] = ("System: User Password");
        $tmp['diag_logs_settings.php'] = ("Diagnostics: Logs: Settings");
        $tmp['diag_logs_vpn.php'] = ("Diagnostics: Logs: PPTP VPN");
        $tmp['diag_logs_filter.php'] = ("Diagnostics: Logs: Firewall");
        $tmp['diag_logs_portal.php'] = ("Diagnostics: Logs: Captive Portal");
        $tmp['diag_logs_dhcp.php'] = ("Diagnostics: Logs: DHCP");
        $tmp['diag_logs.php'] = ("Diagnostics: Logs: System");

        $tmp['ifstats.php'] = ("Hidden: *XMLRPC Interface Stats");
        $tmp['license.php'] = ("System: License");
        $tmp['progress.php'] = ("Hidden: *No longer included");
        $tmp['diag_logs_filter_dynamic.php'] = ("Hidden: *No longer included");
        $tmp['preload.php'] = ("Hidden: *XMLRPC Preloader");
        $tmp['xmlrpc.php'] = ("Hidden: *XMLRPC Library");
        $tmp['pkg.php'] = ("System: *Renderer for XML based package GUIs (Part I)");
        $tmp['pkg_edit.php'] = ("System: *Renderer for XML based package GUIs (Part II)");

        $tmp['functions.inc.php'] = ("Hidden: Ajax Helper 1");
        $tmp['javascript.inc.php'] = ("Hidden: Ajax Helper 2 ");
        $tmp['sajax.class.php'] = ("Hidden: Ajax Helper 3");

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
        $ok_to_delete = true;
        if (isset($config['system']['user'])) {
            foreach ($config['system']['user'] as $userent) {
                if ($userent['groupname'] == $a_group[$_GET['id']]['name']) {
                    $ok_to_delete = false;
                    $input_errors[] = gettext("users still exist who are members of this group!");
                    break;
                }
            }
        }
        if ($ok_to_delete) {
            unset($a_group[$_GET['id']]);
            write_config();
            pfSenseHeader("system_groupmanager.php");
            exit;
        }
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
        $input_errors[] = gettext("The group name contains invalid characters.");

    if (!$input_errors && !(isset($id) && $a_group[$id])) {
        /* make sure there are no dupes */
        foreach ($a_group as $group) {
            if ($group['name'] == $_POST['groupname']) {
                $input_errors[] = gettext("Another entry with the same group name already exists.");
                break;
            }
        }
    }

    if (!$input_errors) {
        if (isset($id) && $a_group[$id])
            $group = $a_group[$id];

        $group['name'] = $_POST['groupname'];
        isset($_POST['homepage']) ? $group['home'] = $_POST['homepage'] : $group['home'] = "index.php";
        isset($_POST['gtype']) ? $group['scope'] = $_POST['gtype'] : $group['scope'] = "system";
        $group['description'] = $_POST['description'];
        unset($group['pages'][0]['page']);

        $file_split = split("\n", file_get_contents("/tmp/" . $_POST['session']));
        for($x = 0; $x < count($file_split); $x++) {
            if ($file_split[$x])
                $group['pages'][0]['page'][] = $file_split[$x];
        }

        if (isset($id) && $a_group[$id])
            $a_group[$id] = $group;
        else
            $a_group[] = $group;

        write_config();

        unlink_if_exists("/tmp/" . $_GET['session']);

        pfSenseHeader("system_groupmanager.php");
        exit;
    }
}

include("head.inc");

$checkallstr = <<<EOD
<script type="text/javascript">

  function checkallareas(enable) {
    var elem = document.iform.elements.length;
    var endis = (document.iform.checkall.checked || enable);

    for (i = 0; i < elem; i++) {
      if (document.iform.elements[i].name.indexOf("chk-") >= 0) {
        document.iform.elements[i].checked = true;
        document.iform.elements[i].click();
      }
    }
  }
  
</script>
EOD;

// XXX: billm TODO
//$pfSenseHead->addScript("<script type=\"text/javascript\">\n" . $checkallstr . "</script>\n");
//$pfSenseHead->addLink("<link href=\"/tree/tree.css\" rel=\"stylesheet\" type=\"text/css\" />");
//echo $pfSenseHead->getHTML();

?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc");?>
<p class="pgtitle"><?=$pgtitle;?></p>
<?php if ($input_errors) print_input_errors($input_errors);?>
<?php if ($savemsg) print_info_box($savemsg);?>
  <table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
      <td class="tabnavtbl">
<?php
  $tab_array = array();
  $tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
  $tab_array[] = array(gettext("Group"), true, "system_groupmanager.php");
  $tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
  display_top_tabs($tab_array);
?>
      </td>
    </tr>
    <tr>
      <td class="tabcont">
<?php
if ($_GET['act'] == "new" || $_GET['act'] == "edit") {
    $tmpfname = tempnam("/tmp", "edit_add_groupmanager");
    $tmpfname = str_replace("/tmp/", "", $tmpfname);
    unlink("/tmp/$tmpfname");
    init_ajax_helper_file($tmpfname);
    if ($_GET['act'] == "edit") {
        if (isset($id) && $a_group[$id]) {
            $pconfig['name'] = $a_group[$id]['name'];
            $pconfig['description'] = $a_group[$id]['description'];
            $pconfig['home'] = $a_group[$id]['home'];
            $pconfig['gtype'] = $a_group[$id]['scope'];
            $pconfig['pages'] = $a_group[$id]['pages'][0]['page'];
        }
    } else if ($_GET['act'] == "new") {
      /* set this value cause the text field is read only
       * and the user should not be able to mess with this
       * setting.
       */
      $pconfig['gtype'] = "user";
    }

?>
      <form action="system_groupmanager.php" method="post" name="iform" id="iform">
        <div id="inputerrors"></div>
<script type="text/javascript">
if (typeof getURL == 'undefined') {
    getURL = function(url, callback) {
        if (!url)
            throw 'No URL for getURL';
        try {
            if (typeof callback.operationComplete == 'function')
                callback = callback.operationComplete;
        } catch (e) {}
            if (typeof callback != 'function')
                throw 'No callback function for getURL';
        var http_request = null;
        if (typeof XMLHttpRequest != 'undefined') {
            http_request = new XMLHttpRequest();
        }
        else if (typeof ActiveXObject != 'undefined') {
            try {
                http_request = new ActiveXObject('Msxml2.XMLHTTP');
            } catch (e) {
                try {
                    http_request = new ActiveXObject('Microsoft.XMLHTTP');
                } catch (e) {}
            }
        }
        if (!http_request)
            throw 'Both getURL and XMLHttpRequest are undefined';
        http_request.onreadystatechange = function() {
            if (http_request.readyState == 4) {
                callback( { success : true,
                  content : http_request.responseText,
                  contentType : http_request.getResponseHeader("Content-Type") } );
            }
        }
        http_request.open('GET', url, true);
        http_request.send(null);
    }
}
function after_request_callback(callback_data) {
    var data = callback_data.content;
    data_split = data.split("||");
    var item = document.getElementById(data_split[0]);
    var check = document.getElementById("chk-" + data_split[0]);
    item.style.backgroundImage = 'url(' + data_split[1] + ')';
    if (data_split[1] == "/tree/page-file_play.gif") {
      check.checked = true;
    } else {
      check.checked = false;
    }
  $('troot_text').innerHTML = '<?= gettext("webConfigurator"); ?>';
}

function rotate() {
  $('troot_text').innerHTML = '<img src="/themes/<?= $g['theme'] ?>/images/misc/loader.gif" alt="" />';
}
</script>

        <input type="hidden" name="session" value="<?=$tmpfname?>" />
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
          <tr>
            <td width="22%" valign="top" class="vncellreq">
                <?=gettext("Group name");?>
            </td>
            <td width="78%" class="vtable">
              <input name="groupname" type="text" class="formfld group" id="groupname" size="20" value="<?=htmlspecialchars($pconfig['name']);?>" />
            </td>
          </tr>
          <tr>
            <td width="22%" valign="top" class="vncell">
                <?=gettext("Home Page");?>
            </td>
            <td width="78%" class="vtable">
              <input name="homepage" type="text" class="formfld url" id="homepage" size="20" value="<?=htmlspecialchars($pconfig['home']);?>" />
              <br />
              <?=gettext("A webpage that should be shown to the user after having logged in.");?>
            </td>
          </tr>
          <tr>
            <td width="22%" valign="top" class="vncellreq">
                <?=gettext("Group Type");?>
            </td>
            <td width="78%" class="vtable">
              <input name="gtype" type="text" class="formfld unknown" id="gtype" size="20" value="<?=htmlspecialchars($pconfig['gtype']);?>" readonly="readonly" />
              <br />
              <?=gettext("Indicates whether this is a system (aka non-deletable) group or a group created by the user.");?>
            </td>
          </tr>
          <tr>
            <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
            <td width="78%" class="vtable">
              <textarea name="description" class="formpre" id="description" rows="2" cols="20"><?=htmlspecialchars($pconfig['description']);?></textarea>
              <br />
              <?=gettext("Group description, for your own information only");?>
            </td>
          </tr>
          <tr>
            <td colspan="5">
              <script type="text/javascript" src="/tree/tree.js"></script>
<?php
    if (isset($id) && $a_group[$id])
        $group = $a_group[$id];
    else
        $group = array();
    $menu_array = array();
    /* build up an array similar to fbegin.inc's $menu */
    foreach ($pages as $fname => $title) {
        $identifier = str_replace('.php', '', $fname);
        $identifier = $fname;
        $title_split = split(": ", $title);
        $tmp = "\$menu_array";
        foreach($title_split as $ts)
        $tmp .= "['{$ts}']";
        $tmp .= " = \"{$identifier}\";";
        echo "<!-- $tmp -->\n";
        eval($tmp);
    }

    echo "<span id=\"troot_text\" style=\"position: relative; top: 12px;\">" . gettext("webConfigurator") . "</span><ul class=\"tree\" id=\"troot\">\n";
    $counter = 0;
    /* XXX: we may wanna pull from or add to each row a +e item (+edit) */
    $script_tag = "";

    if (is_array($menu_array) && count($menu_array) > 0) {
      foreach($menu_array as $title => $m) {
          echo "<li class=\"closed\"><a id=\"treeitem_{$treeItemID}\" href=\"#\">$title</a><ul>";
          $treeItemID++;
          if (is_array($m) && count($m) > 0) {
            foreach($m as $t => $area) {
                if (is_array($area) && count($area) > 0) {
                  echo "<li class=\"closed\"><a id=\"treeitem_{$treeItemID}\" href=\"#\">$t</a><ul>";
                  $treeItemID++;
                  walkArea("{$title}_{$t}",
                           $t,
                           $area,
                           $id,
                           $counter,
                           $script_tag,
                           $tmpfname,
                           $group);
                  echo "</ul>\n";
                } else {
                    $trimmed_title = trim($title);
                    $trimmed_t = trim($t);
                    $this_id = "{$trimmed_title}_{$trimmed_t}_{$counter}";
                    $this_id = str_replace(" ", "", $this_id);
                    $this_id = str_replace("/", "", $this_id);
                    $allowed = false;
                    if (is_array($group['pages'][0]['page'])) {
                        foreach($group['pages'][0]['page'] as $page) {
                            if (stristr($area, $page))
                                $allowed = true;
                            // echo "$page || $area || $t";
                        }
                    }
                    $allowed ? $checked = " checked=\"checked\"" : $checked = "";
                    $stripped_session = str_replace("/tmp/", "", $tmpfname);
                    echo"<li id=\"treeitem_{$treeItemID}\" class=\"closed\" title=\"{$area}\"><a name=\"anchor_{$treeItemID}\" style=\"display: none;\">&nbsp;</a>";
                    $idForOnClick = $treeItemID;
                    $treeItemID++;
                    echo "  <input type=\"checkbox\" class=\"formfld\" id=\"treeitem_{$treeItemID}\" ";
                    $treeItemID++;
                    echo "name=\"treeitem_{$treeItemID}\" title=\"{$area}\" onclick=\"getURL('system_groupmanager.php?id={$id}&amp;toggle={$area}&amp;item={$idForOnClick}&amp;session={$stripped_session}', after_request_callback); rotate();\" {$checked}/>&nbsp;";
                    echo "  <a id=\"treeitem_{$treeItemID}\" ";
                    $idForScript = $treeItemID;
                    $treeItemID++;
                    echo "href=\"#anchor_{$idForOnClick}\" onclick=\"getURL('system_groupmanager.php?id={$id}&amp;toggle={$area}&amp;item={$idForOnClick}&amp;session={$stripped_session}', after_request_callback); rotate();\">{$t}</a></li>\n";
                    $treeItemID++;
                    $script_tag .= "var item = document.getElementById('treeitem_{$idForScript}');\n";
                    if ($allowed) {
                        $script_tag .= "item.style.backgroundImage = \"url('/tree/page-file_play.gif')\";\n";
                    } else {
                        $script_tag .= "item.style.backgroundImage = \"url('/tree/page-file_x.gif')\";\n";
                    }
                    $counter++;
                }
            }
          }
          echo "</ul>\n";
      }
    }
    echo "</ul>\n";

?>
            </td>
          </tr>
          <tr>
            <td colspan="5">
              <table>
                <tr>
                  <td><input type="checkbox" name="checkall" id="checkall" title="Check/Uncheck all areas" onclick="checkallareas();"/></td>
                  <td><?=gettext("Check/Uncheck all areas");?></td>
                </tr>
                <tr>
                  <td><img src="/tree/page-file_play.gif" alt="" /></td>
                  <td><?=gettext("Allowed access to area");?></td>
                </tr>
                <tr>
                  <td><img src="/tree/page-file_x.gif" alt="" /></td>
                  <td><?=gettext("Disallowed access to area");?></td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td colspan="5">
              <?=gettext("Select that pages that this group may access.  Members of this group will be able to perform all actions that are possible from each individual web page.  Ensure you set access levels appropriately.");?>
            </td>
          </tr>
          <tr>
            <td colspan="5">
              <input id="submit" name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
              <?php if (isset($id) && $a_group[$id]): ?>
              <input name="id" type="hidden" value="<?=$id;?>" />
              <?php endif;?>
              <p>
                <span class="vexpl">
                  <span class="red">
                    <strong><?=gettext("Note");?>: </strong>
                  </span>
                  <?=gettext("Pages marked with an * are strongly recommended for every group.");?>
                </span>
              </p>
            </td>
          </tr>
        </table>
      </form>
      </td> <!-- end <td class="tabcont"/> -->
    </tr>
  </table>
<?php
} else {

?>
  <table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
      <td width="35%" class="listhdrr"><?=gettext("Group name");?></td>
      <td width="20%" class="listhdrr"><?=gettext("Description");?></td>
      <td width="20%" class="listhdrr"><?=gettext("Pages Accessible");?></td>
      <td width="10%" class="list"></td>
    </tr>
<?php
    $i = 0;
    foreach($a_group as $group):
?>
    <tr>
      <td class="listlr" valign="middle" nowrap="nowrap">
        <table border="0" cellpadding="0" cellspacing="0">
          <tr>
            <td align="left" valign="middle">
              <?php if($group['scope'] == "user"): ?>
              <img src="/themes/<?=$g['theme'];?>/images/icons/icon_system-group.png" alt="Group" title="Group" border="0" height="20" width="20" />
              <?php else: ?>
              <img src="/themes/<?=$g['theme'];?>/images/icons/icon_system-group-grey.png" alt="Group" title="Group" border="0" height="20" width="20" />
              <?php endif; ?>
              &nbsp;
            </td>
            <td align="left" valign="middle">
		<?
		if($group['name'] != "")
			echo htmlspecialchars($group['name']);
		else
			echo "&nbsp";
		?>
            </td>
          </tr>
        </table>
      </td>
      <td class="listr">
                <?
		if($group['description'] != "")
			echo htmlspecialchars($group['description']);
		else
			echo "&nbsp;";
		?>
      </td>
      <td class="listbg">
        <?php if(is_array($group['pages'][0])): ?>
        <font color="white"><?=count($group['pages'][0]['page']);?></font>
        <?php elseif (isset($group['pages'][0])): ?>
          <font color="white"><?=$group['pages'][0];?></font>
        <?php else: ?>
          <font color="white"><?=gettext("NOT SET");?></font>
        <?php endif; ?>
      </td>
      <?php if($group['scope'] == "user"): ?>
      <td valign="middle" nowrap class="list">
        <a href="system_groupmanager.php?act=edit&amp;id=<?=$i;?>">
          <img src="/themes/<?= $g['theme'];?>/images/icons/icon_e.gif" title="<?=gettext("edit group");?>" width="17" height="17" border="0" alt="" />
        </a>
        <a href="system_groupmanager.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this group?");?>')">
          <img src="./themes/<?= $g['theme'];?>/images/icons/icon_x.gif" title="<?=gettext("delete group");?>" width="17" height="17" border="0" alt="" />
        </a>
      </td>
      <?php endif; ?>
    </tr>
<?php
    $i++;
    endforeach;
?>
    <tr>
      <td class="list" colspan="3"></td>
      <td class="list">
        <a href="system_groupmanager.php?act=new">
          <img src="/themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" title="<?=gettext("add group");?>" width="17" height="17" border="0" alt="" />
        </a>
      </td>
    </tr>
    <tr>
      <td colspan="3">
        <p>
        <?=gettext("Additional webConfigurator admin groups can be added here.  Each group can be restricted to specific portions of the webConfigurator.  Individually select the desired web pages each group may access.  For example, a troubleshooting group could be created which has access only to selected Status and Diagnostics pages.");?>
        </p>
        <p>
          <?=gettext("A group icon that appears grey indicates that it is a system group and thus can't be modified or deleted.");?>
        </p>
      </td>
    </tr>
  </table>
</td></tr>
</table>
<?php
}
?>

<script type="text/javascript">
    window.setTimeout('afterload()', '10');
    function afterload() {
        <?php echo $script_tag ?>
    }
</script>
<?php include("fend.inc");?>
</body>
</html>
