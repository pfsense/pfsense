<?php
/*
	services_captiveportal_filemanager.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2005-2006 Jonathan De Graeve (jonathan.de.graeve@imelda.be)
	and Paul Taylor (paultaylor@winn-dixie.com).
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
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-filemanager
##|*NAME=Services: Captive portal: File Manager page
##|*DESCR=Allow access to the 'Services: Captive portal: File Manager' page.
##|*MATCH=services_captiveportal_filemanager.php*
##|-PRIV

function cpelementscmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function cpelements_sort() {
        global $config, $cpzone;

        usort($config['captiveportal'][$cpzone]['element'],"cpelementscmp");
}

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
        $cpzone = $_POST['zone'];
                        
if (empty($cpzone)) {
        header("Location: services_captiveportal_zones.php");
        exit;
}

if (!is_array($config['captiveportal']))
        $config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"),gettext("Captive portal"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal";

if (!is_array($a_cp[$cpzone]['element']))
	$a_cp[$cpzone]['element'] = array();
$a_element =& $a_cp[$cpzone]['element'];

// Calculate total size of all files
$total_size = 0;
foreach ($a_element as $element) {
	$total_size += $element['size'];
}

if ($_POST) {
    unset($input_errors);

    if (is_uploaded_file($_FILES['new']['tmp_name'])) {

    	if(!stristr($_FILES['new']['name'], "captiveportal-"))
    		$name = "captiveportal-" . $_FILES['new']['name'];
    	else
    		$name = $_FILES['new']['name'];
    	$size = filesize($_FILES['new']['tmp_name']);

    	// is there already a file with that name?
    	foreach ($a_element as $element) {
			if ($element['name'] == $name) {
				$input_errors[] = sprintf(gettext("A file with the name '%s' already exists."), $name);
				break;
			}
		}

		// check total file size
		if (($total_size + $size) > $g['captiveportal_element_sizelimit']) {
			$input_errors[] = gettext("The total size of all files uploaded may not exceed ") .
				format_bytes($g['captiveportal_element_sizelimit']) . ".";
		}

		if (!$input_errors) {
			$element = array();
			$element['name'] = $name;
			$element['size'] = $size;
			$element['content'] = base64_encode(file_get_contents($_FILES['new']['tmp_name']));

			$a_element[] = $element;
			cpelements_sort();

			write_config();
			captiveportal_write_elements();
			header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
			exit;
		}
    }
} else if (($_GET['act'] == "del") && !empty($cpzone) && $a_element[$_GET['id']]) {
	conf_mount_rw();
	@unlink("{$g['captiveportal_element_path']}/" . $a_element[$_GET['id']]['name']);
	@unlink("{$g['captiveportal_path']}/" . $a_element[$_GET['id']]['name']);
	conf_mount_ro();
	unset($a_element[$_GET['id']]);
	write_config();
	header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
	exit;
}

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_captiveportal_filemanager.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input type="hidden" name="zone" id="zone" value="<?=htmlspecialchars($cpzone);?>" />
<?php if ($input_errors) print_input_errors($input_errors); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="captiveportal file manager">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Captive portal(s)"), false, "services_captiveportal.php?zone={$cpzone}");
	$tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Allowed IP addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
	$tab_array[] = array(gettext("File Manager"), true, "services_captiveportal_filemanager.php?zone={$cpzone}");
	display_top_tabs($tab_array, true);
?>  </td></tr>
  <tr>
    <td class="tabcont">
	<table width="80%" border="0" cellpadding="0" cellspacing="0" summary="main">
      <tr>
        <td width="70%" class="listhdrr"><?=gettext("Name"); ?></td>
        <td width="20%" class="listhdr"><?=gettext("Size"); ?></td>
        <td width="10%" class="list">
		<table border="0" cellspacing="0" cellpadding="1" summary="icons">
		    <tr>
			<td width="17" height="17"></td>
			<td><a href="services_captiveportal_filemanager.php?zone=<?=$cpzone;?>&amp;act=add"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add file"); ?>" width="17" height="17" border="0" alt="add" /></a></td>
		    </tr>
		</table>
	</td>
      </tr>
<?php if (is_array($a_cp[$cpzone]['element'])):
	$i = 0; foreach ($a_cp[$cpzone]['element'] as $element): ?>
  	  <tr>
		<td class="listlr"><?=htmlspecialchars($element['name']);?></td>
		<td class="listr" align="right"><?=format_bytes($element['size']);?></td>
		<td valign="middle" class="list nowrap">
		<a href="services_captiveportal_filemanager.php?zone=<?=$cpzone;?>&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this file?"); ?>')"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete file"); ?>" width="17" height="17" border="0" alt="delete" /></a>
		</td>
	  </tr>
  <?php $i++; endforeach; endif; ?>

  <?php if ($total_size > 0): ?>
  	  <tr>
		<td class="listlr" style="background-color: #eee"><strong><?=gettext("TOTAL"); ?></strong></td>
		<td class="listr" style="background-color: #eee" align="right"><strong><?=format_bytes($total_size);?></strong></td>
		<td valign="middle" class="list nowrap"></td>
	  </tr>
  <?php endif; ?>

  <?php if ($_GET['act'] == 'add'): ?>
	  <tr>
		<td class="listlr" colspan="2"><input type="file" name="new" class="formfld file" size="40" id="new" />
		<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Upload"); ?>" /></td>
		<td valign="middle" class="list nowrap">
		<a href="services_captiveportal_filemanager.php?zone=<?=$cpzone;?>"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("cancel"); ?>" width="17" height="17" border="0" alt="delete" /></a>
		</td>
	  </tr>
  <?php else: ?>
	  <tr>
		<td class="list" colspan="2"></td>
		<td class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="add">
			    <tr>
				<td width="17" height="17"></td>
				<td><a href="services_captiveportal_filemanager.php?zone=<?=$cpzone;?>&amp;act=add"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add file"); ?>" width="17" height="17" border="0" alt="add" /></a></td>
			    </tr>
			</table>
		</td>
	  </tr>
  <?php endif; ?>
	</table>
	<span class="vexpl"><span class="red"><strong>
	<?=gettext("Note:"); ?><br />
	</strong></span>
	<?=gettext("Any files that you upload here with the filename prefix of captiveportal- will " .
	"be made available in the root directory of the captive portal HTTP(S) server. " .
	"You may reference them directly from your portal page HTML code using relative paths. " .
	"Example: you've uploaded an image with the name 'captiveportal-test.jpg' using the " .
	"file manager. Then you can include it in your portal page like this:"); ?><br /><br />
	<tt>&lt;img src=&quot;captiveportal-test.jpg&quot; width=... height=...&gt;</tt>
	<br /><br />
	<?=gettext("In addition, you can also upload .php files for execution.  You can pass the filename " .
	"to your custom page from the initial page by using text similar to:"); ?>
	<br /><br />
	<tt>&lt;a href="/captiveportal-aup.php?zone=$PORTAL_ZONE$&amp;redirurl=$PORTAL_REDIRURL$"&gt;<?=gettext("Acceptable usage policy"); ?>&lt;/a&gt;</tt>
	<br /><br />
	<?php printf(gettext("The total size limit for all files is %s."), format_bytes($g['captiveportal_element_sizelimit']));?></span>
</td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
