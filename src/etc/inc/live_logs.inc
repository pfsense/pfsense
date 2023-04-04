<?php
/*
 * live_logs.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2023 Rubicon Communications, LLC (Netgate)
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
 * Uses bytes to read the file unless line number is specified; reading using
 * bytes is slightly quicker, but line numbers can be helpful.
 */
function checkForAjaxLog($file) {
	$response = [];
	$maxRead = (isset($_REQUEST['maxRead'])) ? $_REQUEST['maxRead'] : null;

	if (isset($_REQUEST['line'])) {
		$response['linesRead'] = 0;
		$response['output'] = '';

		if ($maxRead != 0)  {
			$line = $_REQUEST['line'];
			$fp = new SplFileObject($file);
			$fp->seek($line);
			for ($i = 0; !$fp->eof(); $i++) {
				$response['output'] .= $fp->current();
				$fp->next();
				$response['linesRead']++;
				if ($maxRead != null && $maxRead >= $response['linesRead']) {
					break;
				}
			}
			$fp = null;
		}
	} else {
		$response['bytesRead'] = 0;
		$response['output'] = '';

		if ($maxRead != 0) {
			$byte = (isset($_REQUEST['byte'])) ? $_REQUEST['byte'] : 0;
			$fp = fopen($file, 'rb');
			fseek($fp, $byte);

			if ($maxRead != null) {
				$response['output'] = fread($fp, $maxRead);
			} else {
				while (!feof($fp)) {
					$response['output'] .= fread($fp, 8192);
				}
			}

			$response['bytesRead'] = ftell($fp) - $byte;
			fclose($fp);
		}
	}

	echo json_encode($response);
}
