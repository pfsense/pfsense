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

function get_file_contents($filename) {
    $filecontents = "";
    if(file_exists($filename)) {
	$fd = fopen($filename, "r");
	$tmp = fread($fd,8096);
	$filecontents .= $tmp;
	fclose($fd);
	return $filecontents;
    }
    return "File not found " . $filename;
}

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
    fwrite($fd, "prompt=no\n");
    fwrite($fd, "default_bits            = 1024\n");
    fwrite($fd, "default_keyfile         = privkey.pem\n");
    fwrite($fd, "distinguished_name      = req_distinguished_name\n");
    fwrite($fd, "attributes              = req_attributes\n");
    fwrite($fd, "x509_extensions = v3_ca # The extentions to add to the self signed cert\n");
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
    fwrite($fd, "[ ca ]\n");
    fwrite($fd, "default_ca      = CA_default\n");
    fwrite($fd, "[ CA_default ]\n");
    fwrite($fd, "certificate             = /tmp/cacert.pem \n");
    fwrite($fd, "private_key             = /tmp/cakey.pem \n");
    fwrite($fd, "dir                     = /tmp/\n");
    fwrite($fd, "certs                   = /tmp/certs\n");
    fwrite($fd, "crl_dir                 = /tmp/crl\n");
    fwrite($fd, "database                = /tmp/index.txt \n");
    fwrite($fd, "new_certs_dir           = /tmp/newcerts \n");
    fwrite($fd, "serial                  = /tmp/serial \n");
    fwrite($fd, "crl                     = /tmp/crl.pem \n");
    fwrite($fd, "RANDFILE                = /tmp/.rand  \n");
    fwrite($fd, "x509_extensions         = usr_cert  \n");
    fwrite($fd, "name_opt                = ca_default \n");
    fwrite($fd, "cert_opt                = ca_default \n");
    fwrite($fd, "default_days            = 365 \n");
    fwrite($fd, "default_crl_days        = 30  \n");
    fwrite($fd, "default_md              = md5 \n");
    fwrite($fd, "preserve                = no \n");
    fwrite($fd, "policy                  = policy_match\n");
    fwrite($fd, "[ policy_match ]\n");
    fwrite($fd, "countryName             = match\n");
    fwrite($fd, "stateOrProvinceName     = match\n");
    fwrite($fd, "organizationName        = match\n");
    fwrite($fd, "organizationalUnitName  = optional\n");
    fwrite($fd, "commonName              = supplied\n");
    fwrite($fd, "emailAddress            = optional\n");
    fwrite($fd, "[ policy_anything ]\n");
    fwrite($fd, "countryName             = optional\n");
    fwrite($fd, "stateOrProvinceName     = optional\n");
    fwrite($fd, "localityName            = optional\n");
    fwrite($fd, "organizationName        = optional\n");
    fwrite($fd, "organizationalUnitName  = optional\n");
    fwrite($fd, "commonName              = supplied\n");
    fwrite($fd, "emailAddress            = optional\n");
    fwrite($fd, "[ req_distinguished_name ]\n");
    fwrite($fd, "countryName                     = US\n");
    fwrite($fd, "[ req_attributes ]\n");
    fwrite($fd, "challengePassword               = A challenge password\n");
    fwrite($fd, "unstructuredName                = An optional company name\n");
    fwrite($fd, "[ usr_cert ]\n");
    fwrite($fd, "basicConstraints=CA:FALSE\n");
    fwrite($fd, "[ v3_ca ]\n");
    fwrite($fd, "subjectKeyIdentifier=hash\n");
    fwrite($fd, "authorityKeyIdentifier=keyid:always,issuer:always\n");
    fwrite($fd, "basicConstraints = CA:true\n");
    fwrite($fd, "[ crl_ext ]\n");
    fwrite($fd, "authorityKeyIdentifier=keyid:always,issuer:always\n");
    fclose($fd);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<script language="JavaScript">
<!--
function f(ta_id){
 var d=document, ta, rng;
 if(d.all){
  ta=d.all[ta_id];
  if(ta && ta.createTextRange){
   rng=ta.createTextRange();
   rng.collapse(false);
   rng.select();
  } else {
    ta_id.focus();
    ta_id.select();
    ta_id.blur();
  }
 }
}
-->
</script>
<title><?=gentitle("System: Advanced functions");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form action="system_advanced_create_certs.php" method="post" name="iform" id="iform">
<?php include("fbegin.inc"); ?>
      <p class="pgtitle">System: Advanced functions - Create Certificates</p>
            <?php if ($input_errors) print_input_errors($input_errors); ?>
            <?php if ($savemsg) print_info_box($savemsg); ?>
	    <p>
	    <textarea cols="55" rows="1" name="status" id="status" wrap="hard">One moment please... This will take a while!</textarea>
	    <textarea cols="55" rows="25" name="output" id="output" wrap="hard"></textarea>
