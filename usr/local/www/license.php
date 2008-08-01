<?php
/* $Id$ */
/*
    license.php

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

##|+PRIV
##|*IDENT=page-system-license
##|*NAME=System: License page
##|*DESCR=Allow access to the 'System: License' page.
##|*MATCH=license.php*
##|-PRIV

require("guiconfig.inc");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">License</p>

            <p><strong>{$g['product_name']} is Copyright &copy; {$g['product_copyright_years']} by {$g['product_copyright']}
              (<a href="mailto:geekgod@geekgod.com">geekgod@geekgod.com</a>).<br>
              All rights reserved.</strong></p>

            <p><strong>m0n0wall is Copyright &copy; 2002-2007 by Manuel Kasper
              (<a href="mailto:mk@neon1.net">mk@neon1.net</a>).<br>
              All rights reserved.</strong></p>
            <p> Redistribution and use in source and binary forms, with or without<br>
              modification, are permitted provided that the following conditions
              are met:<br>
              <br>
              1. Redistributions of source code must retain the above copyright
              notice,<br>
              this list of conditions and the following disclaimer.<br>
              <br>
              2. Redistributions in binary form must reproduce the above copyright<br>
              notice, this list of conditions and the following disclaimer in
              the<br>
              documentation and/or other materials provided with the distribution.<br>
              <br>
              <strong>THIS SOFTWARE IS PROVIDED &quot;AS IS'' AND ANY EXPRESS
              OR IMPLIED WARRANTIES,<br>
              INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY<br>
              AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT
              SHALL THE<br>
              AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
              EXEMPLARY,<br>
              OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT
              OF<br>
              SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
              BUSINESS<br>
              INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER
              IN<br>
              CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)<br>
              ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
              OF THE<br>
              POSSIBILITY OF SUCH DAMAGE</strong>.</p>
            <hr size="1">
            <p>The following persons have contributed code to m0n0wall:</p>
            <p>Bob Zoller (<a href="mailto:bob@kludgebox.com">bob@kludgebox.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">Diagnostics: Ping
              function; WLAN channel auto-select; DNS forwarder</font></em><br>
              <br>
              Michael Mee (<a href="mailto:mikemee2002@pobox.com">mikemee2002@pobox.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">Timezone and NTP
              client support</font></em><br>
              <br>
              Magne Andreassen (<a href="mailto:magne.andreassen@bluezone.no">magne.andreassen@bluezone.no</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">Remote syslog'ing;
              some code bits for DHCP server on optional interfaces</font></em><br>
              <br>
              Rob Whyte (<a href="mailto:rob@g-labs.com">rob@g-labs.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">Idea/code bits
              for encrypted webConfigurator passwords; minimalized SNMP agent</font></em><br>
              <br>
              Petr Verner (<a href="mailto:verner@ipps.cz">verner@ipps.cz</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">Advanced outbound
              NAT: destination selection</font></em><br>
              <br>
              Bruce A. Mah (<a href="mailto:bmah@acm.org">bmah@acm.org</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">Filtering bridge
              patches </font></em><br>
              <br>
              Jim McBeath (<a href="mailto:monowall@j.jimmc.org">monowall@j.jimmc.org</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">Filter rule patches
              (ordering, block/pass, disabled); better status page;<br>
              &nbsp;&nbsp;&nbsp;&nbsp;webConfigurator assign network ports page </font></em><br>
              <br>
              Chris Olive (<a href="mailto:chris@technologEase.com">chris@technologEase.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">enhanced &quot;execute
              command&quot; page</font></em><br>
              <br>
              Pauline Middelink (<a href="mailto:middelink@polyware.nl">middelink@polyware.nl</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">DHCP client: send hostname patch</font></em><br>
              <br>
              Björn Pålsson (<a href="mailto:bjorn@networksab.com">bjorn@networksab.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">DHCP lease list page</font></em><br>
              <br>
              Peter Allgeyer (<a href="mailto:allgeyer@web.de">allgeyer@web.de</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">&quot;reject&quot; type filter rules; dial-on-demand</font></em><br>
              <br>
              Thierry Lechat (<a href="mailto:dev@lechat.org">dev@lechat.org</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">SVG-based traffic grapher</font></em><br>
              <br>
              Steven Honson (<a href="mailto:steven@honson.org">steven@honson.org</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">per-user IP address assignments for PPTP VPN</font></em><br>
              <br>
              Kurt Inge Smådal (<a href="mailto:kurt@emsp.no">kurt@emsp.no</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">NAT on optional interfaces</font></em><br>
              <br>
              Dinesh Nair (<a href="mailto:dinesh@alphaque.com">dinesh@alphaque.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">captive portal: pass-through MAC/IP addresses, RADIUS authentication &amp; accounting;<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666"></font></em>HTTP server concurrency limit</font></em><br>
              <br>
              Justin Ellison (<a href="mailto:justin@techadvise.com">justin@techadvise.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">traffic shaper TOS matching; magic shaper; DHCP deny unknown clients;<br>
			  &nbsp;&nbsp;&nbsp;&nbsp;IPsec user FQDNs; DHCP relay</font></em><br>
			  <br>
              Michael Hanselmann (<a href="mailto:m0n0@hansmi.ch">m0n0@hansmi.ch</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">IDE hard disk standby</font></em><br>
			  <br>
              Audun Larsen (<a href="mailto:larsen@xqus.com">larsen@xqus.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">CPU/memory usage display</font></em><br>
			  <br>
              Peter Curran (<a href="mailto:peter@closeconsultants.com">peter@closeconsultants.com</a>)<br>
              &nbsp;&nbsp;&nbsp;&nbsp;<em><font color="#666666">OpenVPN support</font></em></p>
            <hr size="1">
            <p>{$g['product_name']} is based upon/includes various free software packages,
              listed below.<br>
              The author of {$g['product_name']} would like to thank the authors of these
              software packages for their efforts.</p>
            <p>FreeBSD (<a href="http://www.freebsd.org" target="_blank">http://www.freebsd.org</a>)<br>
              Copyright &copy; 1994-2003 FreeBSD, Inc. All rights reserved.<br>
              <br>
              This product includes PHP, freely available from <a href="http://www.php.net/" target="_blank">http://www.php.net</a>.<br>
              Copyright &copy; 1999 - 2003 The PHP Group. All rights reserved.<br>
              <br>
              LightTPD (<a href="http://www.lighttpd.net" target="_blank">http://www.lighttpd.net)</a><br>
              Copyright &copy; 2004 by Jan Kneschke <jan@kneschke.de>
              All rights reserved.<br>
              <br>
              ISC DHCP server (<a href="http://www.isc.org/products/DHCP/" target="_blank">http://www.isc.org/products/DHCP</a>)<br>
              Copyright &copy; 1996-2003 Internet Software Consortium. All rights
              reserved.<br>
              <br>
              PF (<a href="http://www.openbsd.org/faq/pf" target="_blank">http://www.openbsd.org</a>)<br>
			  <br>
              MPD - Multi-link PPP daemon for FreeBSD (<a href="http://www.dellroad.org/mpd" target="_blank">http://www.dellroad.org/mpd</a>)<br>
              Copyright &copy; 2003-2004, Archie L. Cobbs, Michael Bretterklieber, Alexander Motin<br>
			  All rights reserved.<br>
              <br>
              Circular log support for FreeBSD syslogd (<a href="http://software.wwwi.com/syslogd/" target="_blank">http://software.wwwi.com/syslogd</a>)<br>
              Copyright &copy; 2001 Jeff Wheelhouse (jdw@wwwi.com)<br>
              <br>
              Dnsmasq - a DNS forwarder for NAT firewalls (<a href="http://www.thekelleys.org.uk" target="_blank">http://www.thekelleys.org.uk</a>)<br>
              Copyright &copy; 2000-2003 Simon Kelley.<br>
              <br>
              Racoon (<a href="http://www.kame.net/racoon" target="_blank">http://www.kame.net/racoon</a>)<br>
              Copyright &copy; 1995-2002 WIDE Project. All rights reserved.<br>
              <br>
              msntp (<a href="http://www.hpcf.cam.ac.uk/export" target="_blank">http://www.hpcf.cam.ac.uk/export</a>)<br>
              Copyright &copy; 1996, 1997, 2000 N.M. Maclaren, University of Cambridge.
              All rights reserved.<br>
              <br>
              UCD-SNMP (<a href="http://www.ece.ucdavis.edu/ucd-snmp" target="_blank">http://www.ece.ucdavis.edu/ucd-snmp</a>)<br>
              Copyright &copy; 1989, 1991, 1992 by Carnegie Mellon University.<br>
              Copyright &copy; 1996, 1998-2000 The Regents of the University of
              California. All rights reserved.<br>
              Copyright &copy; 2001-2002, Network Associates Technology, Inc.
              All rights reserved.<br>
              Portions of this code are copyright &copy; 2001-2002, Cambridge
              Broadband Ltd. All rights reserved.<br>
              <br>
              choparp (<a href="http://choparp.sourceforge.net/" target="_blank">http://choparp.sourceforge.net</a>)<br>
              Copyright &copy; 1997 Takamichi Tateoka (tree@mma.club.uec.ac.jp)<br>
			 Copyright &copy; 2002 Thomas Quinot (thomas@cuivre.fr.eu.org)<br>
              <br>
              BPALogin (<a href="http://bpalogin.sourceforge.net/" target="_blank">http://bpalogin.sourceforge.net</a>) - lightweight portable BIDS2 login client<br>
              Copyright &copy; 2001-3 Shane Hyde, and others.<br>
              <br>
              php-radius (<a href="http://www.mavetju.org/programming/php.php" target="_blank">http://www.mavetju.org/programming/php.php</a>)<br>
              Copyright 2000, 2001, 2002 by Edwin Groothuis. All rights reserved.<br>
			  This product includes software developed by Edwin Groothuis.<br>
			  <br>
			  wol (<a href="http://ahh.sourceforge.net/wol" target="_blank">http://ahh.sourceforge.net/wol</a>)<br>
			  Copyright &copy; 2000,2001,2002,2003,2004 Thomas Krennwallner &lt;krennwallner@aon.at&gt;
			  <br>
			  OpenVPN (<a href="http://openvpn.net/" target="_blank">http://openvpn.net/</a>)
			  Copyright (C) 2002-2005 OpenVPN Solutions LLC <info@openvpn.net>
			  <?php include("fend.inc"); ?>
</body>
</html>
