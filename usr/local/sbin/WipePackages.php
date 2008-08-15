<?php

/* $Id$ */
/*
	WipePackages.php
	part of the pfSense project
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
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

if(!function_exists("readline")) {
	echo "\nThis script requires the readline() libary which is not present on this system.";
	echo "\n\nSorry, but we cannot continue.\n";
	die("Need readline() library");
}

require("functions.inc");
require("config.inc");

echo "\nThis script will wipe all installed packages off of your pfSense installation.\n";

$command = readline("\nAre you sure you would like to continue [y/N]? ");
if(strtoupper($command) == "Y" || strtoupper($command) == "YES") {

	$rmconfig = readline("\nWould you like to remove all package configuration information as well [y/N]? ");

	echo "\n\nStarting package wipe... One moment please... ";
	exec("cd /var/db/pkg/ && find . -exec 'pkg_delete {}' \; ");
	exec("rm -rf /var/db/pkg/*");
	
	if(strtoupper($rmconfig) == "Y" || strtoupper($rmconfig) == "YES") {
		echo "\nRemoving pfSense package configuration information...";
		if($config['installedpackages']['package']) {
			unset($config['installedpackages']['package']);
			write_config("Package wipe procedure completed.");
		}
		echo "\n";
	}
	
	echo "\npfSense package wipe procedure has completed.\n\n";
}

?>