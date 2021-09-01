<?php
/*
 * tools_for_debugging_n_testing.inc
 *
 * Copyright (c) 2017-2019 Anders Lind (anders.lind@gmail.com)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * Returns false when parameters are not set and when isset($var[$index])
 * returns false or if $var[$index] is a string and (strlen($var[$index]) < 1)
 */
function setnlen($var, $index) {
	if (isset($var) && isset($index) && isset($var[$index])) {
		if (is_string($var[$index])) {
			return strlen($var[$index]) > 0;
		}

		return true;
	}

	return false;
}

/*
 * Returns true if $entry[$property] already exists with a value set defined by setnlen
 * and prints out debug information if environment is suitable; false otherwise.
 */
function does_entry_exist($entry, $property) {
	if (setnlen($entry, $property)) {
		general_debug('Entry for '.$property.' already set!', $entry[$property]);
		return true;
	}

	return false;
}

/*
 * $entry is target entry to write to.
 * $property is the property to check if it exists with a value in $entry.
 * $value is the value to set.
 * $overwrite should be set to true if overwriting existing value is wanted.
 * returns true if $entry was set, false otherwise.
 */
function check_n_set(&$entry, $property, $value, $overwrite) {
	if ((does_entry_exist($entry, $property) === false) || ($overwrite === true)) {
		$entry[$property] = $value;
		return true;
	}

	return false;
}

/*
 * $value is the source array to use.
 * $source_property is the lookup index for $value.
 * $entry is the target entry to write to.
 * $target_property is the property to check if it exists with a value in $entry.
 * $overwrite should be set to true if overwriting existing value in $entry is wanted.
 * If not specified (NULL) the method will just output debug information and not set anything.
 * $custom_target_value if custom target value is desired/specified as a function then we
 * still check source value and source property and debug these! Responsibility for the returned
 * content of the function lies by the caller!
 * returns true if $entry was set, false otherwise.
 */
function check_value_set_entry($value, $source_property, &$entry = NULL, $target_property = NULL,
			       $overwrite = NULL, $custom_target_value = NULL) {
	if (setnlen($value, $source_property)) {
		general_debug($value, $source_property);
		//  We let check_n_set administer what to do when $entry and $target_property are set to NULL.
                if ($overwrite !== NULL) {
                    return check_n_set($entry, $target_property,
                                       is_callable($custom_target_value) ? $custom_target_value()
									 : $value[$source_property],
				       $overwrite);
                }
	}

	return false;
}

// alternatives: https://unicode-table.com/en/#enclosed-alphanumerics e.g.'⊢'
const index_mark = [ 'utf8'  => ['normal' => '┠',
                                 'last'   => '┖' ],
                     'other' => ['normal' => '}',
                                 'last'   => 'L' ] ];

