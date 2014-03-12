<?php
/* $Id$ */
/*
	services_igmpproxy_edit_edit.php

	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
	pfSense_MODULE:	igmpproxy
*/

##|+PRIV
##|*IDENT=page-services-igmpproxy-edit
##|*NAME=Firewall: Igmpproxy: Edit page
##|*DESCR=Allow access to the 'Services: Igmpproxy: Edit' page.
##|*MATCH=services_igmpproxy_edit.php*
##|-PRIV

$pgtitle = array(gettext("Firewall"),gettext("IGMP Proxy"), gettext("Edit"));

require("guiconfig.inc");

if (!is_array($config['igmpproxy']['igmpentry']))
	$config['igmpproxy']['igmpentry'] = array();

//igmpproxy_sort();
$a_igmpproxy = &$config['igmpproxy']['igmpentry'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_igmpproxy[$id]) {
	$pconfig['ifname'] = $a_igmpproxy[$id]['ifname'];
	$pconfig['threshold'] = $a_igmpproxy[$id]['threshold'];
	$pconfig['type'] = $a_igmpproxy[$id]['type'];
	$pconfig['address'] = $a_igmpproxy[$id]['address'];
	$pconfig['descr'] = html_entity_decode($a_igmpproxy[$id]['descr']);

}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if ($_POST['type'] == "upstream") {
		foreach ($a_igmpproxy as $pid => $proxyentry) {
			if (isset($id) && $id == $pid)
				continue;
			if ($proxyentry['type'] == "upstream" && $proxyentry['ifname'] != $_POST['interface'])
				$input_errors[] = gettext("Only one 'upstream' interface can be configured.");
		}
	}
	$igmpentry = array();
	$igmpentry['ifname'] = $_POST['ifname'];
	$igmpentry['threshold'] = $_POST['threshold'];
	$igmpentry['type'] = $_POST['type'];
	$address = "";
	$isfirst = 0;
	/* item is a normal igmpentry type */
	for($x=0; $x<4999; $x++) {
		if($_POST["address{$x}"] <> "") {
			if ($isfirst > 0)
				$address .= " ";
			$address .= $_POST["address{$x}"];
			$address .= "/" . $_POST["address_subnet{$x}"];
			$isfirst++;
		}
	}

	if (!$input_errors) {
		$igmpentry['address'] = $address;
		$igmpentry['descr'] = $_POST['descr'];

		if (isset($id) && $a_igmpproxy[$id])
			$a_igmpproxy[$id] = $igmpentry;
		else
			$a_igmpproxy[] = $igmpentry;

		write_config();

		mark_subsystem_dirty('igmpproxy');
		header("Location: services_igmpproxy.php");
		exit;		
	}
	//we received input errors, copy data to prevent retype
	else
	{
		$pconfig['descr'] = $_POST['descr'];
		$pconfig['address'] = $address;
		$pconfig['type'] = $_POST['type'];
	}
}

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php
	include("fbegin.inc");
?>

<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js">
</script>
<script type="text/javascript" src="/javascript/row_helper.js">
</script>

<input type='hidden' name='address_type' value='textbox' class="formfld unknown" />
<input type='hidden' name='address_subnet_type' value='select' />

<script type="text/javascript">
	rowname[0] = "address";
	rowtype[0] = "textbox,ipv4v6";
	rowsize[0] = "30";

	rowname[1] = "address_subnet";
	rowtype[1] = "select,ipv4v6";
	rowsize[1] = "1";

	rowname[2] = "detail";
	rowtype[2] = "textbox";
	rowsize[2] = "50";
</script>

<?php if ($input_errors) print_input_errors($input_errors); ?>
<div id="inputerrors"></div>

