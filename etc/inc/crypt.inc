<?php

/* $Id$ */
/*
    Copyright (C) 2008 Shrew Soft Inc
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

	pfSense_BUILDER_BINARIES:	/usr/bin/openssl
	pfSense_MODULE:	crypto

    DISABLE_PHP_LINT_CHECKING
*/

	function crypt_data($val, $pass, $opt) {
		$file = tempnam("/tmp", "php-encrypt");
		file_put_contents("{$file}.dec", $val);
		exec("/usr/bin/openssl enc {$opt} -aes-256-cbc -in {$file}.dec -out {$file}.enc -k " . escapeshellarg($pass));
		if (file_exists("{$file}.enc"))
			$result = file_get_contents("{$file}.enc");
		else {
			$result = "";
			log_error("Failed to encrypt/decrypt data!");
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

		if (($btag_pos === false) || ($etag_pos === false))
			return false;

		$body_pos = $btag_pos + $btag_len;
		$body_len = strlen($in);
		$body_len -= $btag_len;
		$body_len -= $etag_len + 1;

		$out = substr($in, $body_pos, $body_len);

		return true;
	}

?>
