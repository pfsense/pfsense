<?php
/* $Id$ */
/*
	diag_ipsec.php
	Copyright (C) 2007 Scott Ullrich
	All rights reserved.

	Parts of this code was originally based on vpn_ipsec_sad.php
	Copyright (C) 2003-2004 Manuel Kasper

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

$pgtitle = "Status: IPsec";

require("guiconfig.inc");
include("head.inc");

function get_ipsec_tunnel_src($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	if ($tunnel['interface']) {
		$if = $tunnel['interface'];
		$realinterface = convert_friendly_interface_to_real_interface_name($if);
		$interfaceip = find_interface_ip($realinterface);
	}
	return $interfaceip;
}

function output_ipsec_tunnel_status($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	$interfaceip = get_ipsec_tunnel_src($tunnel);
	$foundsrc = false;
	$founddst = false;
	if(!is_ipaddr($tunnel['remote-gateway']))
		$tunnel['remote-gateway'] = resolve_retry($tunnel['remote-gateway']);

	foreach($sad as $sa) {
		if($sa['src'] == $interfaceip) 
			$foundsrc = true;
		if($sa['dst'] == $tunnel['remote-gateway']) 
			$founddst = true;
	}
	if($foundsrc && $founddst) { 
		/* tunnel is up */
		$iconfn = "pass";
	} else {
		/* tunnel is down */
		$iconfn = "reject";
	}
	echo "<img src ='/themes/{$g['theme']}/images/icons/icon_{$iconfn}.gif'>";
}

/* query SAD */
$sad = return_ipsec_sad_array();

if (!is_array($config['ipsec']['tunnel'])) {
	$config['ipsec']['tunnel'] = array();
}

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<div id="inputerrors"></div>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Overview", true, "diag_ipsec.php");
	$tab_array[1] = array("SAD", false, "diag_ipsec_sad.php");
	$tab_array[2] = array("SPD", false, "diag_ipsec_spd.php");
	display_top_tabs($tab_array);
?>
    </td>
  </tr>
  <tr>
    <td>
	<div id="mainarea">
            <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
<?php if (count($sad)): ?>
  <tr>
                <td nowrap class="listhdrr">Source</td>
                <td nowrap class="listhdrr">Destination</a></td>
                <td nowrap class="listhdrr">Description</a></td>
                <td nowrap class="listhdrr">Status</td>
	</tr>
<?php
foreach ($config['ipsec']['tunnel'] as $ipsec) {
	if(! isset($ipsec['disabled'])) {
?>
	<tr>
		<td class="listlr"><?=htmlspecialchars(get_ipsec_tunnel_src($ipsec));?>
		<br/>
        <?php	if ($ipsec['local-subnet']['network'])
					echo strtoupper($ipsecent['local-subnet']['network']);
				else
					echo $ipsec['local-subnet']['address'];
		?>		
		</td>
		<td class="listr"><?=htmlspecialchars($ipsec['remote-gateway']);?>
		<br/>
		<?=$ipsec['remote-subnet'];?>
		</td>
		<td class="listr"><?=htmlspecialchars($ipsec['descr']);?></td>
		<td class="listr"><?php echo output_ipsec_tunnel_status($ipsec); ?></td>
	</tr>
<?php 
	}
}
?>
<?php else: ?>
  <tr>
    <td>
      <p>
        <strong>No IPsec security associations.</strong>
      </p>
    </td>
  </tr>
<?php endif; ?>
  <tr>
    <td colspan="4">
		  <p>
        <span class="vexpl">
          <span class="red">
            <strong>
              Note:<br />
            </strong>
          </span>
          You can configure your IPsec 
          <a href="vpn_ipsec.php">here</a>.
        </span>
      </p>
		</td>
  </tr>
</table>
</div>

</td></tr>

</table>

<?php include("fend.inc"); ?>
</body>
</html>
