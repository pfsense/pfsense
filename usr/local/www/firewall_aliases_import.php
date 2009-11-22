<?php
/* $Id$ */
/*
	firewall_aliases_edit.php
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

$pgtitle = array("Firewall","Aliases","Import");

require("guiconfig.inc");
require("filter.inc");
require("shaper.inc");

aliases_sort();
$a_aliases = &$config['aliases']['alias'];

if($_POST['aliasimport'] <> "") {
	$reqdfields = explode(" ", "name aliasimport");
	$reqdfieldsn = explode(",", "Name,Aliases");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
	if (!$input_errors) {			
		$alias = array();
		$alias['address'] = str_replace("\n", " ", $_POST['aliasimport']);
		$alias['name'] = $_POST['name'];
		$alias['descr'] = $_POST['descr'];
		$a_aliases[] = $alias;
		write_config();
		
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
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
	  <td valign="top" class="vncellreq">Alias Name</td>
	  <td class="vtable"> <input name="name" type="text" class="formfld unknown" id="name" size="40" value="<?=htmlspecialchars($_POST['name']);?>" />
	    <br /> <span class="vexpl">
	    The name of the alias may only consist of the characters a-z, A-Z and 0-9.</span></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell">Description</td>
	  <td width="78%" class="vtable"> <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($_POST['descr']);?>" />
	    <br /> <span class="vexpl">You may enter a description here
	    for your reference (not parsed).</span></td>
	</tr>
	<tr>
	  <td valign="top" class="vncellreq">Aliases to import</td>
	  <td class="vtable"><textarea name="aliasimport" ROWS="15" COLS="40"><?php echo $_POST['aliasimport']; ?></textarea>
	    <br /> <span class="vexpl">Paste in the aliases to import seperated by a carriage return.  Common examples are list of ips, networks, blacklists, etc.</span></td>
	</tr>
	<tr>
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%">
      <input id="submit" name="Submit" type="submit" class="formbtn" value="Save" />
      <input class="formbtn" type="button" value="Cancel" onclick="history.back()" />
	</tr>
</table>


</form>
</div>

<?php include("fend.inc"); ?>
	    
<script type="text/javascript">
	NiftyCheck();
	Rounded("div#nifty","top","#FFF","#EEEEEE","smooth");
</script>


</body>
</html>

