<?php
/*
 * crypt.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

define('PFS_OPENSSL_DEFAULT_ITERATIONS', '500000');

	function crypt_data($val, $pass, $opt, $legacy = false, $iterations = PFS_OPENSSL_DEFAULT_ITERATIONS) {
		$file = tempnam("/tmp", "php-encrypt");
		/* Ensure the files do not already exist */
		unlink_if_exists($file);
		unlink_if_exists("{$file}.dec");
		unlink_if_exists("{$file}.enc");

		file_put_contents("{$file}.dec", $val);

		/* Use PBKDF2 Key Derivation (https://en.wikipedia.org/wiki/PBKDF2)
		 *  unless we need to read old data encrypted without it. */
		$keyder = ($legacy) ? "" : "-pbkdf2";
		$md = ($legacy) ? "md5" : "sha256";
		$iter = ($legacy) ? '' : ' -iter ' . escapeshellarg($iterations);

		$output = "";
		$exitcode = "";
		exec("/usr/bin/openssl enc {$opt} -aes-256-cbc -in {$file}.dec -out {$file}.enc -pass pass:" . escapeshellarg($pass) . " -salt -md ${md} {$keyder} {$iter} 2> /dev/null", $output, $exitcode);

		if (($exitcode == 0) && file_exists("{$file}.enc") && (filesize("{$file}.enc") > 0)) {
			$result = file_get_contents("{$file}.enc");
		} elseif ($iterations == PFS_OPENSSL_DEFAULT_ITERATIONS) {
			/* If it failed with the current default iterations,
			 * next try with previous default number of iterations. */
			$result = crypt_data($val, $pass, $opt, false, '10000');
		} elseif ($legacy === false) {
			/* Operation failed without new options, try old. */
			$result = crypt_data($val, $pass, $opt, true);
		} else {
			$result = "";
			log_error(gettext("Failed to encrypt/decrypt data!"));
		}

		/* Cleanup */
		unlink_if_exists($file);
		unlink_if_exists("{$file}.dec");
		unlink_if_exists("{$file}.enc");
		return $result;
	}

	function encrypt_data(& $data, $pass, $legacy = false) {
		return base64_encode(crypt_data($data, $pass, "-e", $legacy));
	}

	function decrypt_data(& $data, $pass, $legacy = false) {
		return crypt_data(base64_decode($data), $pass, "-d", $legacy);
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
