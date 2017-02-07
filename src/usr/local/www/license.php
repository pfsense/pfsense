<?php
/*
 * license.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

##|+PRIV
##|*IDENT=page-system-license
##|*NAME=System: License
##|*DESCR=Allow access to the 'System: License' page.
##|*MATCH=license.php*
##|-PRIV

require_once("guiconfig.inc");
$pgtitle = array(gettext($g['product_name']), gettext("License"));
include("head.inc");
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("License")?></h2></div>
	<div class="panel-body content">
		<p><strong><?php printf(gettext('%1$s is Copyright &copy; %2$s %3$s. All rights reserved.'), $g['product_name'], $g['product_copyright_years'], $g['product_copyright'])?></strong></p>
		<p><?=gettext("m0n0wall is Copyright &copy; 2002-2015 by Manuel Kasper (mk@neon1.net). All rights reserved.")?></p>
		<p><?=sprintf(gettext('Licensed under the Apache License, Version 2.0 (the "License");%1$syou may not use this file except in compliance with the License.%1$sYou may obtain a copy of the License at'), '<br />')?></p>
		<p><a href="http://www.apache.org/licenses/LICENSE-2.0">http://www.apache.org/licenses/LICENSE-2.0</a></p>
		<p><?=sprintf(gettext('Unless required by applicable law or agreed to in writing, software%1$sdistributed under the License is distributed on an \"AS IS\" BASIS,%1$sWITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.%1$sSee the License for the specific language governing permissions and%1$slimitations under the License.'), '<br />')?></p>
	</div>
</div>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Third Party Credits")?></h2></div>
	<div class="panel-body content">
		<p>
			<?php printf(gettext('%1$s is based upon/includes various free software packages, listed below. The authors of %1$s would like to thank the authors of these software packages for their efforts.'), $g['product_name'])?><br />
		</p>
		<ul class="list-group">
			<li class="list-group-item">
				<strong>FreeBSD</strong> (<a href="http://www.freebsd.org" target="_blank">http://www.freebsd.org</a>)<br />
				<?=gettext("Copyright")?> &copy;<?=gettext("1992-2016 The FreeBSD Project. All rights reserved.")?>
			</li>
			<li class="list-group-item">
				<?=sprintf(gettext('This product includes %1$s, freely available from (%2$s)'), '<strong>PHP</strong>', '<a href="http://www.php.net/" target="_blank">http://www.php.net</a>')?> . <br />
				<?=gettext("Copyright"); ?> &copy; <?=gettext("1999-2016 The PHP Group. All rights reserved.")?>
			</li>
			<li class="list-group-item">
				<strong>PF</strong> originally from OpenBSD (<a href="http://www.openbsd.org/faq/pf" target="_blank">http://www.openbsd.org</a>)
			</li>
			<li class="list-group-item">
				<strong>bind-tools</strong> (<a href="https://www.isc.org/downloads/bind/" target="_blank">https://www.isc.org/downloads/bind/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2004-2013 Internet Systems Consortium, Inc. ("ISC")
			</li>
			<li class="list-group-item">
				<strong>Bootstrap</strong> HTML, CSS and JS framework (<a href="https://getbootstrap.com/" target="_blank">https://getbootstrap.com/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2015 Twitter
			</li>
			<li class="list-group-item">
				<strong>ca_root_nss</strong> Root certificates from certificate authorities included in the (<a href="https://developer.mozilla.org/en-US/docs/Mozilla/Projects/NSS" target="_blank">Mozilla
NSS library</a>)<br />
			</li>
			<li class="list-group-item">
				<strong>choparp</strong> (<a href="http://choparp.sourceforge.net/" target="_blank">http://choparp.sourceforge.net</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 1997 Takamichi Tateoka (tree@mma.club.uec.ac.jp)<br />
				<?=gettext("Copyright"); ?> &copy; 2002 Thomas Quinot (thomas@cuivre.fr.eu.org)
			</li>
			<li class="list-group-item">
				<strong>Circular log support for FreeBSD syslogd</strong> (<a href="http://software.wheelhouse.org/syslogd/" target="_blank">http://software.wheelhouse.org/syslogd/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2001 Jeff Wheelhouse (jdw@wwwi.com)
			</li>
			<li class="list-group-item">
				<strong>cpdup</strong> (<a href="http://apollo.backplane.com/FreeSrc/" target="_blank">http://apollo.backplane.com/FreeSrc/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 1997-1999 Matthew Dillon and Dima Ruban.
			</li>
			<li class="list-group-item">
				<strong>curl</strong> (<a href="https://curl.haxx.se/" target="_blank">https://curl.haxx.se/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 1998-2016 Daniel Stenberg.
			</li>
			<li class="list-group-item">
				<strong>D3.js</strong> (<a href="https://d3js.org/" target="_blank">https://d3js.org/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2015-2016 Mike Bostock.
			</li>
			<li class="list-group-item">
				<strong>d3pie</strong> (<a href="http://d3pie.org/" target="_blank">http://d3pie.org/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2014-2016 Benjamin Keen.
			</li>
			
			<li class="list-group-item">
				<strong>Dnsmasq</strong> - a DNS forwarder for NAT firewalls (<a href="http://www.thekelleys.org.uk" target="_blank">http://www.thekelleys.org.uk</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2000-2016 Simon Kelley.
			</li>
			<li class="list-group-item">
				<strong>dpinger</strong> - Pinger engine for monitoring latency and loss (<a href="https://github.com/dennypage/dpinger" target="_blank">https://github.com/dennypage/dpinger</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2015-2016 Denny Page.
			</li>
			<li class="list-group-item">
				<strong>ipmitool</strong> - command-line interface to IPMI-enabled devices (<a href="https://sourceforge.net/projects/ipmitool/" target="_blank">https://sourceforge.net/projects/ipmitool/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2003-2016 Sun Microsystems, Inc.
			</li>
			<li class="list-group-item">
				<strong>ISC DHCP</strong> <?=gettext("server")?> (<a href="http://www.isc.org/products/DHCP/" target="_blank">http://www.isc.org/products/DHCP</a>)<br />
				<?=gettext("Copyright"); ?> &copy; <?=gettext("2004-2013 Internet Software Consortium, Inc.")?><br />
				<?=gettext("Copyright"); ?> &copy; <?=gettext("1995-2003 Internet Software Consortium")?>
			</li>
			<li class="list-group-item">
				<strong>jQuery</strong> (<a href="https://jquery.com/" target="_blank">https://jquery.com/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2005-2016 jQuery Foundation and other contributors. 
			</li>
			<li class="list-group-item">
				<strong>MPD</strong> - Multi-link PPP daemon for FreeBSD (<a href="http://mpd.sourceforge.net/" target="_blank">http://mpd.sourceforge.net/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2003-2004, Archie L. Cobbs, Michael Bretterklieber, Alexander Motin<br />
				<?=gettext("All rights reserved.")?>
			</li>
			<li class="list-group-item">
				<strong>nginx</strong> (<a href="http://www.nginx.org" target="_blank">http://www.nginx.org)</a><br />
				<?=gettext("Copyright"); ?> &copy;<?=gettext("2011-2016 Nginx, Inc.")?>
				<?=gettext("All rights reserved.")?>
			</li>
			<li class="list-group-item">
				<strong>php-radius</strong> (<a href="http://www.mavetju.org/programming/php.php" target="_blank">http://www.mavetju.org/programming/php.php</a>)<br />
				<?=gettext("Copyright 2000, 2001, 2002 by Edwin Groothuis. All rights reserved.")?><br />
				<?=gettext("This product includes software developed by Edwin Groothuis.")?>
			</li>
			<li class="list-group-item">
				<strong>strongSwan</strong> (<a href="https://www.strongswan.org/" target="_blank">https://www.strongswan.org</a>)<br />
				<?=gettext("Copyright"); ?> &copy; <?=gettext("2005-2016 University of Applied Sciences Rapperswil")?>
			</li>
			<li class="list-group-item">
				<strong>wol</strong> (<a href="http://ahh.sourceforge.net/wol" target="_blank">http://ahh.sourceforge.net/wol</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2000,2001,2002,2003,2004 Thomas Krennwallner &lt;krennwallner@aon.at&gt;
			</li>
			<li class="list-group-item">
				<strong>openldap-client</strong> (<a href="http://www.openldap.org/" target="_blank">http://www.openldap.org/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 1999-2016 The OpenLDAP Foundation
			</li>
			<li class="list-group-item">
				<strong>OpenVPN</strong> (<a href="http://openvpn.net/" target="_blank">http://openvpn.net/</a>)<br />
				<?=gettext("Copyright (C) 2002-2016 OpenVPN Solutions LLC ")?>
			</li>
			<li class="list-group-item">
				<strong>pftop</strong> (<a href="http://www.eee.metu.edu.tr/~canacar/pftop/" target="_blank">http://www.eee.metu.edu.tr/~canacar/pftop/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2001, 2007 Can Erkin Acar<br />
				<?=gettext("Copyright"); ?> &copy; 2001 Daniel Hartmeier
			</li>
			<li class="list-group-item">
				<strong>radvd</strong> IPv6 router advertisement daemon(<a href="http://www.litech.org/radvd/" target="_blank">http://www.litech.org/radvd/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 1996-2015 Lars Fenneberg, Pedro Roque
			</li>
			<li class="list-group-item">
				<strong>rate</strong> command line traffic analysis tool(<a href="http://s-tech.elsat.net.pl/bmtools/" target="_blank">http://s-tech.elsat.net.pl/bmtools/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2003-2016 Mateusz 'mteg' Golicz
			</li>
			<li class="list-group-item">
				<strong>relayd</strong> server load balancing with pf (<a href="http://www.openbsd.org" target="_blank">http://www.openbsd.org</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2007-2016 Reyk Floeter
			</li>
			<li class="list-group-item">
				<strong>rrdtool</strong> data logging and graphing system for time series data (<a href="http://oss.oetiker.ch/rrdtool/" target="_blank">http://oss.oetiker.ch/rrdtool/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 1998-2016 Tobias Oetiker
			</li>
			<li class="list-group-item">
				<strong>scponly</strong> (<a href="https://github.com/scponly/scponly/wiki" target="_blank">https://github.com/scponly/scponly/wiki</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2001, 2002, 2003 Joe Boyle
			</li>
			<li class="list-group-item">
				<strong>smartmontools</strong> (<a href="https://www.smartmontools.org/" target="_blank">https://www.smartmontools.org/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2002-2016 Bruce Allen, Christian Franke
			</li>
			<li class="list-group-item">
				<strong>sortable</strong> Sortable JavaScript and CSS library (<a href="http://github.hubspot.com/sortable/" target="_blank">http://github.hubspot.com/sortable/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2013 Adam Schwartz
			</li>
			<li class="list-group-item">
				<strong>sqlite3</strong> (<a href="https://www.sqlite.org/" target="_blank">https://www.sqlite.org/</a>)<br />
				<?=gettext("Public Domain"); ?>
			</li>
			<li class="list-group-item">
				<strong>Unbound</strong> (<a href="https://www.unbound.net/" target="_blank">https://www.unbound.net/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 2007 NLnet Labs
			</li>
			<li class="list-group-item">
				<strong>xinetd</strong> (<a href="http://www.xinetd.org/" target="_blank">http://www.xinetd.org/</a>)<br />
				<?=gettext("Copyright"); ?> &copy; 1992-2016 Panagiotis Tsirigotis
			</li>
		</ul>
	</div>
</div>

<?php include("foot.inc");
