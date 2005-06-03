#!/usr/local/bin/php
<?php 
/* $Id$ */
/*
	services_snmp.php
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

if (!is_array($config['snmpd'])) {
	$config['snmpd'] = array();
	$config['snmpd']['rocommunity'] = "public";
}

$pconfig['enable'] = isset($config['snmpd']['enable']);
$pconfig['syslocation'] = $config['snmpd']['syslocation'];
$pconfig['syscontact'] = $config['snmpd']['syscontact'];
$pconfig['rocommunity'] = $config['snmpd']['rocommunity'];
/* disabled until some docs show up on what this does.
$pconfig['rwenable'] = isset($config['snmpd']['rwenable']);
$pconfig['rwcommunity'] = $config['snmpd']['rwcommunity'];
*/
$pconfig['trapenable'] = isset($config['snmpd']['trapenable']);
$pconfig['trapserver'] = $config['snmpd']['trapserver'];
$pconfig['trapserverport'] = $config['snmpd']['trapserverport'];
$pconfig['trapstring'] = $config['snmpd']['trapstring'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "rocommunity");
		$reqdfieldsn = explode(",", "Community");
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	}

	if ($_POST['trapenable']) {
		$reqdfields = explode(" ", "trapserver");
		$reqdfieldsn = explode(",", "Trap server");
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		$reqdfields = explode(" ", "trapserverport");
		$reqdfieldsn = explode(",", "Trap server port");
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		$reqdfields = explode(" ", "trapstring");
		$reqdfieldsn = explode(",", "Trap string");
		do_input_validation($_POST, $reqdfields, $reqdfields, $reqdfieldsn, &$input_errors);
	}


