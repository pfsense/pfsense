<?php

/*
	cmd_chain.inc
	Copyright (C) 2008 Scott Ullrich
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
 * HANDY FOR STAND ALONE DEBUGGING OF CLASS. 
 *
	function log_error($text) {
		echo $text . "\n";
	}
*/

Class CmdCHAIN {

		var $cmd_chain_array = array();
		var $is_debugging;
		
		/* clear() erases the current cmdchain */
		function clear() {
			unset($cmd_chain_array);
			$this->cmd_chain_array = array();
		}
		
		/* enables log_error() of each command we run */
		function setdebug() {
			$this->is_debugging = true;
		}

		/* adds a command to the cmdchain */
		function add($cmd_title = "", $command = "") {
			if(!$cmd_title) 
				return "$cmd_title must be set.";
			if(!$command)
				return "$command must be set.";
			$temp = array();
			$temp['cmd_title'] = $cmd_title;
			$temp['command'] = $command;
			$this->cmd_chain_array[] = $temp;
			return;
		}
		
		/* executes the command chain one command at a time */
		function execute() {
			foreach($this->cmd_chain_array as $cmd) {
				$cmd_title = $cmd['cmd_title'];
				$command = $cmd['command'];
				if($this->is_debugging == true) {
					log_error("CmdCHAIN is executing -> {$cmd_title} - {$command}");
					usleep(100);	// give network stack time to deliver network syslog message
				}
				$status = exec($command);
				if(!$status) {
					log_error("$cmd_title failed with return code -> {$status}.  The command was {$command}");
					return("$cmd_title failed with return code -> " . $status);
				} else {
					if($this->is_debugging == true) {
						log_error("{$cmd_title} returned -> {$status}");
					}
				}
			}
			return;
		}
       
}

/* 
 * example usage:
 *

$cmdchain = new CmdCHAIN();
$cmdchain->add("grab freebsd version", "uname -a");
$cmdchain->setdebug();
$cmdchain->execute();

*/

?>