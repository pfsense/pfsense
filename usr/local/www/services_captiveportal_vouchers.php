<?php 
/*
	Copyright (C) 2007 Marcel Wiget <mwiget@mac.com>
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
	pfSense_BUILDER_BINARIES:	/usr/local/bin/voucher
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-vouchers
##|*NAME=Services: Captive portal Vouchers page
##|*DESCR=Allow access to the 'Services: Captive portal Vouchers' page.
##|*MATCH=services_captiveportal_vouchers.php*
##|-PRIV

$pgtitle = array("Services", "Captive portal", "Vouchers");
require("guiconfig.inc");
require("functions.inc");
require("filter.inc");
require("shaper.inc");
require("captiveportal.inc");
require_once("voucher.inc");

if (!is_array($config['voucher'])) {
    $config['voucher'] = array();
}

if (!is_array($config['voucher']['roll'])) {
    $config['voucher']['roll'] = array();
}
if (!isset($config['voucher']['charset'])) {
    $config['voucher']['charset'] = '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
}
if (!isset($config['voucher']['rollbits'])) {
    $config['voucher']['rollbits'] = 16;
}
if (!isset($config['voucher']['ticketbits'])) {
    $config['voucher']['ticketbits'] = 10;
}
if (!isset($config['voucher']['saveinterval'])) {
    $config['voucher']['saveinterval'] = 300;
}
if (!isset($config['voucher']['checksumbits'])) {
    $config['voucher']['checksumbits'] = 5;
}
if (!isset($config['voucher']['magic'])) {
    $config['voucher']['magic'] = rand();   // anything slightly random will do
}
if (!isset($config['voucher']['publickey'])) {
	
	/* generate a random 64 bit RSA key pair using the voucher binary */
	$fd = popen("/usr/local/bin/voucher -g 64", "r");
	if ($fd !== false) {
		$output = fread($fd, 16384);
	    pclose($fd);
		
		list($privkey, $pubkey) = explode("\0", $output);
		
	    $config['voucher']['publickey'] = base64_encode($pubkey);
	    $config['voucher']['privatekey'] = base64_encode($privkey);
	}
}
if (!isset($config['voucher']['msgnoaccess'])) {
    $config['voucher']['msgnoaccess'] = "Voucher invalid";
}
if (!isset($config['voucher']['msgexpired'])) {
    $config['voucher']['msgexpired'] = "Voucher expired";
}

$a_roll = &$config['voucher']['roll'];

if ($_GET['act'] == "del") {
    $id = $_GET['id'];
    if ($a_roll[$id]) {
        $roll = $a_roll[$id]['number']; 
	$voucherlck = lock('voucher');
        unset($a_roll[$id]);
        voucher_unlink_db($roll);
	unlock($voucherlck);
        write_config();
        header("Location: services_captiveportal_vouchers.php");
        exit;
    }
}

/* print all vouchers of the selected roll */
if ($_GET['act'] == "csv") {
    $privkey = base64_decode($config['voucher']['privatekey']);
    if (strstr($privkey,"BEGIN RSA PRIVATE KEY")) {
        $fd = fopen("{$g['varetc_path']}/voucher.private","w");
        if (!$fd) {
            $input_errors[] = "Cannot write private key file.\n";
        } else {
            chmod("{$g['varetc_path']}/voucher.private", 0600);
            fwrite($fd, $privkey);
            fclose($fd);

            $a_voucher = &$config['voucher']['roll'];
            $id = $_GET['id'];
            if (isset($id) && $a_voucher[$id]) {
                $number = $a_voucher[$id]['number'];
                $count = $a_voucher[$id]['count'];

                header("Content-Type: application/octet-stream");
                header("Content-Disposition: attachment; filename=vouchers_roll$number.csv");
                system("/usr/local/bin/voucher -c {$g['varetc_path']}/voucher.cfg -p {$g['varetc_path']}/voucher.private $number $count");
                unlink("{$g['varetc_path']}/voucher.private");
                exit;
            }
        }
    } else {
        $input_errors[] = "Need private RSA key to print vouchers\n";
    }
}

