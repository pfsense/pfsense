<?php
/*
 * diag_sockets.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-sockets
##|*NAME=Diagnostics: Sockets
##|*DESCR=Allow access to the 'Diagnostics: Sockets' page.
##|*MATCH=diag_sockets.php*
##|-PRIV

require_once('guiconfig.inc');

$pgtitle = array(gettext("Diagnostics"), gettext("Sockets"));

include('head.inc');

$showAll = isset($_REQUEST['showAll']);
$showAllText = $showAll ? gettext("Show only listening sockets") : gettext("Show all socket connections");
$showAllOption = $showAll ? "" : "?showAll";

?>
<button class="btn btn-info btn-sm" type="button" value="<?=$showAllText?>" onclick="window.location.href='diag_sockets.php<?=$showAllOption?>'">
	<i class="fa fa-<?= ($showAll) ? 'minus-circle' : 'plus-circle' ; ?> icon-embed-btn"></i>
	<?=$showAllText?>
</button>
<br />
<br />

<?php
	if (isset($_REQUEST['showAll'])) {
		$internet4 = shell_exec('sockstat -4');
		$internet6 = shell_exec('sockstat -6');
	} else {
		$internet4 = shell_exec('sockstat -4l');
		$internet6 = shell_exec('sockstat -6l');
	}


	foreach (array(&$internet4, &$internet6) as $tabindex => $table) {
		$elements = ($tabindex == 0 ? 7 : 7);
		$name = ($tabindex == 0 ? 'IPv4' : 'IPv6');
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=$name?> <?=gettext("System Socket Information")?></h2></div>
	<div class="panel-body">
		<div class="table table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
<?php
					foreach (explode("\n", $table) as $i => $line) {
						if (trim($line) == "") {
							continue;
						}

						$j = 0;
						print("<tr>\n");
						foreach (explode(' ', $line) as $entry) {
							if ($entry == '' || $entry == "ADDRESS") {
								continue;
							}

							if ($i == 0) {
								print("<th class=\"$class\">$entry</th>\n");
							} else {
								print("<td class=\"$class\">$entry</td>\n");
							}

							$j++;
						}
						print("</tr>\n");
						if ($i == 0) {
							print("</thead>\n");
							print("<tbody>\n");
						}
					}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php
	}
?>

<div>
<div class="infoblock">
<?php
print_info_box(
	gettext('Socket Information') .
		'<br /><br />' .
		sprintf(gettext('This page shows all listening sockets by default, and shows both listening and outbound connection sockets when %1$sShow all socket connections%2$s is clicked.'), '<strong>', '</strong>') .
		'<br /><br />' .
		gettext('The information listed for each socket is:') .
		'<br /><br />' .
		'<dl class="dl-horizontal responsive">' .
		sprintf(gettext('%1$sUSER%2$s	%3$sThe user who owns the socket.%4$s'), '<dt>', '</dt>', '<dd>', '</dd>') .
		sprintf(gettext('%1$sCOMMAND%2$s	%3$sThe command which holds the socket.%4$s'), '<dt>', '</dt>', '<dd>', '</dd>') .
		sprintf(gettext('%1$sPID%2$s	%3$sThe process ID of the command which holds the socket.%4$s'), '<dt>', '</dt>', '<dd>', '</dd>') .
		sprintf(gettext('%1$sFD%2$s	%3$sThe file descriptor number of the socket.%4$s'), '<dt>', '</dt>', '<dd>', '</dd>') .
		sprintf(gettext('%1$sPROTO%2$s	%3$sThe transport protocol associated with the socket.%4$s'), '<dt>', '</dt>', '<dd>', '</dd>') .
		sprintf(gettext('%1$sLOCAL ADDRESS%2$s	%3$sThe address the local end of the socket is bound to.%4$s'), '<dt>', '</dt>', '<dd>', '</dd>') .
		sprintf(gettext('%1$sFOREIGN ADDRESS%2$s	%3$sThe address the foreign end of the socket is bound to.%4$s'), '<dt>', '</dt>', '<dd>', '</dd>') .
		'</dl>',
	'info',
	false);
?>
</div>
</div>
<?php

include('foot.inc');
