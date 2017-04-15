<?php
/*
 * diag_ping.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2017 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2005 Bob Zoller (bob@kludgebox.com)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

define('MAX_PING_COUNT', 10);
define('DEFAULT_PING_COUNT', 3);
define('PING_ACTION_FILE_NAME', 'diag_ping.php');

function getPingForm($host, $ipproto, $sourceip, $count) {
    $form = new Form(false);

    $section = new Form_Section('Ping');

    $section->addInput(new Form_Input(
        'host',
        '*Hostname',
        'text',
        $host,
        ['placeholder' => 'Hostname to ping']
    ));

    $section->addInput(new Form_Select(
        'ipproto',
        '*IP Protocol',
        $ipproto,
        ['ipv4' => 'IPv4', 'ipv6' => 'IPv6']
    ));

    $section->addInput(new Form_Select(
        'sourceip',
        '*Source address',
        $sourceip,
        ['' => gettext('Automatically selected (default)')] + get_possible_traffic_source_addresses(true)
    ))->setHelp('Select source address for the ping.');

    $section->addInput(new Form_Select(
        'count',
        'Maximum number of pings',
        $count,
        array_combine(range(1, MAX_PING_COUNT), range(1, MAX_PING_COUNT))
    ))->setHelp('Select the maximum number of pings.');

    $form->add($section);

    $form->addGlobal(new Form_Button(
        'Submit',
        'Ping',
        null,
        'fa-rss'
    ))->addClass('btn-primary');

    return $form;
}

