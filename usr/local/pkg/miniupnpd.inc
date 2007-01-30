<?php
	require_once("config.inc");
	require_once("functions.inc");

	/* MiniUPnPd */

	define('UPNP_RCFILE', '/usr/local/etc/rc.d/miniupnpd.sh');
	define('UPNP_CONFIG','/usr/local/etc/miniupnpd.conf');

	function upnp_notice ($msg) { syslog(LOG_NOTICE, "miniupnpd: {$msg}"); return; }
	function upnp_warn ($msg) { syslog(LOG_WARNING, "miniupnpd: {$msg}"); return; }

	function upnp_action ($action) {
		if (file_exists(UPNP_RCFILE))
			mwexec(UPNP_RCFILE.' '.$action);
	}

	function upnp_running () {
		if((int)exec("pgrep miniupnpd | wc -l") > 0)
			return true;
		else
			return false;
	}	

	function upnp_config ($name) {
		global $config;
		if($config['installedpackages']['miniupnpd']['config'][0]["{$name}"])
			return $config['installedpackages']['miniupnpd']['config'][0]["{$name}"];
		else
			return NULL;
	}

	function upnp_write_config($conf_file, $conf_text) {
		$conf = fopen($conf_file, "w");
		if(!$conf) {
			upnp_warn("Could not open {$conf_file} for writing.");
			exit;
		}
		fwrite($conf, $conf_text);
		fclose($conf);	
	}		

	function upnp_validate_ip($ip,$check_cdir) {
		/* validate cdir */	
		if($check_cdir)	{
			$ip_array = explode("/",$ip);
			if(count($ip_array) == 2) {
				if($ip_array[1] < 1 || $ip_array[1] > 32)
					return false;
			} else
				if(count($ip_array) != 1)
					return false;
		} else
			$ip_array[] = $ip;

		/* validate ip */
		if(!eregi("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $ip_array[0]))
			return false;
		foreach(explode(".", $ip_array[0]) as $sub)
			if($sub < 0 || $sub > 256)
				return false;
		return true;
	}

	function upnp_validate_port($port) {
		foreach(explode("-", $port) as $sub)
			if($sub < 0 || $sub > 65535)
				return false;
		return true;	
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
		if($post['overridewanip'] && !upnp_validate_ip($post['overridewanip'],false))
			$input_errors[] = 'You must specify a valid ip address in the \'Override WAN address\' field';
		if(($post['download'] && !$post['upload']) || ($post['upload'] && !$post['download']))
			$input_errors[] = 'You must fill in both \'Maximum Download Speed\' and \'Maximum Upload Speed\' fields';
		if($post['download'] && $post['download'] <= 0)
			$input_errors[] = 'You must specify a value greater than 0 in the \'Maximum Download Speed\' field';
		if($post['upload'] && $post['upload'] <= 0)
			$input_errors[] = 'You must specify a value greater than 0 in the \'Maximum Upload Speed\' field';

		/* user permissions validation */
		for($i=1; $i<=4; $i++) {
			if($post["permuser{$i}"]) {
				$perm = explode(' ',$post["permuser{$i}"]);
				/* should explode to 4 args */
				if(count($perm) != 4) {
					$input_errors[] = "You must follow the specified format in the 'User specified permissions {$i}' field";
				} else {
					/* must with allow or deny */
					if(!($perm[0] == 'allow' || $perm[0] == 'deny'))
						$input_errors[] = "You must begin with allow or deny in the 'User specified permissions {$i}' field";
					/* verify port or port range */
					if(!upnp_validate_port($perm[1]) || !upnp_validate_port($perm[3]))
						$input_errors[] = "You must specify a port or port range between 0 and 65535 in the 'User specified
							permissions {$i}' field";
					/* verify ip address */
					if(!upnp_validate_ip($perm[2],true))
						$input_errors[] = "You must specify a valid ip address in the 'User specified permissions {$i}' field";
				}
			}
		}		
	}

	function sync_package_miniupnpd() {
		global $config;
		global $input_errors;

		config_lock();		

		$configtext = "ext_ifname=".get_real_wan_interface()."\n";
		$configtext .= "port=2189\n";

		/* since config is written before this file invoked we don't need to read post data */
		if(upnp_config('enable') && upnp_config('iface_array'))
			$iface_array = explode(',',upnp_config('iface_array'));

		if($iface_array) {
			foreach($iface_array as $iface) {
				$if = convert_friendly_interface_to_real_interface_name($iface);
				/* above function returns iface if fail */
				if($if!=$iface) {
					$addr = find_interface_ip($if);
					/* non enabled interfaces are displayed in list on miniupnpd settings page */
					/* check that the interface has an ip address before adding parameters */
					if($addr) {
						$configtext .= "listening_ip={$addr}\n";
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

			if($ifaces_active) {
				/* override wan ip address, common for carp, etc */
				if(upnp_config('overridewanip'))
					$configtext .= "ext_ip=".upnp_config('overridewanip')."\n";

				/* if shaper connection speed defined use those values */
				if($config['ezshaper']['step2']['download'] && $config['ezshaper']['step2']['upload']) {
					$download = $config['ezshaper']['step2']['download']*1000;
					$upload = $config['ezshaper']['step2']['upload']*1000;
				} else {
					$download = upnp_config('download')*1000;
					$upload = upnp_config('upload')*1000;
				}

				/* set upload and download bitrates */
				if($download && $upload) {
					$configtext .= "bitrate_down={$download}\n";
					$configtext .= "bitrate_up={$upload}\n";
				}
				
				/* enable logging of packets handled by miniupnpd rules */
				if(upnp_config('logpackets'))
					$configtext .= "packet_log=yes\n";
				
				/* enable system uptime instead of miniupnpd uptime */
				if(upnp_config('sysuptime'))
					$configtext .= "system_uptime=yes\n";

				/* upnp access restrictions */
				for($i=1; $i<=4; $i++) {
					if(upnp_config("permuser{$i}"))
						$configtext .= upnp_config("permuser{$i}")."\n";
				}

				if(upnp_config('permdefault'))
					$configtext .= "deny 0-65535 0.0.0.0/0 0-65535\n";

				/* generate rc file start and stop */
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
				$start = $stop."\n\t/usr/local/sbin/miniupnpd -f ".UPNP_CONFIG;

				/* write out the configuration */
				conf_mount_rw();
				upnp_write_config(UPNP_CONFIG,$configtext);
				write_rcfile(array(
					    'file' => 'miniupnpd.sh',
					    'start' => $start,
					    'stop' => $stop
				    )
				);
				conf_mount_ro();

				/* if miniupnpd not running start it */
				if(!upnp_running()) {
					upnp_notice("Starting service on interface: {$ifaces_active}");
					upnp_action('start');	
				}
				/* or restart miniupnpd if settings were changed */
				elseif($_POST['iface_array']) {
					upnp_notice("Restarting service on interface: {$ifaces_active}");
					upnp_action('restart');
				}
			}
		}

		if(!$iface_array || !$ifaces_active) {
			/* no parameters user does not want miniupnpd running */
			/* lets stop the service and remove the rc file */

			if(file_exists(UPNP_RCFILE)) {
				if(!upnp_config('enable'))
					upnp_notice('Stopping service: miniupnpd disabled');
				else
					upnp_notice('Stopping service: no interfaces selected');
					
				upnp_action('stop');

				conf_mount_rw();
				unlink(UPNP_RCFILE);
				unlink(UPNP_CONFIG);
				conf_mount_ro();
			}
		}

		config_unlock();
	}
?>
