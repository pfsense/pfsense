<?php
/*
    getstats.php
    Copyright (C) 2009 Bill Marquette
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
	pfSense_MODULE:	ajax
*/

##|+PRIV
##|*IDENT=page-getstats
##|*NAME=AJAX: Get Stats
##|*DESCR=Allow access to the 'AJAX: Get Stats' page.
##|*MATCH=getstats.php*
##|-PRIV

header("Last-Modified: " . gmdate( "D, j M Y H:i:s" ) . " GMT" );
header("Expires: " . gmdate( "D, j M Y H:i:s", time() ) . " GMT" );
header("Cache-Control: no-store, no-cache, must-revalidate" ); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", FALSE );
header("Pragma: no-cache"); // HTTP/1.0

require_once("guiconfig.inc");
include_once("includes/functions.inc.php");

echo get_stats();

?>