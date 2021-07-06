<?php
/*
 * firewall_nat_out.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

// Functions to support services_dnsmasq.php, services_dnsmasq_edit.php
// and services_dnsmaq_domainoverride_edit.php

require_once("config.gui.inc");
require_once("interfaces.inc");
require_once("services.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("util.inc");

// Retrieve the current DNS forwarder config, the list of hosts & DNS overrides and the
// possible interfaces
function getDNSMasqConfig($json = false) {
    global $config;

    $pconfig = array();

    $pconfig['enable'] = isset($config['dnsmasq']['enable']);
    $pconfig['regdhcp'] = isset($config['dnsmasq']['regdhcp']);
    $pconfig['regdhcpstatic'] = isset($config['dnsmasq']['regdhcpstatic']);
    $pconfig['dhcpfirst'] = isset($config['dnsmasq']['dhcpfirst']);
    $pconfig['strict_order'] = isset($config['dnsmasq']['strict_order']);
    $pconfig['domain_needed'] = isset($config['dnsmasq']['domain_needed']);
    $pconfig['no_private_reverse'] = isset($config['dnsmasq']['no_private_reverse']);
    $pconfig['port'] = $config['dnsmasq']['port'];
    $pconfig['custom_options'] = $config['dnsmasq']['custom_options'];
    $pconfig['strictbind'] = isset($config['dnsmasq']['strictbind']);

    if (!empty($config['dnsmasq']['interface'])) {
        $pconfig['interface'] = explode(",", $config['dnsmasq']['interface']);
    } else {
        $pconfig['interface'] = array();
    }
    
    init_config_arr(array('dnsmasq', 'hosts'));
    $a_hosts = &$config['dnsmasq']['hosts'];
    
    // Add a temporary index so we don't lose the order after sorting
    for ($idx=0; $idx<count($a_hosts); $idx++) {
        $a_hosts[$idx]['idx'] = $idx;
    }
    
    hosts_sort($a_hosts);
    
    init_config_arr(array('dnsmasq', 'domainoverrides'));
    $a_domainOverrides = &$config['dnsmasq']['domainoverrides'];
    
    // Add a temporary index so we don't lose the order after sorting
    for ($idx=0; $idx<count($a_domainOverrides); $idx++) {
        $a_domainOverrides[$idx]['idx'] = $idx;
    }
    
    domains_sort($a_domainOverrides);

    $rv = array();

    $rv['config'] = $pconfig;
    $rv['hosts'] = $a_hosts;
    $rv['domainoverrides'] = $a_domainOverrides;
    $rv['iflist'] = build_if_list($pconfig);

    return $json ? json_encode($rv) : $rv;
}

// Sort host entries for display in alphabetical order
function hostcmp($a, $b) {
	return strcasecmp($a['host'], $b['host']);
}

function hosts_sort(&$a_hosts) {
	if (!is_array($a_hosts)) {
		return;
	}

	uasort($a_hosts, "hostcmp");
}

// Sort domain entries for display in alphabetical order
function domaincmp($a, $b) {
	return strcasecmp($a['domain'], $b['domain']);
}

function domains_sort(&$a_domainOverrides) {
	if (!is_array($a_domainOverrides)) {
		return;
	}

	uasort($a_domainOverrides, "domaincmp");
}

// Delete a DNS host or override (depending on "type")
function deleteDNSMasqEntry($post, $json=false) {
    global $config;

    if ($post['type'] == 'host') {
        init_config_arr(array('dnsmasq', 'hosts'));
        $a_hosts = &$config['dnsmasq']['hosts'];

        if ($a_hosts[$post['id']]) {
            unset($a_hosts[$post['id']]);
            write_config("DNS Forwarder host override deleted"); 
        }
    } elseif ($post['type'] == 'doverride') {
        init_config_arr(array('dnsmasq', 'domainoverrides'));
        $a_domainOverrides = &$config['dnsmasq']['domainoverrides'];

        if ($a_domainOverrides[$post['id']]) {
            unset($a_domainOverrides[$post['id']]);
            write_config("DNS Forwarder domain override deleted");
        }
    }

    if (!$json) {
        mark_subsystem_dirty('hosts');
        header("Location: services_dnsmasq.php");
        exit;
    } else {
        applyDNSMasqConfig(true);
    }
}

function build_if_list($pconfig) {
	$interface_addresses = get_possible_listen_ips(true);
	$iflist = array('options' => array(), 'selected' => array());

	$iflist['options'][""]	= "All";
	if (empty($pconfig['interface']) || empty($pconfig['interface'][0])) {
		array_push($iflist['selected'], "");
	}

	foreach ($interface_addresses as $laddr => $ldescr) {
		$iflist['options'][$laddr] = htmlspecialchars($ldescr);

		if ($pconfig['interface'] && in_array($laddr, $pconfig['interface'])) {
			array_push($iflist['selected'], $laddr);
		}
	}

	unset($interface_addresses);

	return($iflist);
}

// Save the DNS forwarder configuration (only). Does not affect the host or override list.
function saveDNSMasqConfig($post, $json=false) {
    global $config;

    $pconfig = $post;
    $input_errors = array();

	$config['dnsmasq']['enable'] = ($post['enable']) ? true : false;
	$config['dnsmasq']['regdhcp'] = ($post['regdhcp']) ? true : false;
	$config['dnsmasq']['regdhcpstatic'] = ($post['regdhcpstatic']) ? true : false;
	$config['dnsmasq']['dhcpfirst'] = ($post['dhcpfirst']) ? true : false;
	$config['dnsmasq']['strict_order'] = ($post['strict_order']) ? true : false;
	$config['dnsmasq']['domain_needed'] = ($post['domain_needed']) ? true : false;
	$config['dnsmasq']['no_private_reverse'] = ($post['no_private_reverse']) ? true : false;
	$config['dnsmasq']['custom_options'] = str_replace("\r\n", "\n", $post['custom_options']);
	$config['dnsmasq']['strictbind'] = ($post['strictbind']) ? true : false;

	if (isset($post['enable']) && isset($config['unbound']['enable'])) {
		if ($post['port'] == $config['unbound']['port']) {
			$input_errors[] = gettext("The DNS Resolver is enabled using this port. Choose a non-conflicting port, or disable DNS Resolver.");
		}
	}

	if ((isset($post['regdhcp']) || isset($post['regdhcpstatic']) || isset($post['dhcpfirst'])) && !is_dhcp_server_enabled()) {
		$input_errors[] = gettext("DHCP Server must be enabled for DHCP Registration to work in DNS Forwarder.");
	}

	if ($post['port']) {
		if (is_port($post['port'])) {
			$config['dnsmasq']['port'] = $post['port'];
		} else {
			$input_errors[] = gettext("A valid port number must be specified.");
		}
	} else if (isset($config['dnsmasq']['port'])) {
		unset($config['dnsmasq']['port']);
	}

	if (is_array($post['interface'])) {
		$config['dnsmasq']['interface'] = implode(",", $post['interface']);
	} elseif (isset($config['dnsmasq']['interface'])) {
		unset($config['dnsmasq']['interface']);
	}

	if ($config['dnsmasq']['custom_options']) {
		$args = '';
		foreach (preg_split('/\s+/', $config['dnsmasq']['custom_options']) as $c) {
			$args .= escapeshellarg("--{$c}") . " ";
		}

		exec("/usr/local/sbin/dnsmasq --test $args", $output, $rc);

		if ($rc != 0) {
			$input_errors[] = gettext("Invalid custom options");
		}
	}

	if (!$input_errors) {
		write_config("DNS Forwarder settings saved");
		mark_subsystem_dirty('hosts');
	}

    

    if ($json) {
        applyDNSMasqConfig(true);
        return json_encode($input_errors);
    } else {
        $rv = array();
        $rv['input_errors'] = $input_errors;
        $rv['pconfig'] = $pconfig;
        return $rv;
    }
}

// Applies the changes. This is called automatically in JSON mode, manually
// if vie the web UI
function applyDNSMasqConfig($json=false) {
    $retval = 0;

	$retval |= services_dnsmasq_configure();

	// Reload filter (we might need to sync to CARP hosts)
	filter_configure();
	/* Update resolv.conf in case the interface bindings exclude localhost. */
	system_resolvconf_generate();
	/* Start or restart dhcpleases when it's necessary */
	system_dhcpleases_configure();

    if (!$json) {
        if ($retval == 0) {
            clear_subsystem_dirty('hosts');
        }

        return $retval;
    }
}

