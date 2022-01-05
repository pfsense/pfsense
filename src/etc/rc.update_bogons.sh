#!/bin/sh
#
# rc.update_bogons.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Based on src/etc/rc.d/savecore from FreeBSD
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

# Global variables
proc_error=""

do_not_send_uniqueid=$(/usr/local/sbin/read_xml_tag.sh boolean system/do_not_send_uniqueid)
if [ "${do_not_send_uniqueid}" != "true" ]; then
	uniqueid=$(/usr/sbin/gnid)
	export HTTP_USER_AGENT="${product}/${product_version}:${uniqueid}"
else
	export HTTP_USER_AGENT="${product}/${product_version}"
fi

# Download and extract if necessary
process_url() {
	local file=$1
	local url=$2
	local filename=${url##*/}
	local ext=${filename#*.}

	/usr/bin/fetch -a -w 600 -T 30 -q -o $file "${url}"

	if [ ! -f $file ]; then
		echo "Could not download ${url}" | logger
		proc_error="true"
	fi

	case "$ext" in
		tar)
			mv $file $file.tmp
			/usr/bin/tar -xf $file.tmp -O > $file 2> /dev/null
			;;
		tar.gz)
			mv $file $file.tmp
			/usr/bin/tar -xzf $file.tmp -O > $file 2> /dev/null
			;;
		tgz)
			mv $file $file.tmp
			/usr/bin/tar -xzf $file.tmp -O > $file 2> /dev/null
			;;
		tar.bz2)
			mv $file $file.tmp
			/usr/bin/tar -xjf $file.tmp -O > $file 2> /dev/null
			;;
		*)
			;;
	esac

	if [ -f $file.tmp ]; then
		rm $file.tmp
	fi

	if [ ! -f $file ]; then
		echo "Could not extract ${filename}" | logger
		proc_error="true"
	fi
}

echo "rc.update_bogons.sh is starting up." | logger

# Sleep for some time, unless an argument is specified.
if [ "$1" = "" ]; then
	# Grab a random value
	value=`od -A n -d -N2 /dev/random | awk '{ print $1 }'`
	echo "rc.update_bogons.sh is sleeping for $value" | logger
	sleep $value
fi

echo "rc.update_bogons.sh is beginning the update cycle." | logger

# Load custom bogon configuration
if [ -f /var/etc/bogon_custom ]; then
	. /var/etc/bogon_custom
fi

# Set default values if not overriden
v4url=${v4url:-"https://files.netgate.com/lists/fullbogons-ipv4.txt"}
v6url=${v6url:-"https://files.netgate.com/lists/fullbogons-ipv6.txt"}
v4urlcksum=${v4urlcksum:-"${v4url}.md5"}
v6urlcksum=${v6urlcksum:-"${v6url}.md5"}

process_url /tmp/bogons "${v4url}"
process_url /tmp/bogonsv6 "${v6url}"

if [ "$proc_error" != "" ]; then
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh &
	exit
fi

BOGON_V4_CKSUM=`/usr/bin/fetch -T 30 -q -o - "${v4urlcksum}" | awk '{ print $4 }'`
ON_DISK_V4_CKSUM=`md5 /tmp/bogons | awk '{ print $4 }'`
BOGON_V6_CKSUM=`/usr/bin/fetch -T 30 -q -o - "${v6urlcksum}" | awk '{ print $4 }'`
ON_DISK_V6_CKSUM=`md5 /tmp/bogonsv6 | awk '{ print $4 }'`

if [ "$BOGON_V4_CKSUM" = "$ON_DISK_V4_CKSUM" ] || [ "$BOGON_V6_CKSUM" = "$ON_DISK_V6_CKSUM" ]; then
	ENTRIES_MAX=`pfctl -s memory | awk '/table-entries/ { print $4 }'`

	if [ "$BOGON_V4_CKSUM" = "$ON_DISK_V4_CKSUM" ]; then
		ENTRIES_TOT=`pfctl -vvsTables | awk '/Addresses/ {s+=$2}; END {print s}'`
		ENTRIES_V4=`pfctl -vvsTables | awk '/-\tbogons$/ {getline; print $2}'`
		LINES_V4=`wc -l /tmp/bogons | awk '{ print $1 }'`
		if [ $ENTRIES_MAX -gt $((2*ENTRIES_TOT-${ENTRIES_V4:-0}+LINES_V4)) ]; then
			egrep -v "^192.168.0.0/16|^172.16.0.0/12|^10.0.0.0/8" /tmp/bogons > /etc/bogons
			RESULT=`/sbin/pfctl -t bogons -T replace -f /etc/bogons 2>&1`
			echo "$RESULT" | awk '{ print "Bogons V4 file downloaded: " $0 }' | logger
		else
			echo "Not updating IPv4 bogons (increase table-entries limit)" | logger
		fi
		rm /tmp/bogons
	else
		echo "Could not download ${v4url} (checksum mismatch)" | logger
		checksum_error="true"
	fi

	if [ "$BOGON_V6_CKSUM" = "$ON_DISK_V6_CKSUM" ]; then
		BOGONS_V6_TABLE_COUNT=`pfctl -sTables | grep ^bogonsv6$ | wc -l | awk '{ print $1 }'`
		ENTRIES_TOT=`pfctl -vvsTables | awk '/Addresses/ {s+=$2}; END {print s}'`
		LINES_V6=`wc -l /tmp/bogonsv6 | awk '{ print $1 }'`
		if [ $BOGONS_V6_TABLE_COUNT -gt 0 ]; then
			ENTRIES_V6=`pfctl -vvsTables | awk '/-\tbogonsv6$/ {getline; print $2}'`
			if [ $ENTRIES_MAX -gt $((2*ENTRIES_TOT-${ENTRIES_V6:-0}+LINES_V6)) ]; then
				egrep -iv "^fc00::/7" /tmp/bogonsv6 > /etc/bogonsv6
				RESULT=`/sbin/pfctl -t bogonsv6 -T replace -f /etc/bogonsv6 2>&1`
				echo "$RESULT" | awk '{ print "Bogons V6 file downloaded: " $0 }' | logger
			else
				echo "Not saving or updating IPv6 bogons (increase table-entries limit)" | logger
			fi
		else
			if [ $ENTRIES_MAX -gt $((2*ENTRIES_TOT+LINES_V6)) ]; then
				egrep -iv "^fc00::/7" /tmp/bogonsv6 > /etc/bogonsv6
				echo "Bogons V6 file downloaded but not updating IPv6 bogons table because it is not in use." | logger
			else
				echo "Not saving IPv6 bogons table (IPv6 Allow is off and table-entries limit is potentially too low)" | logger
			fi
		fi
		rm /tmp/bogonsv6
	else
		echo "Could not download ${v6url} (checksum mismatch)" | logger
		checksum_error="true"
	fi
fi

if [ "$checksum_error" != "" ]; then
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh &
	exit
fi

echo "rc.update_bogons.sh is ending the update cycle." | logger