$pconfig['enable'] = isset($config['voucher']['enable']);
$pconfig['charset'] = $config['voucher']['charset'];
$pconfig['rollbits'] = $config['voucher']['rollbits'];
$pconfig['ticketbits'] = $config['voucher']['ticketbits'];
$pconfig['saveinterval'] = $config['voucher']['saveinterval'];
$pconfig['checksumbits'] = $config['voucher']['checksumbits'];
$pconfig['magic'] = $config['voucher']['magic'];
$pconfig['publickey'] = base64_decode($config['voucher']['publickey']);
$pconfig['privatekey'] = base64_decode($config['voucher']['privatekey']);
$pconfig['msgnoaccess'] = $config['voucher']['msgnoaccess'];
$pconfig['msgexpired'] = $config['voucher']['msgexpired'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "charset rollbits ticketbits checksumbits publickey magic saveinterval");
		$reqdfieldsn = explode(",", "charset,rollbits,ticketbits,checksumbits,publickey,magic,saveinterval");
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	}
	
	if ($_POST['charset'] && (strlen($_POST['charset'] < 2))) {
		$input_errors[] = "Need at least 2 characters to create vouchers.";
	}
	if ($_POST['charset'] && (strpos($_POST['charset'],"\"")>0)) {
		$input_errors[] = "Double quotes aren't allowed.";
	}
	if ($_POST['charset'] && (strpos($_POST['charset'],",")>0)) {
		$input_errors[] = "',' aren't allowed.";
	}
	if ($_POST['rollbits'] && (!is_numeric($_POST['rollbits']) || ($_POST['rollbits'] < 1) || ($_POST['rollbits'] > 31))) {
		$input_errors[] = "# of Bits to store Roll Id needs to be between 1..31.";
	}
	if ($_POST['ticketbits'] && (!is_numeric($_POST['ticketbits']) || ($_POST['ticketbits'] < 1) || ($_POST['ticketbits'] > 16))) {
		$input_errors[] = "# of Bits to store Ticket Id needs to be between 1..16.";
	}
	if ($_POST['checksumbits'] && (!is_numeric($_POST['checksumbits']) || ($_POST['checksumbits'] < 1) || ($_POST['checksumbits'] > 31))) {
		$input_errors[] = "# of Bits to store checksum needs to be between 1..31.";
	}
	if ($_POST['saveinterval'] && (!is_numeric($_POST['saveinterval']) || ($_POST['saveinterval'] < 1))) {
		$input_errors[] = "Save interval in minutes cant be negative.";
	}
	if ($_POST['publickey'] && (!strstr($_POST['publickey'],"BEGIN PUBLIC KEY"))) {
		$input_errors[] = "This doesn't look like an RSA Public key.";
	}
	if ($_POST['privatekey'] && (!strstr($_POST['privatekey'],"BEGIN RSA PRIVATE KEY"))) {
		$input_errors[] = "This doesn't look like an RSA Private key.";
	}

	if (!$input_errors) {
        	$config['voucher']['enable'] = $_POST['enable'] ? true : false;
        	$config['voucher']['charset'] = $_POST['charset'];
        	$config['voucher']['rollbits'] = $_POST['rollbits'];
        	$config['voucher']['ticketbits'] = $_POST['ticketbits'];
        	$config['voucher']['checksumbits'] = $_POST['checksumbits'];
        	$config['voucher']['magic'] = $_POST['magic'];
        	$config['voucher']['saveinterval'] = $_POST['saveinterval'];
        	$config['voucher']['publickey'] = base64_encode($_POST['publickey']);
        	$config['voucher']['privatekey'] = base64_encode($_POST['privatekey']);
        	$config['voucher']['msgnoaccess'] = $_POST['msgnoaccess'];
        	$config['voucher']['msgexpired'] = $_POST['msgexpired'];

		write_config();
        	voucher_configure();
        	if (isset($config['voucher']['enable']) && !isset($config['captiveportal']['enable'])) {
            		$savemsg = "Don't forget to configure and enable Captive Portal.";
        	}
	}
}
include("head.inc");
?>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis;
	endis = !(document.iform.enable.checked || enable_change);
	
	document.iform.charset.disabled = endis;
	document.iform.rollbits.disabled = endis;
	document.iform.ticketbits.disabled = endis;
	document.iform.saveinterval.disabled = endis;
	document.iform.checksumbits.disabled = endis;
	document.iform.magic.disabled = endis;
	document.iform.publickey.disabled = endis;
	document.iform.privatekey.disabled = endis;
	document.iform.msgnoaccess.disabled = endis;
	document.iform.msgexpired.disabled = endis;
}
//-->
</script>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="services_captiveportal_vouchers.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="tab pane">
  <tr><td class="tabnavtbl">
  <ul id="tabnav">
