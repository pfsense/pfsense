<?php
/* $Id$ */
/*
	diag_defaults.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved. 
 *  Copyright (c)  2004, 2005 Scott Ullrich
 *
 *  Redistribution and use in source and binary forms, with or without modification, 
 *  are permitted provided that the following conditions are met: 
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution. 
 *
 *  3. All advertising materials mentioning features or use of this software 
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/). 
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
  *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
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

if ($_POST['Submit'] == " " . gettext("No") . " ") {
	header("Location: index.php");
	exit;
}

$pgtitle = array(gettext("Diagnostics"), gettext("Factory defaults"));
include("head.inc");
?>

<?php if ($_POST['Submit'] == " " . gettext("Yes") . " "):
	print_info_box(gettext("The system has been reset to factory defaults and is now rebooting. This may take a few minutes, depending on your hardware."))?>
<pre>
<?php
	reset_factory_defaults();
	system_reboot();
?>
</pre>
<?php else:?>
<form action="diag_defaults.php" method="post">
	<p><strong><?=gettext("If you click") . " &quot;" . gettext("Yes") . "&quot;, " . gettext("the firewall will:")?></strong></p>
	<ul>
		<li><?=gettext("Reset to factory defaults")?></li>
		<li><?=gettext("LAN IP address will be reset to 192.168.1.1")?></li>
		<li><?=gettext("System will be configured as a DHCP server on the default LAN interface")?></li>
		<li><?=gettext("Reboot after changes are installed")?></li>
		<li><?=gettext("WAN interface will be set to obtain an address automatically from a DHCP server")?></li>
		<li><?=gettext("webConfigurator admin username will be reset to 'admin'")?></li>
		<li><?=gettext("webConfigurator admin password will be reset to")?> '<?=$g['factory_shipped_password']?>'</li>
	</ul>
	<p><strong><?=gettext("Are you sure you want to proceed?")?></strong></p>
	<p>
		<input name="Submit" type="submit" class="btn btn-sm btn-success" value=" <?=gettext("Yes")?> " />
		<input name="Submit" type="submit" class="btn btn-sm btn-default" value=" <?=gettext("No")?> " />
	</p>
</form>
<?php endif?>
<?php include("foot.inc")?>