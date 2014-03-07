<?php
/*
	Copyright (C) 2010-2012 Ermal Luci <eri@pfsense.org>
	Copyright (C) 2010 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2007 Marcel Wiget <mwiget@mac.com>
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
	pfSense_BUILDER_BINARIES:	/usr/local/bin/voucher
	pfSense_MODULE:	captiveportal
*/

/* include all configuration functions */
if(!function_exists('captiveportal_syslog'))
	require_once("captiveportal.inc");

function xmlrpc_sync_voucher_expire($vouchers, $syncip, $port, $password, $username) {
	global $g, $config, $cpzone;
	require_once("xmlrpc.inc");

	$protocol = "http";
	if (is_array($config['system']) && is_array($config['system']['webgui']) && !empty($config['system']['webgui']['protocol']) &&
	    $config['system']['webgui']['protocol'] == "https")
		$protocol = "https";
	if ($protocol == "https" || $port == "443")
		$url = "https://{$syncip}";
	else
		$url = "http://{$syncip}";

	/* Construct code that is run on remote machine */
	$method = 'pfsense.exec_php';
	$execcmd  = <<<EOF
	global \$cpzone;
	require_once('/etc/inc/captiveportal.inc');
	require_once('/etc/inc/voucher.inc');
	\$cpzone = "$cpzone";
	voucher_expire("$vouchers");

EOF;

	/* assemble xmlrpc payload */
	$params = array(
		XML_RPC_encode($password),
		XML_RPC_encode($execcmd)
	);

	log_error("Captive Portal Voucher XMLRPC sync data {$url}:{$port}.");
	$msg = new XML_RPC_Message($method, $params);
	$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
	$cli->setCredentials($username, $password);
	$resp = $cli->send($msg, "250");
	if(!is_object($resp)) {
		$error = "A communications error occurred while attempting CaptivePortalVoucherSync XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
		log_error($error);
		file_notice("CaptivePortalVoucherSync", $error, "Communications error occurred", "");
		return false;
	} elseif($resp->faultCode()) {
		$error = "An error code was received while attempting CaptivePortalVoucherSync XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
		log_error($error);
		file_notice("CaptivePortalVoucherSync", $error, "Error code received", "");
		return false;
	} else {
		log_error("CaptivePortalVoucherSync XMLRPC reload data success with {$url}:{$port} (pfsense.exec_php).");
	}

	$toreturn =  XML_RPC_Decode($resp->value());

	return $toreturn;
}

function xmlrpc_sync_voucher_disconnect($dbent, $syncip, $port, $password, $username, $term_cause = 1, $stop_time = null) {
	global $g, $config, $cpzone;
	require_once("xmlrpc.inc");

	$protocol = "http";
	if (is_array($config['system']) && is_array($config['system']['webgui']) && !empty($config['system']['webgui']['protocol']) &&
	    $config['system']['webgui']['protocol'] == "https")
		$protocol = "https";
	if ($protocol == "https" || $port == "443")
		$url = "https://{$syncip}";
	else
		$url = "http://{$syncip}";

	/* Construct code that is run on remote machine */
	$dbent_str = serialize($dbent);
	$tmp_stop_time = (isset($stop_time)) ? $stop_time : "null";
	$method = 'pfsense.exec_php';
	$execcmd  = <<<EOF
	global \$cpzone;
	require_once('/etc/inc/captiveportal.inc');
	require_once('/etc/inc/voucher.inc');
	\$cpzone = "$cpzone";
	\$radiusservers = captiveportal_get_radius_servers();
	\$dbent = unserialize("$dbent_str");
	captiveportal_disconnect(\$dbent, \$radiusservers, $term_cause, $tmp_stop_time);

EOF;

	/* assemble xmlrpc payload */
	$params = array(
		XML_RPC_encode($password),
		XML_RPC_encode($execcmd)
	);

	log_error("Captive Portal Voucher XMLRPC sync data {$url}:{$port}.");
	$msg = new XML_RPC_Message($method, $params);
	$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
	$cli->setCredentials($username, $password);
	$resp = $cli->send($msg, "250");
	if(!is_object($resp)) {
		$error = "A communications error occurred while attempting CaptivePortalVoucherSync XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
		log_error($error);
		file_notice("CaptivePortalVoucherSync", $error, "Communications error occurred", "");
		return false;
	} elseif($resp->faultCode()) {
		$error = "An error code was received while attempting CaptivePortalVoucherSync XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
		log_error($error);
		file_notice("CaptivePortalVoucherSync", $error, "Error code received", "");
		return false;
	} else {
		log_error("CaptivePortalVoucherSync XMLRPC reload data success with {$url}:{$port} (pfsense.exec_php).");
	}

	$toreturn =  XML_RPC_Decode($resp->value());

	return $toreturn;
}

