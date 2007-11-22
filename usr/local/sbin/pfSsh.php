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

$shell_cmds = array("alias", "alloc", "bg", "bind", "bindkey", "break", 
     "breaksw", "builtins", "case", "cd", "chdir", "command", "complete", "continue", "default",
     "dirs", "do", "done", "echo", "echotc", "elif", "else", "end", "endif", "endsw", "esac", "eval",
     "exec", "exit", "export", "false", "fc", "fg", "filetest", "fi", "for", "foreach", "getopts",
     "glob", "goto", "hash", "hashstat", "history", "hup", "if", "jobid", "jobs", "kill", "limit",
     "local", "log", "login", "logout", "ls-F", "nice", "nohup", "notify", "onintr", "popd",
     "printenv", "pushd", "pwd", "read", "readonly", "rehash", "repeat", "return", "sched", "set",
     "setenv", "settc", "setty", "setvar", "shift", "source", "stop", "suspend", "switch",
     "telltc", "test", "then", "time", "trap", "true", "type", "ulimit", "umask", "unalias",
     "uncomplete", "unhash", "unlimit", "unset", "unsetenv", "until", "wait", "where", "which",
     "while");

function pipe_cmd($command, $text_to_pipe) {
	$descriptorspec = array(
	    0 => array("pipe", "r"),  // stdin
	    1 => array("pipe", "w"),  // stdout
	    2 => array("pipe", "w")); // stderr ?? instead of a file
	
	$fd = proc_open("$command", $descriptorspec, $pipes);
	if (is_resource($fd)) {
	        fwrite($pipes[0], "{$text_to_pipe}");
	        fclose($pipes[0]);
	        while($s= fgets($pipes[1], 1024)) {
	          // read from the pipe
	          $buffer .= $s;
	        }
	        fclose($pipes[1]);
	        fclose($pipes[2]);
	}
	return $buffer;
}

if(!function_exists("readline")) {
	function readline() {
		$fp = fopen('php://stdin', 'r');
		$textinput = chop(fgets($fp));
		fclose($fp);
	}
	return $textinput;
}

function more($text, $count=24) {
        $counter=0;
        $lines = split("\n", $text);
        foreach($lines as $line) {
                if($counter > $count) {
                        echo "Press RETURN to continue ...";
                        $fp = fopen('php://stdin', 'r');
                        $pressreturn = chop(fgets($fp));
                        if($pressreturn == "q" || $pressreturn == "quit") 
                        	return; 
                        fclose($fp);
                        $counter = 0;
                }
                echo "{$line}\n";
                $counter++;
        }
}

function show_help() {
	echo "\nExample commands:\n";
	
	echo "\nparse_config(true);  # reloads the \$config array\n";

	echo "\n\$temp = print_r(\$config, true);\n";
	echo "more(\$temp);\n";

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
	echo "system_reboot_sync();\n";
}

$fp = fopen('php://stdin', 'r');

echo ".\n\n";

$pkg_interface='console';

$shell_active = true;

echo "Welcome to the pfSense php shell system\n";
echo "Written by Scott Ullrich (sullrich@gmail.com)\n";
echo "\nType \"help\" to show common usage scenarios.\n";

$recording = false;
$dontunsetplaybacksplit = false;

while($shell_active == true) {
		if(!$playback_file_contents) { 
        	$command = readline("\npfSense shell: ");
			readline_add_history($command);
	        $command_split = split(" ", $command);
	        $first_command = $command_split[0];        	
        }
		if($command_split[0] == "playback" || $command_split[0] == "run") {
			$playback_file = $command_split[1];
			if(!$playback_file || !file_exists("/etc/phpshellsessions/{$playback_file}")) {
				$command = "";
				echo "Could not locate playback file.\n";
			} else {
				$playback_file_contents = file_get_contents("/etc/phpshellsessions/{$playback_file}");
				$playback_file_split = split("\n", $playback_file_contents);
				$playbackinprogress = true;
				$dontunsetplaybacksplit = true;
				$command = "";
				echo "\nPlayback of file {$command_split[1]} started.\n";      			
			}
		}	        
		if($command) 
			$playback_file_split = array($command);
   		foreach($playback_file_split as $pfc) {
    		$command = $pfc;
	        if($command == "exit") {
	                $shell_active = false;
	                echo "\n";
	                break;
			}		
			readline_add_history($command);
	        $command_split = split(" ", $command);
	        $first_command = $command_split[0];
	        switch($first_command) {
	        	case "=":
	        		$newcmd = "";
	        		$counter = 0;
	        		foreach($command_split as $cs) {
	        			if($counter > 0)
	        				$newcmd .= " {$cs}";
	        			$counter++;
	        		}
	        		if($playbackinprogress)
	        			echo "\npfSense shell: {$command}\n";
					if($recording) 
						fwrite($recording_fd, $command . "\n");
	        		system("$newcmd");
	        		if($command_split[1] == "cd") {
	        			echo "\nChanging working directory to {$command_split[2]}.\n";
	        			chdir($command_split[2]);
	        		}
	        		$command = "";
					break;
	        	case "!":
	        		system("$newcmd");
	        		$command = "";
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
			        $command = readline("Command: ");
			        readline_add_history($command);
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
    		if($command_split[0] == "stoprecording" || $command_split[0] == "stoprecord" || $command_split[0] == "stop") {
    			if($recording) {
    				fclose($recording_fd);
    				$command = "";
    				conf_mount_ro();
    				echo "\nRecording stopped.\n";
    				$recording = false; 
    			} else {
    				echo "\nNo recording session in progress.\n";
    				$command = "";
    			}
    		}
    		if($command_split[0] == "showrecordings") {
    			conf_mount_rw();
    			safe_mkdir("/etc/phpshellsessions");
    			if($recording) 
    				conf_mount_ro();
    			echo "\n==> Sessions available for playback are:\n\n";
    			system("cd /etc/phpshellsessions && ls /etc/phpshellsessions");
    			echo "\n==> end of list.\n";
    			$command = "";
    		}
    		if($command_split[0] == "record") {
    			if(!$command_split[1]) {
    				echo "usage: record playbackname\n";
					$command = "";
    			} else {
    				/* time to record */
    				conf_mount_rw();
					safe_mkdir("/etc/phpshellsessions");
    				$recording_fd = fopen("/etc/phpshellsessions/{$command_split[1]}","w");
    				if(!$recording_fd) {
    					echo "Could not start recording session.\n";
    					$command = "";
    				} else { 
    					$recording = true;
    					echo "\nRecording of {$command_split[1]} started.\n";
    					$command = "";
    				}
    			}
    		}
			if($command) {
		        eval($command);
		        if($playbackinprogress) 
		        	echo "\npfSense shell: {$command}\n";
		        if($recording) 
		        	fwrite($recording_fd, $command . "\n"); 
		    }
		}
		unset($playback_file_contents);
		unset($playback);
}

