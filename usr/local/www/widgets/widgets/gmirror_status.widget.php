<?php
/*
    gmirror_status.widget.php
    Copyright (C) 2009-2010 Jim Pingle

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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("gmirror.inc");

if ($_GET['textonly'] == "true") {
	header("Cache-Control: no-cache");
	echo gmirror_html_status();
	exit;
}
?>
<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="gmirror status">
	<tbody id="gmirror_status_table">
		<?php echo gmirror_html_status(); ?>
	</tbody>
</table>

<script type="text/javascript">
//<![CDATA[
	var gmirrorupdater = new Ajax.PeriodicalUpdater('gmirror_status_table', '/widgets/widgets/gmirror_status.widget.php?textonly=true',
	  { method: 'get', frequency: 5 } );
//]]>
</script>
