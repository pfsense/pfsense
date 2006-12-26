<?php
	require_once("config.inc");
	require_once("functions.inc");

	/* MiniUPnPd */

	define('UPNP_RCFILE', '/usr/local/etc/rc.d/miniupnpd.sh');

	function upnp_notice ($msg) { syslog(LOG_NOTICE, "miniupnpd: {$msg}"); return; }
	function upnp_warn ($msg) { syslog(LOG_WARNING, "miniupnpd: {$msg}"); return; }

	function upnp_action ($action) {
		if (file_exists(UPNP_RCFILE))
			mwexec(UPNP_RCFILE.' '.$action);
	}

	function upnp_config ($name) {
		global $config;
		if($config['installedpackages']['miniupnpd']['config'][0]["{$name}"])
			return $config['installedpackages']['miniupnpd']['config'][0]["{$name}"];
		else
			return NULL;
	}

	function upnp_validate_ip($ip) {
		if(!eregi("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $ip))
			return FALSE;
		foreach(explode(".", $ip) as $sub)
			if($sub<0 || $sub>256)
				return FALSE;
		return TRUE;
	}

	function before_form_miniupnpd($pkg) {
		global $config;

		config_lock();		
		
		/* if shaper connection speed defined hide fields */
		if($config['ezshaper']['step2']['download'] && $config['ezshaper']['step2']['upload']) {
			$i=0;
			foreach ($pkg['fields']['field'] as $field) {
				if ($field['fieldname'] == 'download' || $field['fieldname'] == 'upload')
					unset($pkg['fields']['field'][$i]);
				$i++;
			}
		}

		config_unlock();
	}

	function validate_form_miniupnpd($post, $input_errors) {
		if($post['iface_array'])
			foreach($post['iface_array'] as $iface)
				if($iface == "wan")
					$input_errors[] = 'It is a security risk to specify WAN in the \'Interface\' field';
		if($post['overridewanip'] && !upnp_validate_ip($post['overridewanip']))
			$input_errors[] = 'You must specify a valid ip address in the \'Override WAN address\' field';
		if(($post['download'] && !$post['upload']) || ($post['upload'] && !$post['download']))
			$input_errors[] = 'You must fill in both \'Maximum Download Speed\' and \'Maximum Upload Speed\' fields';
		if($post['download'] && $post['download']<=0)
			$input_errors[] = 'You must specify a value greater than 0 in the \'Maximum Download Speed\' field';
		if($post['upload'] && $post['upload']<=0)
			$input_errors[] = 'You must specify a value greater than 0 in the \'Maximum Upload Speed\' field';
	}

	function sync_package_miniupnpd() {
		global $config;
		global $input_errors;
		$ifaces_final = "";
		$wanif = get_real_wan_interface();

		config_lock();

		/* since config is written before this file invoked we don't need to read post data */
		if(upnp_config("enable") && upnp_config("iface_array"))
			$iface_array = explode(",",upnp_config("iface_array"));

		if($iface_array) {
			foreach($iface_array as $iface) {
				$if = convert_friendly_interface_to_real_interface_name($iface);
				/* above function returns iface if fail */
				if($if!=$iface) {
					$addr = find_interface_ip($if);
					/* non enabled interfaces are displayed in list on miniupnpd settings page */
					/* check that the interface has an ip address before adding parameters */
					if($addr) {
			    		$ifaces_final .= " -a {$addr}";
						if(!$ifaces_active)
							$ifaces_active = $iface;
						else
							$ifaces_active .= ", {$iface}";
					} else {
						upnp_warn("Interface {$iface} has no ip address, ignoring");
					}
				} else {
					upnp_warn("Could not resolve real interface for {$iface}");
				}
			}

			if($ifaces_final) {
				$overridewanip = upnp_config("overridewanip");
				$logpackets = upnp_config("logpackets");
				$sysuptime = upnp_config("sysuptime");

				/* if shaper connection speed defined use those values */
				if($config['ezshaper']['step2']['download'] && $config['ezshaper']['step2']['upload']) {
					$download = $config['ezshaper']['step2']['download']*1000;
					$upload = $config['ezshaper']['step2']['upload']*1000;
				} else {
					$download = upnp_config("download")*1000;
					$upload = upnp_config("upload")*1000;
				}

				/* valid paramters lets create rc file and start miniupnpd */

				$stop = <<<EOD
if [ `pgrep miniupnpd | wc -l` != 0  ]; then
		/usr/bin/killall miniupnpd
		while [ `pgrep miniupnpd | wc -l` != 0 ]; do
			sleep 1
		done
	fi
	# Clear existing rules and rdr entries
	if [ `pfctl -aminiupnpd -sr | wc -l` != 0  ]; then
		/sbin/pfctl -aminiupnpd -Fr 2>&1 >/dev/null
	fi
	if [ `pfctl -aminiupnpd -sn | wc -l` != 0  ]; then
		/sbin/pfctl -aminiupnpd -Fn 2>&1 >/dev/null
	fi
EOD;

				$start = $stop."\n\t/usr/local/sbin/miniupnpd -p 2869 -i {$wanif}{$ifaces_final}";

				/* define maximum downstream and upstream bitrates */
				if($download && $upload)
					$start .= " -B {$download} {$upload}";

				/* override wan ip address, common for carp, etc */
				if($overridewanip)
					$start .= " -o {$overridewanip}";

				/* enable logging of packets handled by miniupnpd rules */
				if($logpackets)
					$start .= " -L";

				/* enable system uptime instead of miniupnpd uptime */
				if($sysuptime)
					$start .= " -U";

				conf_mount_rw();
					
				write_rcfile(array(
					    "file" => "miniupnpd.sh",
					    "start" => $start,
					    "stop" => $stop
				    )
				);

				conf_mount_ro();

				/* if miniupnpd not running start it */
				if((int)exec("pgrep miniupnpd | wc -l") != 1) {
					upnp_notice("Starting service on interface: {$ifaces_active}");
					upnp_action("start");	
				}
				/* or restart miniupnpd if settings were changed */
				elseif($_POST['iface_array']) {
					upnp_notice("Restarting service on interface: {$ifaces_active}");
					upnp_action("restart");
				}
			}
		}

		if(!$iface_array || !$ifaces_final) {
			/* no parameters user does not want miniupnpd running */
			/* lets stop the service and remove the rc file */

			if(file_exists(UPNP_RCFILE)) {
				if(!upnp_config("enable"))
					upnp_notice("Stopping service, miniupnpd disabled");
				else
					upnp_notice("Stopping service, no interfaces selected");
					
				upnp_action("stop");

				conf_mount_rw();
				unlink(UPNP_RCFILE);
				conf_mount_ro();
			}
		}

		config_unlock();
	}
?>
