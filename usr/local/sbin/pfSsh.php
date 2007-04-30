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

$fp = fopen('php://stdin', 'r');

echo ".\n\n";

$shell_active = true;

echo "Example commands:\n\n";
echo "    print_r(\$config);\n";
echo "    \$config['interfaces']['lan']['ipaddr'] = \"192.168.1.1\";\n";
echo "    write_config();\n";
echo "    multiline\n";
echo "    exit";

while($shell_active == true) {
        echo "\n\npfSense shell> ";
        $command = chop(fgets($fp));
        if($command == "exit") {
                $shell_active = false;
                echo "\n";
                break;
		}
		if($command == "multiline" or $command == "ml") {
			echo "\nmultiline mode enabled.  enter EOF on a blank line to execute.\n\n";
			$command = "";
			$mlcommand = "";
			$xxxyzyz = 0;
			while($command <> "EOF") {
				echo "pfSense multiline shell[$xxxyzyz]> ";
		        $command = chop(fgets($fp));
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
