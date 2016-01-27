<?php
/*
	led.inc

	LED control library that wraps around the functionality of led(4)

	part of pfSense (https://www.pfsense.org)
	Copyright (c) 2009-2016 Electric Sheep Fencing, LLC.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in
	   the documentation and/or other materials provided with the
	   distribution.

	3. All advertising materials mentioning features or use of this software
	   must display the following acknowledgment:
	   "This product includes software developed by the pfSense Project
	   for use in the pfSense® software distribution. (http://www.pfsense.org/).

	4. The names "pfSense" and "pfSense Project" must not be used to
	   endorse or promote products derived from this software without
	   prior written permission. For written permission, please contact
	   coreteam@pfsense.org.

	5. Products derived from this software may not be called "pfSense"
	   nor may "pfSense" appear in their names without prior written
	   permission of the Electric Sheep Fencing, LLC.

	6. Redistributions of any form whatsoever must retain the following
	   acknowledgment:

	"This product includes software developed by the pfSense Project
	for use in the pfSense software distribution (http://www.pfsense.org/).

	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
	OF THE POSSIBILITY OF SUCH DAMAGE.
 */

$led_root = "/dev/led/led";

/*
 * Send the control string to an LED
 */
function led_ctl($led, $str) {
	global $led_root;
	if (led_exists($led)) {
		exec("/bin/echo " . escapeshellarg($str) . " > {$led_root}{$led}");
		return true;
	}
	return false;
}

/*
 * Blink an LED at set speed from 1-9 (1=Very Fast, 9=Very Slow)
 */
function led_blink($led, $speed = 0) {
	switch ($speed) {
		case "reallyfast":
		case "veryfast":
			$speed = 1;
			break;
		case "fast":
			$speed = 3;
			break;
		case "medium":
			$speed = 5;
			break;
		case "slow":
			$speed = 7;
			break;
		case "reallyslow":
		case "veryslow":
			$speed = 9;
			break;
	}
	if (is_numeric($speed) && ($speed > 0) && ($speed < 10)) {
		return led_ctl($led, "f{$speed}");
	}
	return false;
}

/*
 * Blink an LED in a specific pattern
 * Letters A-J are on from 1/10s to 1s
 * Letters a-j are off from 1/10s to 1s
 */
function led_pattern($led, $pattern, $repeat = true) {
	/*  End with a . to stop after one iteration. */
	$end = $repeat ? "" : ".";
	return led_ctl($led, "s{$pattern}{$end}");
}

/*
 * Encode a text message into morse code, and send it to an LED
 */
function led_morse($led, $message) {
	return led_ctl($led, "m" . str_to_morse($message));
}

/*
 * Blink digits out on LED at 1/10s intervals
 * e.g 1=1 blink, 8=8 blinks
 * 0 is 10 pulses.
 * One second pause between digits.
 */
function led_digit($led, $digitstring) {
	$i = 0;
	$dstring = "d";
	while ($i < strlen($digitstring)) {
		$thisdigit = substr($digitstring, $i++, 1);
		if (is_numeric($thisdigit)) {
			$dstring .= $thisdigit;
		}
	}
	led_ctl($led, $dstring);
}

/*
 * Turn an LED on
 */
function led_on($led) {
	led_ctl($led, "1");
}

/*
 * Turn an LED off
 */
function led_off($led) {
	led_ctl($led, "0");
}

/*
 * Find the number of LEDs present on the system.
 */
function led_count() {
	global $led_root;
	$count = 0;
	$leds = array();
	if (is_dir(dirname($led_root))) {
		$leds = glob("{$led_root}*");
		$count = count($leds);
	}
	return $count;
}

/*
 * Test to see if a given LED exists.
 */
function led_exists($led) {
	global $led_root;
	if (!is_numeric($led)) {
		return false;
	}
	return file_exists("{$led_root}{$led}");
}

