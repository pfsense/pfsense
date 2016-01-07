<?php
/*
	diag_sockets.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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

##|+PRIV
##|*IDENT=page-diagnostics-sockets
##|*NAME=Diagnostics: Sockets
##|*DESCR=Allow access to the 'Diagnostics: Sockets' page.
##|*MATCH=diag_sockets.php*
##|-PRIV

include('guiconfig.inc');

$pgtitle = array(gettext("Diagnostics"), gettext("Sockets"));

include('head.inc');

$showAll = isset($_GET['showAll']);
$showAllText = $showAll ? "Show only listening sockets" : "Show all socket connections";
$showAllOption = $showAll ? "" : "?showAll";

?>
<input class="btn btn-info btn-sm" type="button" value="<?=$showAllText?>" onclick="window.location.href='diag_sockets.php<?=$showAllOption?>'"/>
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
	<div class="panel-heading"><h2 class="panel-title"><?=$name?> <?=gettext("system socket information")?></h2></div>
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
			'</dl>'), 'info');
?>
</div>
</div>
<?php

include('foot.inc');


