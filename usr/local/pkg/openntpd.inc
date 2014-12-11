<?php
require_once('config.inc');
require_once('globals.inc');
require_once('service-utils.inc');

function openntpd_install() {
	global $g;

	$config_file = $g['varetc_path'] . '/openntpd.conf';
	$rcfile = array();
	$rcfile['file'] = 'openntpd.sh';
	$rcfile['start'] = <<<EOD
if [ -f $config_file ]; then
	ntpd -f $config_file
fi

EOD;
	$rcfile['stop'] = "killall ntpd";
	write_rcfile($rcfile);
}

function openntpd_get_iface_ip($iface) {
	$iface = convert_friendly_interface_to_real_interface_name($iface);
	$line = trim(shell_exec("ifconfig " . escapeshellarg($iface) . " | grep inet | grep -v inet6"));
	list($dummy, $ip, $dummy2, $dummy3) = explode(' ', $line);

	return $ip;
}

function openntpd_resync() {
	global $g, $config;

	$settings = $config['installedpackages']['openntpd']['config'][0];
	$config_file = $g['varetc_path'] . '/openntpd.conf';

	if ($settings['enable'] == 'on') {
		$conf = '';

		$ifaces = explode(',', $settings['interface']);
		$ips = array_map('openntpd_get_iface_ip', $ifaces);
		foreach ($ips as $ip)
			$conf .= "listen on $ip\n";

		$servers = explode(',', $config['system']['timeservers']);
		foreach ($servers as $server)
			$conf .= "servers $server\n";

		file_put_contents($config_file, $conf);
	}
	else {
		if (file_exists($config_file))
			unlink($config_file);
	}
	restart_service('openntpd');
}