/*
Print debug output.
Print a message: set $var but let there be no $params
Iterate an array for specific indexes: set $var and let there be an array in $params; multiple arrays in $params are handled
Print a message with content: set $var to not be an array and let there be $params
Print name of variable and its content: let $var contain the name of a variable and let there be one parameter in $params
Iterate an array for all indexes: set $var but let there be no $params
*/
function general_debug($var, ...$params) {
	static $debug;
	if ( ! isset($debug)) {
		$debug = ( php_sapi_name() == 'cli' && ( ! isset($_SERVER['REMOTE_ADDR']) ) );
	}

	if ($debug == true) {
		static $inner_debug;
		if ( ! isset($inner_debug)) {
			// Use parameter $inner_debug seems to be the only real variable that seems
			// to need the reference & - maybe because the rest either resides inside
			// a static method. Anyway we are not going to change/redefine either of
			// the variables inside.
			$inner_debug = function ($rerun, $var, $params) use (&$inner_debug) {
			        static $has_utf8, $index_mark;
			        if ( ! isset($has_utf8)) {
			          // For testing try: setLocale(LC_CTYPE, 'C');
			          $has_utf8 = preg_match('/utf-?8$/i', setLocale(LC_CTYPE, 0));
			          if ($has_utf8) {
			            $index_mark['last'] = index_mark['utf8']['last'];
			            $index_mark['normal'] = index_mark['utf8']['normal'];
			          } else {
			            $index_mark['last'] = index_mark['other']['last'];
			            $index_mark['normal'] = index_mark['other']['normal'];
			          }
			        }

				static $the_choice;
				if ( ! isset($the_choice)) {
					$the_choice = function ($is_last, $spaces, $key, $value) use (&$the_choice, &$inner_debug, &$index_mark) {
						if ($is_last) {
							$selected_char = $index_mark['last'];
						} else {
							$selected_char = $index_mark['normal'];
						}

						if (is_array($value)) {
							$inner_debug(1, "$spaces $selected_char ".$key.": [ ]", $value);
						} else {
							echo "$spaces $selected_char ".$key.": " . (isset($value) ? $value : 'undefined@1') . "\n";
						}
					};
				}

				static $spaces_func;
				if ( ! isset($spaces_func)) {
					$spaces_func = function ($is_last, $string, &$spaces) use (&$index_mark) {
						if ($is_last) {
							$selected_char = $index_mark['last'];
						} else {
							$selected_char = $index_mark['normal'];
						}
						$bias = strlen($selected_char) - 1;
						$spaces = '';
						$amount = 0;
						if (preg_match('/^(.*: )/', $string, $match)) {
							$decrease = $amount = strlen($match[0]) - $bias;

							for (;$decrease>0;$decrease--) {
								$spaces .= ' ';
							}
						}

						return $amount;
					};
				}

				// Are we only given the variable $var?
				if (count($params) == 0) {
					// NOTICE: We can ONLY get here zero to one time namely at the very first
					// call to general_debug. All the other times the internal calls to
					// inner_debug have two parameters: $var and $params, where the latter
					// contains something.

					// Test if it is array
					if (is_array($var)) {
						// Print out identification that indicates an array
						echo "[ ]\n";
						$dec_counter = count($var);
						foreach ($var as $key => $value) {
							$dec_counter -= 1;
							$the_choice( ! $dec_counter, '', $key, $value);
						}
					} else {
						if (isset($var)) {
							echo "$var\n";
						} else {
							echo "undefined@7\n";
						}
					}
				} elseif ( ! is_array($var)) {
					// Or are we given a $var who is not an array

					// If yes, print it
					if (setnlen($var, 0)) {
						echo "$var\n";
					} else {
						echo "undefined@8\n";
					}

					// Regardless if params were given by second iterations or directly
					// at the first run it means we need to print all param key indexes
					// and content, because $var does not contain anything to iterate over
					// and if second run because all the_choice calls and inner_debug
					// calls use (part of) the previous $var which must have been an array.

					$spaces_func(true, $var, $spaces);

					// print out each parameter
					$dec_counter = count($params);
					foreach ($params as $pkey => $value) {
						$dec_counter -= 1;
						if (is_array($value)) {
							// handle if the value is an array
							$the_choice( ! $dec_counter, $spaces, $pkey, $value);
						} else {
							// parameter not an array var
							$the_choice( ! $dec_counter, $spaces, $pkey, $value);
						}
					}
				} else {
					// NOTICE: We can ONLY get here zero to one time namely at the very first
					// call to general_debug. All the other times the internal calls to
					// general_debug are non arrays.

					// Meaning here we simply search for indexes that is used for look up
					// in val.

					// So $var is an array.
					if ( ! $rerun) {
						echo "[ ]\n";
					}

					$dec_counter = count($params);
					foreach ($params as $key => $value) {
						$dec_counter -= 1;
						// Are we given an array
						if (is_array($value)) {
							$dec_counter2 = count($value);
							foreach ($value as $key2 => $value2) {
								$dec_counter2 -= 1;

								// key2 does not need to be printed as well
								// since it is our lookup table for $var.
								// Here we are namely only interested in the values.
								if (is_array($value2)) {
									$inner_debug(1, $var, $value2); // And here is an exception to our NOTICE above! :)
									// no problems with spacing as we are traversing params for
									// indexes.
								} else {
									// use value2 (indirect from $params) as the look up index
									// in $var and use the_choice to return the tree of return
									// values.
									$the_choice( ! $dec_counter, '', $value2, (setnlen($var, $value2) ? $var[$value2] : 'undefined@9'));
								}
							}
						} else {
							// or are we given some index

							// now find the return value/tree in var
							$the_choice( ! $dec_counter, '', $value, (setnlen($var, $value) ? $var[$value] : 'undefined@10'));
						}
					}
				}
			};
		}

		$inner_debug(0, $var, $params);
	}
}
