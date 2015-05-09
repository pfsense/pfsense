<?php
/* $Id$ */
/*
	Exec+ v1.02-000 - Copyright 2001-2003, All rights reserved
	Created by André Ribeiro and Hélder Pereira

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
	pfSense_MODULE: shaper
*/

##|+PRIV
##|*IDENT=page-diagnostics-patters
##|*NAME=Diagnostics: Patterns page
##|*DESCR=Allow access to the 'Diagnostics: Patterns' page.
##|*MATCH=patterns.php*
##|-PRIV

require("guiconfig.inc");

// Defining this here ensures that both instances (button name and POST check) are identical
$buttonlabel = gettext("Upload Pattern file");

//Move the upload file to /usr/local/share/protocols (is_uploaded_file must use tmp_name as argument)
if (($_POST['submit'] == $buttonlabel) && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
	if(fileExtension($_FILES['ulfile']['name'])) {
		if (!is_array($config['l7shaper']['custom_pat']))
			$config['l7shaper']['custom_pat'] = array();

		$config['l7shaper']['custom_pat'][$_FILES['ulfile']['name']] = base64_encode(file_get_contents($_FILES['ulfile']['tmp_name']));
		write_config(sprintf(gettext("Added custom l7 pattern %s"), $_FILES['ulfile']['name']));
		move_uploaded_file($_FILES['ulfile']['tmp_name'], "/usr/local/share/protocols/" . $_FILES['ulfile']['name']);
		$ulmsg = gettext("Uploaded file to") . " /usr/local/share/protocols/" . htmlentities($_FILES['ulfile']['name']);
		$class = 'alert-success';
	}
	else {
		$ulmsg = gettext("Error: You must upload a file with .pat extension.");
		$class = 'alert-danger';
	}
}

//Check if file has correct extension (.pat)
function fileExtension($nameFile) {
	$format = substr($nameFile, -4);
	return ($format == ".pat");
}

$pgtitle = array(gettext("Diagnostics"), gettext("Add layer7 pattern"));
include("head.inc");

if($ulmsg)
	print_info_box($ulmsg, $class);

require('classes/Form.class.php');

$form = new Form(new Form_Button(
	submit,
	$buttonlabel,
	null
));

$form->setMultipartEncoding();

$section = new Form_Section('Upload Layer7 pattern file');

$filepicker = new Form_Input(
	'ulfile',
	'File to upload',
	'file',
	''
);

$section->addInput($filepicker)->setHelp('Choose the file you wish to upload (*.pat)');

$form->add($section);
print($form);

include("foot.inc");