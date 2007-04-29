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
echo "    print_r($config);\n";
echo "    \$config['interfaces']['lan']['ipaddr'] = \"192.168.1.1\";\n";
echo "    write_config();\n";

while($shell_active == true) {
        echo "\n\npfSense shell> ";
        $command = chop(fgets($fp));
        if($command == "exit") {
                $shell_active = false;
                echo "\n";
                break;
		}
        eval($command); 
}
