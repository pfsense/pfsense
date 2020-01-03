#!/bin/sh
#
# openvpn.attributes.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

if [ "$script_type" = "client-connect" ]; then
	if [ -f /tmp/$common_name ]; then
		/bin/cat /tmp/$common_name > $1
		/bin/rm /tmp/$common_name
	fi
elif [ "$script_type" = "client-disconnect" ]; then
	command="/sbin/pfctl -a 'openvpn/$common_name' -F rules"
	eval $command
	/sbin/pfctl -k $ifconfig_pool_remote_ip
	/sbin/pfctl -K $ifconfig_pool_remote_ip
fi

exit 0
