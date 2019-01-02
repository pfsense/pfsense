<?php
/*
 * auth_func.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2006 Paul Taylor <paultaylor@winn-dixie.com>.
 * Copyright (c) 2008 Shrew Soft Inc
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>.
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
 * Function put in seperate file to avoid processing priv.inc which is cpu intensive
 * cmp_page_matches is used by both auth_check.inc and priv.inc which is used by guiconfig.inc
 */

function cmp_page_matches($page, & $matches, $fullwc = true) {

//	$dbg_matches = implode(",", $matches);
//	log_error("debug: checking page {$page} match with {$dbg_matches}");

	if (!is_array($matches)) {
		return false;
	}

	/* skip any leading fwdslash */
	$test = strpos($page, "/");
	if ($test !== false && $test == 0) {
		$page = substr($page, 1);
	}

	/* look for a match */
	foreach ($matches as $match) {

		/* possibly ignore full wildcard match */
		if (!$fullwc && !strcmp($match , "*")) {
			continue;
		}

		/* compare exact or wildcard match */
		$match = str_replace(array(".", "*", "?"), array("\.", ".*", "\?"), $match);
		$result = preg_match("@^/{$match}$@", "/{$page}");

		if ($result) {
			return true;
		}
	}

	return false;
}