function xmlrpc_sync_used_voucher($voucher_received, $syncip, $port, $password, $username) {
	global $g, $config, $cpzone;
	require_once("xmlrpc.inc");

	$protocol = "http";
	if (is_array($config['system']) && is_array($config['system']['webgui']) && !empty($config['system']['webgui']['protocol']) &&
	    $config['system']['webgui']['protocol'] == "https")
		$protocol = "https";
	if ($protocol == "https" || $port == "443")
		$url = "https://{$syncip}";
	else
		$url = "http://{$syncip}";

	/* Construct code that is run on remote machine */
	$method = 'pfsense.exec_php';
	$execcmd  = <<<EOF
	global \$cpzone;
	require_once('/etc/inc/voucher.inc');
	\$cpzone = "$cpzone";
	\$timeleft = voucher_auth("$voucher_received");
	\$toreturn = array();
	\$toreturn['timeleft'] = \$timeleft;
	\$toreturn['voucher'] = array();
	\$toreturn['voucher']['roll'] = \$config['voucher'][\$cpzone]['roll'];

EOF;

	/* assemble xmlrpc payload */
	$params = array(
		XML_RPC_encode($password),
		XML_RPC_encode($execcmd)
	);

	log_error("Captive Portal Voucher XMLRPC sync data {$url}:{$port}.");
	$msg = new XML_RPC_Message($method, $params);
	$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
	$cli->setCredentials($username, $password);
	$resp = $cli->send($msg, "250");
	if(!is_object($resp)) {
		$error = "A communications error occurred while attempting CaptivePortalVoucherSync XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
		log_error($error);
		file_notice("CaptivePortalVoucherSync", $error, "Communications error occurred", "");
		return null; // $timeleft
	} elseif($resp->faultCode()) {
		$error = "An error code was received while attempting CaptivePortalVoucherSync XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
		log_error($error);
		file_notice("CaptivePortalVoucherSync", $error, "Error code received", "");
		return null; // $timeleft
	} else {
		log_error("CaptivePortalVoucherSync XMLRPC reload data success with {$url}:{$port} (pfsense.exec_php).");
	}
	$toreturn =  XML_RPC_Decode($resp->value());
	if (!is_array($config['voucher']))
		$config['voucher'] = array();

	if (is_array($toreturn['voucher']) && is_array($toreturn['voucher']['roll'])) {
		$config['voucher'][$cpzone]['roll'] = $toreturn['voucher']['roll'];
		write_config("Captive Portal Voucher database synchronized with {$url}");
		voucher_configure_zone(true);
		unset($toreturn['voucher']);
	} else if (!isset($toreturn['timeleft']))
		return null;

	return $toreturn['timeleft'];
}

