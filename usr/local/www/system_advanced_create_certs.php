#!/usr/local/bin/php
<?php
/*
	system_advanced_create_certs.php
	part of pfSense

	Copyright (C) 2004 Scott Ullrich
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

$fd = fopen("/etc/ssl/openssl.cnf", "r");
$openssl = fread($fd,8096);
fclose($fd);

/* Lets match the fileds in the read in file and
   populate the variables for the form */
preg_match('/\nC\=(.*)\n/', $openssl, $countrycodeA);
preg_match('/\nST\=(.*)\n/', $openssl, $stateorprovinceA);
preg_match('/\nL\=(.*)\n/', $openssl, $citynameA);
preg_match('/\nO\=(.*)\n/', $openssl, $orginizationnameA);
preg_match('/\nOU\=(.*)\n/', $openssl, $orginizationdepartmentA);
preg_match('/\nCN\=(.*)\n/', $openssl, $commonnameA);

$countrycode = $countrycodeA[1];
$stateorprovince = $stateorprovinceA[1];
$cityname = $citynameA[1];
$orginizationname = $orginizationnameA[1];
$orginizationdepartment = $orginizationdepartmentA[1];
$commonname = $commonnameA[1];

if ($_POST) {

    /* Grab posted variables and create a new openssl.cnf */
    $countrycode=$_POST['countrycode'];
    $stateorprovince=$_POST['stateorprovince'];
    $cityname=$_POST['cityname'];
    $orginizationname=$_POST['orginizationname'];
    $orginizationdepartment=$_POST['orginizationdepartment'];
    $commonname=$_POST['commonname'];

    /* Write out /etc/ssl/openssl.cnf */
    $fd = fopen("/etc/ssl/openssl.cnf", "w");
    fwrite($fd, "");
    fwrite($fd, "[ req ]\n");
    fwrite($fd, "distinguished_name=req_distinguished_name \n");
    fwrite($fd, "req_extensions = v3_req \n");
    fwrite($fd, "prompt=no \n");
    fwrite($fd, "[ req_distinguished_name ] \n");
    fwrite($fd, "C=" . $countrycode . " \n");
    fwrite($fd, "ST=" . $stateorprovince. " \n");
    fwrite($fd, "L=" . $cityname . " \n");
    fwrite($fd, "O=" . $orginizationname . " \n");
    fwrite($fd, "OU=" . $orginizationdepartment . " \n");
    fwrite($fd, "CN=" . $commonname . " \n");
    fwrite($fd, "[EMAIL PROTECTED] \n");
    fwrite($fd, "[EMAIL PROTECTED] \n");
    fwrite($fd, "[ v3_req ] \n");
    fwrite($fd, "basicConstraints = critical,CA:FALSE \n");
    fwrite($fd, "keyUsage = nonRepudiation, digitalSignature, keyEncipherment, dataEncipherment, keyAgreement \n");
    fwrite($fd, "extendedKeyUsage=emailProtection,clientAuth \n");
    fclose($fd);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Advanced functions");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form action="system_advanced_create_certs.php" method="post" name="iform" id="iform">
<?php include("fbegin.inc"); ?>
      <p class="pgtitle">System: Advanced functions - Create Certificates</p>
            <?php if ($input_errors) print_input_errors($input_errors); ?>
            <?php if ($savemsg) print_info_box($savemsg); ?>
	    <p>One moment please...
	<?php
	    //mwexec("cd /tmp && openssl req -nodes -new > cert.csr && openssl rsa -in privkey.pem -out key.pem && openssl x509 -in cert.csr -out cert.pem -req -signkey key.pem -days 365");
	    mwexec("cd /tmp/ && /usr/bin/openssl req -new -x509 -keyout cakey.pem -out cacert.pem -days 3650 -config /etc/ssl/openssl.cnf -passin pass:test -nodes");
	    $fd = fopen("/tmp/cacert.pem", "r");
	    $cacert = fread($fd,8096);
	    fclose($fd);
	    $fd = fopen("/tmp/cakey.pem", "r");
	    $cakey = fread($fd,8096);
	    fclose($fd);
	    $cacertA = ereg_replace("\r","",$cacert);
	    $cakeyA = ereg_replace("\r","",$cakey);
	    $cacert = ereg_replace("\n","\\n",$cacert);
	    $cakey = ereg_replace("\n","\\n",$cakey);
	?>
	<script language="JavaScript">
	<!--
	    var cacert='<?=$cacert?>';
	    var cakey='<?=$cakey?>';
	    opener.document.forms[0].cert.value=cacert;
	    opener.document.forms[0].key.value=cakey;
	    this.close();
	-->
	</script>

<?php include("fend.inc"); ?>
</body>
</html>

<?php

} else {

?>

    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
    <html>
    <head>
    <title><?=gentitle("System: Advanced functions");?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
    <link href="gui.css" rel="stylesheet" type="text/css">
    <body link="#0000CC" vlink="#0000CC" alink="#0000CC">
    <form action="system_advanced_create_certs.php" method="post" name="iform" id="iform">
    <?php include("fbegin.inc"); ?>
	  <p class="pgtitle">System: Advanced - Create Certificates</p>

	  <table width="100%" border="0" cellpadding="6" cellspacing="0">
		    <tr>
		      <td width="35%" valign="top" class="vncell"><B>Country Code (2 Letters)</td>
		      <td width="78%" class="vtable">
			<input name="countrycode" value="<?=$countrycode?>">
			</span></td>
		    </tr>

		    <tr>
		      <td width="35%" valign="top" class="vncell"><B>State or Province name</td>
		      <td width="78%" class="vtable">
			<input name="stateorprovince" value="<?=$stateorprovince?>">
			</span></td>
		    </tr>

		    <tr>
		      <td width="35%" valign="top" class="vncell"><B>City name</td>
		      <td width="78%" class="vtable">
			<input name="cityname" value="<?=$cityname?>">
			</span></td>
		    </tr>

		    <tr>
		      <td width="35%" valign="top" class="vncell"><B>Organization name</td>
		      <td width="78%" class="vtable">
			<input name="orginizationname" value="<?=$orginizationname?>">
			</span></td>
		    </tr>

		    <tr>
		      <td width="35%" valign="top" class="vncell"><B>Organization department</td>
		      <td width="78%" class="vtable">
			<input name="orginizationdepartment" value="<?=$orginizationdepartment?>">
			</span></td>
		    </tr>

		    <tr>
		      <td width="35%" valign="top" class="vncell"><B>Common Name (Your name)</td>
		      <td width="78%" class="vtable">
			<input name="commonname" value="<?=$commonname?>">
			</span></td>
		    </tr>

		    <!--
		    <tr>
		      <td width="35%" valign="top" class="vncell"><B>E-Mail address</td>
		      <td width="78%" class="vtable">
			<input name="email" value="<?=$email?>">
			</span></td>
		    </tr>
		    -->

		    <tr>
		      <td width="35%" valign="top">&nbsp;</td>
		      <td width="78%">
			<input name="Submit" type="submit" class="formbtn" value="Save">
		      </td>
		    </tr>

    <?php include("fend.inc"); ?>
    </body>
    </html>

<?php
}
?>
