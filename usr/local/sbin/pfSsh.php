#!/usr/local/bin/php -f
 
<?php

require_once("globals.inc");
echo "Starting the {$g['product_name']} shell system";
$g['booting'] = true;
require_once("functions.inc");
echo ".";
require_once("config.inc");
echo ".";
require_once("util.inc");
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
        $lines = explode("\n", $text);
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

$show_help_text = <<<EOF

	Enter a series of commands and then execute the set with "exec".
	
	For example:
	echo "foo"; // php command
	echo "foo2"; // php command
	! echo "heh" # shell command
	exec

	Example commands:

	startrecording <recordingfilename>
	stoprecording <recordingfilename>
	showrecordings

	parse_config(true);  # reloads the \$config array

	\$temp = print_r(\$config, true);
	more(\$temp);

	/* to output a configuration array */
	print_r(\$config);
	
	/* to output the interfaces configuration portion of config.xml */
	print_r(\$config['interfaces']);
	
	/* to output the dhcp server configuration */
	print_r(\$config['dhcpd']);

	/* to exit the php {$g['product_name']} shell */
	exit
	
	/* to output supported wireless modes for an interface */
	print_r(get_wireless_modes(\"ath0\"));
	
	/* to enable SSH */
	\$config['system']['enablesshd'] = true;
	
	/* change OPTX to the OPT interface name such as BACKHAUL */
	\$config['interfaces']['optx']['wireless']['standard'] = "11a";
	\$config['interfaces']['optx']['wireless']['mode'] = "hostap";
	\$config['interfaces']['optx']['wireless']['channel'] = "6";
	
	/* to enable dhcp server for an optx interface */
	\$config['dhcpd']['optx']['enable'] = true;
	\$config['dhcpd']['optx']['range']['from'] = "192.168.31.100";
	\$config['dhcpd']['optx']['range']['to'] = "192.168.31.150";
	
	/* to disable the firewall filter */
	\$config['system']['disablefilter'] = true;
	
	/* to enable an interface and set it for dhcp */
	\$config['interfaces']['optx']['disabled'] = false;
	\$config['interfaces']['optx']['ipaddr'] = "dhcp";
	
	/* to enable an interface and set a static ip address */
	\$config['interfaces']['wan']['disabled'] = false;
	\$config['interfaces']['wan']['ipaddr'] = "192.168.100.1";
	\$config['interfaces']['wan']['subnet'] = "24";
	
	/* to save out the new configuration (config.xml) */
	write_config();
	
	/* to reboot the system after saving */
	system_reboot_sync();
	
EOF;

	more($show_help_text);
 	
}

$fp = fopen('php://stdin', 'r');

echo ".\n\n";

$pkg_interface='console';

$shell_active = true;
$tccommands = array();

function completion($string, $index) {
	global $tccommands;
	return $tccommands;
}

readline_completion_function("completion");

if($argc < 2) {
	echo "Welcome to the {$g['product_name']} php shell system\n";
	echo "Written by Scott Ullrich (sullrich@gmail.com)\n";
	echo "\nType \"help\" to show common usage scenarios.\n";
	echo "\nAvailable playback commands:\n     ";
	$files = scandir("/etc/phpshellsessions/");
	$tccommands[] = "playback";
	foreach($files as $file) {
		if($file <> "." and $file <> "..") {
			echo $file . " ";
			if(function_exists("readline_add_history")) {
				readline_add_history("playback $file");
				$tccommands[] = "$file";
			}
		}
	}
	echo "\n\n";
}

$recording = false;
$playback_file_split = array();
$playbackbuffer = "";

if($argv[1]=="playback" or $argv[1]=="run") { 
	if(!file_exists("/etc/phpshellsessions/{$argv[2]}")) {
		echo "Could not locate playback file.";
		exit;
	}
	playback_file($argv[2]);
	exit;
}

// Define more commands
$tccommands[] = "exit";
$tccommands[] = "quit";
$tccommands[] = "?";
$tccommands[] = "exec";
$tccommands[] = "startrecording";
$tccommands[] = "stoprecording";
$tccommands[] = "showrecordings";
$tccommands[] = "record";
$tccommands[] = "reset";
$tccommands[] = "master";
$tccommands[] = "RELENG_1_2";

while($shell_active == true) {
	$command = readline("{$g['product_name']} shell: ");
	readline_add_history($command);
    $command_split = explode(" ", $command);
    $first_command = $command_split[0];	
	if($first_command == "playback" || $first_command == "run") {
		$playback_file = $command_split[1];
		if(!$playback_file || !file_exists("/etc/phpshellsessions/{$playback_file}")) {
			$command = "";
			echo "Could not locate playback file.\n";
		} else {
			$command = "";
			echo "\nPlayback of file {$command_split[1]} started.\n\n";
			playback_file("{$playback_file}");
			continue;
		}
	}
	if($first_command == "exit" or $first_command == "quit") 
		die;
	if($first_command == "help" or $first_command == "?") {
		show_help();
		$playbackbuffer = "";
		continue;
	}
	if($first_command == "exec" or $first_command == "exec;") {
		playback_text($playbackbuffer);
		$playbackbuffer = "";
		continue;
	}
	if($first_command == "stoprecording" || $first_command == "stoprecord" || $first_command == "stop") {
		if($recording) {
			fwrite($recording_fd, $playbackbuffer);
			fclose($recording_fd);
			$command = "";
			conf_mount_ro();
			echo "Recording stopped.\n";
			$recording = false; 
		} else {
			echo "No recording session in progress.\n";
			$command = "";
		}
	}
	if($first_command == "showrecordings") {
		show_recordings();
		$command = "";		
	}
	if($first_command == "reset") {
		$playbackbuffer = "";
		echo "\nBuffer reset.\n\n";
		continue;
	}
	if($first_command == "record") {
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
				echo "Recording of {$command_split[1]} started.\n";
				$command = "";
			}
		}
	}
	$playbackbuffer .= $command . "\n";
}