function voucher_expire($voucher_received) {
	global $g, $config, $cpzone;

	// XMLRPC Call over to the master Voucher node
	if(!empty($config['voucher'][$cpzone]['vouchersyncdbip'])) {
		$syncip   = $config['voucher'][$cpzone]['vouchersyncdbip'];
		$syncport = $config['voucher'][$cpzone]['vouchersyncport'];
		$syncpass = $config['voucher'][$cpzone]['vouchersyncpass'];
		$vouchersyncusername = $config['voucher'][$cpzone]['vouchersyncusername'];
		xmlrpc_sync_voucher_expire($voucher_received, $syncip, $syncport, $syncpass, $vouchersyncusername);
	}

	$voucherlck = lock("voucher{$cpzone}", LOCK_EX);

	// read rolls into assoc array with rollid as key and minutes as value
	$tickets_per_roll = array();
	$minutes_per_roll = array();
	if (is_array($config['voucher'][$cpzone]['roll'])) {
		foreach ($config['voucher'][$cpzone]['roll'] as $rollent) {
			$tickets_per_roll[$rollent['number']] = $rollent['count'];
			$minutes_per_roll[$rollent['number']] = $rollent['minutes'];
		}
	}

	// split into an array. Useful for multiple vouchers given
	$a_vouchers_received = preg_split("/[\t\n\r ]+/s", $voucher_received); 
	$active_dirty = false;
	$unsetindexes = array();

	// go through all received vouchers, check their valid and extract
	// Roll# and Ticket# using the external readvoucher binary
	foreach ($a_vouchers_received as $voucher) {
		$v = escapeshellarg($voucher);
		if (strlen($voucher) < 3)
			continue;   // seems too short to be a voucher!

		unset($output);
		$_gb = exec("/usr/local/bin/voucher -c {$g['varetc_path']}/voucher_{$cpzone}.cfg -k {$g['varetc_path']}/voucher_{$cpzone}.public -- $v", $output);
		list($status, $roll, $nr) = explode(" ", $output[0]);
		if ($status == "OK") {
			// check if we have this ticket on a registered roll for this ticket 
			if ($tickets_per_roll[$roll] && ($nr <= $tickets_per_roll[$roll])) {
				// voucher is from a registered roll. 
				if (!isset($active_vouchers[$roll]))
					$active_vouchers[$roll] = voucher_read_active_db($roll);
				// valid voucher. Store roll# and ticket#
				if (!empty($active_vouchers[$roll][$voucher])) {
					$active_dirty = true;
					unset($active_vouchers[$roll][$voucher]);
				}
				// check if voucher already marked as used
				if (!isset($bitstring[$roll]))
					$bitstring[$roll] = voucher_read_used_db($roll);
				$pos = $nr >> 3; // divide by 8 -> octet
				$mask = 1 << ($nr % 8);
				// mark bit for this voucher as used
				if (!(ord($bitstring[$roll][$pos]) & $mask))
					$bitstring[$roll][$pos] = chr(ord($bitstring[$roll][$pos]) | $mask);
				captiveportal_syslog("{$voucher} ({$roll}/{$nr}) forced to expire");

				/* Check if this voucher has any active sessions */
				$cpentry = captiveportal_read_db("WHERE username = '{$voucher}'");
				if (!empty($cpentry)) {
					captiveportal_disconnect($cpentry,null,13);
					captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"FORCLY TERMINATING VOUCHER {$voucher} SESSION");
					$unsetindexes[] = $cpentry[5];
				}
			} else
				captiveportal_syslog("$voucher ($roll/$nr): not found on any registererd Roll");
		} else
			// hmm, thats weird ... not what I expected
			captiveportal_syslog("$voucher invalid: {$output[0]}!!");
	}

	// Refresh active DBs
	if ($active_dirty == true) {
		foreach ($active_vouchers as $roll => $active)
			voucher_write_active_db($roll, $active);
		unset($active_vouchers);

		/* Triger a sync of the vouchers on config */
		send_event("service sync vouchers");
	}

	// Write back the used DB's
	if (is_array($bitstring)) {
		foreach ($bitstring as $roll => $used) {
			if(is_array($used)) {
				foreach($used as $u)
					voucher_write_used_db($roll, base64_encode($u));
			} else {
				voucher_write_used_db($roll, base64_encode($used));
			}
		}
		unset($bitstring);
	}

	unlock($voucherlck);

	/* Write database */
	if (!empty($unsetindexes))
		captiveportal_remove_entries($unsetindexes);

	return true;
}

