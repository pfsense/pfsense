#!/bin/sh
#
# openvpn.attributes.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

# Signal deferred handler
if [ "${script_type}" = "client-connect" ]; then
	/bin/echo 2 > "${client_connect_deferred_file}"
fi

# Handle 'client-connect' and 'client-disconnect'
/usr/bin/nohup /usr/local/sbin/openvpn.connect_async.sh > /dev/null &

# Signal "deferred handler started OK" for client-connect
exit 0
