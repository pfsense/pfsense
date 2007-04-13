<?php
/* $Id$ */
/*
    part of pfSense (http://www.pfsense.org/)

    Copyright (C) 2007 Bill Marquette <bill.marquette@gmail.com>
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
$pconfig['session_timeout'] = &$config['system']['webgui']['session_timeout'];

// Page title for main admin
$pgtitle = array(gettext("System"), gettext("User manager settings"));

if ($_POST) {
	unset($input_errors);

	/* input validation */
	$reqdfields = explode(" ", "session_timeout");
	$reqdfieldsn = explode(",", "Session Timeout");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['session_timeout'] != "" && !is_numeric($_POST['session_timeout']))
		$input_errors[] = gettext("Session timeout must be an integer with value 0 or greater.");

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}


	if (!$input_errors) {
		$pconfig['session_timeout'] = $_POST['session_timeout'];

		write_config();

		pfSenseHeader("system_usermanager_settings.php");
	}
}

include("head.inc");
// XXX billm FIXME
//echo $pfSenseHead->getHTML();
?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc");?>
<?php if ($input_errors) print_input_errors($input_errors);?>
<?php if ($savemsg) print_info_box($savemsg);?>
  <table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
      <td class="tabnavtbl">
<?php
    $tab_array = array();
    $tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
    $tab_array[] = array(gettext("Group"), false, "system_groupmanager.php");
    $tab_array[] = array(gettext("Settings"), true, "system_usermanager_settings.php");
    display_top_tabs($tab_array);
?>
      </td>
    <tr>
       <td>
            <div id="mainarea">
            <form id="iform" name="iform" action="system_usermanager_settings.php" method="post">
              <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td width="22%" valign="top" class="vncell">Session Timeout</td>
                        <td width="78%" class="vtable"> <input name="session_timeout" id="session_timeout" type="text"size="20" class="formfld unknown" value="<?=htmlspecialchars($pconfig['session_timeout']);?>" />
                          <br />
                          <?=gettext("Time in minutes to expire idle management sessions.");?><br />
			</td>
                      </tr>

                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> <input id="submit" name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
                  </td>
                </tr>
              </table>
            </form>
            </div>
      </td>
    </tr>
  </table>

<?php include("fend.inc");?>
</body>
</html>
