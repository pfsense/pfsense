<?php
/*
 * diag_defaults.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-diagnostics-factorydefaults
##|*NAME=Diagnostics: Factory defaults
##|*DESCR=Allow access to the 'Diagnostics: Factory defaults' page.
##|*WARN=standard-warning-root
##|*MATCH=diag_defaults.php*
##|-PRIV

require_once("guiconfig.inc");

if ($_POST['Submit'] == " " . gettext("No") . " ") {
	header("Location: index.php");
	exit;
}

$pgtitle = array(gettext("Diagnostics"), gettext("Factory Defaults"));
include("head.inc");
?>

<?php if ($_POST['Submit'] == " " . gettext("Yes") . " "):
	print_info_box(gettext("The system has been reset to factory defaults and is now rebooting. This may take a few minutes, depending on the hardware."))?>
<pre>
<?php
	reset_factory_defaults();
	system_reboot();
?>
</pre>
<?php else:?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Factory Defaults Reset")?></h2>
	</div>
	<div class="panel-body">
		<div class="content">
			<form action="diag_defaults.php" method="post">
				<p><strong><?=gettext('Resetting the system to factory defaults will remove all user configuration and apply the following settings:')?></strong></p>
				<ul>
					<li><?=gettext("Reset to factory defaults")?></li>
					<li><?=gettext("LAN IP address will be reset to 192.168.1.1")?></li>
					<li><?=gettext("System will be configured as a DHCP server on the default LAN interface")?></li>
					<li><?=gettext("Reboot after changes are installed")?></li>
					<li><?=gettext("WAN interface will be set to obtain an address automatically from a DHCP server")?></li>
					<li><?=gettext("webConfigurator admin username will be reset to 'admin'")?></li>
					<li><?=sprintf(gettext("webConfigurator admin password will be reset to '%s'"), $g['factory_shipped_password'])?></li>
				</ul>
				<p><strong><?=gettext("Are you sure you want to proceed?")?></strong></p>
				<p>
					<button name="Submit" type="submit" class="btn btn-sm btn-danger" value=" <?=gettext("Yes")?> " title="<?=gettext("Perform a factory reset")?>">
						<i class="fa fa-undo"></i>
						<?=gettext("Factory Reset")?>
					</button>
					<button name="Submit" type="submit" class="btn btn-sm btn-success" value=" <?=gettext("No")?> " title="<?=gettext("Return to the dashboard")?>">
						<i class="fa fa-save"></i>
						<?=gettext("Keep Configuration")?>
					</button>
				</p>
			</form>
		</div>
	</div>
</div>
<?php endif?>
<?php include("foot.inc")?>
