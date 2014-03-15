<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (https://www.pfsense.org)
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

	write_config("Saved Filter Log Entries via Dashboard");
  $filename = $_SERVER['HTTP_REFERER'];
  if(headers_sent($file, $line)){
    echo '<script type="text/javascript">';
    echo '//<![CDATA[';
    echo 'window.location.href="'.$filename.'";';
    echo '//]]>';
    echo '</script>';
    echo '<noscript>';
    echo '<meta http-equiv="refresh" content="0;url='.$filename.'" />';
    echo '</noscript>';
  }
	Header("Location: /");
}

$nentries = isset($config['widgets']['filterlogentries']) ? $config['widgets']['filterlogentries'] : 5;

//set variables for log

$nentriesacts       = isset($config['widgets']['filterlogentriesacts'])       ? $config['widgets']['filterlogentriesacts']       : 'All';
$nentriesinterfaces = isset($config['widgets']['filterlogentriesinterfaces']) ? $config['widgets']['filterlogentriesinterfaces'] : 'All';

$filterfieldsarray = array("act", "interface");
$filterfieldsarray['act'] = $nentriesacts;
$filterfieldsarray['interface'] = $nentriesinterfaces;

$filter_logfile = "{$g['varlog_path']}/filter.log";
$filterlog = conv_log_filter($filter_logfile, $nentries, 50, $filterfieldsarray);        //Get log entries

/* AJAX related routines */
handle_ajax($nentries, $nentries + 20);

?>

<script type="text/javascript">
//<![CDATA[
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
	var line = '<td class="listMRlr" align="center">' + row[0] + '<\/td>' +
		'<td class="listMRr ellipsis" title="' + row[1] + '">' + row[1].slice(0,-3) + '<\/td>' +
		'<td class="listMRr ellipsis" title="' + row[2] + '">' + row[2] + '<\/td>' +
		'<td class="listMRr ellipsis" title="' + row[3] + '">' + row[3] + '<\/td>' +
 		'<td class="listMRr ellipsis" title="' + row[4] + '">' + row[4] + '<\/td>';

	var nentriesacts = "<?php echo $nentriesacts; ?>";
	var nentriesinterfaces = "<?php echo $nentriesinterfaces; ?>";

	var Action = row[0].match(/alt=.*?(pass|block|reject)/i).join("").match(/pass|block|reject/i).join("");
	var Interface = row[2];

	if ( !(in_arrayi(Action,	nentriesacts.replace      (/\s+/g, ',').split(',') ) ) && (nentriesacts != 'All') )			return false;
	if ( !(in_arrayi(Interface,	nentriesinterfaces.replace(/\s+/g, ',').split(',') ) ) && (nentriesinterfaces != 'All') )	return false;

	return line;
}
//]]>
</script>
<script src="/javascript/filter_log.js" type="text/javascript"></script>
<input type="hidden" id="log-config" name="log-config" value="" />

<div id="log-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/log.widget.php" method="post" name="iforma">
		Number of lines to display:
		<select name="filterlogentries" class="formfld unknown" id="filterlogentries">
		<?php for ($i = 1; $i <= 20; $i++) { ?>
			<option value="<?php echo $i;?>" <?php if ($nentries == $i) echo "SELECTED";?>><?php echo $i;?></option>
		<?php } ?>
		</select>

<?php
		$Include_Act = explode(",", str_replace(" ", ",", $nentriesacts));
		if ($nentriesinterfaces == "All") $nentriesinterfaces = "";
?>
		<input id="actpass"   name="actpass"   type="checkbox" value="Pass"   <?php if (in_arrayi('Pass',   $Include_Act)) echo "checked=\"checked\""; ?> /> Pass
		<input id="actblock"  name="actblock"  type="checkbox" value="Block"  <?php if (in_arrayi('Block',  $Include_Act)) echo "checked=\"checked\""; ?> /> Block
		<input id="actreject" name="actreject" type="checkbox" value="Reject" <?php if (in_arrayi('Reject', $Include_Act)) echo "checked=\"checked\""; ?> /> Reject
		<br />
		Interfaces:
		<select id="filterlogentriesinterfaces" name="filterlogentriesinterfaces" class="formselect">
			<option value="All">ALL</option>
                      <?php
						$interfaces = get_configured_interface_with_descr();
					  	foreach ($interfaces as $iface => $ifacename): ?>
                        <option value="<?=$iface;?>" <?php if ($nentriesinterfaces == $iface) echo "selected='selected'";?>>
                      <?=htmlspecialchars($ifacename);?>
                      </option>
                      <?php endforeach; ?>
                    </select>

		<input id="submita" name="submita" type="submit" class="formbtn" value="Save" />
	</form>
</div>

<table width="100%" border="0" cellpadding="0" cellspacing="0" style="table-layout: fixed;" summary="logs">
	<colgroup>
		<col style='width:  7%;' />
		<col style='width: 23%;' />
		<col style='width: 11%;' />
		<col style='width: 28%;' />
		<col style='width: 31%;' />
	</colgroup>
	<thead>
		<tr>
			<td class="listhdrr"><?=gettext("Act");?></td>
			<td class="listhdrr"><?=gettext("Time");?></td>
			<td class="listhdrr"><?=gettext("IF");?></td>
			<td class="listhdrr"><?=gettext("Source");?></td>
			<td class="listhdrr"><?=gettext("Destination");?></td>
		</tr>
	</thead>
	<tbody id='filter-log-entries'>
	<?php
	$rowIndex = 0;
	foreach ($filterlog as $filterent):
	$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
	$rowIndex++;
	?>
		<tr class="<?=$evenRowClass?>">
			<td class="listMRlr" nowrap="nowrap" align="center">
			<a href="#" onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);">
			<img border="0" src="<?php echo find_action_image($filterent['act']);?>" width="11" height="11" alt="<?php echo $filterent['act'];?>" title="<?php echo $filterent['act'];?>" />
			</a>
			</td>
			<td class="listMRr ellipsis nowrap" title="<?php echo htmlspecialchars($filterent['time']);?>"><?php echo substr(htmlspecialchars($filterent['time']),0,-3);?></td>
			<td class="listMRr ellipsis nowrap" title="<?php echo htmlspecialchars($filterent['interface']);?>"><?php echo htmlspecialchars($filterent['interface']);?></td>
			<td class="listMRr ellipsis nowrap" title="<?php echo htmlspecialchars($filterent['src']);?>">
				<a href="#" onclick="javascript:getURL('diag_dns.php?host=<?php echo "{$filterent['srcip']}"; ?>&dialog_output=true', outputrule);" title="<?=gettext("Reverse Resolve with DNS");?>">
				<?php echo htmlspecialchars($filterent['srcip']);?></a></td>
			<td class="listMRr ellipsis nowrap" title="<?php echo htmlspecialchars($filterent['dst']);?>">
				<a href="#" onclick="javascript:getURL('diag_dns.php?host=<?php echo "{$filterent['dstip']}"; ?>&dialog_output=true', outputrule);" title="<?=gettext("Reverse Resolve with DNS");?>">
				<?php echo htmlspecialchars($filterent['dstip']);?></a><?php echo ":" . htmlspecialchars($filterent['dstport']);?></td>
			<?php
				if ($filterent['proto'] == "TCP")
					$filterent['proto'] .= ":{$filterent['tcpflags']}";
			?>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<!-- needed to display the widget settings menu -->
<script type="text/javascript">
//<![CDATA[
	selectIntLink = "log-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
//]]>
</script>
