<?php
/*
	restart_httpd.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005 Bill Marquette <bill.marquette@gmail.com>
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
/*
	pfSense_BUILDER_BINARIES:	/bin/chmod
	pfSense_MODULE:	pkgs
*/

##|+PRIV
##|*IDENT=page-diagnostics-restart-httpd
##|*NAME=Diagnostics: Restart Web Server Daemon
##|*DESCR=Allow access to the 'Diagnostics: Restart Web Server Daemon' page.
##|*MATCH=restart_httpd.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Restarting httpd"));
include("head.inc");
?>

<form>
<?php include("fbegin.inc"); ?>

<?=gettext("Mounting file systems read/write");?>...
<?php flush(); sleep(1); conf_mount_rw(); ?>
<?=gettext("Done");?>.<br />
<?=gettext("Forcing all PHP file permissions to 0755");?>...
<?php flush(); sleep(1); system('/bin/chmod -R 0755 /usr/local/www/*.php'); ?>
<?=gettext("Done");?>.<br />
<?=gettext("Mounting file systems read only");?>...
<?php flush(); sleep(1); conf_mount_ro(); ?>
<?=gettext("Done");?>.<br />
<?=gettext("Restarting mini_httpd");?>...
<?php flush(); sleep(1); system_webgui_start(); ?>
<?=gettext("Done");?>.<br />

<?php
include("foot.inc");
?>
