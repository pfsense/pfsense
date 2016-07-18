<?php
/*
 * diag_sockets.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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

$showAll = isset($_GET['showAll']);
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
	if (isset($_GET['showAll'])) {
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
print_info_box(gettext('Socket information - explanation.') . '<br /><br />' .
gettext('This page shows the output for the commands: "sockstat -4lL" and "sockstat -6lL".' . '<br />' .
		'Or in case of showing all sockets the output for: "sockstat -4" and "sockstat -6".' . '<br />' . '<br />' .
'The information listed for each socket is:' . '<br /><br />' .
' <dl class="dl-horizontal responsive">' .
				'<dt>USER</dt>			<dd>The user who owns the socket.</dd>' .
				'<dt>COMMAND</dt>		<dd>The command which holds the socket.</dd>' .
				'<dt>PID</dt>			<dd>The process ID of the command which holds the socket.</dd>' .
				'<dt>FD</dt>				<dd>The file descriptor number of the socket.</dd>' .
				'<dt>PROTO</dt>			<dd>The transport protocol associated with the socket for Internet sockets, or the type of socket (stream or data-gram) for UNIX sockets.</dd>' .
				'<dt>ADDRESS</dt>		<dd>(UNIX sockets only) For bound sockets, this is the file-name of the socket. For other sockets, it is the name, PID and file descriptor number of the peer, or "(none)" if the socket is neither bound nor connected.</dd>' .
				'<dt>LOCAL ADDRESS</dt> <dd>(Internet sockets only) The address the local end of the socket is bound to (see getsockname(2)).</dd>' .
				'<dt>FOREIGN ADDRESS</dt><dd>(Internet sockets only) The address the foreign end of the socket is bound to (see getpeername(2)).</dd>' .
			'</dl>'), 'info', false);
?>
</div>
</div>
<?php

include('foot.inc');


