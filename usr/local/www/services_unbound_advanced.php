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
$a_unboundcfg =& $config['unbound'];

$pconfig['enable'] = isset($config['unbound']['enable']);
$pconfig['active_interface'] = $config['unbound']['active_interface'];
$pconfig['outgoing_interface'] = $config['unbound']['outgoing_interface'];
$pconfig['dnssec'] = isset($config['unbound']['dnssec']);
$pconfig['forwarding'] = isset($config['unbound']['forwarding']);
$pconfig['regdhcp'] = isset($config['unbound']['regdhcp']);
$pconfig['regdhcpstatic'] = isset($config['unbound']['regdhcpstatic']);
$pconfig['dhcpfirst'] = isset($config['unbound']['dhcpfirst']);
$pconfig['hideidentity'] = isset($config['unbound']['hideidentity']);
$pconfig['hideversion'] = isset($config['unbound']['hideversion']);
$pconfig['prefetch'] = isset($config['unbound']['prefetch']);
$pconfig['prefetchkey'] = isset($config['unbound']['prefetchkey']);
$pconfig['hardenglue'] = isset($config['unbound']['hardenglue']);
$pconfig['dnssecstripped'] = isset($config['unbound']['dnssecstripped']);
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

	unset($input_errors);

	if (!$input_errors) {
		$a_unboundcfg['hideidentity'] = ($_POST['hideidentity']) ? true : false;
		$a_unboundcfg['hideversion'] = ($_POST['hideversion']) ? true : false;
		$a_unboundcfg['prefetch'] = ($_POST['prefetch']) ? true : false;
		$a_unboundcfg['prefetchkey'] = ($_POST['prefetchkey']) ? true : false;
		$a_unboundcfg['hardenglue'] = ($_POST['hardenglue']) ? true : false;
		$a_unboundcfg['dnssecstripped'] = ($_POST['dnssecstripped']) ? true : false;
		$a_unboundcfg['custom_options'] =  str_replace("\r\n", "\n", $_POST['custom_options']);
		write_config("DNS Resolver configured.");

		$retval = 0;
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('unbound');
	}
}

$closehead = false;
$pgtitle = array(gettext("Services"),gettext("DNS Resolver"),gettext("Advanced"));
include_once("head.inc");

?>