/*
 * Sweep across three LEDs in a K.I.T.T.-like way.
 */
function led_kitt() {
	led_pattern(1, 'AaaaaA');
	led_pattern(2, 'aAaaAa');
	led_pattern(3, 'aaAAaa');
}

/*
 * Custom pattern for assigning interfaces
 */
function led_assigninterfaces() {
	led_pattern(1, 'AaaAaaaaaaaaaaaa');
	led_pattern(2, 'aaaaaAaaAaaaaaaa');
	led_pattern(3, 'aaaaaaaaaaAaaAaa');
}

/*
 * Return the three LEDs to a standard setup (1=on, 2 and 3 = off)
 */
function led_normalize() {
	led_on(1);
	led_off(2);
	led_off(3);
}

/*
 * Shut off ALL LEDs.
 */
function led_alloff() {
	led_off(1);
	led_off(2);
	led_off(3);
}

/*
 * Translate a string to morse code. Characters not known to have a
 * valid morse code representation will be ignored.
 */
function str_to_morse($string) {
	$i = 0;
	$morsestring = "";
	while ($i < strlen($string)) {
		$morsestring .= char_to_morse(substr($string, $i++, 1)) . " ";
	}
	return $morsestring . "\n";
}

/*
 * Translate a single character to morse code. Characters not known
 * to have a valid morse code representation will be ignored.
 */
function char_to_morse($char) {
	switch (strtoupper($char)) {
		case "A":
			return ".-";
			break;
		case "B":
			return "-...";
			break;
		case "C":
			return "-.-.";
			break;
		case "D":
			return "-..";
			break;
		case "E":
			return ".";
			break;
		case "F":
			return "..-.";
			break;
		case "G":
			return "--.";
			break;
		case "H":
			return "....";
			break;
		case "I":
			return "..";
			break;
		case "J":
			return ".---";
			break;
		case "K":
			return "-.-";
			break;
		case "L":
			return ".-..";
			break;
		case "M":
			return "--";
			break;
		case "N":
			return "-.";
			break;
		case "O":
			return "---";
			break;
		case "P":
			return ".--.";
			break;
		case "Q":
			return "--.-";
			break;
		case "R":
			return ".-.";
			break;
		case "S":
			return "...";
			break;
		case "T":
			return "-";
			break;
		case "U":
			return "..-";
			break;
		case "V":
			return "...-";
			break;
		case "W":
			return ".--";
			break;
		case "X":
			return "-..-";
			break;
		case "Y":
			return "-.--";
			break;
		case "Z":
			return "--..";
			break;
		case "0":
			return "-----";
			break;
		case "1":
			return ".----";
			break;
		case "2":
			return "..---";
			break;
		case "3":
			return "...--";
			break;
		case "4":
			return "....-";
			break;
		case "5":
			return ".....";
			break;
		case "6":
			return "-....";
			break;
		case "7":
			return "--...";
			break;
		case "8":
			return "---..";
			break;
		case "9":
			return "----.";
			break;
		case ".":
			return ".-.-.-";
			break;
		case ",":
			return "--..--";
			break;
		case "?":
			return "..--..";
			break;
		case "'":
			return ".----.";
			break;
		case "!":
			return "-.-.--";
			break;
		case "/":
			return "-..-.";
			break;
		case "(":
			return "-.--.";
			break;
		case ")":
			return "-.--.-";
			break;
		case "&":
			return ".-...";
			break;
		case ":":
			return "---...";
			break;
		case ";":
			return "-.-.-.";
			break;
		case "=":
			return "-...-";
			break;
		case "+":
			return ".-.-.";
			break;
		case "-":
			return "-....-";
			break;
		case "_":
			return "..--.-";
			break;
		case "$":
			return "...-..-";
			break;
		case "@":
			return ".--.-.";
			break;
		case '"':
			return ".-..-.";
			break;
		default:
			return "";
			break;
	}
}

?>
