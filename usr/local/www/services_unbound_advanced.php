<?php
/* $Id$ */
/*
	services_unbound_advanced.php
	part of the pfSense project (https://www.pfsense.org)
	Copyright (C) 2011	Warren Baker (warren@pfsense.org)
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
	pfSense_MODULE:	dnsresolver
*/

##|+PRIV
##|*IDENT=page-services-unbound
##|*NAME=Services: DNS Resolver Advanced page
##|*DESCR=Allow access to the 'Services: DNS Resolver Advanced' page.
##|*MATCH=services_unbound.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("unbound.inc");

if(!is_array($config['unbound']))
	$config['unbound'] = array();

if (isset($config['unbound']['hideidentity']))
	$pconfig['hideidentity'] = true;
if (isset($config['unbound']['hideversion']))
	$pconfig['hideversion'] = true;
if (isset($config['unbound']['prefetch']))
	$pconfig['prefetch'] = true;
if (isset($config['unbound']['prefetchkey']))
	$pconfig['prefetchkey'] = true;
if (isset($config['unbound']['hardenglue']))
	$pconfig['hardenglue'] = true;
if (isset($config['unbound']['dnssecstripped']))
	$pconfig['dnssecstripped'] = true;
$pconfig['msgcachesize'] = $config['unbound']['msgcachesize'];
$pconfig['outgoing_num_tcp'] = $config['unbound']['outgoing_num_tcp'];
$pconfig['incoming_num_tcp'] = $config['unbound']['incoming_num_tcp'];
$pconfig['edns_buffer_size'] = $config['unbound']['edns_buffer_size'];
$pconfig['num_queries_per_thread'] = $config['unbound']['num_queries_per_thread'];
$pconfig['jostle_timeout'] = $config['unbound']['jostle_timeout'];
$pconfig['cache_max_ttl'] = $config['unbound']['cache_max_ttl'];
$pconfig['cache_min_ttl'] = $config['unbound']['cache_min_ttl'];
$pconfig['infra_host_ttl'] = $config['unbound']['infra_host_ttl'];
$pconfig['infra_lame_ttl'] = $config['unbound']['infra_lame_ttl'];
$pconfig['infra_cache_numhosts'] = $config['unbound']['infra_cache_numhosts'];
$pconfig['unwanted_reply_threshold'] = $config['unbound']['unwanted_reply_threshold'];
$pconfig['log_verbosity'] = isset($config['unbound']['log_verbosity']) ? $config['unbound']['log_verbosity'] : "1";

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('unbound');
	} else {
		if (isset($_POST['hideidentity']))
			$config['unbound']['hideidentity'] = true;
		else
			unset($config['unbound']['hideidentity']);
		if (isset($_POST['hideversion']))
			$config['unbound']['hideversion'] = true;
		else
			unset($config['unbound']['hideversion']);
		if (isset($_POST['prefetch']))
			$config['unbound']['prefetch'] = true;
		else
			unset($config['unbound']['prefetch']);
		if (isset($_POST['prefetchkey']))
			$config['unbound']['prefetchkey'] = true;
		else
			unset($config['unbound']['prefetchkey']);
		if (isset($_POST['hardenglue']))
			$config['unbound']['hardenglue'] = true;
		else
			unset($config['unbound']['hardenglue']);
		if (isset($_POST['dnssecstripped']))
			$config['unbound']['dnssecstripped'] = true;
		else
			unset($config['unbound']['dnssecstripped']);
		$config['unbound']['msgcachesize'] = $_POST['msgcachesize'];
		$config['unbound']['outgoing_num_tcp'] = $_POST['outgoing_num_tcp'];
		$config['unbound']['incoming_num_tcp'] = $_POST['incoming_num_tcp'];
		$config['unbound']['edns_buffer_size'] = $_POST['edns_buffer_size'];
		$config['unbound']['num_queries_per_thread'] = $_POST['num_queries_per_thread'];
		$config['unbound']['jostle_timeout'] = $_POST['jostle_timeout'];
		$config['unbound']['cache_max_ttl'] = $_POST['cache_max_ttl'];
		$config['unbound']['cache_min_ttl'] = $_POST['cache_min_ttl'];
		$config['unbound']['infra_host_ttl'] = $_POST['infra_host_ttl'];
		$config['unbound']['infra_lame_ttl'] = $_POST['infra_lame_ttl'];
		$config['unbound']['infra_cache_numhosts'] = $_POST['infra_cache_numhosts'];
		$config['unbound']['unwanted_reply_threshold'] = $_POST['unwanted_reply_threshold'];
		$config['unbound']['log_verbosity'] = $_POST['log_verbosity'];
		write_config("DNS Resolver configured.");

		mark_subsystem_dirty('unbound');
	}
}

