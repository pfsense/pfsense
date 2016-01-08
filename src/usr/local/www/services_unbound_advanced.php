<?php
/*
	services_unbound_advanced.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2015  Warren Baker (warren@percol8.co.za)
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-services-dnsresolver-advanced
##|*NAME=Services: DNS Resolver: Advanced
##|*DESCR=Allow access to the 'Services: DNS Resolver: Advanced' page.
##|*MATCH=services_unbound_advanced.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("unbound.inc");

if (!is_array($config['unbound'])) {
	$config['unbound'] = array();
}

if (isset($config['unbound']['hideidentity'])) {
	$pconfig['hideidentity'] = true;
}

if (isset($config['unbound']['hideversion'])) {
	$pconfig['hideversion'] = true;
}

if (isset($config['unbound']['prefetch'])) {
	$pconfig['prefetch'] = true;
}

if (isset($config['unbound']['prefetchkey'])) {
	$pconfig['prefetchkey'] = true;
}

if (isset($config['unbound']['dnssecstripped'])) {
	$pconfig['dnssecstripped'] = true;
}

$pconfig['msgcachesize'] = $config['unbound']['msgcachesize'];
$pconfig['outgoing_num_tcp'] = isset($config['unbound']['outgoing_num_tcp']) ? $config['unbound']['outgoing_num_tcp'] : '10';
$pconfig['incoming_num_tcp'] = isset($config['unbound']['incoming_num_tcp']) ? $config['unbound']['incoming_num_tcp'] : '10';
$pconfig['edns_buffer_size'] = isset($config['unbound']['edns_buffer_size']) ? $config['unbound']['edns_buffer_size'] : '4096';
$pconfig['num_queries_per_thread'] = $config['unbound']['num_queries_per_thread'];
$pconfig['jostle_timeout'] = isset($config['unbound']['jostle_timeout']) ? $config['unbound']['jostle_timeout'] : '200';
$pconfig['cache_max_ttl'] = isset($config['unbound']['cache_max_ttl']) ? $config['unbound']['cache_max_ttl'] : '86400';
$pconfig['cache_min_ttl'] = isset($config['unbound']['cache_min_ttl']) ? $config['unbound']['cache_min_ttl'] : '0';
$pconfig['infra_host_ttl'] = isset($config['unbound']['infra_host_ttl']) ? $config['unbound']['infra_host_ttl'] : '900';
$pconfig['infra_cache_numhosts'] = isset($config['unbound']['infra_cache_numhosts']) ? $config['unbound']['infra_cache_numhosts'] : '10000';
$pconfig['unwanted_reply_threshold'] = isset($config['unbound']['unwanted_reply_threshold']) ? $config['unbound']['unwanted_reply_threshold'] : 'disabled';
$pconfig['log_verbosity'] = isset($config['unbound']['log_verbosity']) ? $config['unbound']['log_verbosity'] : "1";

if (isset($config['unbound']['disable_auto_added_access_control'])) {
	$pconfig['disable_auto_added_access_control'] = true;
}

if (isset($config['unbound']['use_caps'])) {
	$pconfig['use_caps'] = true;
}

if ($_POST) {
	if ($_POST['apply']) {
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			clear_subsystem_dirty('unbound');
		}
	} else {
		unset($input_errors);
		$pconfig = $_POST;

		if (isset($_POST['msgcachesize']) && !in_array($_POST['msgcachesize'], array('4', '10', '20', '50', '100', '250', '512'), true)) {
			$input_errors[] = "A valid value for Message Cache Size must be specified.";
		}
		if (isset($_POST['outgoing_num_tcp']) && !in_array($_POST['outgoing_num_tcp'], array('0', '10', '20', '30', '40', '50'), true)) {
			$input_errors[] = "A valid value must be specified for Outgoing TCP Buffers.";
		}
		if (isset($_POST['incoming_num_tcp']) && !in_array($_POST['incoming_num_tcp'], array('0', '10', '20', '30', '40', '50'), true)) {
			$input_errors[] = "A valid value must be specified for Incoming TCP Buffers.";
		}
		if (isset($_POST['edns_buffer_size']) && !in_array($_POST['edns_buffer_size'], array('512', '1480', '4096'), true)) {
			$input_errors[] = "A valid value must be specified for EDNS Buffer Size.";
		}
		if (isset($_POST['num_queries_per_thread']) && !in_array($_POST['num_queries_per_thread'], array('512', '1024', '2048'), true)) {
			$input_errors[] = "A valid value must be specified for Number of Queries per Thread.";
		}
		if (isset($_POST['jostle_timeout']) && !in_array($_POST['jostle_timeout'], array('100', '200', '500', '1000'), true)) {
			$input_errors[] = "A valid value must be specified for Jostle Timeout.";
		}
		if (isset($_POST['cache_max_ttl']) && (!is_numericint($_POST['cache_max_ttl']) || ($_POST['cache_max_ttl'] < 0))) {
			$input_errors[] = "'Maximum TTL for RRsets and Messages' must be a positive integer.";
		}
		if (isset($_POST['cache_min_ttl']) && (!is_numericint($_POST['cache_min_ttl']) || ($_POST['cache_min_ttl'] < 0))) {
			$input_errors[] = "'Minimum TTL for RRsets and Messages' must be a positive integer.";
		}
		if (isset($_POST['infra_host_ttl']) && !in_array($_POST['infra_host_ttl'], array('60', '120', '300', '600', '900'), true)) {
			$input_errors[] = "A valid value must be specified for TTL for Host Cache Entries.";
		}
		if (isset($_POST['infra_cache_numhosts']) && !in_array($_POST['infra_cache_numhosts'], array('1000', '5000', '10000', '20000', '50000'), true)) {
			$input_errors[] = "A valid value must be specified for Number of Hosts to Cache.";
		}
		if (isset($_POST['unwanted_reply_threshold']) && !in_array($_POST['unwanted_reply_threshold'], array('disabled', '5000000', '10000000', '20000000', '40000000', '50000000'), true)) {
			$input_errors[] = "A valid value must be specified for Unwanted Reply Threshold.";
		}
		if (isset($_POST['log_verbosity']) && !in_array($_POST['log_verbosity'], array('0', '1', '2', '3', '4', '5'), true)) {
			$input_errors[] = "A valid value must be specified for Log Level.";
		}
		if (isset($_POST['dnssecstripped']) && !isset($config['unbound']['dnssec'])) {
			$input_errors[] = "Harden DNSSEC Data option can only be enabled if DNSSEC support is enabled.";
		}

		if (!$input_errors) {
			if (isset($_POST['hideidentity'])) {
				$config['unbound']['hideidentity'] = true;
			} else {
				unset($config['unbound']['hideidentity']);
			}
			if (isset($_POST['hideversion'])) {
				$config['unbound']['hideversion'] = true;
			} else {
				unset($config['unbound']['hideversion']);
			}
			if (isset($_POST['prefetch'])) {
				$config['unbound']['prefetch'] = true;
			} else {
				unset($config['unbound']['prefetch']);
			}
			if (isset($_POST['prefetchkey'])) {
				$config['unbound']['prefetchkey'] = true;
			} else {
				unset($config['unbound']['prefetchkey']);
			}
			if (isset($_POST['dnssecstripped'])) {
				$config['unbound']['dnssecstripped'] = true;
			} else {
				unset($config['unbound']['dnssecstripped']);
			}
			$config['unbound']['msgcachesize'] = $_POST['msgcachesize'];
			$config['unbound']['outgoing_num_tcp'] = $_POST['outgoing_num_tcp'];
			$config['unbound']['incoming_num_tcp'] = $_POST['incoming_num_tcp'];
			$config['unbound']['edns_buffer_size'] = $_POST['edns_buffer_size'];
			$config['unbound']['num_queries_per_thread'] = $_POST['num_queries_per_thread'];
			$config['unbound']['jostle_timeout'] = $_POST['jostle_timeout'];
			$config['unbound']['cache_max_ttl'] = $_POST['cache_max_ttl'];
			$config['unbound']['cache_min_ttl'] = $_POST['cache_min_ttl'];
			$config['unbound']['infra_host_ttl'] = $_POST['infra_host_ttl'];
			$config['unbound']['infra_cache_numhosts'] = $_POST['infra_cache_numhosts'];
			$config['unbound']['unwanted_reply_threshold'] = $_POST['unwanted_reply_threshold'];
			$config['unbound']['log_verbosity'] = $_POST['log_verbosity'];

			if (isset($_POST['disable_auto_added_access_control'])) {
				$config['unbound']['disable_auto_added_access_control'] = true;
			} else {
				unset($config['unbound']['disable_auto_added_access_control']);
			}

			if (isset($_POST['use_caps'])) {
				$config['unbound']['use_caps'] = true;
			} else {
				unset($config['unbound']['use_caps']);
			}

			write_config("DNS Resolver configured.");

			mark_subsystem_dirty('unbound');
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("DNS Resolver"), gettext("Advanced"));
$shortcut_section = "resolver";
include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
        print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('unbound')) {
	print_info_box_np(gettext("The configuration of the DNS Resolver has been changed. You must apply changes for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("General settings"), false, "services_unbound.php");
$tab_array[] = array(gettext("Advanced settings"), true, "services_unbound_advanced.php");
$tab_array[] = array(gettext("Access Lists"), false, "/services_unbound_acls.php");
display_top_tabs($tab_array, true);

$form = new Form();

$section = new Form_Section('Advanced Resolver Options');

$section->addInput(new Form_Checkbox(
	'hideidentity',
	'Hide Identity',
	'id.server and hostname.bind queries are refused',
	$pconfig['hideidentity']
));

$section->addInput(new Form_Checkbox(
	'hideversion',
	'Hide Version',
	'version.server and version.bind queries are refused',
	$pconfig['hideversion']
));

$section->addInput(new Form_Checkbox(
	'prefetch',
	'Prefetch Support',
	'Message cache elements are prefetched before they expire to help keep the cache up to date',
	$pconfig['prefetch']
))->setHelp('When enabled, this option can cause an increase of around 10% more DNS traffic and load on the server, but frequently requested items will not expire from the cache');

$section->addInput(new Form_Checkbox(
	'prefetchkey',
	'Prefetch DNS Key Support',
	'DNSKEYs are fetched earlier in the validation process when a Delegation signer is encountered',
	$pconfig['prefetchkey']
))->setHelp('This helps lower the latency of requests but does utilize a little more CPU. See: <a href="http://en.wikipedia.org/wiki/List_of_DNS_record_types">Wikipedia</a>');

$section->addInput(new Form_Checkbox(
	'dnssecstripped',
	'Harden DNSSEC Data',
	'DNSSEC data is required for trust-anchored zones.',
	$pconfig['dnssecstripped']
))->setHelp('If such data is absent, the zone becomes bogus. If Disabled and no DNSSEC data is received, then the zone is made insecure. ');

$section->addInput(new Form_Select(
	'msgcachesize',
	'Message Cache Size',
	$pconfig['msgcachesize'],
	array_combine(array("4", "10", "20", "50", "100", "250", "512"), array("4 MB", "10 MB", "20 MB", "50 MB", "100 MB", "250 MB", "512 MB"))
))->setHelp('Size of the message cache. The message cache stores DNS response codes and validation statuses. The Resource Record Set (RRSet) cache will automatically be set to twice this amount. The RRSet cache contains the actual RR data. The default is 4 megabytes.');

$section->addInput(new Form_Select(
	'outgoing_num_tcp',
	'Outgoing TCP Buffers',
	$pconfig['outgoing_num_tcp'],
	array_combine(array("0", "10", "20", "30", "50", "50"), array("0", "10", "20", "30", "50", "50"))
))->setHelp('The number of outgoing TCP buffers to allocate per thread. The default value is 10. If 0 is selected then TCP queries are not sent to authoritative servers.');

$section->addInput(new Form_Select(
	'incoming_num_tcp',
	'Incoming TCP Buffers',
	$pconfig['incoming_num_tcp'],
	array_combine(array("0", "10", "20", "30", "50", "50"), array("0", "10", "20", "30", "50", "50"))
))->setHelp('The number of incoming TCP buffers to allocate per thread. The default value is 10. If 0 is selected then TCP queries are not accepted from clients.');

$section->addInput(new Form_Select(
	'edns_buffer_size',
	'EDNS Buffer Size',
	$pconfig['edns_buffer_size'],
	array_combine(array("512", "1480", "4096"), array("512", "1480", "4096"))
))->setHelp('Number of bytes size to advertise as the EDNS reassembly buffer size. This is the value that is used in UDP datagrams sent to peers. ' .
			'RFC recommendation is 4096 (which is the default). If you have fragmentation reassemble problems, usually seen as timeouts, then a value of 1480 should help. ' .
			'The 512 value bypasses most MTU path problems, but it can generate an excessive amount of TCP fallback.');

$section->addInput(new Form_Select(
	'num_queries_per_thread',
	'Number of Queries per Thread',
	$pconfig['num_queries_per_thread'],
	array_combine(array("512", "1024", "2048"), array("512", "1024", "2048"))
))->setHelp('The number of queries that every thread will service simultaneously. If more queries arrive that need to be serviced, and no queries can be jostled, then these queries are dropped');

$section->addInput(new Form_Select(
	'jostle_timeout',
	'Jostle Timeout',
	$pconfig['jostle_timeout'],
	array_combine(array("100", "200", "500", "1000"), array("100", "200", "500", "1000"))
))->setHelp('This timeout is used for when the server is very busy. This protects against denial of service by slow queries or high query rates. The default value is 200 milliseconds. ');

$section->addInput(new Form_Input(
	'cache_max_ttl',
	'Maximum TTL for RRsets and Messages',
	'text',
	$pconfig['cache_max_ttl']
))->setHelp('The Maximum Time to Live for RRsets and messages in the cache. The default is 86400 seconds (1 day). ' .
			'When the internal TTL expires the cache item is expired. This can be configured to force the resolver to query for data more often and not trust (very large) TTL values');

$section->addInput(new Form_Input(
	'cache_min_ttl',
	'Minimum TTL for RRsets and Messages',
	'text',
	$pconfig['cache_min_ttl']
))->setHelp('The Minimum Time to Live for RRsets and messages in the cache. ' .
			'The default is 0 seconds. If the minimum value kicks in, the data is cached for longer than the domain owner intended, and thus less queries are made to look up the data. ' .
			'The 0 value ensures the data in the cache is as the domain owner intended. High values can lead to trouble as the data in the cache might not match up with the actual data anymore.');

$section->addInput(new Form_Select(
	'infra_host_ttl',
	'TTL for Host Cache Entries',
	$pconfig['infra_host_ttl'],
	array_combine(array("60", "120", "300", "600", "900"), array("1 minute", "2 minutes", "5 minutes", "10 minutes", "15 minutes"))
))->setHelp('Time to Live, in seconds, for entries in the infrastructure host cache. The infrastructure host cache contains round trip timing, lameness, and EDNS support information for DNS servers. The default value is 15 minutes.');

$section->addInput(new Form_Select(
	'infra_cache_numhosts',
	'Number of Hosts to Cache',
	$pconfig['infra_cache_numhosts'],
	array_combine(array("1000", "5000", "10000", "20000", "50000"), array("1000", "5000", "10000", "20000", "50000"))
))->setHelp('Number of infrastructure hosts for which information is cached. The default is 10,000.');

$section->addInput(new Form_Select(
	'unwanted_reply_threshold',
	'Unwanted Reply Threshold',
	$pconfig['unwanted_reply_threshold'],
	array_combine(array("disabled", "5000000", "10000000", "20000000", "40000000", "50000000"),
				  array("Disabled", "5 million", "10 million", "20 million", "40 million", "50 million"))
))->setHelp('If enabled, a total number of unwanted replies is kept track of in every thread. When it reaches the threshold, a defensive action is taken ' .
			'and a warning is printed to the log file. This defensive action is to clear the RRSet and message caches, hopefully flushing away any poison. ' .
			'The default is disabled, but if enabled a value of 10 million is suggested.');

$section->addInput(new Form_Select(
	'log_verbosity',
	'Log Level',
	$pconfig['log_verbosity'],
	array_combine(array("0", "1", "2", "3", "4", "5"), array("Level 0", "Level 1", "Level 2", "Level 3", "Level 4", "Level 5"))
))->setHelp('Select the log verbosity.');

$section->addInput(new Form_Checkbox(
	'disable_auto_added_access_control',
	'Disable Auto-added Access Control',
	'disable the automatically-added access control entries',
	$pconfig['disable_auto_added_access_control']
))->setHelp('By default, IPv4 and IPv6 networks residing on internal interfaces of this system are permitted. ' .
			'Allowed networks must be manually configured on the Access Lists tab if the auto-added entries are disabled.');

$section->addInput(new Form_Checkbox(
	'use_caps',
	'Experimental Bit 0x20 Support',
	'Use 0x-20 encoded random bits in the DNS query to foil spoofing attempts.',
	$pconfig['use_caps']
))->setHelp('See the implementation <a href="https://tools.ietf.org/html/draft-vixie-dnsext-dns0x20-00">draft dns-0x20</a> for more information: ');

$form->add($section);
print($form);

include("foot.inc");
