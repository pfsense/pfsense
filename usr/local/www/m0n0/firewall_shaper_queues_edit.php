#!/usr/local/bin/php
<?php 
/*
	firewall_shaper_queues_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
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

$pgtitle = array("Firewall", "Traffic shaper", "Edit queue");
require("guiconfig.inc");

$a_queues = &$config['shaper']['queue'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_queues[$id]) {
	$pconfig['targetpipe'] = $a_queues[$id]['targetpipe'];
	$pconfig['weight'] = $a_queues[$id]['weight'];
	$pconfig['mask'] = $a_queues[$id]['mask'];
	$pconfig['descr'] = $a_queues[$id]['descr'];
}

if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "weight");
	$reqdfieldsn = explode(",", "Weight");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if ($_POST['weight'] && (!is_int($_POST['weight'])
			|| ($_POST['weight'] < 1) || ($_POST['weight'] > 100))) {
		$input_errors[] = "The weight must be an integer between 1 and 100.";
	}

	if (!$input_errors) {
		$queue = array();
		
		$queue['targetpipe'] = $_POST['targetpipe'];
		$queue['weight'] = $_POST['weight'];
		if ($_POST['mask'])
			$queue['mask'] = $_POST['mask'];
		$queue['descr'] = $_POST['descr'];
		
		if (isset($id) && $a_queues[$id])
			$a_queues[$id] = $queue;
		else
			$a_queues[] = $queue;
		
		write_config();
		touch($d_shaperconfdirty_path);
		
		header("Location: firewall_shaper_queues.php");
		exit;
	}
}
$pgtitle = "Firewall: Traffic Shaper: Queues Edit";
include("head.inc");

?>
<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (is_array($config['shaper']['pipe']) && (count($config['shaper']['pipe']) > 0)): ?>
            <form action="firewall_shaper_queues_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td valign="top" class="vncellreq">Pipe</td>
                  <td class="vtable"><select name="targetpipe" class="formselect">
                      <?php 
					  foreach ($config['shaper']['pipe'] as $pipei => $pipe): ?>
                      <option value="<?=$pipei;?>" <?php if ($pipei == $pconfig['targetpipe']) echo "selected"; ?>> 
                      <?php
					  	echo htmlspecialchars("Pipe " . ($pipei + 1));
						if ($pipe['descr'])
							echo htmlspecialchars(" (" . $pipe['descr'] . ")");
					  ?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    <span class="vexpl">Choose the pipe that this queue is linked 
                    to.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Weight</td>
                  <td width="78%" class="vtable"><?=$mandfldhtml;?><input name="weight" type="text" id="weight" size="5" value="<?=htmlspecialchars($pconfig['weight']);?>"> 
                    <br> <span class="vexpl">Valid range: 1..100.<br>
                    All backlogged (i.e., with packets queued) queues linked to 
                    the same pipe share the pipe's bandwidth proportionally to 
                    their weights (higher weight = higher share of bandwidth). 
                    Note that weights are not priorities; a queue with a lower 
                    weight is still guaranteed to get its fraction of the bandwidth 
                    even if a queue with a higher weight is permanently backlogged.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Mask</td>
                  <td width="78%" class="vtable"> <select name="mask" class="formselect">
                      <option value="" <?php if (!$pconfig['mask']) echo "selected"; ?>>none</option>
                      <option value="source" <?php if ($pconfig['mask'] == "source") echo "selected"; ?>>source</option>
                      <option value="destination" <?php if ($pconfig['mask'] == "destination") echo "selected"; ?>>destination</option>
                    </select> <br> <span class="vexpl">If 'source' or 'destination' 
                    is chosen, a dynamic queue associated with the pipe and with 
                    the weight given above will be created for each source/destination 
                    IP address encountered, respectively.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
                    <br> <span class="vexpl">You may enter a description here 
                    for your reference (not parsed).</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="Save"> 
                    <?php if (isset($id) && $a_queues[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>"> 
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
<?php else: ?>
<p><strong>You need to create a pipe before you can add a new queue.</strong></p>
<?php endif; ?>
</form>
<?php include("fend.inc"); ?>
</body>
</html>

