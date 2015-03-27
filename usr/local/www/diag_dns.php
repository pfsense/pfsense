<?php
/*
	diag_dns.php

	Copyright (C) 2009 Jim Pingle (jpingle@gmail.com)
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
	pfSense_MODULE: dns
*/

/* Bootstrap conversion: sbeaver@netgate.com */

$pgtitle = array(gettext("Diagnostics"),gettext("DNS lookup"));
require("guiconfig.inc");

$host = trim($_REQUEST['host'], " \t\n\r\0\x0B[];\"'");
$host_esc = escapeshellarg($host);

/* If this section of config.xml has not been populated yet we need to set it up 
*/
if (!is_array($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
$a_aliases = &$config['aliases']['alias'];

$aliasname = str_replace(array(".","-"), "_", $host);
$alias_exists = false;
$counter=0;
foreach($a_aliases as $a) {
	if($a['name'] == $aliasname) {
		$alias_exists = true;
		$id=$counter;
	}
	$counter++;
}

if(isset($_POST['create_alias']) && (is_hostname($host) || is_ipaddr($host))) {
	if($_POST['override'])
		$override = true;
	$resolved = gethostbyname($host);
	$type = "hostname";
	if($resolved) {
		$resolved = array();
		exec("/usr/bin/drill {$host_esc} A | /usr/bin/grep {$host_esc} | /usr/bin/grep -v ';' | /usr/bin/awk '{ print $5 }'", $resolved);
		$isfirst = true;
		foreach($resolved as $re) {
			if($re != "") {
				if(!$isfirst) 
					$addresses .= " ";
				$addresses .= rtrim($re) . "/32";
				$isfirst = false;
			}
		}
		$newalias = array();
		if($override) 
			$alias_exists = false;
		if($alias_exists == false) {
			$newalias['name'] = $aliasname;
			$newalias['type'] = "network";
			$newalias['address'] = $addresses;
			$newalias['descr'] = "Created from Diagnostics-> DNS Lookup";
			if($override) 
				$a_aliases[$id] = $newalias;
			else
				$a_aliases[] = $newalias;
			write_config();
			$createdalias = true;
		}
	}
}

if ($_POST) {
	unset($input_errors);
	
	$reqdfields = explode(" ", "host");
	$reqdfieldsn = explode(",", "Host");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	
	if (!is_hostname($host) && !is_ipaddr($host)) {
		$input_errors[] = gettext("Host must be a valid hostname or IP address.");
	} else {
		// Test resolution speed of each DNS server.
		$dns_speeds = array();
		$dns_servers = array();
		exec("/usr/bin/grep nameserver /etc/resolv.conf | /usr/bin/cut -f2 -d' '", $dns_servers);
		foreach ($dns_servers as $dns_server) {
			$query_time = exec("/usr/bin/drill {$host_esc} " . escapeshellarg("@" . trim($dns_server)) . " | /usr/bin/grep Query | /usr/bin/cut -d':' -f2");
			if($query_time == "")
				$query_time = gettext("No response");
			$new_qt = array();
			$new_qt['dns_server'] = $dns_server;
			$new_qt['query_time'] = $query_time;
			$dns_speeds[] = $new_qt;
			unset($new_qt);
		}
	}

	$type = "unknown";
	$resolved = "";
	$ipaddr = "";
	$hostname = "";
	if (!$input_errors) {
		if (is_ipaddr($host)) {
			$type = "ip";
			$resolved = gethostbyaddr($host);
			$ipaddr = $host;
			if ($host != $resolved)
				$hostname = $resolved;
		} elseif (is_hostname($host)) {
			$type = "hostname";
			$resolved = gethostbyname($host);
			if($resolved) {
				$resolved = array();
				exec("/usr/bin/drill {$host_esc} A | /usr/bin/grep {$host_esc} | /usr/bin/grep -v ';' | /usr/bin/awk '{ print $5 }'", $resolved);
			}
			$hostname = $host;
			if ($host != $resolved)
				$ipaddr = $resolved[0];
		}

		if ($host == $resolved) {
			$resolved = gettext("No record found");
		}
	}
}

if( ($_POST['host']) && ($_POST['dialog_output']) ) {
	display_host_results ($host,$resolved,$dns_speeds);
	exit;
}

function display_host_results ($address,$hostname,$dns_speeds) {
	$map_lengths = function($element) { return strlen($element[0]); };

	$text_table = array();
	$text_table[] = array(gettext("Server"), gettext("Query Time"));
	if (is_array($dns_speeds)) {
		foreach ($dns_speeds as $qt) {
			$text_table[] = array(trim($qt['dns_server']), trim($qt['query_time']));
		}
	}
	$col0_padlength = max(array_map($map_lengths, $text_table)) + 4;
	foreach ($text_table as $text_row) {
		echo str_pad($text_row[0], $col0_padlength) . $text_row[1] . "\n";
	}
}

include("head.inc"); ?>

<?php
	/* Dosplay any error messages resulting from user input */ 
	if ($input_errors) 
		print_input_errors($input_errors);
	else if (!$resolved && $type)
		{
		$errors[] = gettext("Host") . ": " . "\"" . $host . "\"" . " " . gettext("could not be resolved");
		print_errors($errors, gettext("DNS Lookup error:"));		
	}
?>
  
<form action="diag_dns.php" method="post" name="iform" id="iform">

<!-- First table displayes the DNS lookup form and the resulting IP addresses -->
	<div class="table-responsive">
	
		<table class="table" summary="results">
		<thead>
			<tr>
				<th width="30%">Host name</th>
				<?php if ($resolved && $type) { ?> 
				<th>IP Addresse(s)</th>
				<?php } ?>
			</tr>
		</thead>
		<tbody>
			<tr class="active">
				<td>
					<input name="host" type="text" class="formfld" id="host" size="20" value="<?=htmlspecialchars($host)?>" />
					<br /><br />
					<button type="submit" class="btn btn-primary"><?=gettext("DNS Lookup");?></button>
				</td>
							
				<td>
					<?php if ($resolved && $type) { ?>
					<font size="+1">
<?php
					$found = 0;
					if(is_array($resolved)) { 
						foreach($resolved as $hostitem) {
							if($hostitem != "") {
								echo $hostitem . "<br />";
								$found++;
							}
						}
					} else {
						echo $resolved; 
					} 
				
					if($found > 0) { ?>
						</font> <font size='-1'">
					<?PHP	if($alias_exists) { ?>
							 An alias already exists for the hostname <?= htmlspecialchars($host) ?>. <br /> 
							<input type="hidden" name="override" value="true"/>
							<button type="submit" name="override" class="btn-xs btn-success"><?=gettext("Overwrite alias");?></button>
							
					<?PHP	} else {
						if(!$createdalias) { ?>
							<button type="submit" name="create_alias" class="btn-xs btn-success"><?=gettext("Create Alias from These Entries");?></button>
					<?PHP	} else { ?>
							Alias created with name <?= htmlspecialchars($newalias['name']) ?>
					<?PHP	}
						}
					}
					
?>

					<?php } ?>
					</font>
				</td>
			</tr>				 
		</tbody> 
		</table>
	</div>			
			
<!-- Second table displays the server resolution times -->		
	<div class="table-responsive">
		<table width="70%" class="table table-condensed" summary="results">
		<?php		if($_POST): ?>
		<thead>
			<tr>
				<th width="30%">Name server</th>
				<th>Query time</th>
			</tr>
		</thead>
		
		<tbody>		


<?php
		if(is_array($dns_speeds)) 
			foreach($dns_speeds as $qt):
?>
			<tr class="active">
				<td class="listlr">
					<?=$qt['dns_server']?>
				</td>
				<td class="listr">
					<?=$qt['query_time']?>
				</td>
			</tr>
<?php
			endforeach;
?>


		<?php endif; ?>
		</tbody>

		</table>
	</div>
		
<!-- Third table displays "More information" --> 
	<div class="table-responsive">
		<table class="table table-condensed" summary="results">
		<?php		if($_POST && ($found > 0)) { ?>
		<thead>
			<tr>
				<th width="30%">More information</th>
				<th></th>
			</tr>
		</thead>
	
		<tbody>
		<?php if (!$input_errors && $ipaddr) { ?>
		<tr class="active">
			<td width="78%" class="vtable">
				<a href ="/diag_ping.php?host=<?=htmlspecialchars($host)?>&amp;interface=wan&amp;count=3"><?=gettext("Ping")?></a><br />
				<a href ="/diag_traceroute.php?host=<?=htmlspecialchars($host)?>&amp;ttl=18"><?=gettext("Traceroute")?></a>
				<p><br />
				<?=gettext("NOTE: The following links are to external services, so their reliability cannot be guaranteed.<br />They use only the first IP address returned above.")?><br /><br />
				<a target="_blank" href="http://private.dnsstuff.com/tools/whois.ch?ip=<?=$ipaddr; ?>"><?=gettext("IP WHOIS @ DNS Stuff")?></a><br />
				<a target="_blank" href="http://private.dnsstuff.com/tools/ipall.ch?ip=<?=$ipaddr; ?>"><?=gettext("IP Info @ DNS Stuff")?></a>
				</p>
			</td>
			<td></td>
		</tr>
		<?php } ?>
		</tbody>
		<?php } ?>
		</table>
	</div>	
	
</form>
<?php include("foot.inc"); ?>

