<?php
/* $Id$ */
/*
	crash_reporter.php
	part of pfSense
	Copyright (C) 2011 Scott Ullrich
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
	pfSense_MODULE:	header
*/

##|+PRIV
##|*IDENT=page-diagnostics-crash-reporter
##|*NAME=Crash reporter
##|*DESCR=Uploads crash reports to pfSense and or deletes crash reports.
##|*MATCH=crash_reporter.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require("captiveportal.inc");
require("./classes/CrashReporter.inc");

define("FILE_SIZE", 450000);

$pgtitle = array(gettext("Diagnostics"),gettext("Crash reporter"));

require('head.inc');

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php require("fbegin.inc"); ?>

	<form action="crash_reporter.php" method="post">

<?php
	$crash_reporter = new CrashReporter();
	$crash_reporter->get_php_errors();
	$crash_reporter->prepare_report_header();
	$crash_reporter->get_crash_files();
	
	if (gettext($_POST['Submit']) == "Yes") {
		echo gettext("Processing...");
	$crash_reporter->submit_form();
		echo "<p/>";
		echo gettext("Uploading...");
		ob_flush();
		flush();
		if(count($crash_reporter->files_to_upload) > 0 ) {
                        $resp = $crash_reporter->upload();
                        $crash_reporter->purge_crash_logs();
			echo "<p/>";
			print_r($resp);
			echo "<p/><a href='/'>" . gettext('Continue') . 
                                "</a>" . gettext('and delete crash report files from local disk.');
		} else {
			echo "Could not find any crash files.";
		}
	} else if(gettext($_POST['Submit']) == "No") {
	$crash_reporter->purge_crash_logs();
	Header("Location: /");
		exit;
	} else {
	$crash_reporter->prepare_report();
	//glob() gives an empty array if path is not found
	if(count($crash_reporter->crash_files) <= 0) {
	echo "Could not find any crash files."; 
	}            
	echo $crash_reporter->output_html();
	}
?>

<?php include("fend.inc"); ?>

</body>
</html>
