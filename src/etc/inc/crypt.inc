<?php
/*
 * crypt.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc. All rights reserved.
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

	function crypt_data($val, $pass, $opt) {
		$file = tempnam("/tmp", "php-encrypt");
		file_put_contents("{$file}.dec", $val);
		exec("/usr/bin/openssl enc {$opt} -aes-256-cbc -in {$file}.dec -out {$file}.enc -k " . escapeshellarg($pass));
		if (file_exists("{$file}.enc")) {
			$result = file_get_contents("{$file}.enc");
		} else {
			$result = "";
			log_error(gettext("Failed to encrypt/decrypt data!"));
		}
		@unlink($file);
		@unlink("{$file}.dec");
		@unlink("{$file}.enc");
		return $result;
	}

	function encrypt_data(& $data, $pass) {
		return base64_encode(crypt_data($data, $pass, "-e"));
	}

	function decrypt_data(& $data, $pass) {
		return crypt_data(base64_decode($data), $pass, "-d");
	}

	function tagfile_reformat($in, & $out, $tag) {

		$out = "---- BEGIN {$tag} ----\n";

		$size = 80;
		$oset = 0;
		while ($size >= 64) {
			$line = substr($in, $oset, 64);
			$out .= $line."\n";
			$size = strlen($line);
			$oset += $size;
		}

		$out .= "---- END {$tag} ----\n";

		return true;
	}

	function tagfile_deformat($in, & $out, $tag) {

		$btag_val = "---- BEGIN {$tag} ----";
		$etag_val = "---- END {$tag} ----";

		$btag_len = strlen($btag_val);
		$etag_len = strlen($etag_val);

		$btag_pos = stripos($in, $btag_val);
		$etag_pos = stripos($in, $etag_val);

		if (($btag_pos === false) || ($etag_pos === false)) {
			return false;
		}

		$body_pos = $btag_pos + $btag_len;
		$body_len = strlen($in);
		$body_len -= $btag_len;
		$body_len -= $etag_len + 1;

		$out = substr($in, $body_pos, $body_len);

		return true;
	}

?>
