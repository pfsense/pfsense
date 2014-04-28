<?php
/* $Id$ */
/* Run various commands and collect their output into HTML tables.
 * Jim McBeath <jimmc@macrovision.com> Nov 2003
 *
 * (modified for m0n0wall by Manuel Kasper <mk@neon1.net>)
 * (modified for pfSense by Scott Ullrich geekgod@pfsense.com)
 */
/*
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1.	Redistributions of source code must retain the above copyright notice,
		this list of conditions and the following disclaimer.

	2.	Redistributions in binary form must reproduce the above copyright
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
	pfSense_BUILDER_BINARIES:	/usr/bin/vmstat	/usr/bin/netstat	/sbin/dmesg	/sbin/mount	/usr/local/sbin/setkey	/usr/local/sbin/pftop	
	pfSense_BUILDER_BINARIES:	/sbin/pfctl	/sbin/sysctl	/usr/bin/top	/usr/bin/netstat	/sbin/pfctl	/sbin/ifconfig
	pfSense_MODULE:	support
*/

##|+PRIV
##|*IDENT=page-hidden-detailedstatus
##|*NAME=Hidden: Detailed Status page
##|*DESCR=Allow access to the 'Hidden: Detailed Status' page.
##|*MATCH=status.php*
##|-PRIV

/* Execute a command, with a title, and generate an HTML table
 * showing the results.
 */

/* include all configuration functions */
require_once("guiconfig.inc");
require_once("functions.inc");

function doCmdT($title, $command) {
	$rubbish = array('|', '-', '/', '.', ' ');  /* fixes the <a> tag to be W3C compliant */
	echo "\n<a name=\"" . str_replace($rubbish,'',$title) . "\" id=\"" . str_replace($rubbish,'',$title) . "\"></a>\n";
	echo "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" summary=\"" . $title . "\">\n";
	echo "\t<tr><td class=\"listtopic\">" . $title . "</td></tr>\n";
	echo "\t<tr>\n\t\t<td class=\"listlr\">\n\t\t\t<pre>";		/* no newline after pre */

	if ($command == "dumpconfigxml") {
		$fd = @fopen("/conf/config.xml", "r");
		if ($fd) {
			while (!feof($fd)) {
				$line = fgets($fd);
				/* remove sensitive contents */
				$line = preg_replace("/<password>.*?<\\/password>/", "<password>xxxxx</password>", $line);
				$line = preg_replace("/<pre-shared-key>.*?<\\/pre-shared-key>/", "<pre-shared-key>xxxxx</pre-shared-key>", $line);
				$line = preg_replace("/<rocommunity>.*?<\\/rocommunity>/", "<rocommunity>xxxxx</rocommunity>", $line);
				$line = str_replace("\t", "    ", $line);
				echo htmlspecialchars($line,ENT_NOQUOTES);
			}
		}
		fclose($fd);
	} else {
		$execOutput = "";
		$execStatus = "";
		exec ($command . " 2>&1", $execOutput, $execStatus);
		for ($i = 0; isset($execOutput[$i]); $i++) {
			if ($i > 0) {
				echo "\n";
			}
			echo htmlspecialchars($execOutput[$i],ENT_NOQUOTES);
		}
	}
    echo "\n\t\t\t</pre>\n\t\t</td>\n\t</tr>\n";
    echo "</table>\n";
}

/* Execute a command, giving it a title which is the same as the command. */
function doCmd($command) {
	doCmdT($command,$command);
}

/* Define a command, with a title, to be executed later. */
function defCmdT($title, $command) {
	global $commands;
	$title = htmlspecialchars($title,ENT_NOQUOTES);
	$commands[] = array($title, $command);
}

/* Define a command, with a title which is the same as the command,
 * to be executed later.
 */
function defCmd($command) {
	defCmdT($command,$command);
}

/* List all of the commands as an index. */
function listCmds() {
	global $commands;
	$rubbish = array('|', '-', '/', '.', ' ');  /* fixes the <a> tag to be W3C compliant */
	echo "\n<p>" . gettext("This status page includes the following information") . ":\n";
	echo "<ul>\n";
	for ($i = 0; isset($commands[$i]); $i++ ) {
		echo "\t<li><strong><a href=\"#" . str_replace($rubbish,'',$commands[$i][0]) . "\">" . $commands[$i][0] . "</a></strong></li>\n";
	}
	echo "</ul>\n";
}

/* Execute all of the commands which were defined by a call to defCmd. */
function execCmds() {
	global $commands;
	for ($i = 0; isset($commands[$i]); $i++ ) {
		doCmdT($commands[$i][0], $commands[$i][1]);
	}
}

global $g, $config;

/* Set up all of the commands we want to execute. */
defCmdT("System uptime","uptime");
defCmdT("Interfaces","/sbin/ifconfig -a");

defCmdT("PF Info","/sbin/pfctl -s info");

