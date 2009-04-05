<?php
/*
    gmirror_status.widget.php
    Copyright (C) 2009 Jim Pingle

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once("/usr/local/www/widgets/include/gmirror_status.inc");

$mirrors = get_gmirror_status();

?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tbody>
<?php if (count($mirrors) > 0) { ?>
		<tr>
			<td width="40%" class="vncellt">Name</td>
			<td width="40%" class="vncellt">Status</td>
			<td width="20%" class="vncellt">Component</td>
		</tr>
	<?php foreach ($mirrors as $mirror => $name) { ?>
		<tr>
			<td width="40%" rowspan="<?= count($name["components"]) ?>" class="listr"><?= $name["name"] ?></td>
			<td width="40%" rowspan="<?= count($name["components"]) ?>" class="listr"><?= $name["status"] ?></td>
			<td width="20%" class="listr"><?= $name["components"][0] ?></td>
		</tr>
		<?php
		if (count($name["components"]) > 1) {
			$morecomponents = array_slice($name["components"], 1);
			foreach ($morecomponents as $component) { ?>
		<tr>
			<td width="20%" class="listr"><?= $component ?></td>
		</tr>
		<?php	}
		} ?>
	<?php } ?>
<?php } else { ?>
		<tr><td colspan="3" class="listr">No Mirrors Found</td></tr>
<?php } ?>
	</tbody>
</table>
