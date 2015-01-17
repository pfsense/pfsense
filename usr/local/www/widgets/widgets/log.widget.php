<?php
/*
	log.widget.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

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

if(is_numeric($_POST['filterlogentries'])) {
	$config['widgets']['filterlogentries'] = $_POST['filterlogentries'];

	$acts = array();
	if ($_POST['actpass'])	$acts[] = "Pass";
	if ($_POST['actblock'])  $acts[] = "Block";
	if ($_POST['actreject']) $acts[] = "Reject";

	if (!empty($acts))
		$config['widgets']['filterlogentriesacts'] = implode(" ", $acts);
	else
		unset($config['widgets']['filterlogentriesacts']);
	unset($acts);

	if( ($_POST['filterlogentriesinterfaces']) and ($_POST['filterlogentriesinterfaces'] != "All") )
		$config['widgets']['filterlogentriesinterfaces'] = trim($_POST['filterlogentriesinterfaces']);
	else
		unset($config['widgets']['filterlogentriesinterfaces']);

	write_config("Saved Filter Log Entries via Dashboard");
	Header("Location: /");
	exit(0);
}

$nentries = isset($config['widgets']['filterlogentries']) ? $config['widgets']['filterlogentries'] : 5;

//set variables for log
$nentriesacts		= isset($config['widgets']['filterlogentriesacts'])		? $config['widgets']['filterlogentriesacts']		: 'All';
$nentriesinterfaces = isset($config['widgets']['filterlogentriesinterfaces']) ? $config['widgets']['filterlogentriesinterfaces'] : 'All';

$filterfieldsarray = array(
	"act" => $nentriesacts,
	"interface" => $nentriesinterfaces
);

$filter_logfile = "{$g['varlog_path']}/filter.log";
$filterlog = conv_log_filter($filter_logfile, $nentries, 50, $filterfieldsarray);		//Get log entries

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
	var rrText = "<?php echo gettext("Reverse Resolve with DNS"); ?>";

	if ( row[8] == '6' ) {
		srcIP = '[' + row[3] + ']';
		dstIP = '[' + row[5] + ']';
	} else {
		srcIP = row[3];
		dstIP = row[5];
	}

	if ( row[4] == '' )
		srcPort = '';
	else
		srcPort = ':' + row[4];
	if ( row[6] == '' )
		dstPort = '';
	else
		dstPort = ':' + row[6];

	var line = '<td class="listMRlr" align="center">' + row[0] + '</td>' +
		'<td class="listMRr ellipsis" title="' + row[1] + '">' + row[1].slice(0,-3) + '</td>' +
		'<td class="listMRr ellipsis" title="' + row[2] + '">' + row[2] + '</td>' +
		'<td class="listMRr ellipsis" title="' + srcIP + srcPort + '"><a href="diag_dns.php?host=' + row[3] + '" title="' + rrText + '">' + srcIP + '</a></td>' +
		'<td class="listMRr ellipsis" title="' + dstIP + dstPort + '"><a href="diag_dns.php?host=' + row[5] + '" title="' + rrText + '">' + dstIP + '</a>' + dstPort + '</td>';

	var nentriesacts = "<?php echo $nentriesacts; ?>";
	var nentriesinterfaces = "<?php echo $nentriesinterfaces; ?>";

	var Action = row[0].match(/alt=.*?(pass|block|reject)/i).join("").match(/pass|block|reject/i).join("");
	var Interface = row[2];

	if ( !(in_arrayi(Action,	nentriesacts.replace		(/\s+/g, ',').split(',') ) ) && (nentriesacts != 'All') )			return false;
	if ( !(in_arrayi(Interface,	nentriesinterfaces.replace(/\s+/g, ',').split(',') ) ) && (nentriesinterfaces != 'All') )	return false;

	return line;
}
//]]>
</script>
<script src="/javascript/filter_log.js" type="text/javascript"></script>

<table class="table table-striped">
	<thead>
		<tr>
			<th><?=gettext("Act");?></th>
			<th><?=gettext("Time");?></th>
			<th><?=gettext("IF");?></th>
			<th><?=gettext("Source");?></th>
			<th><?=gettext("Destination");?></th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach ($filterlog as $filterent):
		if ($filterent['version'] == '6') {
			$srcIP = "[" . htmlspecialchars($filterent['srcip']) . "]";
			$dstIP = "[" . htmlspecialchars($filterent['dstip']) . "]";
		} else {
			$srcIP = htmlspecialchars($filterent['srcip']);
			$dstIP = htmlspecialchars($filterent['dstip']);
		}

		if ($filterent['srcport'])
			$srcPort = ":" . htmlspecialchars($filterent['srcport']);
		else
			$srcPort = "";

		if ($filterent['dstport'])
			$dstPort = ":" . htmlspecialchars($filterent['dstport']);
		else
			$dstPort = "";

		if ($filterent['act'] == "block")
			$iconfn = "remove";
		else if ($filterent['act'] == "reject")
			$iconfn = "fire";
		else if ($filterent['act'] == "match")
			$iconfn = "filter";
		else
			$iconfn = "ok";

		$rule = find_rule_by_number($filterent['rulenum'], $filterent['tracker'], $filterent['act']);
?>
		<tr>
			<td>
				<a role="button" data-toggle="popover" data-trigger="hover" data-title="Rule that triggered this action" data-content="<?=htmlspecialchars($rule)?>">
					<i class="icon icon-<?=$iconfn?>"></i>
				</a>
			</td>
			<td><?=substr(htmlspecialchars($filterent['time']),0,-3)?></td>
			<td><?=htmlspecialchars($filterent['interface']);?></td>
			<td>
				<a href="diag_dns.php?host=<?=$filterent['srcip']?>" title="<?=gettext("Reverse Resolve with DNS")?>">
					<?=$srcIP?>
				</a>:<?=$srcPort?>
			</td>
			<td>
				<a href="diag_dns.php?host=<?=$filterent['dstip']?>" title="<?=gettext("Reverse Resolve with DNS");?>">
					<?=$dstIP?>
				</a>:<?=$dstPort?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div class="panel-footer collapse">

<form action="/widgets/widgets/log.widget.php" method="post">
	Number of lines to display:
	<select name="filterlogentries" class="formfld unknown" id="filterlogentries">
	<?php for ($i = 1; $i <= 20; $i++) { ?>
		<option value="<?php echo $i;?>" <?php if ($nentries == $i) echo "selected=\"selected\"";?>><?php echo $i;?></option>
	<?php } ?>
	</select>

<?php
	$Include_Act = explode(" ", $nentriesacts);
	if ($nentriesinterfaces == "All") $nentriesinterfaces = "";
?>
	<input id="actpass"	name="actpass"	type="checkbox" value="Pass"	<?php if (in_arrayi('Pass',	$Include_Act)) echo "checked=\"checked\""; ?> /> Pass
	<input id="actblock"  name="actblock"  type="checkbox" value="Block"  <?php if (in_arrayi('Block',  $Include_Act)) echo "checked=\"checked\""; ?> /> Block
	<input id="actreject" name="actreject" type="checkbox" value="Reject" <?php if (in_arrayi('Reject', $Include_Act)) echo "checked=\"checked\""; ?> /> Reject
	<br />
	Interfaces:
	<select id="filterlogentriesinterfaces" name="filterlogentriesinterfaces" class="formselect">
		<option value="All">ALL</option>
<?php
	$interfaces = get_configured_interface_with_descr();
	foreach ($interfaces as $iface => $ifacename):
?>
		<option value="<?=$iface;?>" <?php if ($nentriesinterfaces == $iface) echo "selected=\"selected\"";?>>
			<?=htmlspecialchars($ifacename);?>
		</option>
<?php
	endforeach;
	unset($interfaces);
	unset($Include_Act);
?>
	</select>

	<input id="submita" name="submita" type="submit" class="formbtn" value="Save" />
</form>