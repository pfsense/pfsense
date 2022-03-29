#!/bin/sh
#
# openvpn.connect_async.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2021-2022 Rubicon Communications, LLC (Netgate)
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

if [ -z "${untrusted_ip6}" ]; then
	ipaddress="${untrusted_ip}"
else
	ipaddress="${untrusted_ip6}"
fi

# Remote Access (SSL/TLS) mode
if [ -z "${username}" ]; then
	if [ "$script_type" = "client-connect" ]; then
		/usr/bin/logger -t openvpn "openvpn server '${dev}' user cert CN '${X509_0_CN}' address '${ipaddress}' - connected"
		# Verify defer status code before continuing
		i=1
		while
			deferstatus=$(/usr/bin/head -1 "${client_connect_deferred_file}")
			if [ "${deferstatus}" -ne 2 ]; then
				/bin/sleep 1
				i="$((i+1))"
			else
				break
			fi
			[ "${i}" -lt 3 ]
		do :;  done
		if [ ${i} -ge 3 ]; then
			/bin/echo 0 > ${client_connect_deferred_file}
			exit 1
		fi
		# success; allow client connection
		/bin/echo 1 > ${client_connect_deferred_file}
	elif [ "$script_type" = "client-disconnect" ]; then
		/usr/bin/logger -t openvpn "openvpn server '${dev}' user cert CN '${X509_0_CN}' address '${ipaddress}' - disconnected"
		/sbin/pfctl -k $ifconfig_pool_remote_ip
		/sbin/pfctl -K $ifconfig_pool_remote_ip
		/sbin/pfctl -k $ifconfig_pool_remote_ip6
		/sbin/pfctl -K $ifconfig_pool_remote_ip6
	fi
	exit 0
fi

lockfile="/tmp/ovpn_${dev}_${username}_${trusted_port}.lock"
rulesfile="/tmp/ovpn_${dev}_${username}_${trusted_port}.rules"
anchorname="openvpn/${dev}_${username}_${trusted_port}"

if [ "$script_type" = "client-connect" ]; then
	/usr/bin/logger -t openvpn "openvpn server '${dev}' user '${username}' address '${ipaddress}' - connected"

	# Verify defer status code before continuing
	i=1
	while
		deferstatus=$(/usr/bin/head -1 "${client_connect_deferred_file}")
		if [ "${deferstatus}" -ne 2 ]; then
			/bin/sleep 1
			i="$((i+1))"
		else
			break
		fi
		[ "${i}" -lt 3 ]
	do :;  done
	if [ ${i} -ge 3 ]; then
		/bin/echo 0 > ${client_connect_deferred_file}
		exit 1
	fi

	i=1
	while [ -f "${lockfile}" ]; do
		if [ $i -ge 30 ]; then
			/bin/echo "Timeout while waiting for lockfile"
			/bin/echo 0 > ${client_connect_deferred_file}
			exit 1
		fi

		/bin/sleep 1
		i=$(( i + 1 ))
	done
	/usr/bin/touch "${lockfile}"

	/bin/cat "${rulesfile}" | /usr/bin/sed "s/{clientip}/${ifconfig_pool_remote_ip}/g" | /usr/bin/sed "s/{clientipv6}/${ifconfig_pool_remote_ip6}/g" > "${rulesfile}.tmp" && /bin/mv "${rulesfile}.tmp" "${rulesfile}"
	/sbin/pfctl -a "openvpn/${dev}_${username}_${trusted_port}" -f "${rulesfile}"

	/bin/rm "${lockfile}"

	# success; allow client connection
	/bin/echo 1 > ${client_connect_deferred_file}
elif [ "$script_type" = "client-disconnect" ]; then
	/usr/bin/logger -t openvpn "openvpn server '${dev}' user '${username}' address '${ipaddress}' - disconnected"
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
	/sbin/pfctl -k $ifconfig_pool_remote_ip6
	/sbin/pfctl -K $ifconfig_pool_remote_ip6

	/bin/rm "${rulesfile}"
	/bin/rm "${lockfile}"
fi

exit 0