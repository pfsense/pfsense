<?php
/*
    services_unbound_domainoverride_edit.php
    part of the pfSense project (https://www.pfsense.org)
    Copyright (C) 2014 Warren Baker (warren@decoy.co.za)
    All rights reserved.
	
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
	pfSense_MODULE:	dnsresolver
*/

##|+PRIV
##|*IDENT=page-services-dnsresolver-editdomainoverride
##|*NAME=Services: DNS Resolver: Edit Domain Override page
##|*DESCR=Allow access to the 'Services: DNS Resolver: Edit Domain Override' page.
##|*MATCH=services_unbound_domainoverride_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['unbound']['domainoverrides']))
       $config['unbound']['domainoverrides'] = array();

$a_domainOverrides = &$config['unbound']['domainoverrides'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_domainOverrides[$id]) {
    $pconfig['domain'] = $a_domainOverrides[$id]['domain'];
    $pconfig['ip'] = $a_domainOverrides[$id]['ip'];
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
        if ($subdomainstr && !is_domain($subdomainstr))
            $input_errors[] = gettext("A valid domain must be specified after _msdcs.");
    } elseif ($_POST['domain'] && !is_domain($_POST['domain']))
        $input_errors[] = gettext("A valid domain must be specified.");

    if ($_POST['ip']) {
        if (strpos($_POST['ip'],'@') !== false) {
            $ip_details = explode("@", $_POST['ip']);
            if (!is_ipaddr($ip_details[0]) && !is_port($ip_details[1]))
                $input_errors[] = gettext("A valid IP address and port must be specified, for example 192.168.100.10@5353.");
        } else if (!is_ipaddr($_POST['ip']))
            $input_errors[] = gettext("A valid IP address must be specified, for example 192.168.100.10.");
    }

    if (!$input_errors) {
        $doment = array();
        $doment['domain'] = $_POST['domain'];
        $doment['ip'] = $_POST['ip'];
        $doment['descr'] = $_POST['descr'];

        if (isset($id) && $a_domainOverrides[$id])
            $a_domainOverrides[$id] = $doment;
        else
            $a_domainOverrides[] = $doment;

        mark_subsystem_dirty('unbound');

        write_config();

        header("Location: services_unbound.php");
        exit;
    }
}

$pgtitle = array(gettext("Services"),gettext("DNS Resolver"),gettext("Edit Domain Override"));
$shortcut_section = "resolver";
include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
    <form action="services_unbound_domainoverride_edit.php" method="post" name="iform" id="iform">
        <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="domain override">
            <tr>
                <td width="22%" valign="top" class="vncellreq"><?=gettext("Domain");?></td>
                <td width="78%" class="vtable">
                    <?=$mandfldhtml;?><input name="domain" type="text" class="formfld unknown" id="domain" size="40" value="<?=htmlspecialchars($pconfig['domain']);?>" /><br />
                    <span class="vexpl">
                        <?=gettext("Domain to override (NOTE: this does not have to be a valid TLD!)"); ?><br />
                        <?=gettext("e.g."); ?> <em><?=gettext("test"); ?></em> <?=gettext("or"); ?> <em>mycompany.localdomain</em> <?=gettext("or"); ?> <em>1.168.192.in-addr.arpa</em>
                    </span>
                </td>
            </tr>
            <tr>
                <td width="22%" valign="top" class="vncellreq"><?=gettext("IP address");?></td>
                <td width="78%" class="vtable">
                    <?=$mandfldhtml;?><input name="ip" type="text" class="formfld unknown" id="ip" size="40" value="<?=htmlspecialchars($pconfig['ip']);?>" /><br />
                    <span class="vexpl">
                    <?=gettext("IP address of the authoritative DNS server for this domain"); ?><br />
                    <?=gettext("e.g."); ?> <em>192.168.100.100</em><br />
                    <?=gettext("To use a nondefault port for communication, append an '@' with the port number."); ?><br />
                    </span>
                </td>
            </tr>
            <tr>
                <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" /><br />
                    <span class="vexpl">
                        <?=gettext("You may enter a description here for your reference (not parsed).");?>
                    </span>
                </td>
            </tr>
            <tr>
                <td width="22%" valign="top">&nbsp;</td>
                <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />  <input class="formbtn" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()" />
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
