### 2.0.3 (April 15, 2013)

Security Fixes

* Updated to OpenSSL 0.9.8y to address [FreeBSD-SA-13:03](http://www.freebsd.org/security/advisories/FreeBSD-SA-13:03.openssl.asc).
* Fix below XSS in IPsec log possible from users possessing shared key or valid certificate
* Below S.M.A.R.T. input validation fix isn’t security relevant in the vast majority of use cases, but it could lead to privilege escalation for an administrative user with limited rights who can access the S.M.A.R.T. pages but cannot access any of the pages that allow command execution by design.

PPP

* Fix obtaining DNS servers from PPP type WANs (PPP, PPPoE, PPTP, L2TP)

Captive Portal

* Fix Captive Portal Redirect URL trimming
* Voucher sync fixes
* Captive portal pruning/locking fixes
* Fix problem with fastcgi crashing which caused CP issues on 2.0.2

OpenVPN

* Clear the route for an OpenVPN endpoint IP when restarting the VPN, to avoid a situation where a learned route from OSPF or elsewhere could prevent an instance from restarting properly
* Always clear the OpenVPN route when using shared key, no matter how the tunnel network "CIDR" is set
* Use the actual OpenVPN restart routine when starting/stopping from services rather than killing/restarting manually
* Allow editing an imported CRL, and refresh OpenVPN CRLs when saving. [[#2652](https://redmine.pfsense.org/issues/2652)]
* Fix interface assignment descriptions when using &gt; 10 OpenVPN instances

Logging

* Put syslogd into secure mode so it refuses remote syslog messages
* If syslog messages are in the log, and the hostname does not match the firewall, display the supplied hostname
* Fix PPP log display to use the correct log handling method
* Run IPsec logs through htmlspecialchars before display to avoid a potential persistent XSS from racoon log output (e.g. username)

Traffic Shaper

* Fix editing of traffic shaper default queues. [[#1995](https://redmine.pfsense.org/issues/1995)]
* Fix wording for VoIP address option in the shaper. Add rule going the other direction to catch connections initiated both ways

Dashboard &amp; General GUI

* Use some tweaks to PHP session management to prevent the GUI from blocking additional requests while others are active
* Remove cmd_chain.inc and preload.php to fix some issues with lighttpd, fastcgi, and resource usage
* Firmware settings manifest (Site list) now bolds and denotes entries that match the current architecture, to help avoid accidental cross-architecture upgrades
* Add header to DHCP static mappings table
* When performing a factory reset in the GUI, change output style to follow halt.php and reboot.php so the shutdown output appears in the correct location on the page
* Better validation of parameters passed during S.M.A.R.T. operations for testing HDDs
* Fixed SNMP interface binding glitch (Setting was active but not reflected when viewed in GUI)
* Add a new class called addgatewaybox to make it easier to respect custom themes [[#2900](https://redmine.pfsense.org/issues/2900)]

Console Menu Changes

* Correct accidental interface assignment changes when changing settings on the console menu
* Console menu option 11 now kills all active PHP processes, kills lighttpd, and then restarts the GUI. This is a more effective way to restart the GUI since if a PHP process is hung, restarting lighttpd alone will not recover from that
* Fix port display after LAN IP reset

Misc Changes

* Change how the listening address is passed to miniupnpd, the old method was resulting in errors for some users
* Fix "out" packet count reporting
* Be a little smarter about the default kernel in rare cases where we cannot determine what was in use
* Pass -S to tcpdump to avoid an increase in memory consumption over time in certain cases
* Minimise rewriting of /etc/gettytab
* Make is_pid_running function return more consistent results by using isvalidpid
* Fix ataidle error on systems that have no ATA HDD. [[#2739](https://redmine.pfsense.org/issues/2739)]
* Update Time Zone database zoneinfo to 2012.j to pick up on recent zone/DST/etc changes
* Fix handling of LDAP certificates, the library no longer properly handles files with spaces in the CA certificate filename
* Bring in the RCFILEPREFIX as constant fixes from HEAD, since otherwise rc.stop_packages was globbing in the wrong dir and executing the wrong scripts. Also seems to have fixed the "bad fd" error
* NTP restart fixes
* Gitsync now pulls in git package from pfSense package repository rather than FreeBSD
* Fixed handling of RRD data in config.xml backups when exporting an encrypted config [[#2836](http://redmine.pfsense.org/issues/2836)]
* Moved apinger status to /var/run instead of /tmp
* Fixes for FTP proxy on non-default gateway WANs
* Fixes for OVA images
* Use new pfSense repository location (http://github.com/pfsense/pfsense/)
* Add patch to compensate apinger calculation for down gateways by time taken from other tasks like rrd/status file/etc

lighttpd changes

* Improve tuning of lighttpd and php processes
* Use separate paths for GUI and Captive Portal fastcgi sockets
* Always make sure php has its own process manager to make lighttpd happy
* Make mod_fastcgi last to have url.rewrite work properly
* Enable mod_evasive if needed for Captive Portal
* Simplify lighttpd config
* Send all lighttpd logs to syslog

Binary changes

* dnsmasq to 2.65
* rsync to 3.0.9
* links 2.7
* rrdtool to 1.2.30
* PHP to 5.2.17_13
* OpenVPN 2.2 stock again (Removed IPv6 patches since those are only needed on 2.1 now)
* Fix missing "beep" binary on amd64
* Fix potential issue with IPsec routing of client traffic
* Remove lighttpd spawnfcgi dependency
* Add splash device to wrap_vga kernels (It’s in GENERIC so full installs already have it). [[#2723](https://redmine.pfsense.org/issues/2723)]

filterdns

* Correct an issue with unallocated structure
* Avoid issues with pidfiles being overwritten, lock the file during modifications
* Make filterdns restartable and properly cleanup its tables upon exit or during a reconfiguration

dhcpleases

* Correct use after free and also support hostnames with other DNS suffix
* Reinit on any error rather than just forgetting. Also the difftime checks are done after having complete view, no need to do them every time
* Typo fixes
* Log that a HUP signal is being sent to the pid file submitted by argument
* Prevent bad parsing of empty hostnames in lease file. Add an f option to run dhcplease in foreground. The only option needed while in foreground is h parameter and the only usable one as well

### 2.0.2 (December 21, 2012)

FreeBSD Security Advisories

* Base OS updated to 8.1-RELEASE-p13 to address the following FreeBSD Security Advisories:
    * [FreeBSD-SA-12:01.openssl](http://security.freebsd.org/advisories/FreeBSD-SA-12:01.openssl.asc) (v1.0/v1.1) 
    * [FreeBSD-SA-12:04.sysret](http://security.FreeBSD.org/advisories/FreeBSD-SA-12:04.sysret.asc) (v1.0/v1.1) 
    * [FreeBSD-SA-12:07.hostapd](http://www.freebsd.org/security/advisories/FreeBSD-SA-12:07.hostapd.asc) 
    * NOTE: FreeBSD-SA-12:03.bind, FreeBSD-SA-12:05.bind, and FreeBSD-SA-12:06.bind do not apply to us, since we do not use nor include bind. FreeBSD-SA-12:08.linux does not apply since we do not use nor include the Linux compatibility layer of FreeBSD. FreeBSD-SA-12:02.crypt doesn’t apply because we don’t use DES in that context.
PPTP
* Added a warning to PPTP VPN configuration page: PPTP is no longer considered a secure VPN technology because it relies upon MS-CHAPv2 which has been compromised. If you continue to use PPTP be aware that intercepted traffic can be decrypted by a third party, so it should be considered unencrypted. We advise migrating to another VPN type such as OpenVPN or IPsec.
* Fix reference to PPTP secondary RADIUS server shared secret.
* PPTP 1.x to 2.x config upgrade fixes.

NTP Changes

* OpenNTPD was dropped in favor of the ntp.org NTP daemon, used by FreeBSD.
* Status page added (Status &gt; NTP) to show status of clock sync
* NTP logging fixed.
* NOTE: ntpd will bind/listen to all interfaces by default, and it has to in order to receive replies. You can still do selective interface binding to control which IPs will accept traffic, but be aware that the default behavior has changed.

Dashboard &amp; General GUI Fixes

* Various fixes for typos, wording, and so on.
* Do not redirect on saving services status widget.
* Don’t use $pconfig in widgets, it has unintended side effects.
* Fix display of widgets with configuration controls in IE.
* Changed some padding/margin in the CSS in order to avoid wrapping the menu.
* Change to embed to prevent IE9 from misbehaving when loading the Traffic Graph page [[#2165](http://redmine.pfsense.org/issues/2165)]

OpenVPN Fixes

* Safer for 1.2.3 upgrades to assume OpenVPN interface == any, since 1.2.3 didn’t have a way to bind to an interface. Otherwise people accepting connections on OPT interfaces on 1.2.3 will break on upgrade until the proper interface is selected in the GUI
* Don’t ignore when multiple OpenVPN DNS, NTP, WINS, etc servers were specified in 1.2.3 when upgrading. 1.2.3 separated by ;, 2.x uses separate vars.
* Fix upgrade code for 1.2.3 with assigned OpenVPN interface.
* Fix LZO setting for Upgraded OpenVPN (was turning compression on even if old config had it disabled.)
* Be more intelligent when managing OpenVPN client connections bound to CARP VIPs. If the interface is in BACKUP status, do not start the client. Add a section to rc.carpmaster and rc.carpbackup to trigger this start/stop. If an OpenVPN client is active on both the master and backup system, they will cause conflicting connections to the server. Servers do not care as they only accept, not initiate.

IPsec fixes

* Only do foreach on IPsec p2′s if it’s actually an array.
* Don’t let an empty subnet into racoon.conf, it can cause parse errors. [[#2201](http://redmine.pfsense.org/issues/2201)]
* Reject an interface without a subnet as a network source in the IPsec Phase 2 GUI. [[#2201](http://redmine.pfsense.org/issues/2201)]
* Add routes even when IPsec is on WAN, as WAN may not be the default gateway.
* Revamped IPsec status display and widget to properly account for mobile clients. [[#1986](http://redmine.pfsense.org/issues/1986)]
* Fixed a bug that caused the IPsec status and widget to display slowly when mobile clients were enabled.

User Manager Fixes

* Improve adding/removing of users accounts to the underlying OS, especially accounts with a numeric username. [[#2066](http://redmine.pfsense.org/issues/2066)]
* Include admin user in bootup account sync
* Fix permission and certificate display for the admin user
* Fix ssh key note to refer to DSA not just RSA since both work.
* ":" chars are invalid in a comment field, filter them out.
* When renaming a user, make sure to remove the previous user or it gets left in /etc/passwd.
* Do not allow empty passwords since this might cause problems for some authentication servers like LDAP. [[#2326](http://redmine.pfsense.org/issues/2326)]

Captive Portal Fixes

* Take routing table into account when figuring out which IP address to use for talking to CP clients.
* Prevent browser auto-fill username and password on voucher config, as it can interfere with the settings being properly saved if sync isn’t fully configured, which this can make happen accidentally.
* Correct the Called-Station-Id attribute setting to be the same on STOP/START packets
* Correct the Called-Station-Id attribute setting to be consistent on the data sent
* Correct the log to display the correct information about an existing session [[#2082](http://redmine.pfsense.org/issues/2082)]
* Remove duplicate rule [[#2052](http://redmine.pfsense.org/issues/2052)]
* Fix which roll to write when writing the active voucher db
* Always load ipfw when enabling CP to ensure the pfil hooks are setup right
* Fix selection of CP interfaces when using more than 10 opt interfaces. [[#2378](http://redmine.pfsense.org/issues/2378)]
* Strengthen voucher randomization.

NAT/Firewall Rules/Alias Fixes

* Respect the value of the per-rule "disable reply-to" checkbox. [[#2327](http://redmine.pfsense.org/issues/2327)]
* Fix an invalid pf rule generated from a port forward with dest=any on an interface with ip=none [[#1882](http://redmine.pfsense.org/issues/1882)]
* 1:1 Reflection fixes for static route subnets and multiple subnets on the same interface. [[#2163](http://redmine.pfsense.org/issues/2163)]
* Better validation on URL table alias input from downloaded files.
* Better validation on URL table alias input from downloaded files.
* Don’t put an extra space after "pass" when assuming it as the default action or later tests will fail to match this as a pass rule. [[#2293](http://redmine.pfsense.org/issues/2293)]
* Update help text for Host aliases to indicate FQDNs are allowed.
* Go back to scrub rather than "scrub in", the latter breaks MSS clamping for egress traffic the way we use it. [[#2210](http://redmine.pfsense.org/issues/2210)]
* Fix preservation of the selection of interfaces on input errors for floating rules.
* Fix URL table update frequency box.
* Fix input validation for port forwards, Local Port must be specified.
* Added a setting to increase the maximum number of pf tables, and increased the default to 3000.
* Properly determine active GUI and redirect ports for anti-lockout rule, for display and in the actual rule.
* Handle loading pf limits (timers, states, table/entry limits, etc) in a separate file to avoid a chicken-and-egg scenario where the limits would never be increased properly.

Interface/Bridging Fixes

* Correct checking if a gif is part of bridge so that it actually works correctly adding a gif after having created it on bootup
* Use the latest functions from pfSense module for getting interface list
* Use the latest functions from pfSense module for creating bridges
* Implement is_jumbo_capable in a more performant way. This should help with large number of interfaces
* Since the CARP interface name changed to "vipN" from "carpN", devd needs to follow that change as well.
* Show lagg protocol and member interfaces on Status &gt; Interfaces. [[#2242](http://redmine.pfsense.org/issues/2242)]
* Correctly stop dhclient process when an interface is changed away from DHCP. [[#2212](http://redmine.pfsense.org/issues/2212)]
* Fixed 3G SIM PIN usage for Huawei devices
* Properly obey MTU set on Interface page for PPP type WANs.

Other Misc. Fixes

* Add a checkbox that disables automatically generating negate rules for directly connected networks and VPNs. [[#2057](http://redmine.pfsense.org/issues/2057)]
* Mark "Destination server" as a required field for DHCP Relay
* Clarify the potential pitfalls when setting the Frequency Probe and Down parameters.
* Add a PHP Shell shortcut to disable referer check (playback disablereferercheck)
* Make Wireless Status tables sortable [[#2040](http://redmine.pfsense.org/issues/2040)]
* Fix multiple keys in a file for RFC2136 dyndns updates. [[#2068](http://redmine.pfsense.org/issues/2068)]
* Check to see if the pid file exists before trying to kill a process
* Be smarter about how to split a Namecheap hostname into host/domain. [[#2144](http://redmine.pfsense.org/issues/2144)]
* Add a small script to disable APM on ATA drives if they claim to support it. Leaving this on will kill drives long-term, especially laptop drives, by generating excessive Load Cycles. The APM bit set will persist until the drive is power cycled, so it’s necessary to run on each boot to be sure.
* Change SNMP binding option to work on any eligible interface/VIP. If the old bindlan option is there, assume the lan interface for binding. [[#2158](http://redmine.pfsense.org/issues/2158)]
* Fix reference to PPTP secondary RADIUS server shared secret.
* Add button to download a .p12 of a cert+key. [[#2147](http://redmine.pfsense.org/issues/2147)]
* Carry over the key length on input errors when creating a certificate signing request. [[#2233](http://redmine.pfsense.org/issues/2233)]
* Use PHP’s built-in RFC 2822 date format, rather than trying to make our own. [[#2207](http://redmine.pfsense.org/issues/2207)]
* Allow specifying the branch name after the repository URL for gitsync command-line arguments and remove an unnecessary use of the backtick operator.
* Correct send_multiple_events to conform with new check_reload_status behaviour
* Do not wipe logs on reboot on full install
* Set FCGI_CHILDREN to 0 since it does not make sense for php to manage itself when lighttpd is doing so. This makes it possible to recover from 550-Internal… error.
* Support for xmlrpcauthuser and xmlrpcauthpass in $g.
* Fix Layer 7 pattern upload, button text check was incorrect.
* Correct building of traffic shaping queue to not depend on parent mask
* Add alias support to static routes [[#2239](http://redmine.pfsense.org/issues/2239)]
* Use !empty instead of isset to prevent accidental deletion of the last used repository URL when firmware update gitsync settings have been saved without a repository URL.
* Better error handling for crypt_data and also better password argument handling
* Stop service needs to wait for the process to be stopped before trying to restart it.
* Use a better default update url
* Fix missing description in rowhelper for packages.
* Move the stop_packages code to a function, and call the function from the shell script, and call the function directly for a reboot. [[#2402](http://redmine.pfsense.org/issues/2402), [#1564](http://redmine.pfsense.org/issues/1564)]
* Fix DHCP domain search list [[#1917](http://redmine.pfsense.org/issues/1917)]
* Update Time Zone zoneinfo database using latest zones from FreeBSD
* Handle HTTPOnly and Secure flags on cookies
* Fixed notifications for firmware upgrade progress
* Removed an invalid declaration that considered 99.0.0.0/8 a private address.
* Fixed redirect request for IE8/9
* Fix crashes on NanoBSD during package removal/reinstall. Could result in the GUI being inaccessible after a firmware update. [[#1049](http://redmine.pfsense.org/issues/1049)]
* Fix some issues with upgrading NanoBSD+VGA and NanoBSD+VGA Image Generation
* Fix issues upgrading from systems with the old "Uniprocessor" kernel which no longer exists.
* Fix a few potential XSS/CSRF vectors. Thanks to Ben Williams for his assistance in this area.
* Fixed issue with login page not showing the correct selected theme in certain configurations.
* Fix limiters+multi-wan

Binary/Supporting Program Updates

* Some cleanup to reduce overall image size
* Fixes to ipfw-classifyd file reading and handling
* Updated miniupnpd
* ISC DHCPD 4.2.4-P1
* mdp5 upgraded to 5.6
* pftop updated
* lighttpd updated to 1.4.32, for CVE-2011-4362 and CVE-2012-5533.

### 2.0.1 (December 20, 2011)

* Improved accuracy of automated state killing in various cases [[#1421](http://redmine.pfsense.org/issues/1421)]
* Various fixes and improvements to relayd
    * Added to Status &gt; Services and widget
    * Added ability to kill relayd when restarting [[#1913](http://redmine.pfsense.org/issues/1913)]
    * Added DNS load balancing
    * Moved relayd logs to their own tab
    * Fixed default SMTP monitor syntax and other send/expect syntax
* Fixed path to FreeBSD packages repo for 8.1
* Various fixes to syslog:
    * Fixed syslogd killing/restarting to improve handling on some systems that were seeing GUI hangs resetting logs
    * Added more options for remote syslog server areas
    * Fixed handling of ‘everything’ checkbox
    * Moved wireless to its own log file and tab
* Removed/silenced some irrelevant log entries
* Fixed various typos
* Fixes for RRD upgrade/migration and backup [[#1758](http://redmine.pfsense.org/issues/1758)]
* Prevent users from applying NAT to CARP which would break CARP in various ways [[#1954](https://redmine.pfsense.org/issues/1954)]
* Fixed policy route negation for VPN networks [[#1950](https://redmine.pfsense.org/issues/1950)]
* Fixed "Bypass firewall rules for traffic on the same interface" [[#1950](https://redmine.pfsense.org/issues/1950)]
* Fixed VoIP rules produced by the traffic shaper wizard [[#1948](https://redmine.pfsense.org/issues/1948)]
* Fixed uname display in System Info widget [[#1960](https://redmine.pfsense.org/issues/1960)]
* Fixed LDAP custom port handling
* Fixed Status &gt; Gateways to show RTT and loss like the widget
* Improved certificate handling in OpenVPN to restrict certificate chaining to a specified depth – CVE-2011-4197
* Improved certificate generation to specify/enforce type of certificate (CA, Server, Client) – CVE-2011-4197
* Clarified text of serial field when importing a CA [[#2031](https://redmine.pfsense.org/issues/2031)]
* Fixed MTU setting on upgrade from 1.2.3, now upgrades properly as MSS adjustment [[#1886](https://redmine.pfsense.org/issues/1886)]
* Fixed Captive Portal MAC passthrough rules [[#1976](https://redmine.pfsense.org/issues/1976)]
* Added tab under Diagnostics &gt; States to view/clear the source tracking table if sticky is enabled
* Fixed CARP status widget to properly show "disabled" status.
* Fixed end time of custom timespan RRD graphs [[#1990](https://redmine.pfsense.org/issues/1990)]
* Fixed situation where certain NICs would constantly cycle link with MAC spoofing and DHCP [[#1572](https://redmine.pfsense.org/issues/1572)]
* Fixed OpenVPN ordering of client/server IPs in Client-Specific Override entries [[#2004](https://redmine.pfsense.org/issues/2004)]
* Fixed handling of OpenVPN client bandwidth limit option
* Fixed handling of LDAP certificates [[#2018](https://redmine.pfsense.org/issues/2018), [#1052](https://redmine.pfsense.org/issues/1052), [#1927](https://redmine.pfsense.org/issues/1927)]
* Enforce validity of RRD graph style
* Fixed crash/panic handling so it will do textdumps and reboot for all, and not drop to a db> prompt.
* Fixed handling of hostnames in DHCP that start with a number [[#2020](https://redmine.pfsense.org/issues/2020)]
* Fixed saving of multiple dynamic gateways [[#1993](https://redmine.pfsense.org/issues/1993)]
* Fixed handling of routing with unmonitored gateways
* Fixed Firewall &gt; Shaper, By Queues view
* Fixed handling of spd.conf with no phase 2′s defined
* Fixed synchronization of various sections that were leaving the last item on the slave (IPsec phase 1, Aliases, VIPs, etc)
* Fixed use of quick on internal DHCP rules so DHCP traffic is allowed properly [[#2041](https://redmine.pfsense.org/issues/2041)]
* Updated ISC DHCP server to 4.2.3 [[#1888](https://redmine.pfsense.org/issues/1888)] – this fixes a denial of service vulnerability in dhcpd.
* Added patch to mpd to allow multiple PPPoE connections with the same remote gateway
* Lowered size of CF images to again fix on newer and ever-shrinking CF cards.
* Clarified text for media selection [[#1910](https://redmine.pfsense.org/issues/1910)]

###2.0.0 (September 17, 2011)

Operating System

* Based on FreeBSD 8.1 release.
* i386 and amd64 variants for all install types (full install, nanobsd/embedded, etc.)
* USB memstick installer images available

Interfaces

* GRE tunnels
* GIF tunnels
* 3G support
* Dial up modem support
* Multi-Link PPP (MLPPP) for bonding PPP connections (ISP/upstream must also support MLPPP)
* LAGG Interfaces
* Interface groups
* IP Alias type Virtual IPs
* IP Alias VIPs can be stacked on CARP VIPs to go beyond the 255 VHID limit in deployments that need very large numbers of CARP VIPs.
* QinQ VLANs
* Can use Block Private Networks / Block Bogon Networks on any interface
* All interfaces are optional except WAN
* All interfaces can be renamed, even LAN/WAN
* Bridging enhancements - can now control all options of if_bridge, and assign bridge interfaces

Gateways/Multi-WAN

* Gateways, including dynamic gateways, are specified under System > Routing
* Gateways can have custom monitor IPs
* Gateways can have a custom weight, allowing load balancing to have ratios between WANs of different speeds
* Gateways can have custom latency, loss, and downtime trigger levels.
* Gateway monitoring via icmp is now configurable.
* You can have multiple gateways per interface
* Multi-WAN is now handled via gateway groups
* Gateway groups can include multiple tiers with any number of gateways on each, for complex failover and load balancing scenarios.

General Web GUI

* Set to HTTPS by default, HTTP redirects to HTTPS port
* Dashboard and widgets added
* System > Advanced screen split into multiple tabs, more options available.
* SMTP email alerts and growl alerts
* New default theme - pfsense_ng
* Some community-contributed themes added
* Contextual help available on every page in the web interface, linking to a webpage containing help and documentation specific to that page.
* Help menu for quick access to online resources (forum, wiki, paid support, etc.)

Aliases

* Aliases may be nested (aliases in aliases)
* Alias autocomplete is no longer case sensitive
* IP Ranges in Aliases
* More Alias entries supported
* Bulk Alias importing
* URL Aliases
* URL Table Aliases - uses a pf persist table for large (40,000+) entry lists

Firewall

* Traffic shaper rewritten - now handles any combination of multi-WAN and multi-LAN interfaces. New wizards added.
* Layer7 protocol filtering
* EasyRule - add firewall rules from log view (and from console!)
* Floating rules allow adding non-interface specific rules
* Dynamically sized state table based on amount of RAM in the system
* More Advanced firewall rule options
* FTP helper now in kernel
* TFTP proxy
* Schedule rules are handled in pf, so they can use all the rule options.
* State summary view, report shows states grouped by originating IP, destination IP, etc.

NAT

* All of the NAT screens were updated with additional functionality
* Port forwards can now handle create/update associated firewall rules automatically, instead of just creating unrelated entries.
* Port forwards can optionally use "rdr pass" so no firewall rule is needed.
* Port forwards can be disabled
* Port forwards can be negated ("no rdr")
* Port forwards can have source and destination filters
* NAT reflection improvements, including NAT reflection for 1:1 NAT
* Per-entry NAT reflection overrides
* 1:1 NAT rules can specify a source and destination address
* 1:1 NAT page redesigned
* Outbound NAT can now translate to an address pool (Subnet of IPs or an alias of IPs) of multiple external addresses
* Outbound NAT rules can be specified by protocol
* Outbound NAT rules can use aliases
* Improved generation of outbound NAT rules when switching from automatic to manual.

IPsec

* Multiple IPsec p2's per p1 (multiple subnets)
* IPsec xauth support
* IPsec transport mode added
* IPsec NAT-T
* Option to push settings such as IP, DNS, etc, to mobile IPsec clients (mod_cfg)
* Mobile IPsec works with iOS and Android (Certain versions, see Mobile IPsec on 2.0)
* More Phase 1/2 options can be configured, including the cipher type/strength
* ipsec-tools version 0.8

User Manager

* New user manager, centralizing the various user configuration screens previously available.
* Per-page user access permissions for administrative users
* Three built-in authentication types - local users, LDAP and RADIUS.
* Authentication diagnostics page

Certificate Manager

* Certificate manager added, for handling of IPsec, web interface, user, and OpenVPN certificates.
* Handles creation/import of Certificate Authorities, Certificates, Certificate Revocation lists.
* Eliminates the need for using command line tools such as EasyRSA for managing certificates.

OpenVPN

* OpenVPN wizard guides through making a CA/Cert and OpenVPN server, sets up firewall rules, and so on. Greatly simplifies the process of creating a remote access OpenVPN server.
* OpenVPN filtering - an OpenVPN rules tab is available, so OpenVPN interfaces don't have to be assigned to perform filtering.
* OpenVPN client export package - provides a bundled Windows installer with certificates, Viscosity export, and export of a zip file containing the user's certificate and configuration files.
* OpenVPN status page with connected client list -- can also kill client connections
* User authentication and certificate management
* RADIUS and LDAP authentication support

Captive Portal

* Voucher support added
* Multi-interface capable
* Pass-through MAC bandwidth restrictions
* Custom logout page contents can be uploaded
* Allowed IP addresses bandwidth restrictions
* Allowed IP addresses supports IP subnets
* "Both" direction added to Allowed IP addresses
* Pass-through MAC Auto Entry - upon successful authentication, a pass-through MAC entry can be automatically added.
* Ability to configure calling station RADIUS attributes

Wireless

* Virtual AP (VAP) support added
* more wireless cards supported with the FreeBSD 8.1 base
Server Load Balancing
* relayd and its more advanced capabilities replace slbd.

Other

* L2TP VPN added
* DNS lookup page added
* PFTop and Top in GUI - realtime updates
* Config History now includes a diff feature
* Config History has download buttons for prior versions
* Config History has mouseover descriptions
* CLI filter log parser (/usr/local/bin/filterparser)
* Switched to PHP 5.2.x
* IGMP proxy added
* Multiple Dynamic DNS account support, including full multi-WAN support and multi-accounts on each interface. DynDNS Account Types supported are:
    * DNS-O-Matic
    * DynDNS (dynamic)
    * DynDNS (static)
    * DynDNS (custom)
    * DHS
    * DyNS
    * easyDNS
    * No-IP
    * ODS.org
    * ZoneEdit
    * Loopia
    * freeDNS
    * DNSexit
    * OpenDNS
    * Namecheap.com
* More interface types (VPNs, etc) available for packet capture
* DNS Forwarder is used by the firewall itself for DNS resolution (configurable) so the firewall benefits from faster resolution via multiple concurrent queries, sees all DNS overrides/DHCP registrations, etc.
* DHCP Server can now handle arbitrary numbered options, rather than only options present in the GUI.
* Automatic update now also works for NanoBSD as well as full installs
* More configuration sections can be synchronized via XMLRPC between CARP nodes.

### 1.2.3 (December 10, 2009)

* Waited on the FreeBSD security advisory for the SSL/TLS renegotiation vulnerability

### 1.2.3-RC3 (October 8, 2009)

* NAT-T support has been removed. Adding it brought out bugs in the underlying ipsec-tools, causing problems in some circumstances with renegotiation and completely breaking DPD. These issues are fixed in the CVS version of ipsec-tools, but it’s still considered alpha, and we found different problems when attempting to use it instead. NAT-T will be back in the 2.0 release, where it’s not as much of a pain since NAT-T is now in stock FreeBSD 8.
* Outbound load balancer replaced – The underlying software that does the monitoring and ruleset reloads for outbound multi-WAN load balancing has been replaced. This does not change anything from the user’s perspective, as only back end code changed. This fixed WAN flapping that was experienced by a small number of users.
* Captive portal locking replaced – the locking used by the captive portal has never been great (same as used in m0n0wall, where a replacement is also under consideration), and in some circumstances in high load environments (hundreds or thousands of users) it could wreak havoc on the portal. This has been replaced with a better locking mechanism that has resolved these issues.
* Embedded switched to nanobsd.
* DNS Forwarder now queries all configured DNS servers simultaneously, using the one that responds the fastest. In some circumstances this will improve DNS performance considerably.
* Atheros driver reverted to the one in FreeBSD 7.1 + patches from Sam Leffler, as existed in 1.2.3-RC1. The FreeBSD 7.2 driver exhibited numerous regressions that are no longer an issue, but reverting removed support for cards newly supported in FreeBSD 7.2.

### 1.2.3-RC1 (April 22, 2009)

* IPsec connection reloading improvements – When making changes to a single IPsec connection, or adding an IPsec connection, it no longer reloads all your IPsec connections. Only the changed connections are reloaded. That wasn’t a big deal in most environments, but in some it meant you couldn’t change anything in IPsec except during maintenance windows. This is being used in a critical production environment with 400 connections, and works well.
* Dynamic site to site IPsec – because of the above change, it was trivial to add support for dynamic DNS hostnames in IPsec. While 1.2.x will not receive new features, this became an exception.
* IPsec NAT-T support has also been added.
* Sticky connections enable/disable – sticky connections were previously only changed status at boot time for the server load balancer. 
* Upgrade to FreeBSD 7.1 – The FreeBSD base version has changed from 7.0 to 7.1. This brings support for new hardware, and seems to fix a number of hardware regressions between 6.2 and 7.0. A number of users have reported that hardware that worked fine on 6.2 stopped working on 7.0. In every case we’re aware of, 7.1 fixed that problem.
* Wireless code update – Sam Leffler, one of the primary developers of wireless on FreeBSD, was kind enough to point us to the latest wireless code back ported from FreeBSD 8.0 to 7.1. This is included in 1.2.3-RC1. There are companies shipping access points on this code base. Several users have reported considerable improvements in compatibility, stability and performance.
* Dynamic interface bridging bug fix – the bridging bug fix in 1.2.2 introduced a problem with bridging any dynamic/non-Ethernet interface, such as VLANs, tun, tap, etc. which has been fixed.
* Ability to delete DHCP leases – A delete button has been added to the DHCP leases page, and when adding a static mapping, the old lease is automatically deleted.
* Polling fixed – polling was not being applied properly previously, and the supported interfaces list has been updated.
* ipfw state table size – for those who use Captive Portal in large scale environments, ipfw’s state table size is now synced with pf’s state table size.
* Server load balancing ICMP monitor fixed.
* UDP state timeout increases – By default, pf does not increase UDP timeouts when set to "conservative", only TCP. Some VoIP services will experience disconnects with the default UDP state timeouts, setting state type to "conservative" under System -> Advanced will now increase UDP timeouts as well to fix this.
* Disable auto-added VPN rules option - added to System -> Advanced to prevent the addition of auto-added VPN rules for PPTP, IPsec, and OpenVPN tun/tap interfaces. Allows filtering of OpenVPN client-initiated traffic when tun/tap interfaces are assigned as an OPT.
* Multiple servers per-domain in DNS forwarder overrides - previously the GUI limited you to one server per domain override in the DNS forwarder, you can now put in multiple entries for the same domain for redundancy. 

### 1.2.2 (January 9, 2009)

* Setup wizard fix – removing BigPond from the WAN page on the setup wizard caused problems.
* SVG graphs fixed in Google Chrome. The graph page used to not require authentication, which is how it works in m0n0wall, I believe because at the time the feature was implemented in m0n0wall that is the only way it would work. We added required authentication on this page, and while it worked in Firefox, the way it was implemented broke Chrome. Chrome is now fixed. IE believed to still be broken, and the only resolution appears to be not requiring authentication for the graph. We would rather break the SVG graphs in IE and tighten that down than leave it open.
* IPsec reload fix specific to large (100+ site) deployments
* Bridge creation code changes – there have always been issues when attempting to bridge more than two interfaces. This fixes several bugs when attempting to use more than one bridge.
* FreeBSD updates for two security advisories on January 7, 2009. The OpenSSL one could possibly affect OpenVPN users.

### 1.2.1 (December 26, 2008)

* Fixed problem preventing RIP from starting
* Fixed broken link in VLAN reboot notification
* Fixed problem with SSL certificate generation

### 1.2.1-RC3 (December 14th, 2008)

* Do not accept \ in alias fieldnames
* Fix setup wizard WAN configuration page since removal of BIGPond
* Replaced route get default with netstat -rn equivalent
* No longer syncs CARP configuration when not needed
* Do not use broadcast on CARP addresses
* Fixes for CARP and VLANs related to interface and IP changes
* Added OpenNTPD to Status -&gt; Services
* Removed enable filter bridge checkbox, it’s on by default
* Fixed "no state" rules
* Corrected description for bogon rule
* Now shows rejected rule icon correctly on firewall edit page
* Minor fixes for embedded upgrades (needs further testing)
* Creates a backup of config.xml prior to package installations
* Correct interface polling
* Minor PHP Shell changes/fixes
* Bumped /cf/ to 4.5M
* No longer destroys enc0
* NAT Reflection timeouts are now consistent for TCP/UDP
* Ensure default gateway is present after filter reload
* Detect iPhone / iPod and switch theme temporarily to pfSense
* Other minor changes, please see cvstrac.pfsense.org reports section for RELENG_1_2

### 1.2.1-RC2 (November 21, 2008)

* Numerous changes to accommodate differences in FreeBSD 7.0. Lesson learned here – we hoped 1.2.1 would be a fast release cycle, but it ended up being a significant amount of work because of the changes in FreeBSD from 6.2 to 7.0. It’s certainly for the better, as 7.0 brings improved performance, more and better hardware support, enhanced wireless capabilities, and more.
* Multi-WAN bug fix – reply-to was not added to WAN rules, which caused difficulties under some specific circumstances with accessing services running on the firewall using OPT WAN interfaces.
* Bridging bug fix – problem with the way firewall rules were being applied to bridging could lead to strange behavior in some bridging scenarios. Also, DHCP clients used to be automatically allowed through bridges. This is no longer the case, if you use a DHCP client behind a bridge, your firewall rules must allow the DHCP traffic.
* Captive Portal bug fix – imported from m0n0wall, related to MAC authentication with RADIUS.
* Keep state change – the newer pf version changed to defaulting to keep state, rules that required no state keeping (same interface firewall rule bypass) needed "no state" added.
* NAT reflection bug fix – 20 second timeout was being incorrectly applied, affecting long-lived connections.
* Mobile IPsec fixes
* Some minor text clean up, typo fixes
* Packages screen now has a "Package Info" column rather than the "maintainer" column which was of limited use. Links to information on the package are shown there, for packages that have links defined. Many have links already, and work is currently under way to add a link for every package and expand the information available on them. The Installed Packages tab also shows the Package Info links. When you access the package screens, it fetches the most recent package information from our servers, incluing the Package Info links. You will see more links come with time, without having to upgrade pfSense.
* Significant speed up in boot process, especially when using CARP. There were some delays in the boot process that could be removed thanks to changes in FreeBSD 7.0, which has made booting quite a bit faster. 

### 1.2.0 (February 25, 2008)

* Improve CARP input validation – GUI previously allowed incorrect configurations that caused panics. Fixed to not allow entry of such configurations, so typos and configuration errors cannot crash system.
* Clarify text and fix typos on several screens.
* Revert DHCP client to default timeout of 60 seconds.
* Reload static routes when an interface IP address is changed by an administrator.
* Fix a few areas allowing potential cross site scripting.
* Fix a couple issues with package uninstalls.
* Shorten firewall rule, NAT and traffic shaper description fields to prevent users from entering description names too long for the pf ruleset.
* Fix traffic shaper queue name generation to prevent creating invalid ruleset for interface names longer than 15 characters.
* Improve efficiency of RRD graph creation by removing duplicate commands. Graph updates now use less CPU time.

### 1.2.0-RC4 (January 16, 2008)

* libc fix for security advisory FreeBSD-SA-08:02
* Numerous text touch ups and typo corrections
* Do not ping other end of IPsec connections on CARP backup hosts
* Fix edit.php error when opening empty file
* Math fix on throughput graph
* Increase maximum alias count (99 to 299)
* Captive portal locking improvements to fix issues in high load environments
* IPsec stability fixes for large deployments (&gt; 200 connections)
* VLAN support for ALIX hardware
* Boot time beep change for hardware like some embedded devices and VMware, where beeps were excessively long and annoying
* Warn after VLAN creation that a reboot may be required (VLANs on some NICs don’t come up properly until after a reboot, and we know of no way to reliably detect when a reboot is required)
* Forced page refreshes removed from all pages. This was problematic for very large log files, and annoying when reviewing logs.
* Fix for display of wireless networks with a space in the SSID
* Updated PHP version
* Fix improper shifting of configuration items (DHCP, rules, NAT, etc.) when an OPT interface is removed
* Updated pf.os for passive OS detection
* Properly remove DynDNS cache after making changes to DynDNS configuration
* PPPoE Server moved from VPN to Services menu to more appropriately reflect its purpose, as labeling it "VPN" is misleading

### 1.2.0-RC3 (November 8th, 2007)

* IPSEC Carp rules cleanup
* IPSEC stability worksarounds for &gt; 150 tunnels
* Only reload webConfiguration from System -&gt; Advanced when cert changes
* Increase net.inet.ip.intr_queue_maxlen to 1000 which is the IP input queue.
* Do not allow sticky connection bit to be set if pppoe is enabled. [#1319]
* Disable firmware upgrade for embedded and cdrom and suggest using the console option to upgrade. [#1433]
* Recompile MPD with MSS/dial-on-demand patches (also fixes idle timeout bug)
* Fix CP not sending Acct-Session-Time to Radius during accounting update [#1434]
* Work around heavy network activity issues. [20070116, update 20070212] Systems with very heavy network activity have been observed to have some problems with the kernel memory allocator. Symptoms are processes that get stuck in zonelimit state, or system livelocks. One partial workaround for this problem is to add the following line to /boot/loader.conf and reboot: kern.ipc.nmbclusters="0″
* Bump lighttpd to 1.4.18
* Show wireless nodes regardless if we can deterimine BSS value.
* IPSEC tunnel endpoint highlighting in system logs
* Show the IPSEC interface as a option for the traffic graph.
* Add RRD Settings page.
* Make it possible to disable RRD graphs. Bump config so it’s on by default if it wasn’t already.
* Correctly set reflection timeout for all protocols.
* Restart snmp services after LAN IP changes Ticket #1453
* Bump miniupnpd version to RC9 -add multiple interface support
* Speedup ARP page by using diag_dhcp_leases.php page code for parsing the dhcpd.leases file
* Relax the ip address check and allow duplicate ip address entries which allows fr example a wireless card and a ethernet card on a laptop to share the same ip address
* Do not allow DHCP server to be enabled when DHCP relay is enabled, and vice versa [#1488]
* IPSEC keep alive pinger using the wrong source IP address Ticket #1482
* Failover DHCP Server in 10 seconds as opposed to 60 seconds

### 1.2.0-RC2 (August 17, 2007)

* Automatically restarts racoon (ipsec-tools if it wedges)
* Ensure CARP status page cache is cleared before load
* Updated lighttpd to 1.4.15
* APC updated to 3.0.14
* Update to DNSMASQ 2.3.9
* Ensure that rules are cleared from UPNP when service is stopped
* Correectly show IPSEC firewall rules tab when Mobile IPSEC is enabled
* Quality graph misc alignments
* Backport show username on captive portal status screen
* Do not allow aliases named "pptp"
* TCP timeout time fixes

### 1.2.0-RC1 (July 21, 2007)

* Many IPSEC improvements when you have more than 40+ IPSEC tunnels
* RRD Queues Graphing fixes
* DNS Forwarder (DNS Masq) has been updated to version 2.39
* Miniupnpd should now shutdown correctly when disabled
* Minor RRD graph fixes for periods longer than 8 months
* DHCPD now started before DNS Forwarder on embedded platform
* pftpx processes now killed correctly after the queue changes (ALTQ)
* ftpsesame recompiled against libevent-1.3

### 1.2.0-BETA3 ()

* Restart filter logging subsystem after time zone changes
* Remove extra SSH password authentication line
* IPSEC filter rule tab now hidden when IPSEC is disabled
* Dyanmic log viewer removed due to too many issues (will reappear in 1.3)
* Increase ephemeral port range for busy firewalls
* More IPSEC /CARP cleanups
* Misc logging viewer fixes

### 1.2.0-BETA2 (July 4, 2007)

* Advanced outbound NAT fixes
* UPNP now works on LiveCD
* Misc log viewing fixes
* Password field lengths now line up on nervecenter theme
* IPSEC now works correctly on CARP interfaces out of the box
* Routed hosts behind a policy-routed segment can now reach the LAN interface correctly when the anti-lockout rule is enabled
* pfSync and CARP now will work correctly on extremely restrictive rulesets
* Captive portal images fixed
* SLBD 100% utilization fixes
* 64 megabyte memory improvements (works but not supported)
* Misc packet capture fixes
* Dashboard package added
* Update static routes on filter reload
* Miniupnpd version bump to 20070521
* Turn off antispoof on bridges
* NAT reflection timeout extended to 2000 which is roughly 33 minutes
* use_rrd_gateway location fixes
* Fixed advanced firewall rule tunables

### 1.2.0-BETA1 (April 29, 2007)

* FreeBSD updated to 6.2
* Reworked load balancing pools which allow for round robin or failover
* miniupnpd has proven to work so well that it is now in the base install but deactivated by default (uninstall the miniupnpd package before upgrading to avoid duplicate menu items)
* Much enhanced RRD graphs
* Numerous Squid Package fixes
* PPTP Server includes WINS server settings correctly now
* General OpenVPN stability improvements
* "Nervecenter" theme added as default
* Status -&gt; DHCP leases now 1500% faster
* Captive portal now allows traffic to port 8000 and 8001 behind the scenes
* Multiple miscellaneous pf rule fixes to prevent broken rulesets
* DNS server with active failover will show up when 1.2 releases
* dnsmasq updated to 2.36
* olsrd updated to 0.4.10
* Alias line item descriptions backported from -HEAD
* Enhanced cron handling backported from -HEAD
* dhclient changes backported from FreeBSD 7
* miniupnpd updated
* Speed NAT apply page up 100%
* PPPoE auto disconnect (for our German users)
* Soekris/WRAP error light usage now when a problem or alert occurs
* TCPDump interface
* VLAN assign interface improvements
* SLBD/load balancing ping times increased to a timeout of 2 seconds
* Package infrastructure to safely sync package data between CARP nodes added
* Miscellaneous DHCP Server OPT interface fixes
* 1:1 NAT outgoing FTP fixes
* OpenVPN stability fixes
* Traffic shaper wizard now displays errors correctly
* BandwidthD package added
* Pinger framework improved
* Dynamic filter log viewer added
* IPSec filtering is now possible. You need to create rules before traffic will pass!!
* Individual kill state feature back ported from HEAD on Diagnostics, Show States screen
* Fix for DHCP Load balancing edge case where monitor IP’s would be mapped through the wrong gateway.
* Option added to turn off TX and RX hardware checksums. We are finding more and more hardware that this feature just simply doesn’t work very well.
* OpenVPN PPPoE fixes
* Reload VLAN interfaces correctly after adding a new one
* Multiple client OpenVPN fixes
* PHP upgraded to 4.4.6
* Synchronized captive portal with m0n0wall
* CARP IP addresses can be used on IPSec VPN connections and multi-WAN IPSec now works correctly
* config.xml stability improvements to drastically reduce chances of corruption
* Packages auto-fix themselves if a problem arises in the installation
* Lighttpd upgraded to 1.4.15
* PPPoE server subnet fixes
* OpenVPN outgoing bandwidth limits added
* Firewall schedules feature added
* Server load balancing pool page added
* Multi-WAN NAT configuration now correct in non-Advanced Outbound NAT mode
* Load balancing ping now uses fping

### 1.0.1 (October 29, 2006)

* Set maximum cache size for apc to 7 megabytes
* Restart check_reload_status if it exits
* Misc syslog.conf fixes
* Snort now blocks traffic correctly
* PF does not know about congestion flags, remove from shaper
* Misc OpenNTPD system logging tab fixes
* Removes states from a user when disconnected by Captive Portal
* Fix FTP helper when strict LAN or Optional LAN rules are in place
* ZoneEdit now works
* Filter reloads rules correctly after changes
* Faster, snappier webConfigurator and console

### 1.0.0 (October 13, 2006)
