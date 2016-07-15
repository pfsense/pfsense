<?php
/*
 * system_user_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

##|+PRIV
##|*IDENT=page-system-user-settings
##|*NAME=System: User Settings
##|*DESCR=Allow access to the 'System: User Settings' page.
##|*MATCH=system_user_settings.php*
##|-PRIV

 require_once("auth.inc");
 require_once("guiconfig.inc");

$pgtitle = array(gettext("System"), gettext("User Settings"));

$a_user = &$config['system']['user'];

if (isset($_SESSION['Username']) && isset($userindex[$_SESSION['Username']])) {
	$id = $userindex[$_SESSION['Username']];
}

if (isset($id) && $a_user[$id]) {
	$pconfig['webguicss'] = $a_user[$id]['webguicss'];
	$pconfig['webguifixedmenu'] = $a_user[$id]['webguifixedmenu'];
	$pconfig['webguihostnamemenu'] = $a_user[$id]['webguihostnamemenu'];
	$pconfig['dashboardcolumns'] = $a_user[$id]['dashboardcolumns'];
	$pconfig['dashboardavailablewidgetspanel'] = isset($a_user[$id]['dashboardavailablewidgetspanel']);
	$pconfig['systemlogsfilterpanel'] = isset($a_user[$id]['systemlogsfilterpanel']);
	$pconfig['systemlogsmanagelogpanel'] = isset($a_user[$id]['systemlogsmanagelogpanel']);
	$pconfig['statusmonitoringsettingspanel'] = isset($a_user[$id]['statusmonitoringsettingspanel']);
	$pconfig['webguileftcolumnhyper'] = isset($a_user[$id]['webguileftcolumnhyper']);
	$pconfig['pagenamefirst'] = isset($a_user[$id]['pagenamefirst']);
} else {
	echo gettext("The settings cannot be managed for a non-local user.");
	include("foot.inc");
	exit;
}

if (isset($_POST['save'])) {
	unset($input_errors);
	/* input validation */

	$reqdfields = explode(" ", "webguicss dashboardcolumns");
	$reqdfieldsn = array(gettext("Theme"), gettext("Dashboard Columns"));
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	$userent = $a_user[$id];

	if (!$input_errors) {
		$pconfig['webguicss'] = $userent['webguicss'] = $_POST['webguicss'];

		if ($_POST['webguifixedmenu']) {
			$pconfig['webguifixedmenu'] = $userent['webguifixedmenu'] = $_POST['webguifixedmenu'];
		} else {
			$pconfig['webguifixedmenu'] = "";
			unset($userent['webguifixedmenu']);
		}

		if ($_POST['webguihostnamemenu']) {
			$pconfig['webguihostnamemenu'] = $userent['webguihostnamemenu'] = $_POST['webguihostnamemenu'];
		} else {
			$pconfig['webguihostnamemenu'] = "";
			unset($userent['webguihostnamemenu']);
		}

		$pconfig['dashboardcolumns'] = $userent['dashboardcolumns'] = $_POST['dashboardcolumns'];

		if ($_POST['dashboardavailablewidgetspanel']) {
			$pconfig['dashboardavailablewidgetspanel'] = $userent['dashboardavailablewidgetspanel'] = true;
		} else {
			$pconfig['dashboardavailablewidgetspanel'] = false;
			unset($userent['dashboardavailablewidgetspanel']);
		}

		if ($_POST['systemlogsfilterpanel']) {
			$pconfig['systemlogsfilterpanel'] = $userent['systemlogsfilterpanel'] = true;
		} else {
			$pconfig['systemlogsfilterpanel'] = false;
			unset($userent['systemlogsfilterpanel']);
		}

		if ($_POST['systemlogsmanagelogpanel']) {
			$pconfig['systemlogsmanagelogpanel'] = $userent['systemlogsmanagelogpanel'] = true;
		} else {
			$pconfig['systemlogsmanagelogpanel'] = false;
			unset($userent['systemlogsmanagelogpanel']);
		}

		if ($_POST['statusmonitoringsettingspanel']) {
			$pconfig['statusmonitoringsettingspanel'] = $userent['statusmonitoringsettingspanel'] = true;
		} else {
			$pconfig['statusmonitoringsettingspanel'] = false;
			unset($userent['statusmonitoringsettingspanel']);
		}

		if ($_POST['webguileftcolumnhyper']) {
			$pconfig['webguileftcolumnhyper'] = $userent['webguileftcolumnhyper'] = true;
		} else {
			$pconfig['webguileftcolumnhyper'] = false;
			unset($userent['webguileftcolumnhyper']);
		}

		if ($_POST['pagenamefirst']) {
			$pconfig['pagenamefirst'] = $userent['pagenamefirst'] = true;
		} else {
			$pconfig['pagenamefirst'] = false;
			unset($userent['pagenamefirst']);
		}

		$a_user[$id] = $userent;
		$savemsg = sprintf(gettext("User settings successfully changed for user %s."), $_SESSION['Username']);
		write_config($savemsg);
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form();

$section = new Form_Section('User Settings for ' . $_SESSION['Username']);

gen_user_settings_fields($section, $pconfig);

$form->add($section);
print($form);
$csswarning = sprintf(gettext("%sUser-created themes are unsupported, use at your own risk."), "<br />");
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Handle displaying a warning message if a user-created theme is selected.
	function setThemeWarning() {
		if ($('#webguicss').val().startsWith("pfSense")) {
			$('#csstxt').html("").addClass("text-default");
		} else {
			$('#csstxt').html("<?=$csswarning?>").addClass("text-danger");
		}
	}

	$('#webguicss').change(function() {
		setThemeWarning();
	});

	// ---------- On initial page load ------------------------------------------------------------
	setThemeWarning();

});
//]]>
</script>
<?php
include("foot.inc");