<?php 
	$tab_array = array();
        $tab_array[] = array("Captive portal", false, "services_captiveportal.php");
        $tab_array[] = array("Pass-through MAC", false, "services_captiveportal_mac.php");
        $tab_array[] = array("Allowed IP addresses", false, "services_captiveportal_ip.php");
        $tab_array[] = array("Vouchers", true, "services_captiveportal_vouchers.php");
        $tab_array[] = array("File Manager", false, "services_captiveportal_filemanager.php");
	$tab_array[] = array("Auth Logs", false, "diag_logs_auth.php");
        display_top_tabs($tab_array);
?> 
  </ul>
  </td></tr>
  <tr>
  <td class="tabcont">
  <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="checkbox pane">
	<tr> 
	  <td width="22%" valign="top" class="vtable">&nbsp;</td>
	  <td width="78%" class="vtable">
		<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
		<strong>Enable Vouchers</strong></td>
	</tr>
	<tr>
	  <td valign="top" class="vncell">Voucher Rolls</td>
	  <td class="vtable">

              <table width="100%" border="0" cellpadding="0" cellspacing="0" summary="content pane">
                <tr>
                  <td width="10%" class="listhdrr">Roll#</td>
                  <td width="20%" class="listhdrr">Minutes/Ticket</td>
                  <td width="20%" class="listhdrr"># of Tickets</td>
                  <td width="35%" class="listhdr">Comment</td>
                  <td width="15%" class="list"></td>
		</tr>
	<?php $i = 0; foreach($a_roll as $rollent): ?>
		<tr>
                  <td class="listlr">
                    <?=htmlspecialchars($rollent['number']); ?>&nbsp;
                  </td>
                  <td class="listr">
                    <?=htmlspecialchars($rollent['minutes']);?>&nbsp;
                  </td>
                  <td class="listr">
                    <?=htmlspecialchars($rollent['count']);?>&nbsp;
                  </td>
                  <td class="listr">
                    <?=htmlspecialchars($rollent['comment']); ?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> 
                  <?php if ($pconfig['enable']): ?> 
                    <a href="services_captiveportal_vouchers_edit.php?id=<?=$i; ?>"><img src="/themes/<?=$g['theme']; ?>/images/icons/icon_e.gif" title="edit voucher" width="17" height="17" border="0" alt="edit voucher"></a>
                    <a href="services_captiveportal_vouchers.php?act=del&amp;id=<?=$i; ?>" onclick="return confirm('Do you really want to delete this voucher? This makes all vouchers from this roll invalid')"><img src="/themes/<?=$g['theme']; ?>/images/icons/icon_x.gif" title="delete vouchers" width="17" height="17" border="0" alt="delete vouchers"></a>
                    <a href="services_captiveportal_vouchers.php?act=csv&amp;id=<?=$i; ?>"><img src="/themes/<?=$g['theme']; ?>/images/icons/icon_log_s.gif" title="generate vouchers for this roll to CSV file" width="11" height="15" border="0" alt="generate vouchers for this roll to CSV file"></a>
                    <?php endif;?>
                  </td>
		</tr>
	<?php $i++; endforeach; ?>
		<tr> 
			  <td class="list" colspan="4"></td>
              <?php
	      if ($pconfig['enable']) {
		echo "<td class=\"list\"> <a href=\"services_captiveportal_vouchers_edit.php\"><img src=\"/themes/{$g['theme']}/images/icons/icon_plus.gif\" title=\"add voucher\" width=\"17\" height=\"17\" border=\"0\" alt=\"add voucher\"></a></td>";
              }
	      ?>
	    </tr>
        </table>     
