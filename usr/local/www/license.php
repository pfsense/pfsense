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
/*
	pfSense_MODULE:	routing
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
<p class="pgtitle"><?=gettext("License");?></p>

            <p><strong><?=$g['product_name'];?> <?=gettext("is Copyright");?> &copy; <?=$g['product_copyright_years'];?> <?=gettext("by");?> <?=$g['product_copyright'];?><br />
              <?=gettext("All rights reserved");?>.</strong></p>

            <p><strong><?=gettext("m0n0wall is Copyright ");?>&copy; <?=gettext("2002-2014 by Manuel Kasper");?>
              (<a href="mailto:mk@neon1.net">mk@neon1.net</a>).<br />
              <?=gettext("All rights reserved");?>.</strong></p>
            <p> <?=gettext("Redistribution and use in source and binary forms, with or without");?><br />
              <?=gettext("modification, are permitted provided that the following conditions ".
              "are met");?>:<br />
              <br />
              <?=gettext("1. Redistributions of source code must retain the above copyright ".
              "notice,");?><br />
              <?=gettext("this list of conditions and the following disclaimer");?>.<br />
              <br />
              <?=gettext("2. Redistributions in binary form must reproduce the above copyright");?><br />
              <?=gettext("notice, this list of conditions and the following disclaimer in ".
              "the");?><br />
              <?=gettext("documentation and/or other materials provided with the distribution.");?><br />
              <br />
              <strong><?=gettext("THIS SOFTWARE IS PROVIDED ");?>&quot;<?=gettext("AS IS'' AND ANY EXPRESS ".
              "OR IMPLIED WARRANTIES,");?><br />
              <?=gettext("INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY");?><br />
              <?=gettext("AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT ".
              "SHALL THE");?><br />
              <?=gettext("AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, ".
              "EXEMPLARY,");?><br />
              <?=gettext("OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT ".
              "OF");?><br />
              <?=gettext("SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR ".
              "BUSINESS");?><br />
              <?=gettext("INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER ".
              "IN");?><br />
              <?=gettext("CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)");?><br />
              <?=gettext("ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED ".
              "OF THE");?><br />
              <?=gettext("POSSIBILITY OF SUCH DAMAGE");?></strong>.</p>
            <hr size="1">
            <p><?= "{$g['product_name']} " . gettext("is based upon/includes various free software packages, ".
              "listed below.");?><br />
              <?php printf(gettext("The authors of %s would like to thank the authors of these " .
              "software packages for their efforts"),$g['product_name']);?>.</p>
            <p>FreeBSD (<a href="http://www.freebsd.org" target="_blank">http://www.freebsd.org</a>)<br />
              <?=gettext("Copyright");?> &copy;<?=gettext("1992-2014 The FreeBSD Project. All rights reserved");?>.<br />
              <br />
              <?=gettext("This product includes PHP, freely available from");?> <a href="http://www.php.net/" target="_blank">http://www.php.net</a>.<br />
              <?=gettext("Copyright"); ?> &copy; <?=gettext("1999-2014 The PHP Group. All rights reserved.");?>.<br />
              <br />
              <?=gettext("LightTPD"); ?> (<a href="http://www.lighttpd.net" target="_blank">http://www.lighttpd.net)</a><br />
              <?=gettext("Copyright"); ?> &copy;<?=gettext("2004, Jan Knescke, incremental");?><jan@kneschke.de>
              <?=gettext("All rights reserved.");?><br />
              <br />
              <?=gettext("ISC DHCP server ");?>(<a href="http://www.isc.org/products/DHCP/" target="_blank">http://www.isc.org/products/DHCP</a>)<br />
              <?=gettext("Copyright"); ?> &copy; <?=gettext("2004-2012 Internet Software Consortium, Inc.");?><br />
              <?=gettext("Copyright"); ?> &copy; <?=gettext("1995-2003 Internet Software Consortium");?><br />
              <br />
              <?=gettext("PF"); ?> (<a href="http://www.openbsd.org/faq/pf" target="_blank">http://www.openbsd.org</a>)<br />
			  <br />
              <?=gettext("MPD - Multi-link PPP daemon for FreeBSD");?> (<a href="http://www.dellroad.org/mpd" target="_blank">http://www.dellroad.org/mpd</a>)<br />
              <?=gettext("Copyright"); ?> &copy; 2003-2004, Archie L. Cobbs, Michael Bretterklieber, Alexander Motin<br />
			  <?=gettext("All rights reserved.");?><br />
              <br />
              <?=gettext("Circular log support for FreeBSD syslogd ");?>(<a href="http://software.wheelhouse.org/syslogd/" target="_blank">http://software.wheelhouse.org/syslogd/</a>)<br />
              <?=gettext("Copyright"); ?> &copy; 2001 Jeff Wheelhouse (jdw@wwwi.com)<br />
              <br />
              <?=gettext("Dnsmasq - a DNS forwarder for NAT firewalls");?> (<a href="http://www.thekelleys.org.uk" target="_blank">http://www.thekelleys.org.uk</a>)<br />
              <?=gettext("Copyright"); ?> &copy; 2000-2012 Simon Kelley.<br />
              <br />
              <?=gettext("IPsec-Tools"); ?> (<a href="http://ipsec-tools.sourceforge.net/" target="_blank">http://ipsec-tools.sourceforge.net/</a>)<br />
              <?=gettext("Copyright"); ?> &copy; <?=gettext("1995-2002 WIDE Project. All rights reserved.");?><br />
              <br />
              <?=gettext("msntp"); ?> (<a href="http://www.hpcf.cam.ac.uk/export" target="_blank">http://www.hpcf.cam.ac.uk/export</a>)<br />
              <?=gettext("Copyright"); ?> &copy;<?=gettext(" 1996, 1997, 2000 N.M. Maclaren, University of Cambridge. ".
              "All rights reserved.");?><br />
              <br />
              <?=gettext("UCD-SNMP"); ?> (<a href="http://www.ece.ucdavis.edu/ucd-snmp" target="_blank">http://www.ece.ucdavis.edu/ucd-snmp</a>)<br />
              <?=gettext("Copyright"); ?> &copy; <?=gettext("1989, 1991, 1992 by Carnegie Mellon University.");?><br />
              <?=gettext("Copyright"); ?> &copy; <?=gettext("1996, 1998-2000 The Regents of the University of ".
              "California. All rights reserved");?>.<br />
              <?=gettext("Copyright"); ?> &copy; <?=gettext("2001-2002, Network Associates Technology, Inc. ".
              "All rights reserved.");?><br />
              <?=gettext("Portions of this code are copyright");?> &copy; <?=gettext("2001-2002, Cambridge ".
              "Broadband Ltd. All rights reserved.");?><br />
              <br />
              <?=gettext("choparp"); ?> (<a href="http://choparp.sourceforge.net/" target="_blank">http://choparp.sourceforge.net</a>)<br />
              <?=gettext("Copyright"); ?> &copy; 1997 Takamichi Tateoka (tree@mma.club.uec.ac.jp)<br />
			 <?=gettext("Copyright"); ?> &copy; 2002 Thomas Quinot (thomas@cuivre.fr.eu.org)<br />
              <br />
              <?=gettext("php-radius"); ?> (<a href="http://www.mavetju.org/programming/php.php" target="_blank">http://www.mavetju.org/programming/php.php</a>)<br />
              <?=gettext("Copyright 2000, 2001, 2002 by Edwin Groothuis. All rights reserved.");?><br />
			  <?=gettext("This product includes software developed by Edwin Groothuis.");?><br />
			  <br />
			  <?=gettext("wol"); ?> (<a href="http://ahh.sourceforge.net/wol" target="_blank">http://ahh.sourceforge.net/wol</a>)<br />
			  <?=gettext("Copyright"); ?> &copy; 2000,2001,2002,2003,2004 Thomas Krennwallner &lt;krennwallner@aon.at&gt;
			  <br />
			  <?=gettext("OpenVPN"); ?> (<a href="http://openvpn.net/" target="_blank">http://openvpn.net/</a>)
			  <?=gettext("Copyright (C) 2002-2005 OpenVPN Solutions LLC ");?>
			  <?php include("fend.inc"); ?>
</body>
</html>
