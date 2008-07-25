<?php
/* $Id$ */
/*
	system_usermanager_edit.php

	Copyright (C) 2006 Daniel S. Haischt.
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

$pgtitle = array("System","User manager","Edit privilege");

/*
	NOTE: The following code presumes, that the following XML structure
	exists or if it does not exist, it will be created.

	<priv>
		<id>fooid</id>
		<name>foo</name>
		<descr>foo desc</descr>
	</priv>
	<priv>
		<id>barid</id>
		<name>bar</name>
		<descr>bar desc</descr>
	</priv>
*/

$useract = $_GET['useract'];
if (isset($_POST['useract']))
	$useract = $_POST['useract'];

/* USERID must be set no matter whether this is a new entry or an existing entry */
$userid = $_GET['userid'];
if (isset($_POST['userid']))
	$userid = $_POST['userid'];

/* ID is only set if the user wants to edit an existing entry */
$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (empty($config['system']['user'][$userid])) {
	pfSenseHeader("system_usermanager.php?id={$userid}&act={$_GET['useract']}");
	exit;
}

if (!is_array($config['system']['user'][$userid]['priv']))
	$config['system']['user'][$userid]['priv'] = array();

$t_privs = &$config['system']['user'][$userid]['priv'];

if (isset($id) && $t_privs[$id]) {
	$pconfig['pid'] = $t_privs[$id]['id'];
	$pconfig['pname'] = $t_privs[$id]['name'];
	$pconfig['descr'] = $t_privs[$id]['descr'];
} else {
	$pconfig['pid'] = $_GET['pid'];
	$pconfig['pname'] = $_GET['pname'];
	$pconfig['descr'] = $_GET['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "pid pname");
	$reqdfieldsn = explode(",", "ID, Privilege Name");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	/* check for overlaps */
	foreach ($t_privs as $priv) {
		if (isset($id) && ($t_privs[$id]) && ($t_privs[$id] === $priv))
			continue;
		if ($priv['id'] == $pconfig['pid']) {
			$input_errors[] = gettext("This privilege ID already exists.");
			break;
		}
	}

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $userindex[$userid]['name']))
		$input_errors[] = gettext("The username contains invalid characters " .
								"((this means this user can't be used to create" .
								" a shell account).");

	/* if this is an AJAX caller then handle via JSON */
	if(isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if (!$input_errors) {
		$priv = array();
		$priv['id'] = $pconfig['pid'];
		$priv['name'] = $pconfig['pname'];
		$priv['descr'] = $pconfig['descr'];

		if (isset($id) && $t_privs[$id])
			$t_privs[$id] = $priv;
		else
			$t_privs[] = $priv;
	
		set_local_user($config['system']['user'][$userid]);
		write_config();

		$retval = 0;
		config_lock();
		config_unlock();

		$savemsg = get_std_save_message($retval);

		pfSenseHeader("system_usermanager.php?id={$userid}&act={$useract}");
		exit;
	}
}

/* if ajax is calling, give them an update message */
if(isAjax())
	print_info_box_np($savemsg);

include("head.inc");

$jscriptstr = <<<EOD
<script type="text/javascript">
<!--

  var privs = new Array();


EOD;

$privs =& getSystemPrivs();

if (is_array($privs)) {
  $id = 0;

  $jscriptstr .= "privs[{$id}] = new Object();\n";
  $jscriptstr .= "privs[{$id}]['id'] = 'custom';\n";
  $jscriptstr .= "privs[{$id}]['name'] = '*** Custom privilege ***';\n";
  $jscriptstr .= "privs[{$id}]['desc'] = 'This is your own, user defined privilege that you may change according to your requirements.';\n";
  $id++;

  foreach($privs as $priv){
    $jscriptstr .= "privs[{$id}] = new Object();\n";
    $jscriptstr .= "privs[{$id}]['id'] = '{$priv['id']}';\n";
    $jscriptstr .= "privs[{$id}]['name'] = '{$priv['name']}';\n";
    $jscriptstr .= "privs[{$id}]['desc'] = '{$priv['desc']}';\n";
    $id++;
  }
}

$jscriptstr .= <<<EOD
  function setTextFields() {
    var idx = document.iform.sysprivs.selectedIndex;
    var value = document.iform.sysprivs.options[idx].value;

    for (var i = 0; i < privs.length; i++) {
      if (privs[i]['id'] == value && privs[i]['id'] != 'custom') {
        document.iform.pid.value = privs[i]['id'];
        document.iform.pid.readOnly = true;
        document.iform.pname.value = privs[i]['name'];
        document.iform.pname.readOnly = true;
        document.iform.descr.value = privs[i]['desc'];
        document.iform.descr.readOnly = true;
        break;
      } else if (privs[i]['id'] == value) {
        document.iform.pid.value = privs[i]['id'];
        document.iform.pid.readOnly = false;
        document.iform.pname.value = privs[i]['name'];
        document.iform.pname.readOnly = false;
        document.iform.descr.value = privs[i]['desc'];
        document.iform.descr.readOnly = false;
        break;
      }
    }
  }

//-->
</script>

EOD;

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<?php echo $jscriptstr; ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="system_usermanager_edit.php" method="post" name="iform" id="iform">
            <div id="inputerrors"></div>
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("System Privileges");?></td>
                  <td width="78%" class="vtable">
                    <select name="sysprivs" id="sysprivs" class="formselect" onchange="setTextFields();">
                      <option value="custom">*** Custom privilege ***</option>
                    <?php
                      $privs =& getSystemPrivs();

                      if (is_array($privs)) {
                        foreach($privs as $priv){
                          if (isset($config['system']['ssh']['sshdkeyonly']) &&  $priv['name'] <> "copyfiles")
                              echo "<option value=\"{$priv['id']}\">${priv['name']}</option>";
                          else if (empty($config['system']['ssh']['sshdkeyonly']))
                              echo "<option value=\"{$priv['id']}\">${priv['name']}</option>";
                        }
                      }
                    ?>
                    </select><br />
                    (If you do not want to define your own privilege, you may
                    select one from this list)
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Privilege Identifier");?></td>
                  <td width="78%" class="vtable">
                    <input name="pid" type="text" class="formfld unknown" id="pid" size="30" value="<?=htmlspecialchars($pconfig['pid']);?>" />
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Privilege Name");?></td>
                  <td width="78%" class="vtable">
                    <input name="pname" type="text" class="formfld unknown" id="pname" size="30" value="<?=htmlspecialchars($pconfig['pname']);?>" />
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="60" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                    <br /> <span class="vexpl"><?=gettext("You may enter a description here
                    for your reference (not parsed).");?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input id="submitt"  name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
                    <input id="cancelbutton" class="formbtn" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()" />
                    <?php if (isset($id) && $t_privs[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>" />
                    <?php endif; ?>
                    <?php if (isset($userid)): ?>
                    <input name="userid" type="hidden" value="<?=$userid;?>" />
                    <?php endif; ?>
                    <?php if (isset($useract)): ?>
                    <input name="useract" type="hidden" value="<?=$useract;?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
