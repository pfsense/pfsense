<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (www.pfsense.com)
        originally based on m0n0wall (http://m0n0.ch/wall)

        Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
        and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

/* In an effort to reduce duplicate code, many shared functions have been moved here. */
require_once("filter_log.inc");

if($_POST['filterlogentries']) {
// Passed / Blocked / Rejected Interfaces Filter Selection - Added 20120619
	unset($config['widgets']['filterlogentries']);
	if( ($_POST['filterlogentries']) and ($_POST['filterlogentries'] != ' ') ) $config['widgets']['filterlogentries'] = $_POST['filterlogentries'];

	unset($config['widgets']['filterlogentriesacts']);
	if($_POST['actpass'])   $config['widgets']['filterlogentriesacts'] .= $_POST['actpass']   . " ";
	if($_POST['actblock'])  $config['widgets']['filterlogentriesacts'] .= $_POST['actblock']  . " ";
	if($_POST['actreject']) $config['widgets']['filterlogentriesacts'] .= $_POST['actreject'] . " ";
	if (isset($config['widgets']['filterlogentriesacts'])) $config['widgets']['filterlogentriesacts'] = trim($config['widgets']['filterlogentriesacts']);

	unset($config['widgets']['filterlogentriesinterfaces']);
	if( ($_POST['filterlogentriesinterfaces']) and ($_POST['filterlogentriesinterfaces'] != "All") ) $config['widgets']['filterlogentriesinterfaces'] = $_POST['filterlogentriesinterfaces'];
	if (isset($config['widgets']['filterlogentriesinterfaces'])) $config['widgets']['filterlogentriesinterfaces'] = trim($config['widgets']['filterlogentriesinterfaces']);
// Passed / Blocked / Rejected Interfaces Filter Selection - Added 20120619

//	$config['widgets']['filterlogentries'] = $_POST['filterlogentries'];
	write_config("Saved Filter Log Entries via Dashboard");
  $filename = $_SERVER['HTTP_REFERER'];
  if(headers_sent($file, $line)){
    echo '<script type="text/javascript">';
    echo 'window.location.href="'.$filename.'";';
    echo '</script>';
    echo '<noscript>';
    echo '<meta http-equiv="refresh" content="0;url='.$filename.'" />';
    echo '</noscript>';  
  } 
	Header("Location: /");
}

$nentries = isset($config['widgets']['filterlogentries']) ? $config['widgets']['filterlogentries'] : 5;

//set variables for log
//$filter_logfile = "{$g['varlog_path']}/filter.log";
//$filterlog = conv_log_filter($filter_logfile, $nentries);

// Passed / Blocked / Rejected Interfaces Filter Selection - Added 20120619
$nentriesacts       = isset($config['widgets']['filterlogentriesacts'])       ? $config['widgets']['filterlogentriesacts']       : 'All';
$nentriesinterfaces = isset($config['widgets']['filterlogentriesinterfaces']) ? $config['widgets']['filterlogentriesinterfaces'] : 'All';

$filterfieldsarray = array("act", "interface");
$filterfieldsarray['act'] = $nentriesacts;
$filterfieldsarray['interface'] = $nentriesinterfaces;

$filter_logfile = "{$g['varlog_path']}/filter.log";
$filterlog = conv_log_filter($filter_logfile, $nentries, 50, $filterfieldsarray);        //Get        log entries
// Passed / Blocked / Rejected Interfaces Filter Selection - Added 20120619

/* AJAX related routines */
handle_ajax($nentries, $nentries + 20);

?>

<script language="javascript">
lastsawtime = '<?php echo time(); ?>';
var lines = Array();
var timer;
var updateDelay = 30000;
var isBusy = false;
var isPaused = false;
var nentries = <?php echo $nentries; ?>;

<?php
if(isset($config['syslog']['reverse']))
	echo "var isReverse = true;\n";
else
	echo "var isReverse = false;\n";
?>

/* Called by the AJAX updater */
function format_log_line(row) {
	var line = '';
	line = '  <span class="log-action-mini" nowrap>&nbsp;' + row[0] + '&nbsp;</span>';
	line += '  <span class="log-interface-mini" nowrap>' + row[2] + '</span>';
	line += '  <span class="log-source-mini" nowrap>' + row[3] + '</span>';
	line += '  <span class="log-destination-mini" nowrap>' + row[4] + '</span>';
	line += '  <span class="log-protocol-mini" nowrap>' + row[5] + '</span>';

	var nentriesacts = "<?php echo $nentriesacts; ?>";
	var nentriesinterfaces = "<?php echo $nentriesinterfaces; ?>";

	var Action = row[0].match(/alt=.*?(pass|block|reject)/i).join("").match(/pass|block|reject/i).join("");
	var Interface = row[2];

	if ( !(in_arrayi(Action,	nentriesacts.replace      (/\s+/g, ',').split(',') ) ) && (nentriesacts != 'All') )			return false;
	if ( !(in_arrayi(Interface,	nentriesinterfaces.replace(/\s+/g, ',').split(',') ) ) && (nentriesinterfaces != 'All') )	return false;

	return line;
}
</script>
<script src="/javascript/filter_log.js" type="text/javascript"></script>
<input type="hidden" id="log-config" name="log-config" value="">

<div id="log-settings" name="log-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/log.widget.php" method="post" name="iforma">
		Number of lines to display: 
		<select name="filterlogentries" class="formfld unknown" id="filterlogentries">
		<?php for ($i = 0; $i <= 20; $i++) { ?>
			<option value="<?php if ($i > 0) echo $i; else echo ' ';?>" <?php if ($nentries == $i) echo "SELECTED";?>><?php if ($i > 0) echo ' ' . $i; else echo ' ';?></option>
		<?php } ?>
		</select>

<!-- // Passed / Blocked / Rejected Interfaces Filter Selection - Added 20120619 -->
<?php 
		$Include_Act = explode(",", str_replace(" ", ",", $nentriesacts));
		if ($nentriesinterfaces == "All") $nentriesinterfaces = "";
?>
		<input id="actpass"   name="actpass"   type="checkbox" value="Pass"   <?php if (in_arrayi('Pass',   $Include_Act)) echo "checked"; ?> /> Pass
		<input id="actblock"  name="actblock"  type="checkbox" value="Block"  <?php if (in_arrayi('Block',  $Include_Act)) echo "checked"; ?> /> Block
		<input id="actreject" name="actreject" type="checkbox" value="Reject" <?php if (in_arrayi('Reject', $Include_Act)) echo "checked"; ?> /> Reject
		<br/>
		Interfaces: 
		<input id="filterlogentriesinterfaces" name="filterlogentriesinterfaces" class="formfld unknown" type="text" size="20" class="formfld unknown" value="<?= $nentriesinterfaces ?>" />
        &nbsp &nbsp &nbsp 
<!-- // Passed / Blocked / Rejected Interfaces Filter Selection - Added 20120619 -->

		<input id="submita" name="submita" type="submit" class="formbtn" value="Save" />
	</form>
</div>

<div class="log-header">
    <span class="log-action-mini-header">Act</span>
    <span class="log-interface-mini-header">IF</span>
    <span class="log-source-mini-header">Source</span>
    <span class="log-destination-mini-header">Destination</span>
    <span class="log-protocol-mini-header">Prot</span>
</div>
<?php $counter=0; foreach ($filterlog as $filterent): ?>
<div class="log-entry-mini" <?php echo is_first_row($counter, count($filterlog)); ?> style="clear:both;">
	<span class="log-action-mini" nowrap>
	&nbsp;<a href="#" onClick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);"><img border="0" src="<?php echo find_action_image($filterent['act']);?>" alt="<?php echo $filterent['act'];?>" title="<?php echo $filterent['act'];?>" /></a>&nbsp;</span>
	<span class="log-interface-mini"><?php echo htmlspecialchars($filterent['interface']);?>&nbsp;</span>
	<span class="log-source-mini"><?php echo htmlspecialchars($filterent['src']);?>&nbsp;</span>
	<span class="log-destination-mini"><?php echo htmlspecialchars($filterent['dst']);?>&nbsp;</span>
	<?php
	if ($filterent['proto'] == "TCP")
		$filterent['proto'] .= ":{$filterent['tcpflags']}";
	?>
	<span class="log-protocol-mini"><?php echo htmlspecialchars($filterent['proto']);?>&nbsp;</span>
</div>
<?php $counter++; endforeach; ?>

<!-- needed to display the widget settings menu -->
<script language="javascript" type="text/javascript">
	selectIntLink = "log-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
</script>
