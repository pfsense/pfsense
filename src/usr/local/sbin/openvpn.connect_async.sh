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

	# Get active sessions
	# active_sessions :: ovpns1_'user_01'_30001|ovpns1_'user_01'_30002|ovpns1_'user_01'_30003|
	# Use php-cgi - see https://redmine.pfsense.org/issues/12382
	active_sessions=$("/usr/local/bin/php-cgi" -f "/usr/local/sbin/openvpn_connect_async.php")

	# Process "Duplicate Connection Limit" setting
	if [ -n "${active_sessions}" ]; then
		vpnid=$(/bin/echo ${dev} | /usr/bin/sed -e 's/ovpns//g')
		if [ -f "/var/etc/openvpn/server${vpnid}/connuserlimit" ]; then
			sessionlimit=$(/usr/bin/head -1 "/var/etc/openvpn/server${vpnid}/connuserlimit" | /usr/bin/sed -e 's/[[:space:]]//g')
			if [ "${sessionlimit}" -ge 1 ]; then
				if [ -z "${username}" ]; then
					usersession="${dev}_'${X509_0_CN}'"
				else
					usersession="${dev}_'${username}'"
				fi
				sessioncount=$(/bin/echo "${active_sessions}" | /usr/bin/grep -o "${usersession}" | /usr/bin/wc -l | /usr/bin/sed -e 's/[[:space:]]//g')

				if [ ${sessioncount} -gt ${sessionlimit} ]; then
					log_session "active connection limit of '${sessionlimit}' reached"
					/bin/echo 0 > ${client_connect_deferred_file}
					if [ -n "${username}" ]; then
						/bin/rm "${rulesfile}"
					fi
					exit 1
				fi
			fi
		fi
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

			# for each of this user's anchors loaded in pf
			# $session :: ovpns3_'user_01'_61468
			# $anchor  :: openvpn/ovpns3_user_01_61468
			anchors=$(/sbin/pfctl -s Anchors)
			for anchor in $(/bin/echo "${anchors}" | /usr/bin/grep "${dev}_${username}"); do
				session=$(/bin/echo "${anchor}" | /usr/bin/sed -r -e 's/.+'"${dev}_${username}"'/'"${dev}_\'${username}\'"'/')
				# if no active session exists for the anchor, remove it from pf
				if ! (/bin/echo "${active_sessions}" | /usr/bin/grep -q "${session}"); then
					eval "/sbin/pfctl -a '${anchor}' -F rules"
				fi
			done

			/bin/echo "$(/usr/bin/sed -e "s/{clientip}/${ifconfig_pool_remote_ip}/g;s/{clientipv6}/${ifconfig_pool_remote_ip6}/g" "${rulesfile}")" > "${rulesfile}"
			eval "/sbin/pfctl -a '${anchorname}' -f '${rulesfile}'"

			/bin/rm "${lockfile}"
		fi
	fi

	# success; allow client connection
	/bin/echo 1 > ${client_connect_deferred_file}
	log_session "connected"
fi

exit 0