/* 
 * Authenticate a voucher and return the remaining time credit in minutes
 * if $test is set, don't mark the voucher as used nor add it to the list
 * of active vouchers
 * If $test is set, simply test the voucher. Don't change anything
 * but return a more verbose error and result message back
 */
function voucher_auth($voucher_received, $test = 0) {
	global $g, $config, $cpzone, $dbc;

	if (!isset($config['voucher'][$cpzone]['enable']))
		return 0;

	// XMLRPC Call over to the master Voucher node
	if(!empty($config['voucher'][$cpzone]['vouchersyncdbip'])) {
		$syncip   = $config['voucher'][$cpzone]['vouchersyncdbip'];
		$syncport = $config['voucher'][$cpzone]['vouchersyncport'];
		$syncpass = $config['voucher'][$cpzone]['vouchersyncpass'];
		$vouchersyncusername = $config['voucher'][$cpzone]['vouchersyncusername'];
		$remote_time_used = xmlrpc_sync_used_voucher($voucher_received, $syncip, $syncport, $syncpass, $vouchersyncusername);
	}

	$voucherlck = lock("voucher{$cpzone}", LOCK_EX);

	// read rolls into assoc array with rollid as key and minutes as value
	$tickets_per_roll = array();
	$minutes_per_roll = array();
	if (is_array($config['voucher'][$cpzone]['roll'])) {
		foreach ($config['voucher'][$cpzone]['roll'] as $rollent) {
			$tickets_per_roll[$rollent['number']] = $rollent['count'];
			$minutes_per_roll[$rollent['number']] = $rollent['minutes'];
		}
	}

	// split into an array. Useful for multiple vouchers given
	$a_vouchers_received = preg_split("/[\t\n\r ]+/s", $voucher_received); 
	$error = 0;
	$test_result = array();     // used to display for voucher test option in GUI
	$total_minutes = 0;
	$first_voucher = "";
	$first_voucher_roll = 0;

	// go through all received vouchers, check their valid and extract
	// Roll# and Ticket# using the external readvoucher binary
	foreach ($a_vouchers_received as $voucher) {
		$v = escapeshellarg($voucher);
		if (strlen($voucher) < 3)
			continue;   // seems too short to be a voucher!

		$result = exec("/usr/local/bin/voucher -c {$g['varetc_path']}/voucher_{$cpzone}.cfg -k {$g['varetc_path']}/voucher_{$cpzone}.public -- $v");
		list($status, $roll, $nr) = explode(" ", $result);
		if ($status == "OK") {
			if (!$first_voucher) {
				// store first voucher. Thats the one we give the timecredit
				$first_voucher = $voucher;
				$first_voucher_roll = $roll;
			}
			// check if we have this ticket on a registered roll for this ticket 
			if ($tickets_per_roll[$roll] && ($nr <= $tickets_per_roll[$roll])) {
				// voucher is from a registered roll. 
				if (!isset($active_vouchers[$roll]))
					$active_vouchers[$roll] = voucher_read_active_db($roll);
				// valid voucher. Store roll# and ticket#
				if (!empty($active_vouchers[$roll][$voucher])) {
					list($timestamp,$minutes) = explode(",", $active_vouchers[$roll][$voucher]);
					// we have an already active voucher here.
					$remaining = intval((($timestamp + (60*$minutes)) - time())/60);
					$test_result[] = sprintf(gettext('%1$s (%2$s/%3$s) active and good for %4$d Minutes'), $voucher, $roll, $nr, $remaining);
					$total_minutes += $remaining;
				} else {
					// voucher not used. Check if ticket Id is on the roll (not too high)
					// and if the ticket is marked used.
					// check if voucher already marked as used
					if (!isset($bitstring[$roll]))
						$bitstring[$roll] = voucher_read_used_db($roll);
					$pos = $nr >> 3; // divide by 8 -> octet
					$mask = 1 << ($nr % 8);
					if (ord($bitstring[$roll][$pos]) & $mask) {
						$test_result[] = "$voucher ($roll/$nr) already used and expired";
						captiveportal_syslog("$voucher ($roll/$nr) already used and expired");
						$total_minutes = -1;    // voucher expired
						$error++;
					} else {
						// mark bit for this voucher as used
						$bitstring[$roll][$pos] = chr(ord($bitstring[$roll][$pos]) | $mask);
						$test_result[] = "$voucher ($roll/$nr) good for {$minutes_per_roll[$roll]} Minutes";
						$total_minutes += $minutes_per_roll[$roll];
					}
				}
			} else {
				$test_result[] = "$voucher ($roll/$nr): not found on any registererd Roll";
				captiveportal_syslog("$voucher ($roll/$nr): not found on any registererd Roll");
			}
		} else {
			// hmm, thats weird ... not what I expected
			$test_result[] = "$voucher invalid: $result !!";
			captiveportal_syslog("$voucher invalid: $result !!");
			$error++;
		}
	}

	// if this was a test call, we're done. Return the result.
	if ($test) {
		if ($error) {
			$test_result[] = gettext("Access denied!");
		} else {
			$test_result[] = sprintf(gettext("Access granted for %d Minutes in total."),$total_minutes);
		}
		unlock($voucherlck);

		return $test_result;
	}

	// if we had an error (one of the vouchers is invalid), return 0.
	// Discussion: we could return the time remaining for good vouchers, but then
	// the user wouldn't know that he used at least one invalid voucher.
	if ($error) {
		unlock($voucherlck);
		if ($total_minutes > 0)     // probably not needed, but want to make sure
			$total_minutes = 0;     // we only report -1 (expired) or 0 (no access)
		return $total_minutes;       // well, at least one voucher had errors. Say NO ACCESS
	}

	// If we did a XMLRPC sync earlier check the timeleft
	if (!empty($config['voucher'][$cpzone]['vouchersyncdbip'])) {
		if (!is_null($remote_time_used))
			$total_minutes = $remote_time_used;
		else if ($remote_time_used < $total_minutes) 
			$total_minutes -= $remote_time_used;
	}

	// All given vouchers were valid and this isn't simply a test.
	// Write back the used DB's
	if (is_array($bitstring)) {
		foreach ($bitstring as $roll => $used) {
			if(is_array($used)) {
				foreach($used as $u)
					voucher_write_used_db($roll, base64_encode($u));
			} else {
				voucher_write_used_db($roll, base64_encode($used));
			}
		}
	}

	// Active DB: we only add the first voucher if multiple given
	// and give that one all the time credit. This allows the user to logout and
	// log in later using just the first voucher. It also keeps username limited
	// to one voucher and that voucher shows the correct time credit in 'active vouchers'
	if (!empty($active_vouchers[$first_voucher_roll][$first_voucher])) {
		list($timestamp, $minutes) = explode(",", $active_vouchers[$first_voucher_roll][$first_voucher]);
	} else {
		$timestamp = time();    // new voucher
		$minutes = $total_minutes;
	}

	$active_vouchers[$first_voucher_roll][$first_voucher] = "$timestamp,$minutes";
	voucher_write_active_db($first_voucher_roll, $active_vouchers[$first_voucher_roll]);

	/* Triger a sync of the vouchers on config */
	send_event("service sync vouchers");

	unlock($voucherlck);

	return $total_minutes;
}

