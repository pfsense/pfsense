<?php
/* $Id$ */
/*
	diag_defaults.php
	Copyright (C) 2004-2009 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
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

/*
	pfSense_MODULE:	config
*/

##|+PRIV
##|*IDENT=page-diagnostics-factorydefaults
##|*NAME=Diagnostics: Factory defaults page
##|*DESCR=Allow access to the 'Diagnostics: Factory defaults' page.
##|*MATCH=diag_defaults.php*
##|-PRIV

require("guiconfig.inc");

if ($_POST) {
	if ($_POST['Submit'] != " No ") {
		reset_factory_defaults();
		system_reboot();
		$rebootmsg = "The system has been reset to factory defaults and is now rebooting. This may take one minute.";
	} else {
		header("Location: index.php");
		exit;
	}
}

$pgtitle = array("Diagnostics","Factory defaults");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($rebootmsg): echo print_info_box($rebootmsg); else: ?>
<form action="diag_defaults.php" method="post">
              <p><strong>If you click &quot;Yes&quot;, the firewall will: 
	      
		<ul>
		  <li>Reset to factory defaults</li>
		  <li>LAN IP address will be reset to 192.168.1.1</li>
		  <li>System will be configured as a DHCP server on the default LAN interface</li>
		  <li>Reboot after changes are installed</li>
		  <li>WAN interface will be set to obtain an address automatically from a DHCP server</li>
		  <li>webConfigurator admin username will be reset to 'admin'</li>
		  <li>webConfigurator admin password will be reset to '<?=$g['product_name']?>'</li>
		</ul>
                Are you sure you want to proceed?</strong></p>
        <p>
          <input name="Submit" type="submit" class="formbtn" value=" Yes ">
          <input name="Submit" type="submit" class="formbtn" value=" No ">
        </p>
      </form>
<?php endif; ?>
<?php include("fend.inc"); ?>
</body>
</html>
