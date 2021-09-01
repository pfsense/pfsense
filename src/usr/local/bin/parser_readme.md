# pfSense-dhcpv6-gui-leases-patch

The parser content consists of:
* Pattern matching for IPv6 addresses: *parser_ipv6.inc*<br/>
, found in /etc/inc/
* Unit testing of IPv6 addresses: *parser_ipv6_tester.php*<br/>
, found in /usr/local/bin/<br/>
Call:
<pre>php -f parser_ipv6_tester.php</pre>
, there should always be 0 fails. Amount of passes depends on the test content.<br/>
'php -f' is optional on a pfSense system.
* Tools for debugging purposes: *tools_for_debugging_n_testing.inc*
, found in /etc/inc/
* Parsing of ISC DHCPv6 lease file: *parser_dhcpv6_leases.inc*<br/>
, found in /etc/inc/<br/>
, which is used by the */usr/local/www/status_dhcpv6_leases.php* file<br/>
to present the Status of DHCPv6 leases.
* Lease tester: *parser_dhcpv6_leases_tester.php*<br/>
, found in /usr/local/bin/
, that when started from the command line initiates *parser_dhcpv6_leases.inc*<br/>
and prints out lease and debug information.

Main focus in this read me file will be on the leases tester.<br/>
The lease tester is good to have since it shows what the dhcpv6 leases file
contains before it is consumed by the status dhcpv6 leases page.<br/>
Especially for debugging this comes in handy as you some times might see some
leases that do not contain all the content you expect. Whether it be a defect
in ISC DHCP or something else it is nice to have some command line tool to
provide an overview of the content of the otherwise not so readable lease file.
Basically an overview that gets a little closer to what we know from the
DHCPv6 Status Leases page of pfSense.

## Intention of it all
Original intention was to provide a fix for the status_dhcpv6_leases.php file
to better handle lease content. But as can be seen a little extra got into it.

## Using the lease tester
To parse an ISC DHCPv6 lease file (as used by pfSense) call e.g.:<br/>
<pre>php -f parser_dhcpv6_leases_tester.php &lt;file&gt;</pre>

#### Running on a pfSense system
* 'php -f' is optional on a pfSense system.
* &lt;file&gt; is optional on a pfSense system.<br/>
When not provided the default lease file will be used<br/>
( /var/dhcpd/var/db/dhcpd6.leases )
* Real NDP data is used from the running system.

#### Running on a non-pfSense system
* &lt;file&gt; is required.
* No NDP data is used.

### The tester returns
* Evaluation of each individual lease.<br/>
If you really want to test the parser try modify the content of the lease file
or the arrangement of the curly braces {}.
* Evaluation of each failover entry.<br/>
Experimental as mentioned in the Failover section below.
* The set of *Leases* presented as array entries.
* The set of *Prefixes*, used for prefix delegation (PD), presented as array
entries.
* The set of *Mappings* in IA-NA leases between the DUID+IAID and the IA-NA address.
* The set of *Pools* when failover entries exists. Meaning each entry explain
own and peer status. That means there are two DHCP servers/pfSenses sharing a
common Failover Group name.

## Failover
Failover handling is currently experimental as no live configured system has
been tested. Only source code and other sources of information has been used
and the information gathered here has been tested/injected into a dhcpv6 leases
file:<br/>
<pre><code>failover peer "Failover-Pair-Name" state {
  my state recover-wait at 1 2017/03/03 20:20:12;
  partner state communications-interrupted at 1 2017/03/03 20:20:12;
  mclt 123;
}

failover peer "Failover-2GETHER" state {
  my state recover-done at 1 2017/12/03 21:24:12;
  partner state unknown-state at 1 2017/03/03 21:44:12;
  mclt 456;
}</code></pre>

## Defect reports
https://github.com/al-right/pfSense-dhcpv6-gui-leases-patch<br/>
https://redmine.pfsense.org/<br/>
, but if in doubt first try https://forum.netgate.com/