<?php if ($pconfig['enable']): ?> 
Create, generate and activate Rolls with Vouchers that allow access through the 
captive portal for the configured time. Once a voucher is activated, 
its clock is started and runs uninterrupted until it expires. During that
time, the voucher can be re-used from the same or a different computer. If the voucher 
is used again from another computer, the previous session is stopped.
<?php else: ?>
Enable Voucher support first using the checkbox above and hit Save at the bottom.</td>
<?php endif;?>
	</tr>
	<tr>
      <td valign="top" class="vncellreq">Voucher public key</td>
      <td class="vtable">
        <textarea name="publickey" cols="65" rows="4" id="publickey" class="formpre"><?=htmlspecialchars($pconfig['publickey']);?></textarea>
        <br>
    Paste an RSA public key (64 Bit or smaller) in PEM format here. This key is used to decrypt vouchers.</td>
	</tr>
	<tr>
      <td valign="top" class="vncell">Voucher private key</td>
      <td class="vtable">
        <textarea name="privatekey" cols="65" rows="5" id="privatekey" class="formpre"><?=htmlspecialchars($pconfig['privatekey']);?></textarea>
        <br>
    Paste an RSA private key (64 Bit or smaller) in PEM format here. This key is only used to generate encrypted vouchers and doesn't need to be available if the vouchers have been generated offline.</td>
	</tr>
	<tr> 
       <td width="22%" valign="top" class="vncellreq">Character set</td>
       <td width="78%" class="vtable">
         <input name="charset" type="text" class="formfld" id="charset" size="80" value="<?=htmlspecialchars($pconfig['charset']);?>">
         <br>
         Tickets are generated with the specified character set. It should contain printable characters (numbers, lower case and upper case letters) that are hard to confuse with others. Avoid e.g. 0/O and l/1.</td>
    </tr>
	<tr> 
       <td width="22%" valign="top" class="vncellreq"># of Roll Bits</td>
       <td width="78%" class="vtable">
         <input name="rollbits" type="text" class="formfld" id="rollbits" size="2" value="<?=htmlspecialchars($pconfig['rollbits']);?>">
         <br>
         Reserves a range in each voucher to store the Roll# it belongs to. Allowed range: 1..31. Sum of Roll+Ticket+Checksum bits must be one Bit less than the RSA key size.</td>
    </tr>
	<tr> 
       <td width="22%" valign="top" class="vncellreq"># of Ticket Bits</td>
       <td width="78%" class="vtable">
         <input name="ticketbits" type="text" class="formfld" id="ticketbits" size="2" value="<?=htmlspecialchars($pconfig['ticketbits']);?>">
         <br>
         Reserves a range in each voucher to store the Ticket# it belongs to. Allowed range: 1..16. Using 16 bits allows a roll to have up to 65535 vouchers. A bit array, stored in RAM and in the config, is used to mark if a voucher has been used. A bit array for 65535 vouchers requires 8 KB of storage.</td>
    </tr>
	<tr> 
       <td width="22%" valign="top" class="vncellreq"># of Checksum Bits</td>
       <td width="78%" class="vtable">
         <input name="checksumbits" type="text" class="formfld" id="checksumbits" size="2" value="<?=htmlspecialchars($pconfig['checksumbits']);?>">
         <br>
         Reserves a range in each voucher to store a simple checksum over Roll# and Ticket#. Allowed range is 0..31.</td>
    </tr>
	<tr> 
       <td width="22%" valign="top" class="vncellreq">Magic Number</td>
       <td width="78%" class="vtable">
         <input name="magic" type="text" class="formfld" id="magic" size="20" value="<?=htmlspecialchars($pconfig['magic']);?>">
         <br>
         Magic number stored in every voucher. Verified during voucher check. Size depends on how many bits are left by Roll+Ticket+Checksum bits. If all bits are used, no magic number will be used and checked.</td>
    </tr>
    <tr> 
       <td width="22%" valign="top" class="vncellreq">Save Interval</td>
       <td width="78%" class="vtable">
         <input name="saveinterval" type="text" class="formfld" id="saveinterval" size="4" value="<?=htmlspecialchars($pconfig['saveinterval']);?>">
         Minutes<br>
         The list of active and used vouchers can be stored in the system's configuration file once every x minutes to survive power outages. No save is done if no new vouchers have been activated.  Enter 0 to never write runtime state to XML config.</td>
    </tr>
    <tr> 
       <td width="22%" valign="top" class="vncellreq">Invalid Voucher Message</td>
       <td width="78%" class="vtable">
         <input name="msgnoaccess" type="text" class="formfld" id="msgnoaccess" size="80" value="<?=htmlspecialchars($pconfig['msgnoaccess']);?>">
         <br>Error message displayed for invalid vouchers on captive portal error page ($PORTAL_MESSAGE$).</td>
    </tr>
    <tr> 
       <td width="22%" valign="top" class="vncellreq">Expired Voucher Message</td>
       <td width="78%" class="vtable">
         <input name="msgexpired" type="text" class="formfld" id="msgexpired" size="80" value="<?=htmlspecialchars($pconfig['msgexpired']);?>">
         <br>Error message displayed for expired vouchers on captive portal error page ($PORTAL_MESSAGE$).</td>
    </tr>
    <tr>
       <td width="22%" valign="top">&nbsp;</td>
       <td width="78%">
        <input name="Submit" type="submit" class="formbtn" value="Save" onClick="enable_change(true)">
       </td>
    </tr>
    <tr>
     <td colspan="2" class="list"><p class="vexpl">
     <span class="red"><strong> Note:<br>   </strong></span>
      Changing any Voucher parameter (apart from managing the list of Rolls) on this page will render existing vouchers useless if they were generated with different settings.
      </p>
     </td>
    </tr>
  </table>
  </td>
  </tr>
  </table>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
