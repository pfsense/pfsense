#!/usr/local/bin/php -f
 
<?php

echo "Starting the pfSense shell system";

echo ".";
require("globals.inc");
$g['booting'] = true;
require("functions.inc");
echo ".";
require("config.inc");
echo ".";
$g['booting'] = false;

function show_help() {
	echo "\nExample commands:\n";
	
	echo "\n/* to output a configuration array */\n";
	echo "print_r(\$config);\n";
	
	echo "\n/* to output the interfaces configuration portion of the configuration */\n";
	echo "print_r(\$config['interfaces']);\n";
	
	echo "\n/* to output the dhcp server configuration */\n";
	echo "print_r(\$config['dhcpd']);\n";
	
	echo "\n/* to enable multiline input mode */\n";
	echo "multiline\n";
	
	echo "\n/* to exit the php pfSense shell */\n";
	echo "exit\n";
	
	echo "\n/* to output supported wireless modes for an interface */\n";
	echo "print_r(get_wireless_modes(\"ath0\"));\n";
	
	echo "\n/* to enable SSH */\n";
	echo "\$config['system']['enablesshd'] = true;\n";
	
	echo "\n/* change OPTX to the OPT interface name such as BACKHAUL */\n";
	echo "\$config['interfaces']['optx']['wireless']['standard'] = \"11a\";\n";
	echo "\$config['interfaces']['optx']['wireless']['mode'] = \"hostap\";\n";
	echo "\$config['interfaces']['optx']['wireless']['channel'] = \"6\";\n";
	
	echo "\n/* to enable dhcp server for an optx interface */\n";
	echo "\$config['dhcpd']['optx']['enable'] = true;\n";
	echo "\$config['dhcpd']['optx']['range']['from'] = \"192.168.31.100\";\n";
	echo "\$config['dhcpd']['optx']['range']['to'] = \"192.168.31.150\";\n";
	
	echo "\n/* to disable the firewall filter */\n";
	echo "\$config['system']['disablefilter'] = true;\n";
	
	echo "\n/* to enable an interface and set it for dhcp */\n";
	echo "\$config['interfaces']['optx']['disabled'] = false;\n";
	echo "\$config['interfaces']['optx']['ipaddr'] = \"dhcp\";\n";
	
	echo "\n/* to enable an interface and set a static ip address */\n";
	echo "\$config['interfaces']['wan']['disabled'] = false;\n";
	echo "\$config['interfaces']['wan']['ipaddr'] = \"192.168.100.1\";\n";
	echo "\$config['interfaces']['wan']['subnet'] = \"24\";\n";
	
	echo "\n/* to save out the new configuration (config.xml) */\n";
	echo "write_config();\n";
	
	echo "\n/* to reboot the system after saving */\n";
	echo "system_reboot_sync();";
}

$fp = fopen('php://stdin', 'r');

echo ".\n\n";

$pkg_interface='console';

$shell_active = true;

echo "Type \"help\" to show common usage scnenarios.";

while($shell_active == true) {
        echo "\n\npfSense shell> ";
        $command = chop(fgets($fp));
        if($command == "exit") {
                $shell_active = false;
                echo "\n";
                break;
		}
	    if($command == "help") {
	    	show_help();
	    	$command = "";
	    }
		if($command == "multiline" or $command == "ml") {
			echo "\nmultiline mode enabled.  enter EOF on a blank line to execute.\n\n";
			$command = "";
			$mlcommand = "";
			$xxxyzyz = 0;
			while($command <> "EOF") {
				echo "pfSense multiline shell[$xxxyzyz]> ";
		        $command = chop(fgets($fp));
		        if($command == "help") 
		        	show_help();
		        if($command == "exit") 
		        	die;
		        if($command <> "EOF") 
		        	$mlcommand .= $command;
		        $xxxyzyz++;
			}
			$command = $mlcommand;
		}
		if($command) {
			echo "\n";
	        eval($command); 
	    }
}