/* disabled until some docs show up on what this does.
	if ($_POST['rwenable']) {
               $reqdfields = explode(" ", "rwcommunity");
               $reqdfieldsn = explode(",", "Write community string");
               do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	}
*/

	

	if (!$input_errors) {
		$config['snmpd']['enable'] = $_POST['enable'] ? true : false;
		$config['snmpd']['syslocation'] = $_POST['syslocation'];	
		$config['snmpd']['syscontact'] = $_POST['syscontact'];
		$config['snmpd']['rocommunity'] = $_POST['rocommunity'];
		/* disabled until some docs show up on what this does.
		$config['snmpd']['rwenable'] = $_POST['rwenable'] ? true : false;
		$config['snmpd']['rwcommunity'] = $_POST['rwcommunity'];
		*/
		$config['snmpd']['trapenable'] = $_POST['trapenable'] ? true : false;
		$config['snmpd']['trapserver'] = $_POST['trapserver'];
		$config['snmpd']['trapserverport'] = $_POST['trapserverport'];
		$config['snmpd']['trapstring'] = $_POST['trapstring'];
		
			
		write_config();
		
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = services_snmpd_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: SNMP");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function enable_change(whichone) {

	if( whichone.name == "trapenable" )
        {
	    if( whichone.checked == true )
	    {
	        document.iform.trapserver.disabled = false;
	        document.iform.trapserverport.disabled = false;
	        document.iform.trapstring.disabled = false;
	    }
	    else
	    {
                document.iform.trapserver.disabled = true;
                document.iform.trapserverport.disabled = true;
                document.iform.trapstring.disabled = true;
	    }
	}

	/* disabled until some docs show up on what this does.
	if( whichone.name == "rwenable"  )
	{
	    if( whichone.checked == true )
	    {
		document.iform.rwcommunity.disabled = false;
	    }
	    else
	    {
		document.iform.rwcommunity.disabled = true;
	    }
	}
	*/

	if( document.iform.enable.checked == true )
	{
	    document.iform.syslocation.disabled = false;
	    document.iform.syscontact.disabled = false;
	    document.iform.rocommunity.disabled = false;
	    document.iform.trapenable.disabled = false;
	    /* disabled until some docs show up on what this does.
	    document.iform.rwenable.disabled = false;
	    if( document.iform.rwenable.checked == true )
	    {
	        document.iform.rwcommunity.disabled = false;
	    }
	    else
	    {
		document.iform.rwcommunity.disabled = true;
	    }
	    */
	    if( document.iform.trapenable.checked == true )
	    {
                document.iform.trapserver.disabled = false;
                document.iform.trapserverport.disabled = false;
                document.iform.trapstring.disabled = false;
	    }
	    else
	    {
                document.iform.trapserver.disabled = true;
                document.iform.trapserverport.disabled = true;
                document.iform.trapstring.disabled = true;
	    }
	}
	else
	{
            document.iform.syslocation.disabled = true;
            document.iform.syscontact.disabled = true;
            document.iform.rocommunity.disabled = true;
	    /* 
            document.iform.rwenable.disabled = true;
	    document.iform.rwcommunity.disabled = true;
	    */
            document.iform.trapenable.disabled = true;
            document.iform.trapserver.disabled = true;
            document.iform.trapserverport.disabled = true;
            document.iform.trapstring.disabled = true;
	}
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: SNMP</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="services_snmp.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">

                <tr> 
  		  <td colspan="2" valign="top" class="optsect_t">
  			<table border="0" cellspacing="0" cellpadding="0" width="100%">
  			<tr><td class="optsect_s"><strong>SNMP Daemon</strong></td>
			<td align="right" class="optsect_s"><input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(this)"> <strong>Enable</strong></td></tr>
  			</table></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">System location</td>
                  <td width="78%" class="vtable"> 
                    <input name="syslocation" type="text" class="formfld" id="syslocation" size="40" value="<?=htmlspecialchars($pconfig['syslocation']);?>"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">System contact</td>
                  <td width="78%" class="vtable"> 
                    <input name="syscontact" type="text" class="formfld" id="syscontact" size="40" value="<?=htmlspecialchars($pconfig['syscontact']);?>"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Read Community String</td>
                  <td width="78%" class="vtable"> 
                    <input name="rocommunity" type="text" class="formfld" id="rocommunity" size="40" value="<?=htmlspecialchars($pconfig['rocommunity']);?>"> 
                    <br>
                    In most cases, &quot;public&quot; is used here</td>
                </tr>



<?php 
			/* disabled until some docs show up on what this does.
                <tr>
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
	 	   <input name="rwenable" type="checkbox" value="yes" <?php if ($pconfig['rwenable']) echo "checked"; ?> onClick="enable_change(this)">
                    <strong>Enable Write Community String</strong></td>
                </tr>

		<tr>
		  <td width="22%" valign="top" class="vncellreq">Write community string</td>
          <td width="78%" class="vtable">
                    <input name="rwcommunity" type="text" class="formfld" id="rwcommunity" size="40" value="<?=htmlspecialchars($pconfig['rwcommunity']);?>">
		    <br>
                    Please use something other then &quot;private&quot; here</td>
                </tr>
		    	*/ 
?>



                <tr>
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
                   <input name="trapenable" type="checkbox" value="yes" <?php if ($pconfig['trapenable']) echo "checked"; ?> onClick="enable_change(this)">
                    <strong>Enable SNMP Traps</strong></td>
                </tr>

                <tr>
                  <td width="22%" valign="top" class="vncellreq">Trap server</td>
                  <td width="78%" class="vtable">
                    <input name="trapserver" type="text" class="formfld" id="trapserver" size="40" value="<?=htmlspecialchars($pconfig['trapserver']);?>">
                    <br>
                    Enter trap server name</td>
                </tr>

                <tr>
                  <td width="22%" valign="top" class="vncellreq">Trap server port </td>
                  <td width="78%" class="vtable">
                    <input name="trapserverport" type="text" class="formfld" id="trapserverport" size="40" value="<?=$pconfig['trapserverport'] ? htmlspecialchars($pconfig['trapserverport']) : htmlspecialchars(162);?>">
                    <br>
                    Enter the port to send the traps to (default 162)</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Trap server port </td>
                  <td width="78%" class="vtable">
                    <input name="trapstring" type="text" class="formfld" id="trapstring" size="40" value="<?=htmlspecialchars($pconfig['trapstring']);?>">
                    <br>
                    Trap string</td>
                </tr>


                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onClick="enable_change(true)"> 
                  </td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
enable_change(this);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
