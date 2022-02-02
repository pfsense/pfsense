<?php
/*
 * meta.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Shrew Soft Inc
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * The meta data format used in pfSense is denoted using markers
 * followed by the appropriate value or value pair. All markers
 * are prefixed with a ##| sequence. The + suffix is used to
 * denote the beginning of a tag block followed by the tag name.
 * A - suffix is used to denote the end of a tag block. Values
 * are denoted using the * suffix and can optionally be expressed
 * as a key value pair. An example of a metadata tag block ...
 *
 * ###|+INFO
 * ###|*BLAH
 * ###|*TEXT=SOME TEXT
 * ###|-INFO
 *
 * After calling read_file_metadata, the result array would
 * contain the following information ...
 *
 * metadata['<filename>']['INFO']['BLAH'][0] == true
 * metadata['<filename>']['INFO']['TEXT'][0] == "SOME TEXT"
 *
 * NOTE: All statements must be at the beginning of a line and
 * contiguous for a tag. The example shown above would not be
 * processed due to the extra ' * ' comment chars.
 *
 */

/*
 * locate php files for a given path
 */

function list_phpfiles($path, & $found) {

	if (!is_array($found)) {
		$found = array();
	}

	$dir = opendir($path);
	if (!$dir) {
		printf(gettext("list_phpfiles: unable to examine path %s") . "\n", $path);
		return;
	}

	while ($fname = readdir($dir)) {
		if ($fname == "." || $fname == ".." || $fname[0] == '.') {
			continue;
		}
		if (fnmatch('*.php', $fname)) {
			$found[] = $fname;
		}
	}
}

/*
 * read embedded metadata from a file
 */

function read_file_metadata($fpath, & $metadata, $taglist = false) {

	if (!is_array($metadata)) {
		$metadata = array();
	}

	if ($taglist) {
		$taglist = explode(",", $taglist);
	}

	$fname = $fpath;
	$slash = strrpos($fname, "/");
	if ($slash) {
		$fname = substr($fname, $slash + 1);
	}

	$fdata = @file_get_contents($fpath);
	if (!$fdata) {
		printf(gettext("unable to read %s") . "\n", $fpath);
		return;
	}

	$offset = 0;

	$tags = array();

	while (true) {

		$tagbeg_off = stripos($fdata, "##|+", $offset);
		if ($tagbeg_off === false) {
			break;
		}

		$tagbeg_trm = stripos($fdata, "\n", $tagbeg_off);
		if ($tagbeg_trm === false) {
			break;
		}

		$tagend_off = stripos($fdata, "##|-", $tagbeg_trm);
		if ($tagend_off === false) {
			break;
		}

		$tagend_trm = stripos($fdata, "\n", $tagend_off);
		if ($tagend_trm === false) {
			break;
		}

		$tagbeg_len = $tagbeg_trm - $tagbeg_off;
		$tagend_len = $tagend_trm - $tagend_off;

		$tagbeg = substr($fdata, $tagbeg_off + 4, $tagbeg_len - 4);
		$tagend = substr($fdata, $tagend_off + 4, $tagend_len - 4);

		if ($tagbeg != $tagend) {
			printf(gettext("error: tag mismatch ( %1\$s != %2\$s ) in '%3\$s'%4\$s"), $tagbeg, $tagend, $fpath, "\n");
			break;
		}

		$mdata_off = $tagbeg_trm + 1;
		$mdata_trm = $tagend_off - 1;
		$mdata_len = $mdata_trm - $mdata_off;

		$mdata = substr($fdata, $mdata_off, $mdata_len);

		if (!strlen($mdata)) {
			printf(gettext("warning: tag %1\$s has no data in '%2\$s'%3\$s"), $tagbeg, $fpath, "\n");
			break;
		}

		$offset = $tagend_trm + 1;

		if (is_array($taglist)) {
			if (!in_array($tagbeg, $taglist)) {
				continue;
			}
		}

		$vals = array();

		$lines = explode("\n", $mdata);
		foreach ($lines as $line) {

			if (!strlen($line)) {
				continue;
			}

			$valtag = stripos($line, "##|*");
			if ($valtag === false || $valtag) {
				printf(gettext("warning: tag %1\$s has malformed data in '%2\$s'%3\$s"), $tagbeg, $fpath, "\n");
				continue;
			}

			$value = substr($line, 4, strlen($line) - 1);
			$vlist = explode("=", $value);

			unset($vname);
			unset($vdata);

			switch (count($vlist)) {
				case 1:
					$vname = $vlist[0];
					$vdata = true;
					break;
				case 2:
					$vname = $vlist[0];
					$vdata = $vlist[1];
					break;
			}

			if (!isset($vname) || !isset($vdata)) {
				printf(gettext("warning: tag %1\$s has invalid data in '%2\$s'%3\$s"), $tagbeg, $fpath, "\n");
				continue;
			}

			$vals[$vname][] = $vdata;
		}

		if (count($vals)) {
			$tags[$tagbeg] = $vals;
		}
	}

	if (count($tags)) {
		$metadata[$fname] = $tags;
	}
}

?>