function voucher_configure($sync = false) {
	global $config, $g, $cpzone;

	if (is_array($config['voucher'])) {
		foreach ($config['voucher'] as $voucherzone => $vcfg) {
			if ($g['booting'])
			    echo gettext("Enabling voucher support... ");
			$cpzone = $voucherzone;
			$error = voucher_configure_zone($sync);
			if ($g['booting']) {
				if ($error)
					echo "error\n";
				else
					echo "done\n";
			}
		}
	}
}

function voucher_configure_zone($sync = false) {
	global $config, $g, $cpzone;

	if (!isset($config['voucher'][$cpzone]['enable']))
		return 0;

	if ($sync == true)
	    captiveportal_syslog("Writing voucher db from sync data...");

	$voucherlck = lock("voucher{$cpzone}", LOCK_EX);

        /* write public key used to verify vouchers */
        $pubkey = base64_decode($config['voucher'][$cpzone]['publickey']);
        $fd = fopen("{$g['varetc_path']}/voucher_{$cpzone}.public", "w");
        if (!$fd) {
            captiveportal_syslog("Voucher error: cannot write voucher.public\n");
	    unlock($voucherlck);
            return 1;
        }
        fwrite($fd, $pubkey);
        fclose($fd);
        @chmod("{$g['varetc_path']}/voucher_{$cpzone}.public", 0600);

        /* write config file used by voucher binary to decode vouchers */
        $fd = fopen("{$g['varetc_path']}/voucher_{$cpzone}.cfg", "w");
        if (!$fd) {
	    printf(gettext("Error: cannot write voucher.cfg") . "\n");
	    unlock($voucherlck);
            return 1;
        }
        fwrite($fd, "{$config['voucher'][$cpzone]['rollbits']},{$config['voucher'][$cpzone]['ticketbits']},{$config['voucher'][$cpzone]['checksumbits']},{$config['voucher'][$cpzone]['magic']},{$config['voucher'][$cpzone]['charset']}\n");
        fclose($fd);
        @chmod("{$g['varetc_path']}/voucher_{$cpzone}.cfg", 0600);
	unlock($voucherlck);

        if (($g['booting'] || $sync == true) && is_array($config['voucher'][$cpzone]['roll'])) {

		$voucherlck = lock("voucher{$cpzone}", LOCK_EX);

            // create active and used DB per roll on ramdisk from config
            foreach ($config['voucher'][$cpzone]['roll'] as $rollent) {

                $roll = $rollent['number'];
                voucher_write_used_db($roll, $rollent['used']);
                $minutes = $rollent['minutes'];
                $active_vouchers = array();
                $a_active = &$rollent['active'];
                if (is_array($a_active)) {
                    foreach ($a_active as $activent) {
                        $voucher = $activent['voucher'];
                        $timestamp = $activent['timestamp'];
                        $minutes = $activent['minutes'];
                        // its tempting to check for expired timestamps, but during
                        // bootup, we most likely don't have the correct time time.
                        $active_vouchers[$voucher] = "$timestamp,$minutes";
                    }
                }
                voucher_write_active_db($roll, $active_vouchers);
            }

		unlock($voucherlck);
        }

	return 0;
}