<?php include("fend.inc"); ?>
</body>
</html>

	<?php

	    echo "<script language=\"JavaScript\">document.forms[0].status.value=\"Creating CA...\";</script>";
	    mwexec("rm -rf /tmp/*");
	    //mwexec("rm -rf /tmp/newcerts");
	    mwexec("mkdir /tmp/newcerts");
	    mwexec("touch /tmp/index.txt");
	    $fd = fopen("/tmp/serial","w");
	    fwrite($fd, "01\n");
	    fclose($fd);

	    /*
		mkdir /tmp/newcerts
		touch /tmp/index.txt
		echo 01 > serial
		#Create The Certificate Authority Root Certificate
		cd /tmp/ && openssl req -nodes -new -x509 -keyout cakey.pem -out cacert.pem -config /etc/ssl/openssl.cnf
		#Create User Certificates
		cd /tmp/ && openssl req -nodes -new -keyout vpnkey.pem -out vpncert-req.pem -config /etc/ssl/openssl.cnf
		mkdir /tmp/newcerts
		openssl ca -out vpncert.pem -in vpncert-req.pem -batch


		# Diffie-Hellman Parameters (tls-server only)
		dh dh1024.pem
		# Root certificate
		ca CA-DB/cacert.pem
		# Server certificate
		cert vpncert.pem
		# Server private key
		key vpnkey.pem
           */

	    execute_command_return_output("cd /tmp/ && openssl req -nodes -new -x509 -keyout cakey.pem -out cacert.pem -config /etc/ssl/openssl.cnf");

	    echo "\n<script language=\"JavaScript\">document.forms[0].status.value=\"Creating Server Certificates...\";</script>";

	    execute_command_return_output("cd /tmp/ && openssl req -nodes -new -keyout vpnkey.pem -out vpncert-req.pem -config /etc/ssl/openssl.cnf");

	    execute_command_return_output("cd /tmp/ && openssl ca -out vpncert.pem -in vpncert-req.pem -batch");

	    echo "\n<script language=\"JavaScript\">document.forms[0].status.value=\"Creating DH Parms...\";</script>";

	    execute_command_return_output("cd /tmp/ && openssl dhparam -out dh1024.pem 1024");

	    echo "\n<script language=\"JavaScript\">document.forms[0].status.value=\"Done!\";</script>";

	    //CLIENT
	    //mwexec("openssl req -nodes -new -keyout home.key -out home.csr");
	    //mwexec("openssl ca -out home.crt -in home.csr");

	    $cacertA     = get_file_contents("/tmp/cacert.pem");
	    $serverkeyA  = get_file_contents("/tmp/vpnkey.pem");
	    $servercertA = get_file_contents("/tmp/vpncert.pem");
	    $dhpemA      = get_file_contents("/tmp/dh1024.pem");

	    $cacert     = ereg_replace("\n","\\n", $cacertA);
	    $serverkey  = ereg_replace("\n","\\n", $serverkeyA);
	    $dhpem      = ereg_replace("\n","\\n", $dhpemA);
	    //$servercert = ereg_replace("\n","\\n", $servercertA);

	    $tmp = strstr($servercertA, "-----BEGIN CERTIFICATE-----");
	    $servercert = ereg_replace("\n","\\n", $tmp);

	?>
	<script language="JavaScript">
	<!--
	    var ca_cert     ='<?= $cacert ?>';
	    var srv_key     ='<?= $serverkey ?>';
	    var srv_cert    ='<?= $servercert ?>';
	    var dh_param    ='<?= $dhpem ?>';
	    opener.document.forms[0].ca_cert.value=ca_cert;
	    opener.document.forms[0].srv_key.value=srv_key;
	    opener.document.forms[0].srv_cert.value=srv_cert;
	    opener.document.forms[0].dh_param.value=dh_param;
	    this.close();
	-->
	</script>


<?php

} else {

?>

    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
    <html>
    <head>
    <title><?=gentitle("OpenVPN: Create Certificates");?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
    <link href="gui.css" rel="stylesheet" type="text/css">
    <body link="#0000CC" vlink="#0000CC" alink="#0000CC">
    <form action="vpn_openvpn_create_certs.php" method="post" name="iform" id="iform">
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
