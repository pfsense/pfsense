<?php
/*
	pfSense-utils.inc
	Utilities specific to pfSense
	part of pfSense (www.pfSense.com)

	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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
 * log_error: send string to syslog
 */ 
function log_error($error) {
    syslog(LOG_WARNING, $error);
    return;
}

/*
 * return_dir_as_array($dir): returns $dir contents as an array
 */
function return_dir_as_array($dir) {
    $dir_array = array();
    if (is_dir($dir)) {
	if ($dh = opendir($dir)) {
	    while (($file = readdir($dh)) !== false) {
		$canadd = 0;
		if($file == ".") $canadd = 1;
		if($file == "..") $canadd = 1;
		if($canadd == 0)
		    array_push($dir_array, $file);
	    }
	    closedir($dh);
	}
    }
    return $dir_array;
}

/*
 * return_dir_as_array($filenaem): returns $filename contents as a string
 */
function return_filename_as_string($filename) {
    $tmp = "";
    $fd = fopen($filename, "r");
    while(!feof($fd)) {
	$tmp .= fread($fd,49);
    }
    fclose($fd);
    return $tmp;
}

/*
 * is_carp_defined: returns true if carp is detected in kernel
 */
function is_carp_defined() {
	/* is carp compiled into the kernel and userland? */
	$command = "/sbin/sysctl -a | grep carp";
	$fd = popen($command . " 2>&1 ", "r");
	if(!$fd) {
		log_error("Warning, could not execute command ");
		return 0;
	}
	while(!feof($fd)) {
		    $tmp .= fread($fd,49);
	}
	fclose($fd);

	if($tmp == "")
		return false;
	else
		return true;
}

?>