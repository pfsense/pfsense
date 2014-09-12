<?php
/*
       services_dnsmasq_domainoverride_edit.php
       part of m0n0wall (http://m0n0.ch/wall)

       Copyright (C) 2003-2005 Bob Zoller <bob@kludgebox.com> and Manuel Kasper <mk@neon1.net>.
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
	pfSense_MODULE:	dnsforwarder
*/

##|+PRIV
##|*IDENT=page-services-dnsforwarder-editdomainoverride
##|*NAME=Services: DNS Forwarder: Edit Domain Override page
##|*DESCR=Allow access to the 'Services: DNS Forwarder: Edit Domain Override' page.
##|*MATCH=services_dnsmasq_domainoverride_edit.php*
##|-PRIV

require("guiconfig.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/services_dnsmasq.php');

if (!is_array($config['dnsmasq']['domainoverrides'])) {
       $config['dnsmasq']['domainoverrides'] = array();
}
$a_domainOverrides = &$config['dnsmasq']['domainoverrides'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_domainOverrides[$id]) {
       $pconfig['domain'] = $a_domainOverrides[$id]['domain'];
       if (is_ipaddr($a_domainOverrides[$id]['ip']) && ($a_domainOverrides[$id]['ip'] != '#')) {
              $pconfig['ip'] = $a_domainOverrides[$id]['ip'];
       }
       else {
             $dnsmasqpieces = explode('@', $a_domainOverrides[$id]['ip'], 2);
             $pconfig['ip'] = $dnsmasqpieces[0];
             $pconfig['dnssrcip'] = $dnsmasqpieces[1];
       }
       $pconfig['descr'] = $a_domainOverrides[$id]['descr'];
}

if ($_POST) {

       unset($input_errors);
       $pconfig = $_POST;

       /* input validation */
       $reqdfields = explode(" ", "domain ip");
       $reqdfieldsn = array(gettext("Domain"),gettext("IP address"));

       do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

       function String_Begins_With($needle, $haystack) {
           return (substr($haystack, 0, strlen($needle))==$needle);
       }

       if (String_Begins_With(_msdcs, $_POST['domain'])) {
               $subdomainstr = substr($_POST['domain'], 7);
               if ($subdomainstr && !is_domain($subdomainstr)) {
                  $input_errors[] = gettext("A valid domain must be specified after _msdcs.");
               }
       }
       elseif ($_POST['domain'] && !is_domain($_POST['domain'])) {
               $input_errors[] = gettext("A valid domain must be specified.");
       }
       if ($_POST['ip'] && !is_ipaddr($_POST['ip']) && ($_POST['ip'] != '#') && ($_POST['ip'] != '!')) {
              $input_errors[] = gettext("A valid IP address must be specified, or # for an exclusion or ! to not forward at all.");
       }
       if ($_POST['dnssrcip'] && !in_array($_POST['dnssrcip'], get_configured_ip_addresses())) {
              $input_errors[] = gettext("An interface IP address must be specified for the DNS query source.");
       }
       if (!$input_errors) {
			$doment = array();
			$doment['domain'] = $_POST['domain'];
                       if (empty($_POST['dnssrcip']))
                                $doment['ip'] = $_POST['ip'];
                       else
                               $doment['ip'] = $_POST['ip'] . "@" . $_POST['dnssrcip'];
			$doment['descr'] = $_POST['descr'];

			if (isset($id) && $a_domainOverrides[$id])
				$a_domainOverrides[$id] = $doment;
			else
				$a_domainOverrides[] = $doment;

			$retval = services_dnsmasq_configure();

			write_config();

			header("Location: services_dnsmasq.php");
			exit;
       }
}

$pgtitle = array(gettext("Services"),gettext("DNS forwarder"),gettext("Edit Domain Override"));
$shortcut_section = "resolver";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_dnsmasq_domainoverride_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="domain override">
                               <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Domain");?></td>
                  <td width="78%" class="vtable">
                    <?=$mandfldhtml;?><input name="domain" type="text" class="formfld unknown" id="domain" size="40" value="<?=htmlspecialchars($pconfig['domain']);?>" />
                    <br /> <span class="vexpl"><?=gettext("Domain to override (NOTE: this does not have to be a valid TLD!)"); ?><br />
                    <?=gettext("e.g."); ?> <em><?=gettext("test"); ?></em> <?=gettext("or"); ?> <em>mycompany.localdomain</em> <?=gettext("or"); ?> <em>1.168.192.in-addr.arpa</em> </span></td>
                </tr>
                               <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("IP address");?></td>
                  <td width="78%" class="vtable">
                    <?=$mandfldhtml;?><input name="ip" type="text" class="formfld unknown" id="ip" size="40" value="<?=htmlspecialchars($pconfig['ip']);?>" />
                    <br /> <span class="vexpl"><?=gettext("IP address of the authoritative DNS server for this domain"); ?><br />
                    <?=gettext("e.g."); ?> <em>192.168.100.100</em><br /><?=gettext("Or enter # for an exclusion to pass through this host/subdomain to standard nameservers instead of a previous override."); ?><br /><?=gettext("Or enter ! for lookups for this host/subdomain to NOT be forwarded anywhere."); ?></span></td>
                </tr>
                               <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Source IP");?></td>
                  <td width="78%" class="vtable">
                    <?=$mandfldhtml;?><input name="dnssrcip" type="text" class="formfld unknown" id="dnssrcip" size="40" value="<?=htmlspecialchars($pconfig['dnssrcip']);?>" />
                    <br /> <span class="vexpl"><?=gettext("Source IP address for queries to the DNS server for the override domain."); ?><br />
                    <?=gettext("Leave blank unless your DNS server is accessed through a VPN tunnel."); ?></span></td>
                </tr>
                               <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                    <br /> <span class="vexpl"><?=gettext("You may enter a description here".
                    " for your reference (not parsed).");?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
                    <input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
                    <?php if (isset($id) && $a_domainOverrides[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