/* write bitstring of used vouchers to ramdisk. 
 * Bitstring must already be base64_encoded!
 */
function voucher_write_used_db($roll, $vdb) {
	global $g, $cpzone;

	$fd = fopen("{$g['vardb_path']}/voucher_{$cpzone}_used_$roll.db", "w");
	if ($fd) {
		fwrite($fd, $vdb . "\n");
		fclose($fd);
	} else
		voucher_log(LOG_ERR, sprintf(gettext('cant write %1$s/voucher_%s_used_%2$s.db'), $g['vardb_path'], $cpzone, $roll));
}

/* return assoc array of active vouchers with activation timestamp
 * voucher is index. 
 */
function voucher_read_active_db($roll) {
	global $g, $cpzone;

	$active = array();
	$dirty = 0;
	$file = "{$g['vardb_path']}/voucher_{$cpzone}_active_$roll.db";
	if (file_exists($file)) {
		$fd = fopen($file, "r");
		if ($fd) {
			while (!feof($fd)) {
				$line = trim(fgets($fd));
				if ($line) {
					list($voucher,$timestamp,$minutes) = explode(",", $line); // voucher,timestamp
					if ((($timestamp + (60*$minutes)) - time()) > 0)
						$active[$voucher] = "$timestamp,$minutes";
					else
						$dirty=1;
				}
			}
			fclose($fd);
			if ($dirty) { // if we found expired entries, lets save our snapshot
				voucher_write_active_db($roll, $active);

				/* Triger a sync of the vouchers on config */
				send_event("service sync vouchers");
			}
		}
	}
	return $active;
}

