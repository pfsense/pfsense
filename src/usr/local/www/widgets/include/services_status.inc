<?php
/*
 * services_status.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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


//set variable for custom title
$services_status_title = gettext("Services Status");
$services_status_title_link = "status_services.php";
$services_status_allow_multiple_widget_copies = true;

function get_services_array() {
    $services = get_services();
    $numsvcs = count($services);

    for ($idx=0; $idx<$numsvcs; $idx++) {
        if (!is_array($services[$idx])) {
            $services[$idx] = array();
        }
        $services[$idx]['dispname'] = $services[$idx]['name'];
    }

    // If there are any duplicated names, add an incrementing suffix
    for ($idx=1; $idx < $numsvcs; $idx++) {
        $name = $services[$idx]['name'];

        for ($chk = $idx +1, $sfx=2; $chk<$numsvcs; $chk++) {
            if ($services[$chk]['dispname'] == $name) {
                $services[$chk]['dispname'] .= '_' . $sfx++;
            }
        }
    }
    uasort($services, "service_dispname_compare");
    return $services;
}

function services_compose_widget_body($widgetkey) {
    global $widget_config;
    $services = get_services_array();
    $data_html = null;
    $s = fn($x) => $x;
    $skipservices = explode(",", $widget_config['filter']);
    if (count($services) > 0) {
        $service_is_displayed = false;
        foreach ($services as $service) {
            if ((!$service['dispname'])
                || (in_array($service['dispname'], $skipservices))
                || (!is_service_enabled($service['dispname']))) {
                continue;
            }
            $service_is_displayed = true;
            if (empty($service['description'])) {
                $service['description'] = get_pkg_descr($service['name']);
            }
            $service_desc = explode(". ",$service['description']);
            $data_html .= <<<"ROW"
            <tr>
                <td>{$s(get_service_status_icon($service, false, true, false, 'state'))}</td>
                <td>{$s($service['dispname'])}</td>
                <td>{$s($service_desc[0])}</td>
                <td>{$s(get_service_control_links($service, false, $widgetkey))}</td>
            </tr>
            ROW;
        }
        if (!$service_is_displayed) {
            $data_html .= <<<"ROW"
            <tr><td colspan="4" class="text-center">{$s(gettext("All services are hidden"))}</td></tr>
            ROW;
        }
    } else {
        $data_html .= <<<"ROW"
        <tr><td colspan="4" class="text-center">{$s(gettext("No services found"))}</td></tr>
        ROW;
    }
    return $data_html;
}

?>
