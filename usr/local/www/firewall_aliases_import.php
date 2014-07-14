<?php
/* $Id$ */
/*
	firewall_aliases_import.php
	Copyright (C) 2005 Scott Ullrich
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
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-firewall-alias-import
##|*NAME=Firewall: Alias: Import page
##|*DESCR=Allow access to the 'Firewall: Alias: Import' page.
##|*MATCH=firewall_aliases_import.php*
##|-PRIV


// Keywords not allowed in names
$reserved_keywords = array("all", "pass", "block", "out", "queue", "max", "min", "pptp", "pppoe", "L2TP", "OpenVPN", "IPsec");

require("guiconfig.inc");
require_once("util.inc");
require_once("filter.inc");
require("shaper.inc");

$pgtitle = array(gettext("Firewall"),gettext("Aliases"),gettext("Bulk import"));

// Add all Load balance names to reserved_keywords
if (is_array($config['load_balancer']['lbpool']))
	foreach ($config['load_balancer']['lbpool'] as $lbpool)
		$reserved_keywords[] = $lbpool['name'];

$reserved_ifs = get_configured_interface_list(false, true);
$reserved_keywords = array_merge($reserved_keywords, $reserved_ifs, $reserved_table_names);

if (!is_array($config['aliases']['alias']))
        $config['aliases']['alias'] = array();
$a_aliases = &$config['aliases']['alias'];

if($_POST['aliasimport'] <> "") {
	$reqdfields = explode(" ", "name aliasimport");
	$reqdfieldsn = array(gettext("Name"),gettext("Aliases"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
	if (is_validaliasname($_POST['name']) == false)
		$input_errors[] = gettext("The alias name may only consist of the characters") . " a-z, A-Z, 0-9, _.";

	/* check for name duplicates */
	if (is_alias($_POST['name']))
		$input_errors[] = gettext("An alias with this name already exists.");


	/* Check for reserved keyword names */
        foreach($reserved_keywords as $rk)
                if ($rk == $_POST['name'])
                        $input_errors[] = sprintf(gettext("Cannot use a reserved keyword as alias name %s"), $rk);

        /* check for name interface description conflicts */
        foreach($config['interfaces'] as $interface) {
                if($interface['descr'] == $_POST['name']) {
                        $input_errors[] = gettext("An interface description with this name already exists.");
                        break;
                }
        }

	if ($_POST['aliasimport']) {
		$tocheck = explode("\n", $_POST['aliasimport']);
		$imported = array();
		foreach ($tocheck as $impip) {
			$impip = trim($impip);
			if (is_iprange($impip)) {
				list($startip, $endip) = explode('-', $impip);
				$rangesubnets = ip_range_to_subnet_array($startip, $endip);
				$imported = array_merge($imported, $rangesubnets);
			} else if (!is_ipaddr($impip) && !is_subnet($impip) && !is_hostname($impip) && !empty($impip)) {
				$input_errors[] = sprintf(gettext("%s is not an IP address. Please correct the error to continue"), $impip);
			} elseif (!empty($impip)) {
				$imported[] = $impip;
			}
		}
	}

	if (!$input_errors && is_array($imported)) {
		$alias = array();
		$alias['address'] = implode(" ", $imported);
		$alias['name'] = $_POST['name'];
		$alias['type'] = "network";
		$alias['descr'] = $_POST['descr'];
		$a_aliases[] = $alias;

		// Sort list
		$a_aliases = msort($a_aliases, "name");

		if (write_config())
			mark_subsystem_dirty('aliases');
		pfSenseHeader("firewall_aliases.php");
		
		exit;
	}
}

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<div id="niftyOutter">
<form action="firewall_aliases_import.php" method="post" name="iform" id="iform">
<div id="inputerrors"></div>
<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="firewall alias import">
	<tr>
	  <td colspan="2" valign="top" class="listtopic"><?=gettext("Alias Import"); ?></td>
	</tr>
	<tr>
	  <td valign="top" class="vncellreq"><?=gettext("Alias Name"); ?></td>
	  <td class="vtable"> <input name="name" type="text" class="formfld unknown" id="name" size="40" value="<?=htmlspecialchars($_POST['name']);?>" />
	    <br /> <span class="vexpl">
	    <?=gettext("The name of the alias may only consist of the characters \"a-z, A-Z and 0-9\"."); ?></span></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
	  <td width="78%" class="vtable"> <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($_POST['descr']);?>" />
	    <br /> <span class="vexpl"><?=gettext("You may enter a description here " .
	    "for your reference (not parsed)"); ?>.</span></td>
	</tr>
	<tr>
	  <td valign="top" class="vncellreq"><?=gettext("Aliases to import"); ?></td>
	  <td class="vtable"><textarea name="aliasimport" rows="15" cols="40"><?php echo $_POST['aliasimport']; ?></textarea>
	    <br /> <span class="vexpl"><?=gettext("Paste in the aliases to import separated by a carriage return.  Common examples are lists of IPs, networks, blacklists, etc."); ?> 
	    <br /> <?=gettext("The list may contain only IP addresses."); ?> </span></td>
	</tr>
	<tr>
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%">
      <input id="submit" name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
      <input class="formbtn" type="button" value="<?=gettext("Cancel"); ?>" onclick="history.back()" />
	</td></tr>
</table>


</form>
</div>

<?php include("fend.inc"); ?>
	    
<script type="text/javascript">
//<![CDATA[
	NiftyCheck();
	Rounded("div#nifty","top","#FFF","#EEEEEE","smooth");
//]]>
</script>

</body>
</html>