<form action="services_igmpproxy_edit.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
  <tr>
	<td colspan="2" valign="top" class="listtopic"><?=gettext("IGMP Proxy Edit");?></td>
  </tr>
  <tr>
    <td valign="top" class="vncellreq"><?=gettext("Interface");?></td>
    <td class="vtable"> <select name="ifname" id="ifname" >
		<?php $iflist = get_configured_interface_with_descr();
			foreach ($iflist as $ifnam => $ifdescr) {
				echo "<option value={$ifnam}";
				if ($ifnam == $pconfig['ifname'])
					echo " selected";
				echo ">{$ifdescr}</option>";
			}		
		?>
			</select>
    </td>
  </tr>
  <tr>
    <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
    <td width="78%" class="vtable">
      <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
      <br />
      <span class="vexpl">
        <?=gettext("You may enter a description here for your reference (not parsed).");?>
      </span>
    </td>
  </tr>
  <tr>
    <td valign="top" class="vncellreq"><?=gettext("Type");?></td>
    <td class="vtable">
      <select name="type" class="formselect" id="type" >
        <option value="upstream" <?php if ($pconfig['type'] == "upstream") echo "selected"; ?>><?=gettext("Upstream Interface");?></option>
        <option value="downstream" <?php if ($pconfig['type'] == "downstream") echo "selected"; ?>><?=gettext("Downstream Interface");?></option>
      </select>
      <br />
      <span class="vexpl">
        <?=gettext("The <b>upstream</b> network interface is the outgoing interface which is".
      " responsible for communicating to available multicast data sources.".
      " There can only be one upstream interface.");?>
	</span>
	<br />
	<span class="vexpl">
       <b><?=gettext("Downstream"); ?></b> <?=gettext("network interfaces are the distribution  interfaces  to  the".
      " destination  networks,  where  multicast  clients  can  join groups and".
      " receive multicast data. One or more downstream interfaces must be configured.");?>
      </span>
    </td>
  </tr>
  <tr>
    <td valign="top" class="vncell"><?=gettext("Threshold");?></td>
    <td class="vtable">
      <input name="threshold" class="formfld unknown" id="threshold" value="<?php echo htmlspecialchars($pconfig['threshold']);?>">
      <br />
      <span class="vexpl">
	      <?=gettext("Defines the TTL threshold for  the  network  interface.  Packets".
             " with  a lower TTL than the threshols value will be ignored. This".
             " setting is optional, and by default the threshold is 1.");?>
      </span>
    </td>
  </tr>
  <tr>
    <td width="22%" valign="top" class="vncellreq"><div id="addressnetworkport"><?=gettext("Network (s)");?></div></td>
    <td width="78%" class="vtable">
      <table id="maintable">
        <tbody>
          <tr>
            <td><div id="onecolumn"><?=gettext("Network");?></div></td>
            <td><div id="twocolumn"><?=gettext("CIDR");?></div></td>
          </tr>

	<?php
	$counter = 0;
	$address = $pconfig['address'];
	if ($address <> "") {
		$item = explode(" ", $address);
		foreach($item as $ww) {
			$address = $item[$counter];
			$address_subnet = "";
			$item2 = explode("/", $address);
			foreach($item2 as $current) {
				if($item2[1] <> "") {
					$address = $item2[0];
					$address_subnet = $item2[1];
				}
			}
			$item4 = $item3[$counter];
			$tracker = $counter;
	?>
          <tr>
            <td>
              <input name="address<?php echo $tracker; ?>" type="text" class="formfld unknown" id="address<?php echo $tracker; ?>" size="30" value="<?=htmlspecialchars($address);?>" />
            </td>
            <td>
			        <select name="address_subnet<?php echo $tracker; ?>" class="formselect" id="address_subnet<?php echo $tracker; ?>">
			          <option></option>
			          <?php for ($i = 32; $i >= 1; $i--): ?>
			          <option value="<?=$i;?>" <?php if ($i == $address_subnet) echo "selected"; ?>><?=$i;?></option>
			          <?php endfor; ?>
			        </select>
			      </td>
            <td>
    		<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/<?echo $g['theme'];?>/images/icons/icon_x.gif" /></a>
	      </td>
          </tr>
<?php
        	$counter++;

       		} // end foreach
	} // end if
?>
        </tbody>
        <tfoot>

        </tfoot>
		  </table>
			<a onclick="javascript:addRowTo('maintable'); return false;" href="#">
        <img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="<?=gettext("add another entry");?>" />
      </a>
		</td>
  </tr>
  <tr>
    <td width="22%" valign="top">&nbsp;</td>
    <td width="78%">
      <input id="submit" name="submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
      <a href="services_igmpproxy.php"><input id="cancelbutton" name="cancelbutton" type="button" class="formbtn" value="<?=gettext("Cancel");?>" /></a>
      <?php if (isset($id) && $a_igmpproxy[$id]): ?>
      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
      <?php endif; ?>
    </td>
  </tr>
</table>
</form>

<script type="text/javascript">
	field_counter_js = 2;
	rows = 1;
	totalrows = <?php echo $counter; ?>;
	loaded = <?php echo $counter; ?>;
</script>

<?php include("fend.inc"); ?>
</body>
</html>
