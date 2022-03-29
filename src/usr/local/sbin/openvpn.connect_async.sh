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

log_session() {
	if [ -z "${1}" ]; then
		logmsg=""
	else
		logmsg=" - ${1}"
	fi

	if [ -z "${untrusted_ip6}" ]; then
		hostaddress="${untrusted_ip}:${untrusted_port}"
	else
		hostaddress="${untrusted_ip6}:${untrusted_port}"
	fi
	
	if [ -z "${username}" ]; then
		hostuser="user cert CN '${X509_0_CN}'"
	else
		hostuser="user '${username}'"
	fi

	/usr/bin/logger -t openvpn "openvpn server '${dev}' ${hostuser} address '${hostaddress}'${logmsg}"
}

if [ -n "${username}" ]; then
	lockfile="/tmp/ovpn_${dev}_${username}_${trusted_port}.lock"
	rulesfile="/tmp/ovpn_${dev}_${username}_${trusted_port}.rules"
	anchorname="openvpn/${dev}_${username}_${trusted_port}"
fi

if [ "${script_type}" = "client-disconnect" ]; then
	log_session "disconnected"

	if [ -n "${username}" ]; then
		# Avoid race condition. See https://redmine.pfsense.org/issues/9206
		i=1
		while
			if [ -f "${lockfile}" ]; then
				/bin/sleep 1
				i="$((i+1))"
			else
				break
			fi
			[ "${i}" -lt 30 ]
		do :;  done

		if [ ${i} -ge 30 ]; then
			log_session "Timeout while waiting for lockfile"
		else
			/usr/bin/touch "${lockfile}"
			eval "/sbin/pfctl -a '${anchorname}' -F rules"
			/bin/rm "${lockfile}"

			/bin/rm "${rulesfile}"
		fi
	fi

	/sbin/pfctl -k $ifconfig_pool_remote_ip
	/sbin/pfctl -K $ifconfig_pool_remote_ip
	/sbin/pfctl -k $ifconfig_pool_remote_ip6
	/sbin/pfctl -K $ifconfig_pool_remote_ip6
elif [ "${script_type}" = "client-connect" ]; then
	log_session "connecting"

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
		log_session "server write to defer file failed"
		/bin/echo 0 > ${client_connect_deferred_file}
		exit 1
	fi

	if [ -n "${username}" ]; then
		i=1
		while
			if [ -f "${lockfile}" ]; then
				/bin/sleep 1
				i="$((i+1))"
			else
				break
			fi
			[ "${i}" -lt 30 ]
		do :;  done
		if [ ${i} -ge 30 ]; then
			log_session "Timeout while waiting for lockfile"
			/bin/echo 0 > ${client_connect_deferred_file}
			exit 1
		else
			/usr/bin/touch "${lockfile}"

			/bin/cat "${rulesfile}" | /usr/bin/sed "s/{clientip}/${ifconfig_pool_remote_ip}/g" | /usr/bin/sed "s/{clientipv6}/${ifconfig_pool_remote_ip6}/g" > "${rulesfile}.tmp" && /bin/mv "${rulesfile}.tmp" "${rulesfile}"
			/sbin/pfctl -a "openvpn/${dev}_${username}_${trusted_port}" -f "${rulesfile}"

			/bin/rm "${lockfile}"
		fi
	fi

	# success; allow client connection
	/bin/echo 1 > ${client_connect_deferred_file}
	log_session "connected"
fi

exit 0
