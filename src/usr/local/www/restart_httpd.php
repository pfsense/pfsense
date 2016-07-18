<?php
/*
 * restart_httpd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
<?php include_once("fbegin.inc"); ?>

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