// DNSMasq host table update
function saveDNSMasqHost($post, $id, $json=false) {
    global $config;

	$input_errors = array();
	$pconfig = $post;

    init_config_arr(array('dnsmasq', 'hosts'));
    $a_hosts = &$config['dnsmasq']['hosts'];

    if (!$json) {
        /* input validation */
        $reqdfields = explode(" ", "domain ip");
        $reqdfieldsn = array(gettext("Domain"), gettext("IP address"));

        do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);
    }

	if ($post['host']) {
		if (!is_hostname($post['host'])) {
			$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'. It may not start or end with '-'.");
		} else {
			if (!is_unqualified_hostname($post['host'])) {
				$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
			}
		}
	}

	if (($post['domain'] && !is_domain($post['domain']))) {
		$input_errors[] = gettext("A valid domain must be specified.");
	}

	if (($post['ip'] && !is_ipaddr($post['ip']))) {
		$input_errors[] = gettext("A valid IP address must be specified.");
	}

	/* collect aliases */
	$aliases = array();

	if (!empty($post['aliashost0'])) {
		foreach ($post as $key => $value) {
			$entry = '';
			if (!substr_compare('aliashost', $key, 0, 9)) {
				$entry = substr($key, 9);
				$field = 'host';
			} elseif (!substr_compare('aliasdomain', $key, 0, 11)) {
				$entry = substr($key, 11);
				$field = 'domain';
			} elseif (!substr_compare('aliasdescription', $key, 0, 16)) {
				$entry = substr($key, 16);
				$field = 'description';
			}
			if (ctype_digit($entry)) {
				$aliases[$entry][$field] = $value;
			}
		}

		$pconfig['aliases']['item'] = $aliases;

		/* validate aliases */
		foreach ($aliases as $idx => $alias) {
			if (!$json) {
                $aliasreqdfields = array('aliasdomain' . $idx);
			    $aliasreqdfieldsn = array(gettext("Alias Domain"));

			    do_input_validation($post, $aliasreqdfields, $aliasreqdfieldsn, $input_errors);
            }

			if ($alias['host']) {
				if (!is_hostname($alias['host'])) {
					$input_errors[] = gettext("Hostnames in an alias list can only contain the characters A-Z, 0-9 and '-'. They may not start or end with '-'.");
				} else {
					if (!is_unqualified_hostname($alias['host'])) {
						$input_errors[] = gettext("A valid alias hostname is specified, but the domain name part should be omitted");
					}
				}
			}

			if (($alias['domain'] && !is_domain($alias['domain']))) {
				$input_errors[] = gettext("A valid domain must be specified in alias list.");
			}
		}
	}

	/* check for overlaps */
	foreach ($a_hosts as $hostent) {
		if (isset($id) && ($a_hosts[$id]) && ($a_hosts[$id] === $hostent)) {
			continue;
		}

		if (($hostent['host'] == $post['host']) &&
		    ($hostent['domain'] == $post['domain'])) {
			if (is_ipaddrv4($hostent['ip']) && is_ipaddrv4($post['ip'])) {
				$input_errors[] = gettext("This host/domain override combination already exists with an IPv4 address.");
				break;
			}
			if (is_ipaddrv6($hostent['ip']) && is_ipaddrv6($post['ip'])) {
				$input_errors[] = gettext("This host/domain override combination already exists with an IPv6 address.");
				break;
			}
		}
	}

	if (!$input_errors) {
		$hostent = array();
		$hostent['host'] = $post['host'];
		$hostent['domain'] = $post['domain'];
		$hostent['ip'] = $post['ip'];
		$hostent['descr'] = $post['descr'];
		$hostent['aliases']['item'] = $aliases;

		if (isset($id) && $a_hosts[$id] && ($id != -1)) {
			$a_hosts[$id] = $hostent;
		} else {
			$a_hosts[] = $hostent;
		}

        write_config("DNS Forwarder host override saved");

        if (!$json) {
            mark_subsystem_dirty('hosts');
            header("Location: services_dnsmasq.php");
            exit;
        }
	}

    $rv = array();
    $rv['input_errors'] = $input_errors;
    $rv['config'] = $pconfig;

    return $json ? json_encode($rv) : $rv;
}

