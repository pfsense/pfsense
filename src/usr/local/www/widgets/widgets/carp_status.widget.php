<?php
/*
	carp_status.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (*)  2007 Sam Wenham
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/carp_status.inc");

$carp_enabled = get_carp_status();

?>
<div class="content">
<table>
<?php
	if (is_array($config['virtualip']['vip'])) {
		$carpint=0;
		foreach ($config['virtualip']['vip'] as $carp) {
			if ($carp['mode'] != "carp") {
				continue;
			}
			$ipaddress = $carp['subnet'];
			$password = $carp['password'];
			$netmask = $carp['subnet_bits'];
			$vhid = $carp['vhid'];
			$advskew = $carp['advskew'];
			$status = get_carp_interface_status("_vip{$carp['uniqid']}");
?>
<tr>
	<td>
		<i class="fa fa-inbox"></i>
		<a href="/system_hasync.php">
			<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($carp['interface']) . "@{$vhid}");?>
		</a>
	</td>
	<td>
<?php
			if ($carp_enabled == false) {
				$status = "DISABLED";
				echo '<i class="fa fa-ban"></i>';
			} else {
				if ($status == "MASTER") {
					echo '<i class="fa fa-arrow-right"></i>';
				} else if ($status == "BACKUP") {
					echo '<i class="fa fa-arrow-right"></i>';
				} else if ($status == "INIT") {
					echo '<i class="fa fa-list-alt"></i>';
				}
			}
			if ($ipaddress) {
?>
				&nbsp;
				<?=htmlspecialchars($status);?> &nbsp;
				<?=htmlspecialchars($ipaddress);?>
<?php
			}
?>
</td></tr>
<?php
		}
	} else {
?>
		<tr><td>No CARP Interfaces Defined. Click <a href="status_carp.php">here</a> to configure CARP.</td></tr>
<?php
	}
?>
</table>
</div>
