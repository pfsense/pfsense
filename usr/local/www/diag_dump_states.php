<?php
/*
    diag_dump_states.php
    Copyright (C) 2005 Scott Ullrich, Colin Smith
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

require_once("guiconfig.inc");

$pgtitle = "Diagnostics: Show States";
include("head.inc");

/* get our states */
if($_GET['filter']) {
	exec("/sbin/pfctl -ss | grep {$_GET['filter']}", $states);
} else {
	exec("/sbin/pfctl -ss", $states);
}

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("States", true, "diag_dump_states.php");
	$tab_array[1] = array("Reset States", false, "diag_resetstate.php");
	display_top_tabs($tab_array);
?>
</td></tr>

<tr><td>
        <div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td colspan="10">
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<form action="diag_dump_states.php" method="get" id="search">
						<tr>
							<td style="font-weight:bold;" width="50" align="right">Filter:&nbsp;
								
								<input name="filter" type="text" id="" value="<?=$_GET['filter'];?>" size="30" style="font-size:11px;">
								<input type="submit" class="formbtn" value="Filter">
								</form>
							<td>
						</tr>
					</table>
				</td>
			</tr>
		
		<tr>
			<td class="listhdrr" width="10%">Proto</td>
			<td class="listhdrr" width="65">Source -> Router -> Destination</td>
			<td class="listhdr" width="25%">State</td>
		</tr>
<?php
$state_counter = 0;
if(count($states) > 0) {
	foreach($states as $line) {
		$state_counter++;
		if($state_counter > 1000)
			break;
	
		$line_split = preg_split("/\s+/", $line);
		$state = array_pop($line_split);
		$type = array_shift($line_split);
		$proto = array_shift($line_split);
		$info = implode(" ", $line_split);

		$towrite = <<<EOD
		<tr valign="top">
		<td class="listlr">{$proto}</td>
		<td class="listr">{$info}</td>
		<td class="listr">{$state}</td>
		</tr>
EOD;
		print $towrite;
	}
} else {
	print '<tr><td colspan="4"><center>No states were found.</center></td></tr>';
}

?>
</table>
</div>
</center>
</td></tr>
</table>
<?php include("fend.inc"); ?>
<?php if($_GET['filter']): ?>
<meta http-equiv="refresh" content="60;url=diag_dump_states.php?filter=<?php echo $_GET['filter']; ?>">
<?php else: ?>
<meta http-equiv="refresh" content="60;url=diag_dump_states.php">
<?php endif; ?>

</body>
</html>