function show_recordings() {
	conf_mount_rw();
	safe_mkdir("/etc/phpshellsessions");
	if($recording) 
		conf_mount_ro();
	echo "==> Sessions available for playback are:\n";
	system("cd /etc/phpshellsessions && ls /etc/phpshellsessions");
	echo "==> end of list.\n";	
}

function returnlastchar($command) {
	$commandlen = strlen($command);
	$endofstring = substr($command, ($commandlen-1));
	return $endofstring; 
}

function returnfirstchar($command) {
	$commandlen = strlen($command);
	$endofstring = substr($command, 0, 1);
	return $endofstring; 
}

function str_replace_all($search,$replace,$subject) {
	while(strpos($subject,$search)!==false) 
		$subject = str_replace($search,$replace,$subject);
	return $subject;
}

function playback_text($playback_file_contents) {
	$playback_file_split = explode("\n", $playback_file_contents);
	$playback_text  = "require_once('functions.inc');\n";
	$playback_text .= "require_once('globals.inc');\n";
	$playback_text .= "require_once('config.inc');\n";
	$toquote = '"';
	$toquotereplace = '\\"';	
	foreach($playback_file_split as $pfs) {
		$firstchar = returnfirstchar($pfs);
		$currentline = $pfs;
		if($firstchar == "!") {
			/* XXX: encode " in $pfs */
			$pfsa = str_replace($toquote, $toquotereplace, $currentline);
			$playback_text .= str_replace("!", "system(\"", $pfsa) . "\");\n";
		} else if ($firstchar == "=") {
			/* XXX: encode " in $pfs */
			$pfsa = str_replace($toquote, $toquotereplace, $currentline);
			$currentline   .= str_replace("!", "system(\"", $pfsa) . "\");\n";
		} else {
			$playback_text .= $pfs . "\n";
		}
	}
	global $config;
	eval($playback_text);
}

function playback_file($playback_file) {
	$playback_file_contents = file_get_contents("/etc/phpshellsessions/{$playback_file}");
	playback_text($playback_file_contents);
}

?>