<script type="text/javascript">
//<![CDATA[
function enable_change(enable_over) {
	var endis;
	endis = !(jQuery('#enable').is(":checked") || enable_over);
	jQuery("#active_interface,#outgoing_interface,#dnssec,#forwarding,#regdhcp,#regdhcpstatic,#dhcpfirst,#port").prop('disabled', endis);
}
//]]>
</script>
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
									<p><input name="hideidentity" type="checkbox" id="hideidentity" value="yes" <?php if ($pconfig['hideidentity'] === true) echo "checked=\"checked\"";?> onclick="enable_change(false)" /><br />
									<?=gettext("If enabled, id.server and hostname.bind queries are refused.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Hide Version");?></td>
								<td width="78%" class="vtable">
									<p><input name="enable" type="checkbox" id="hideversion" value="yes" <?php if ($pconfig['hideversion'] == "yes") echo "checked=\"checked\"";?> onclick="enable_change(false)" /><br />
									<?=gettext("If enabled, version.server and version.bind queries are refused.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Prefetch Support");?></td>
								<td width="78%" class="vtable">
									<p><input name="enable" type="checkbox" id="prefetch" value="yes" <?php if ($pconfig['prefetch'] == "yes") echo "checked=\"checked\"";?> onclick="enable_change(false)" /><br />
									<?=gettext("Message cache elements are prefetched before they expire to help keep the cache up to date. When enabled, this option can cause an increase of around 10% more DNS traffic and load on the server, but frequently requested items will not expire from the cache.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Prefetch DNS Key Support");?></td>
								<td width="78%" class="vtable">
									<p><input name="enable" type="checkbox" id="prefetchkey" value="yes" <?php if ($pconfig['prefetchkey'] == "yes") echo "checked=\"checked\"";?> onclick="enable_change(false)" /><br />
									<?=sprintf(gettext("DNSKEY's are fetched earlier in the validation process when a %sDelegation signer%s is encountered. This helps lower the latency of requests but does utilize a little more CPU."), "<a href='http://en.wikipedia.org/wiki/List_of_DNS_record_types'>", "</a>");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Harden Glue");?></td>
								<td width="78%" class="vtable">
									<p><input name="enable" type="checkbox" id="hardenglue" value="yes" <?php if ($pconfig['hardenglue'] == "yes") echo "checked=\"checked\"";?> onclick="enable_change(false)" /><br />
									<?=gettext("Only trust glue if it is within the servers authority.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Harden DNSSEC data");?></td>
								<td width="78%" class="vtable">
									<p><input name="enable" type="checkbox" id="dnssecstripped" value="yes" <?php if ($pconfig['dnssecstripped'] == "yes") echo "checked=\"checked\"";?> onclick="enable_change(false)" /><br />
									<?=gettext("DNSSEC data is required for trust-anchored zones. If such data is absent, the zone becomes bogus. If this is disabled and no DNSSEC data is received, then the zone is made insecure.");?></p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Message Cache Size");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="msgcachesize" name="msgcachesize">
											<option value="4">4MB</option>
											<option value="10">10MB</option>
											<option value="20">20MB</option>
											<option value="50">50MB</option>
											<option value="100">100MB</option>
											<option value="250">250MB</option>
											<option value="512">512MB</option>
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
											<option value="0">0</option>
											<option value="10">10</option>
											<option value="20">20</option>
											<option value="30">30</option>
											<option value="40">40</option>
											<option value="50">50</option>
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
											<option value="0">0</option>
											<option value="10">10</option>
											<option value="20">20</option>
											<option value="30">30</option>
											<option value="40">40</option>
											<option value="50">50</option>
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
											<option value="512">512</option>
											<option value="1480">1480</option>
											<option value="4096">4096</option>
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
											<option value="512">512</option>
											<option value="1024">1024</option>
											<option value="2048">2048</option>
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
											<option value="100">100</option>
											<option value="200">200</option>
											<option value="500">500</option>
											<option value="1000">1000</option>
										</select><br />
										<?=gettext("This timeout is used for when the server is very busy. This protects against denial of service by slow queries or high query rates. The default value is 200 milliseconds.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Maximum TTL for RRsets and messages");?></td>
								<td width="78%" class="vtable">
									<p>
										<input type="text" id="cache_max_ttl" name="cache_max_ttl" size="5" /><br />
										<?=gettext("Configure a maximum Time to live for RRsets and messages in the cache. The default is 86400 seconds (1 day). When the internal TTL expires the cache item is expired. This can be configured to force the resolver to query for data more often and not trust (very large) TTL values.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Minimum TTL for RRsets and messages");?></td>
								<td width="78%" class="vtable">
									<p>
										<input type="text" id="cache_min_ttl" name="cache_min_ttl" size="5" /><br />
										<?=gettext("Configure a minimum Time to live for RRsets and messages in the cache. The default is 0 seconds. If the minimum value kicks in, the data is cached for longer than the domain owner intended, and thus less queries are made to look up the data. The 0 value ensures the data in the cache is as the domain owner intended. High values can lead to trouble as the data in the cache might not match up with the actual data anymore.");?>
									</p>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("TTL for Host cache entries");?></td>
								<td width="78%" class="vtable">
									<p>
										<select id="infra_host_ttl" name="infra_host_ttl">
											<option value="60">1 minute</option>
											<option value="120">2 minutes</option>
											<option value="300">5 minutes</option>
											<option value="600">10 minutes</option>
											<option value="900">15 minutes</option>
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
											<option value="60">1 minute</option>
											<option value="120">2 minutes</option>
											<option value="300">5 minutes</option>
											<option value="600">10 minutes</option>
											<option value="900">15 minutes</option>
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
											<option value="1000">1000</option>
											<option value="5000">5000</option>
											<option value="10000">10 000</option>
											<option value="20000">20 000</option>
											<option value="50000">50 000</option>
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
											<option value="disabled">disabled</option>
											<option value="5000000">5 million</option>
											<option value="10000000">10 million</option>
											<option value="20000000">20 million</option>
											<option value="40000000">40 million</option>
											<option value="50000000">50 million</option>
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
											<option value="0">Level 0</option>
											<option value="1">Level 1</option>
											<option value="2">Level 2</option>
											<option value="3">Level 3</option>
											<option value="4">Level 4</option>
											<option value="5">Level 5</option>
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