$closehead = false;
$pgtitle = array(gettext("Services"),gettext("DNS Resolver"),gettext("Advanced"));
include_once("head.inc");

?>

</head>
	
<body>
<?php include("fbegin.inc"); ?>
<form action="services_unbound_advanced.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('unbound')): ?><br/>
<?php print_info_box_np(gettext("The configuration of the DNS Resolver, has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="services unbound advanced">
	<tbody>
		<tr>
			<td class="tabnavtbl">
				<?php
					$tab_array = array();
					$tab_array[] = array(gettext("General settings"), false, "services_unbound.php");
					$tab_array[] = array(gettext("Advanced settings"), true, "services_unbound_advanced.php");
					$tab_array[] = array(gettext("Access Lists"), false, "/services_unbound_acls.php");
					display_top_tabs($tab_array, true);
				?>
			</td>
		</tr>
		<tr>
			<td id="mainarea">
				<div class="tabcont">
					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
						<tbody>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Advanced Resolver Options");?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Hide Identity");?></td>
								<td width="78%" class="vtable">
									<p><input name="hideidentity" type="checkbox" id="hideidentity" value="yes" <?php if (isset($pconfig['hideidentity'])) echo "checked=\"checked\"";?> /><br />
									<?=gettext("If enabled, id.server and hostname.bind queries are refused.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Hide Version");?></td>
								<td width="78%" class="vtable">
									<p><input name="hideversion" type="checkbox" id="hideversion" value="yes" <?php if (isset($pconfig['hideversion'])) echo "checked=\"checked\"";?> /><br />
									<?=gettext("If enabled, version.server and version.bind queries are refused.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Prefetch Support");?></td>
								<td width="78%" class="vtable">
									<p><input name="prefetch" type="checkbox" id="prefetch" value="yes" <?php if (isset($pconfig['prefetch'])) echo "checked=\"checked\"";?> /><br />
									<?=gettext("Message cache elements are prefetched before they expire to help keep the cache up to date. When enabled, this option can cause an increase of around 10% more DNS traffic and load on the server, but frequently requested items will not expire from the cache.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Prefetch DNS Key Support");?></td>
								<td width="78%" class="vtable">
									<p><input name="prefetchkey" type="checkbox" id="prefetchkey" value="yes" <?php if (isset($pconfig['prefetchkey'])) echo "checked=\"checked\"";?> /><br />
									<?=sprintf(gettext("DNSKEY's are fetched earlier in the validation process when a %sDelegation signer%s is encountered. This helps lower the latency of requests but does utilize a little more CPU."), "<a href='http://en.wikipedia.org/wiki/List_of_DNS_record_types'>", "</a>");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Harden Glue");?></td>
								<td width="78%" class="vtable">
									<p><input name="hardenglue" type="checkbox" id="hardenglue" value="yes" <?php if (isset($pconfig['hardenglue'])) echo "checked=\"checked\"";?> /><br />
									<?=gettext("Only trust glue if it is within the servers authority.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Harden DNSSEC data");?></td>
								<td width="78%" class="vtable">
									<p><input name="dnssecstripped" type="checkbox" id="dnssecstripped" value="yes" <?php if (isset($pconfig['dnssecstripped'])) echo "checked=\"checked\"";?> /><br />
									<?=gettext("DNSSEC data is required for trust-anchored zones. If such data is absent, the zone becomes bogus. If this is disabled and no DNSSEC data is received, then the zone is made insecure.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Message Cache Size");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="msgcachesize" name="msgcachesize">
<?php
										foreach (array("4", "10", "20", "50", "100", "250", "512") as $size) :
?>
											<option value="<?php echo $size; ?>" <?php if ($pconfig['msgcachesize'] == "{$size}") echo "selected=\"selected\""; ?>>
												<?php echo $size; ?>MB
											</option>
<?php
										endforeach;
?>
										</select><br />
										<?=gettext("Size of the message cache. The message cache stores DNS rcodes and validation statuses. The RRSet cache will automatically be set to twice this amount. The RRSet cache contains the actual RR data. The default is 4 megabytes.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Outgoing TCP Buffers");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="outgoing_num_tcp" name="outgoing_num_tcp">
<?php
										for ($num_tcp = 0; $num_tcp <= 50; $num_tcp += 10):
?>
											<option value="<?php echo $num_tcp; ?>" <?php if ($pconfig['outgoing_num_tcp'] == "{$num_tcp}") echo "selected=\"selected\""; ?>>
												<?php echo $num_tcp; ?>
											</option>
<?php
										endfor;
?>
										</select><br />
										<?=gettext("The number of outgoing TCP buffers to allocate per thread. The default value is 10. If 0 is selected then no TCP queries, to authoritative servers, are done.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Incoming TCP Buffers");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="incoming_num_tcp" name="incoming_num_tcp">
<?php
										for ($num_tcp = 0; $num_tcp <= 50; $num_tcp += 10):
?>
											<option value="<?php echo $num_tcp; ?>" <?php if ($pconfig['incoming_num_tcp'] == "{$num_tcp}") echo "selected=\"selected\""; ?>>
												<?php echo $num_tcp; ?>
											</option>
<?php
										endfor;
?>
										</select><br />
										<?=gettext("The number of incoming TCP buffers to allocate per thread. The default value is 10. If 0 is selected then no TCP queries, from clients, are accepted.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("EDNS Buffer Size");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="edns_buffer_size" name="edns_buffer_size">
<?php
										foreach (array("512", "1480", "4096") as $size) :
?>
											<option value="<?php echo $size; ?>" <?php if ($pconfig['edns_buffer_size'] == "{$size}") echo "selected=\"selected\""; ?>>
												<?php echo $size; ?>
											</option>
<?php
										endforeach;
?>
										</select><br />
										<?=gettext("Number of bytes size to advertise as the EDNS reassembly buffer size. This is the value that is used in UDP datagrams sent to peers. RFC recommendation is 4096 (which is the default). If you have fragmentation reassemble problems, usually seen as timeouts, then a value of 1480 should help. The 512 value bypasses most MTU path problems, but it can generate an excessive amount of TCP fallback.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Number of queries per thread");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="num_queries_per_thread" name="num_queries_per_thread">
<?php
										foreach (array("512", "1024", "2048") as $queries) :
?>
											<option value="<?php echo $queries; ?>" <?php if ($pconfig['num_queries_per_thread'] == "{$queries}") echo "selected=\"selected\""; ?>>
												<?php echo $queries; ?>
											</option>
<?php
										endforeach;
?>
										</select><br />
										<?=gettext("The number of queries that every thread will service simultaneously. If more queries arrive that need to be serviced, and no queries can be jostled, then these queries are dropped.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Jostle Timeout");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="jostle_timeout" name="jostle_timeout">
<?php
										foreach (array("100", "200", "500", "1000") as $timeout) :
?>
											<option value="<?php echo $timeout; ?>" <?php if ($pconfig['jostle_timeout'] == "{$timeout}") echo "selected=\"selected\""; ?>>
												<?php echo $timeout; ?>
											</option>
<?php
										endforeach;
?>
										</select><br />
										<?=gettext("This timeout is used for when the server is very busy. This protects against denial of service by slow queries or high query rates. The default value is 200 milliseconds.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Maximum TTL for RRsets and messages");?></td>
								<td width="78%" class="vtable">
									<p>
										<input type="text" id="cache_max_ttl" name="cache_max_ttl" size="5" value="<?php if(isset($pconfig['cache_max_ttl'])) echo $pconfig['cache_max_ttl']; ?>" /><br />
										<?=gettext("Configure a maximum Time to live for RRsets and messages in the cache. The default is 86400 seconds (1 day). When the internal TTL expires the cache item is expired. This can be configured to force the resolver to query for data more often and not trust (very large) TTL values.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Minimum TTL for RRsets and messages");?></td>
								<td width="78%" class="vtable">
									<p>
										<input type="text" id="cache_min_ttl" name="cache_min_ttl" size="5" value="<?php if(isset($pconfig['cache_min_ttl'])) echo $pconfig['cache_min_ttl']; ?>" /><br />
										<?=gettext("Configure a minimum Time to live for RRsets and messages in the cache. The default is 0 seconds. If the minimum value kicks in, the data is cached for longer than the domain owner intended, and thus less queries are made to look up the data. The 0 value ensures the data in the cache is as the domain owner intended. High values can lead to trouble as the data in the cache might not match up with the actual data anymore.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("TTL for Host cache entries");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="infra_host_ttl" name="infra_host_ttl">
											<option value="60"  <?php if ($pconfig['infra_host_ttl'] == "60")  echo "selected=\"selected\""; ?>>1 minute</option>
											<option value="120" <?php if ($pconfig['infra_host_ttl'] == "120") echo "selected=\"selected\""; ?>>2 minutes</option>
											<option value="300" <?php if ($pconfig['infra_host_ttl'] == "300") echo "selected=\"selected\""; ?>>5 minutes</option>
											<option value="600" <?php if ($pconfig['infra_host_ttl'] == "600") echo "selected=\"selected\""; ?>>10 minutes</option>
											<option value="900" <?php if ($pconfig['infra_host_ttl'] == "900") echo "selected=\"selected\""; ?>>15 minutes</option>
										</select><br />
										<?=gettext("Time to live for entries in the host cache. The host cache contains roundtrip timing and EDNS support information. The default is 15 minutes.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("TTL for lame delegation");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="infra_lame_ttl" name="infra_lame_ttl">
											<option value="60"  <?php if ($pconfig['infra_lame_ttl'] == "60")  echo "selected=\"selected\""; ?>>1 minute</option>
											<option value="120" <?php if ($pconfig['infra_lame_ttl'] == "120") echo "selected=\"selected\""; ?>>2 minutes</option>
											<option value="300" <?php if ($pconfig['infra_lame_ttl'] == "300") echo "selected=\"selected\""; ?>>5 minutes</option>
											<option value="600" <?php if ($pconfig['infra_lame_ttl'] == "600") echo "selected=\"selected\""; ?>>10 minutes</option>
											<option value="900" <?php if ($pconfig['infra_lame_ttl'] == "900") echo "selected=\"selected\""; ?>>15 minutes</option>
										</select><br />
										<?=gettext("Time to live for when a delegation is considered to be lame. The default is 15 minutes.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Number of Hosts to cache");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="infra_cache_numhosts" name="infra_cache_numhosts">
											<option value="1000"  <?php if ($pconfig['infra_cache_numhosts'] == "1000")  echo "selected=\"selected\""; ?>>1000</option>
											<option value="5000"  <?php if ($pconfig['infra_cache_numhosts'] == "5000")  echo "selected=\"selected\""; ?>>5000</option>
											<option value="10000" <?php if ($pconfig['infra_cache_numhosts'] == "10000") echo "selected=\"selected\""; ?>>10 000</option>
											<option value="20000" <?php if ($pconfig['infra_cache_numhosts'] == "20000") echo "selected=\"selected\""; ?>>20 000</option>
											<option value="50000" <?php if ($pconfig['infra_cache_numhosts'] == "50000") echo "selected=\"selected\""; ?>>50 000</option>
										</select><br />
										<?=gettext("Number of hosts for which information is cached. The default is 10,000.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Unwanted Reply Threshold");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="unwanted_reply_threshold" name="unwanted_reply_threshold">
											<option value="disabled" <?php if ($pconfig['unwanted_reply_threshold'] == "disabled") echo "selected=\"selected\""; ?>>disabled</option>
											<option value="5000000"  <?php if ($pconfig['unwanted_reply_threshold'] == "5000000")  echo "selected=\"selected\""; ?>>5 million</option>
											<option value="10000000" <?php if ($pconfig['unwanted_reply_threshold'] == "10000000") echo "selected=\"selected\""; ?>>10 million</option>
											<option value="20000000" <?php if ($pconfig['unwanted_reply_threshold'] == "20000000") echo "selected=\"selected\""; ?>>20 million</option>
											<option value="40000000" <?php if ($pconfig['unwanted_reply_threshold'] == "40000000") echo "selected=\"selected\""; ?>>40 million</option>
											<option value="50000000" <?php if ($pconfig['unwanted_reply_threshold'] == "50000000") echo "selected=\"selected\""; ?>>50 million</option>
										</select><br />
										<?=gettext("If enabled, a total number of unwanted replies is kept track of in every thread. When it reaches the threshold, a defensive action is taken and a warning is printed to the log file. This defensive action is to clear the RRSet and message caches, hopefully flushing away any poison. The default is disabled, but if enabled a value of 10 million is suggested.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Log level verbosity");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="log_verbosity" name="log_verbosity">
<?php
										for ($level = 0; $level <= 5; $level++):
?>
											<option value="<?php echo $level; ?>" <?php if ($pconfig['log_verbosity'] == "{$level}") echo "selected=\"selected\""; ?>>
												Level <?php echo $level; ?>
											</option>
<?php
										endfor;
?>
										</select><br />
										<?=gettext("Select the log verbosity.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td colspan="2">&nbsp;</td>
							</tr>
							<tr>
								<td width="22%"></td>
								<td width="78%">
									<input type="submit" name="Save" class="formbtn" id="save" value="Save" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</td>
		</tr>
	</tbody>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
