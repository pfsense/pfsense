<?php
/* $Id$ */
/*
	cmd_chain.inc
	Part of pfSense
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

	pfSense_MODULE:	utils
*/

/* 
 * HANDY FOR STAND ALONE DEBUGGING OF CLASS. 
 *

	function log_error($text) {
		echo $text . "\n";
	}

*/

/*
 *   CmdCHAIN allows a chaining of commands into one call.
 *   If debugging is enabled verbose logging is applied.
 *   During the command(s) execution if it fails the result
 *   will be reported to syslog so that the problem can be reported.
 */
Class CmdCHAIN {

		var $cmd_chain_array = array();
		var $is_debugging;
		var $halt_on_errors = true;
		
		/* clear() erases the current cmdchain */
		function clear() {
			unset($cmd_chain_array);
			$this->cmd_chain_array = array();
		}
		
		/* enables log_error() of each command we run */
		function setdebug() {
			$this->is_debugging = true;
		}
		
		/* no halt execution of CmdCHAIN if there is a failure */
		function nohaltonerror() {
			$this->halt_on_errors = false;			
		}

		/* halts execution of CmdCHAIN if there is a failure */
		function sethaltonerror() {
			$this->halt_on_errors = true;			
		}

		/* adds a command to the CmdCHAIN */
		function add($cmd_title = "", $command = "", $ignore_return_text = false) {
			if(!$cmd_title) 
				return;
			if(!$command) 
				return;
			$temp = array();
			$temp['cmd_title'] = $cmd_title;
			$temp['command'] = $command;
			if($ignore_return_text)
				$temp['ignore_return_text'] = true;
			else 
				$temp['ignore_return_text'] = false;
			$this->cmd_chain_array[] = $temp; // add array to class
			return array();
		}
		
		/* executes the CmdCHAIN one command at a time */
		function execute() {
			foreach($this->cmd_chain_array as $cmd) {
				$cmd_title = $cmd['cmd_title'];
				$command = $cmd['command'];
				$ignore_return_text = $cmd['ignore_return_text'];
				// Should we perform verbose debugging?
				if($this->is_debugging == true) {
					log_error(sprintf(gettext('CmdCHAIN is executing -> %1$s - %2$s'), $cmd_title, $command));
					usleep(100);	// give network stack time to deliver network syslog message
				}
				// Execute command
				$status = exec($command);
				if($this->ignore_return_text == true) 
					continue;
				if(intval($status) <> 0) {
					log_error(sprintf(gettext('%1$s failed with return code -> %2$s.  The command was %3$s'), $cmd_title, $status, $command));
					if($this->halt_on_errors == true) 
						return(sprintf(gettext('%1$s failed with return code -> %2$s.  The command was %3$s'), $cmd_title, $status, $command));
				}
			}
			return;
		}
}

/* 
 * example usage:
 *

$cmdchain = new CmdCHAIN();
$cmdchain->add("grab freebsd version", "uname -a", false);
$cmdchain->setdebug(); // optional for verbose logging
$cmdchain->nohaltonerror(); // tells cmdchain to keep processing commands if any of them fail
$cmdchain->execute();

$cmdchain->clear(); // clears the previous added entries

*/

?>
