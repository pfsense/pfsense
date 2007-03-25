#!/usr/local/bin/php
<?php 
/*
	firewall_shaper_pipes_edit.php
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

$pgtitle = array("Firewall", "Traffic shaper", "Edit pipe");
require("guiconfig.inc");

$a_pipes = &$config['shaper']['pipe'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];
	
if (isset($id) && $a_pipes[$id]) {
	$pconfig['bandwidth'] = $a_pipes[$id]['bandwidth'];
	$pconfig['delay'] = $a_pipes[$id]['delay'];
	$pconfig['plr'] = $a_pipes[$id]['plr'];
	$pconfig['qsize'] = $a_pipes[$id]['qsize'];
	$pconfig['mask'] = $a_pipes[$id]['mask'];
	$pconfig['descr'] = $a_pipes[$id]['descr'];
}

if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "bandwidth");
	$reqdfieldsn = explode(",", "Bandwidth");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['bandwidth'] && !is_int($_POST['bandwidth']))) {
		$input_errors[] = "The bandwidth must be an integer.";
	}
	if (($_POST['delay'] && !is_int($_POST['delay']))) {
		$input_errors[] = "The delay must be an integer.";
	}
	if ($_POST['plr'] && (!is_numeric($_POST['plr']) || $_POST['plr'] < 0 || $_POST['plr'] > 1)) {
		$input_errors[] = "The packet loss rate must be a number between 0 and 1.";
	}
	if ($_POST['qsize'] && (!is_int($_POST['qsize']) || $_POST['qsize'] < 2 || $_POST['qsize'] > 100)) {
		$input_errors[] = "The queue size must be an integer between 2 and 100.";
	}

	if (!$input_errors) {
		$pipe = array();
		
		$pipe['bandwidth'] = $_POST['bandwidth'];
		if ($_POST['delay'])
			$pipe['delay'] = $_POST['delay'];
		if ($_POST['plr'])
			$pipe['plr'] = $_POST['plr'];
		if ($_POST['qsize'])
			$pipe['qsize'] = $_POST['qsize'];
		if ($_POST['mask'])
			$pipe['mask'] = $_POST['mask'];
		$pipe['descr'] = $_POST['descr'];
		
		if (isset($id) && $a_pipes[$id])
			$a_pipes[$id] = $pipe;
		else
			$a_pipes[] = $pipe;
		
		write_config();
		touch($d_shaperconfdirty_path);
		
		header("Location: firewall_shaper_pipes.php");
		exit;
	}
}

$pgtitle = "Firewall: Traffic Shaper - Pipes Edit";
include("head.inc");
?>
<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_shaper_pipes_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Bandwidth</td>
                  <td width="78%" class="vtable"><?=$mandfldhtml;?><input name="bandwidth" type="text" id="bandwidth" size="5" value="<?=htmlspecialchars($pconfig['bandwidth']);?>"> 
                    &nbsp;Kbit/s</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Delay</td>
                  <td width="78%" class="vtable"> <input name="delay" type="text" id="delay" size="5" value="<?=htmlspecialchars($pconfig['delay']);?>"> 
                    &nbsp;ms<br> <span class="vexpl">Hint: in most cases, you 
                    should specify 0 here (or leave the field empty)</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Packet loss rate</td>
                  <td width="78%" class="vtable"> <input name="plr" type="text" id="plr" size="5" value="<?=htmlspecialchars($pconfig['plr']);?>"> 
                    <br> <span class="vexpl">Hint: in most cases, you 
                    should specify 0 here (or leave the field empty). A value of 0.001 means one packet in 1000 gets dropped.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Queue size</td>
                  <td width="78%" class="vtable"> <input name="qsize" type="text" id="qsize" size="8" value="<?=htmlspecialchars($pconfig['qsize']);?>"> 
                    &nbsp;slots<br> 
                    <span class="vexpl">Hint: in most cases, you 
                    should leave the field empty. All packets in this pipe are placed into a fixed-size queue first,
                    then they are delayed by value specified in the Delay field, and then they are delivered to their destination.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Mask</td>
                  <td width="78%" class="vtable"> <select name="mask" class="formselect">
                      <option value="" <?php if (!$pconfig['mask']) echo "selected"; ?>>none</option>
                      <option value="source" <?php if ($pconfig['mask'] == "source") echo "selected"; ?>>source</option>
                      <option value="destination" <?php if ($pconfig['mask'] == "destination") echo "selected"; ?>>destination</option>
                    </select> <br>
                    <span class="vexpl">If 'source' or 'destination' is chosen, 
                    a dynamic pipe with the bandwidth, delay, packet loss and queue size given above will 
                    be created for each source/destination IP address encountered, 
                    respectively. This makes it possible to easily specify bandwidth 
                    limits per host.</span></td>
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
                    <?php if (isset($id) && $a_pipes[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>

</form>
<?php include("fend.inc"); ?>
</body>
</html>

