<?php

/* $Id$ */
/*
	diag_sockets.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2012
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
	pfSense_BUILDER_BINARIES:	/usr/bin/sockstat
*/
##|+PRIV
##|*IDENT=page-diagnostics-sockets
##|*NAME=Diagnostics: Sockets page
##|*DESCR=Allow access to the 'Diagnostics: Sockets' page.
##|*MATCH=diag_sockets.php*
##|-PRIV

include('guiconfig.inc');

$pgtitle = array(gettext("Diagnostics"),gettext("Sockets"));

include('head.inc');

?>

<?php include("fbegin.inc");

$showAll = isset($_GET['showAll']);
$showAllText = $showAll ? "Show only listening sockets" : "Show all socket connections";
$showAllOption = $showAll ? "" : "?showAll";

?>

<div class="panel panel-default">
	<div class="panel-heading">System socket information for both IPv4 and IPv6</div>
	<div class="panel-body">

(Click <a href="#about">here </a>for explanation of the information listed for each socket) <br /><br />
<input class="btn btn-info btn-xs" type="button" value="<?=$showAllText?>" onclick="window.location.href='diag_sockets.php<?=$showAllOption?>'"/>


<?php
	if (isset($_GET['showAll']))
	{
		$internet4 = shell_exec('sockstat -4');
		$internet6 = shell_exec('sockstat -6');
	} else {
		$internet4 = shell_exec('sockstat -4lL');
		$internet6 = shell_exec('sockstat -6lL');
	}
	foreach (array(&$internet4, &$internet6) as $tabindex => $table) {
		$elements = ($tabindex == 0 ? 7 : 7);
		$name = ($tabindex == 0 ? 'IPv4' : 'IPv6');
?>
<a name="<?=$name?>"></a>
<div class="table table-responsive">
<br />
<table class="table table-hover table-striped table-condensed summary="results">
	<thead>
		<tr>
			<th>
				<?=$name?>
			</th>
		</tr>
	</thead>
	<tbody>

<?php
		foreach (explode("\n", $table) as $i => $line) {
			if ($i == 0)
				$class = 'info';
			else
				$class = '';

			if (trim($line) == "")
				continue;

			print("<tr id=\"$name$i\">\n");
			$j = 0;
			foreach (explode(' ', $line) as $entry) {
				if ($entry == '' || $entry == "ADDRESS") continue;
				if ($i == 0)
					print("<th class=\"$class\">$entry</th>\n");
				else
					print("<td class=\"$class\">$entry</td>\n");
				if ($i > 0)
					$class = 'listr';
				$j++;
			}
			print("</tr>\n");
		}?>
	</tbody>
</table>

<?php
	}
?>
</div>
</div>

<a name="about"></a>
<div class="alert alert-success" role="alert">
	<div class="panel panel-default">
	<div class="panel-heading">Socket information - explanation</div>
		<div class="panel-body">

This page show the output for the commands: "sockstat -4lL" and "sockstat -6lL".<br />
Or in case of showing all sockets the output for: "sockstat -4" and "sockstat -6".<br />
			<br />
The information listed for each socket is:
			<br /><br />
			<table>
				<tr><td class="col-sm-3">USER			  </td><td>The user who owns the socket.</td></tr>
				<tr><td class="col-sm-3">COMMAND		  </td><td>The command which holds the socket.</td></tr>
				<tr><td class="col-sm-3">PID			  </td><td>The process ID of the command which holds the socket.</td></tr>
				<tr><td class="col-sm-3">FD				  </td><td>The file descriptor number of the socket.</td></tr>
				<tr><td class="col-sm-3">PROTO			  </td><td>The transport protocol associated with the socket for Internet sockets, or the type of socket (stream or data-gram) for UNIX sockets.</td></tr>
				<tr><td class="col-sm-3">ADDRESS		  </td><td>(UNIX sockets only) For bound sockets, this is the file-name of the socket.	For other sockets, it is the name, PID and file descriptor number of the peer, or ``(none)'' if the socket is neither bound nor connected.</td></tr>
				<tr><td class="col-sm-3">LOCAL ADDRESS	  </td><td>(Internet sockets only) The address the local end of the socket is bound to (see getsockname(2)).</td></tr>
				<tr><td class="col-sm-3">FOREIGN ADDRESS  </td><td>(Internet sockets only) The address the foreign end of the socket is bound to (see getpeername(2)).</td></tr>
			</table>
		</div>
	</div>
</div>
<?php

include('foot.inc');


