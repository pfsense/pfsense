#!/usr/local/bin/php
<?php 
/*
    firewall_nat_out.php
    part of m0n0wall (http://m0n0.ch/wall)
    
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

if (!is_array($config['nat']['advancedoutbound']['rule']))
    $config['nat']['advancedoutbound']['rule'] = array();
    
$a_out = &$config['nat']['advancedoutbound']['rule'];
nat_out_rules_sort();

if ($_POST) {

    $pconfig = $_POST;

    $config['nat']['advancedoutbound']['enable'] = ($_POST['enable']) ? true : false;
    write_config();
    
    $retval = 0;
    
    if (!file_exists($d_sysrebootreqd_path)) {
		config_lock();
        $retval |= filter_configure();
		config_unlock();
    }
    $savemsg = get_std_save_message($retval);
    
    if ($retval == 0) {
        if (file_exists($d_natconfdirty_path))
            unlink($d_natconfdirty_path);
        if (file_exists($d_filterconfdirty_path))
            unlink($d_filterconfdirty_path);
    }
}

if ($_GET['act'] == "del") {
    if ($a_out[$_GET['id']]) {
        unset($a_out[$_GET['id']]);
        write_config();
        touch($d_natconfdirty_path);
        header("Location: firewall_nat_out.php");
        exit;
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: NAT");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: NAT</p>
<form action="firewall_nat_out.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_natconfdirty_path)): ?><p>
<?php print_info_box_np("The NAT configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="firewall_nat.php">Inbound</a></li>
    <li class="tabinact"><a href="firewall_nat_server.php">Server NAT</a></li>
    <li class="tabinact"><a href="firewall_nat_1to1.php">1:1</a></li>
    <li class="tabact">Outbound</li>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td class="vtable"><p>
                      <input name="enable" type="checkbox" id="enable" value="yes" <?php if (isset($config['nat']['advancedoutbound']['enable'])) echo "checked";?>>
                      <strong>Enable advanced outbound NAT<br>
                      </strong></p></td>
                </tr>
                <tr> 
                  <td> <input name="submit" type="submit" class="formbtn" value="Save"> 
                  </td>
                </tr>
                <tr>
                  <td><p><span class="vexpl"><span class="red"><strong>Note:<br>
                      </strong></span>If advanced outbound NAT is enabled, no outbound NAT
                      rules will be automatically generated anymore. Instead, only the mappings
                      you specify below will be used. With advanced outbound NAT disabled,
                      a mapping is automatically created for each interface's subnet
                      (except WAN).</span> If you use target addresses other than the WAN interface's IP address, then depending on<span class="vexpl"> the way your WAN connection is setup, you may also need <a href="services_proxyarp.php">proxy ARP</a>.</span><br>
                      <br>
                      You may enter your own mappings below.</p>
                    </td>
                </tr>
              </table>
              &nbsp;<br>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr> 
                  <td width="10%" class="listhdrr">Interface</td>
                  <td width="20%" class="listhdrr">Source</td>
                  <td width="20%" class="listhdrr">Destination</td>
                  <td width="20%" class="listhdrr">Target</td>
                  <td width="25%" class="listhdr">Description</td>
                  <td width="5%" class="list"></td>
                </tr>
              <?php $i = 0; foreach ($a_out as $natent): ?>
                <tr> 
                  <td class="listlr">
                    <?php
					if (!$natent['interface'] || ($natent['interface'] == "wan"))
					  	echo "WAN";
					else
						echo htmlspecialchars($config['interfaces'][$natent['interface']]['descr']);
					?>
                  </td>
                  <td class="listr"> 
                    <?=$natent['source']['network'];?>
                  </td>
                  <td class="listr"> 
                    <?php
                      if (isset($natent['destination']['any']))
                          echo "*";
                      else {
                          if (isset($natent['destination']['not']))
                              echo "!&nbsp;";
                          echo $natent['destination']['network'];
                      }
                    ?>
                  </td>
                  <td class="listr"> 
                    <?php
                      if (!$natent['target'])
                          echo "*";
                      else
                          echo $natent['target'];
                    ?>
                  </td>
                  <td class="listbg"> 
                    <?=htmlspecialchars($natent['descr']);?>&nbsp;
                  </td>
                  <td class="list" nowrap> <a href="firewall_nat_out_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="firewall_nat_out.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this mapping?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
                </tr>
              <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="5"></td>
                  <td class="list"> <a href="firewall_nat_out_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
                </tr>
              </table>
</td>
  </tr>
</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
