<?php
/*
 * firewall_schedule.inc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

require_once("config.gui.inc");

// Functions included by firewall_schedule*.php =============================
// Delete a schedule after first checking that no rules are using it
// Return an error string if the schedule is in use
function deleteSchedule($post, $json = false) {
    global $config;

    init_config_arr(array('schedules', 'schedule'));
    $a_schedules = &$config['schedules']['schedule'];

    $id = $post['id'];

	if ($a_schedules[$id]) {
		/* make sure rule is not being referenced by any nat or filter rules */
		$is_schedule_referenced = false;
		$referenced_by = false;
		$schedule_name = $a_schedules[$id]['name'];

		if (is_array($config['filter']['rule'])) {
			foreach ($config['filter']['rule'] as $rule) {
				//check for this later once this is established
				if ($rule['sched'] == $schedule_name) {
					$referenced_by = $rule['descr'];
					$is_schedule_referenced = true;
					break;
				}
			}
		}

		if ($is_schedule_referenced == true) {
			$errmsg = sprintf(gettext("Cannot delete schedule. Currently in use by %s."), $referenced_by);
		} else {
			unset($a_schedules[$post['id']]);
			write_config(gettext("Firewall schedule deleted."));
            if (!$json) {
                header("Location: firewall_schedule.php");
                exit;
            }
		}
    }

    return $errmsg;
}
