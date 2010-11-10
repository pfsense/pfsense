<?php
/* $Id$ */
/*
    functions.inc
    Copyright (C) 2004-2006 Scott Ullrich
    All rights reserved.

    originally part of m0n0wall (http://m0n0.ch/wall)
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

	pfSense_MODULE:	utils

*/

/* BEGIN compatibility goo with HEAD */
if(!function_exists("gettext")) {
	function gettext($text) {
		return $text;
	}
}

if(!function_exists("pfSenseHeader")) {
	/****f* pfsense-utils/pfSenseHeader
	 * NAME
	 *   pfSenseHeader
	 * INPUTS
	 *   none
	 * RESULT
	 *   Javascript header change or browser Location:
	 ******/
	function pfSenseHeader($text) {
	        global $_SERVER;
	        if (isAjax()) {
	                if ($_SERVER['HTTPS'] == "on")
	                        $protocol = "https";
	                else
	                        $protocol = "http";
	
	                $port = ":{$_SERVER['SERVER_PORT']}";
	                if ($_SERVER['SERVER_PORT'] == "80" && $protocol == "http")
	                        $port = "";
	                if ($_SERVER['SERVER_PORT'] == "443" && $protocol == "https")
	                        $port = "";
	                $complete_url = "{$protocol}://{$_SERVER['SERVER_NAME']}{$port}/{$text}";
	                echo "\ndocument.location.href = '{$complete_url}';\n";
	        } else {
	                Header("Location: $text");
	        }
	}
}
/* END compatibility goo with HEAD */

/* include all configuration functions */
require_once("interfaces.inc");
require_once("gwlb.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");
require_once("certs.inc");
require_once("system.inc");
require_once("vslb.inc");

?>