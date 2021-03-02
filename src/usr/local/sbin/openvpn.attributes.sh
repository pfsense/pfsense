#!/bin/sh
#
# openvpn.attributes.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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


lockfile="/tmp/ovpn_${dev}_${username}_${trusted_port}.lock"
rulesfile="/tmp/ovpn_${dev}_${username}_${trusted_port}.rules"
anchorname="openvpn/${dev}_${username}_${trusted_port}"

if [ "$script_type" = "client-connect" ]; then
	i=1
	while [ -f "${lockfile}" ]; do
		if [ $i -ge 30 ]; then
			/bin/echo "Timeout while waiting for lockfile"
			exit 1
		fi

		/bin/sleep 1
		i=$(( i + 1 ))
	done
	/usr/bin/touch "${lockfile}"

	/bin/cat "${rulesfile}" | /usr/bin/sed "s/{clientip}/${ifconfig_pool_remote_ip}/g" | /usr/bin/sed "s/{clientipv6}/${ifconfig_pool_remote_ip6}/g" > "${rulesfile}.tmp" && /bin/mv "${rulesfile}.tmp" "${rulesfile}"
	/sbin/pfctl -a "openvpn/${dev}_${username}_${trusted_port}" -f "${rulesfile}"
	/bin/rm "${rulesfile}"

	if [ -f /tmp/$common_name ]; then
		/bin/cat /tmp/$common_name > $1
		/bin/rm /tmp/$common_name
	fi

	/bin/rm "${lockfile}"
elif [ "$script_type" = "client-disconnect" ]; then
	i=1
	while [ -f "${lockfile}" ]; do
		if [ $i -ge 30 ]; then
			/bin/echo "Timeout while waiting for lockfile"
			exit 1
		fi

		/bin/sleep 1
		i=$(( i + 1 ))
	done
	/usr/bin/touch "${lockfile}"

	command="/sbin/pfctl -a '${anchorname}' -F rules"
	eval $command
	/sbin/pfctl -k $ifconfig_pool_remote_ip
	/sbin/pfctl -K $ifconfig_pool_remote_ip

	/bin/rm "${lockfile}"
fi

exit 0