defCmdT("Routing tables","netstat -nr");

defCmdT("top | head -n5", "/usr/bin/top | /usr/bin/head -n5");

defCmdT("sysctl hw.physmem","/sbin/sysctl hw.physmem");

if (isset($config['captiveportal']) && is_array($config['captiveportal']))
	foreach ($config['captiveportal'] as $cpZone => $cpdata)
		if (isset($cpdata['enable']))
			defCmdT("ipfw -x {$cpZone} show", "/sbin/ipfw -x {$cpZone} show");

defCmdT("pfctl -sn", "/sbin/pfctl -sn");
defCmdT("pfctl -sr", "/sbin/pfctl -sr");
defCmdT("pfctl -ss", "/sbin/pfctl -ss");
defCmdT("pfctl -si", "/sbin/pfctl -si");
defCmdT("pfctl -sa", "/sbin/pfctl -sa");
defCmdT("pfctl -s rules -vv","/sbin/pfctl -s rules -vv");
defCmdT("pfctl -s queue -v","/sbin/pfctl -s queue -v");
defCmdT("pfctl -s nat -v","/sbin/pfctl -s nat -v");

defCmdT("PF OSFP","/sbin/pfctl -s osfp");


defCmdT("netstat -s -ppfsync","netstat -s -ppfsync");

defCmdT("pfctl -vsq","/sbin/pfctl -vsq");

defCmdT("pfctl -vs Tables","/sbin/pfctl -vs Tables");

defCmdT("Load Balancer","/sbin/pfctl -a slb -s nat");

defCmdT("pftop -w 150 -a -b","/usr/local/sbin/pftop -a -b");
defCmdT("pftop -w 150 -a -b -v long","/usr/local/sbin/pftop -w 150 -a -b -v long");
defCmdT("pftop -w 150 -a -b -v queue","/usr/local/sbin/pftop -w 150 -a -b -v queue");
defCmdT("pftop -w 150 -a -b -v rules","/usr/local/sbin/pftop -w 150 -a -b -v rules");
defCmdT("pftop -w 150 -a -b -v size","/usr/local/sbin/pftop -w 150 -a -b -v size");
defCmdT("pftop -w 150 -a -b -v speed","/usr/local/sbin/pftop -w 150 -a -b -v speed");

defCmdT("resolv.conf","cat /etc/resolv.conf");

defCmdT("Processes","ps xauww");
defCmdT("dhcpd.conf","cat /var/dhcpd/etc/dhcpd.conf");
defCmdT("ez-ipupdate.cache","cat /conf/ez-ipupdate.cache");

defCmdT("df","/bin/df");

defCmdT("racoon.conf","cat /var/etc/racoon.conf");
defCmdT("SPD","/usr/local/sbin/setkey -DP");
defCmdT("SAD","/usr/local/sbin/setkey -D");

if(isset($config['system']['usefifolog']))  {
	defCmdT("last 200 system log entries","/usr/sbin/fifolog_reader /var/log/system.log 2>&1 | tail -n 200");
	defCmdT("last 50 filter log entries","/usr/sbin/fifolog_reader /var/log/filter.log 2>&1 | tail -n 50");
} else {
	defCmdT("last 200 system log entries","/usr/local/sbin/clog /var/log/system.log 2>&1 | tail -n 200");
	defCmdT("last 50 filter log entries","/usr/local/sbin/clog /var/log/filter.log 2>&1 | tail -n 50");
}
	
defCmd("ls /conf");
defCmd("ls /var/run");

defCmd("/sbin/mount");

defCmdT("cat {$g['tmp_path']}/rules.debug","cat {$g['tmp_path']}/rules.debug");

defCmdT("VMStat", "vmstat -afimsz");

defCmdT("config.xml","dumpconfigxml");

defCmdT("DMESG","/sbin/dmesg -a");

defCmdT("netstat -mb","netstat -mb");
defCmdT("vmstat -z","vmstat -z");

exec("/bin/date", $dateOutput, $dateStatus);
$currentDate = $dateOutput[0];

$pgtitle = array("{$g['product_name']}","status");
include("head.inc");

?>
<style type="text/css">
/*<![CDATA[*/
pre {
	margin: 0px;
	font-family: courier new, courier;
	font-weight: normal;
	font-size: 9pt;
}
/*]]>*/
</style>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<strong><?=$currentDate;?></strong>
<p><span class="red"><strong><?=gettext("Note: make sure to remove any sensitive information " .
"(passwords, maybe also IP addresses) before posting " .
"information from this page in public places (like mailing lists)"); ?>!</strong></span><br />
<?=gettext("Passwords in config.xml have been automatically removed"); ?>.

<div id="cmdspace" style="width:700px">
<?php listCmds(); ?>

<?php execCmds(); ?>
</div>

<?php include("fend.inc"); ?>
</body>
</html>