function getDomainOverride($id, $json = false) {
    global $config;

    init_config_arr(array('dnsmasq', 'domainoverrides'));
    $a_domainOverrides = &$config['dnsmasq']['domainoverrides'];

    $pconfig = array();

    $pconfig['domain'] = $a_domainOverrides[$id]['domain'];
	if (is_ipaddr($a_domainOverrides[$id]['ip']) && ($a_domainOverrides[$id]['ip'] != '#')) {
		$pconfig['ip'] = $a_domainOverrides[$id]['ip'];
	} else {
		$dnsmasqpieces = explode('@', $a_domainOverrides[$id]['ip'], 2);
		$pconfig['ip'] = $dnsmasqpieces[0];
		$pconfig['dnssrcip'] = $dnsmasqpieces[1];
	}

	$pconfig['descr'] = $a_domainOverrides[$id]['descr'];

    return $pconfig;
}

// Domin override table update
function saveDomainOverride($post, $id, $json=false) {
    global $config;

    init_config_arr(array('dnsmasq', 'domainoverrides'));
    $a_domainOverrides = &$config['dnsmasq']['domainoverrides'];

    $input_errors = array();
    $pconfig = $post;

    if (!$JSON) {
        /* input validation */
        $reqdfields = explode(" ", "domain ip");
        $reqdfieldsn = array(gettext("Domain"), gettext("IP address"));

        do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);
    }

    function String_Begins_With($needle, $haystack) {
        return (substr($haystack, 0, strlen($needle)) == $needle);
    }

    if (String_Begins_With('_msdcs', $post['domain'])) {
        $subdomainstr = substr($post['domain'], 7);

        if ($subdomainstr && !is_domain($subdomainstr)) {
            $input_errors[] = gettext("A valid domain must be specified after _msdcs.");
        }
    } elseif ($post['domain'] && !is_domain($post['domain'])) {
        $input_errors[] = gettext("A valid domain must be specified.");
    }

    if ($post['ip'] && !is_ipaddr($post['ip']) && ($post['ip'] != '#') && ($post['ip'] != '!')) {
        $input_errors[] = gettext("A valid IP address must be specified, or # for an exclusion or ! to not forward at all.");
    }

    if ($post['dnssrcip'] && !in_array($post['dnssrcip'], get_configured_ip_addresses())) {
        $input_errors[] = gettext("An interface IP address must be specified for the DNS query source.");
    }

    if (!$input_errors) {
        $doment = array();
        $doment['domain'] = $post['domain'];

        if (empty($post['dnssrcip'])) {
            $doment['ip'] = $post['ip'];
        } else {
            $doment['ip'] = $post['ip'] . "@" . $post['dnssrcip'];
        }

        $doment['descr'] = $post['descr'];

        if (isset($id) && $a_domainOverrides[$id] && ($id != -1)) {
            $a_domainOverrides[$id] = $doment;
        } else {
            $a_domainOverrides[] = $doment;
        }

        $retval = services_dnsmasq_configure();

        write_config("DNS Forwarder domain override saved");

        if (!$json) {
            header("Location: services_dnsmasq.php");
            exit;
        } 
	}

    $rv = array();
    $rv['config'] = $pconfig;
    $rv['retval'] = $retval;
    $rv['input_errors'] = $input_errors;

    return $json ? json_encode($rv) : $rv;
}
?>