/* store array of active vouchers back to DB */
function voucher_write_active_db($roll, $active) {
    global $g, $cpzone;

	if (!is_array($active))
		return;
    $fd = fopen("{$g['vardb_path']}/voucher_{$cpzone}_active_$roll.db", "w");
    if ($fd) {
        foreach($active as $voucher => $value)
            fwrite($fd, "$voucher,$value\n");
        fclose($fd);
    }
}

/* return how many vouchers are marked used on a roll */
function voucher_used_count($roll) {
    global $g, $cpzone;

    $bitstring = voucher_read_used_db($roll);
    $max = strlen($bitstring) * 8;
    $used = 0;
    for ($i = 1; $i <= $max; $i++) {
        // check if ticket already used or not. 
        $pos = $i >> 3;            // divide by 8 -> octet
        $mask = 1 << ($i % 8);  // mask to test bit in octet
        if (ord($bitstring[$pos]) & $mask)
            $used++;
    }
    unset($bitstring);

    return $used;
}

function voucher_read_used_db($roll) {
    global $g, $cpzone;

    $vdb = "";
    $file = "{$g['vardb_path']}/voucher_{$cpzone}_used_$roll.db";
    if (file_exists($file)) {
        $fd = fopen($file, "r");
        if ($fd) {
            $vdb = trim(fgets($fd));
            fclose($fd);
        } else {
	    voucher_log(LOG_ERR, sprintf(gettext('cant read %1$s/voucher_%s_used_%2$s.db'), $g['vardb_path'], $cpzone, $roll));
        }
    }
    return base64_decode($vdb);
}

function voucher_unlink_db($roll) {
    global $g, $cpzone;
    @unlink("{$g['vardb_path']}/voucher_{$cpzone}_used_$roll.db");
    @unlink("{$g['vardb_path']}/voucher_{$cpzone}_active_$roll.db");
}

/* we share the log with captiveportal for now */
function voucher_log($priority, $message) {

    $message = trim($message);
    openlog("logportalauth", LOG_PID, LOG_LOCAL4);
    syslog($priority, sprintf(gettext("Voucher: %s"),$message));
    closelog();
}

/* Save active and used voucher DB into XML config and write it to flash
 * Called during reboot -> system_reboot_cleanup() and every active voucher change
 */
function voucher_save_db_to_config() {
    global $config, $g, $cpzone;

	if (is_array($config['voucher'])) {
		foreach ($config['voucher'] as $voucherzone => $vcfg) {
			$cpzone = $voucherzone;
			voucher_save_db_to_config_zone();
		}
	}
}

function voucher_save_db_to_config_zone() {
    global $config, $g, $cpzone;
    
    if (!isset($config['voucher'][$cpzone]['enable']))
        return;   // no vouchers or don't want to save DB's

    if (!is_array($config['voucher'][$cpzone]['roll']))
	return;

    $voucherlck = lock("voucher{$cpzone}", LOCK_EX);

    // walk all active rolls and save runtime DB's to flash
    $a_roll = &$config['voucher'][$cpzone]['roll'];
    while (list($key, $value) = each($a_roll)) {
        $rollent = &$a_roll[$key];
        $roll = $rollent['number'];
        $bitmask = voucher_read_used_db($roll);
        $rollent['used'] = base64_encode($bitmask);
        $active_vouchers = voucher_read_active_db($roll);
        $db = array();
		$dbi = 1;
        foreach($active_vouchers as $voucher => $line) {
            list($timestamp,$minutes) = explode(",", $line);
            $activent['voucher'] = $voucher;
            $activent['timestamp'] = $timestamp;
            $activent['minutes'] = $minutes;
            $db["v{$dbi}"] = $activent;
	    $dbi++;
        }
        $rollent['active'] = $db;
	unset($active_vouchers);
    }

    unlock($voucherlck);

    write_config("Synching vouchers");
    return;
}

?>
