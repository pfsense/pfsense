#!/bin/sh
#
# rc.backup_logs.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2016 Electric Sheep Fencing
# Copyright (c) 2016-2021 Rubicon Communications, LLC (Netgate)
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

: ${DBPATH:=/var/log}
: ${CF_CONF_PATH:=/cf/conf}

: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store}

# Save the logs database to the RAM disk store.
if [ -d "${DBPATH}" ]; then
	echo -n "Saving Logs to RAM disk store...";

	[ -f "${RAM_Disk_Store}/logs.tgz" ] && /bin/rm -f "${RAM_Disk_Store}/logs.tgz"

	if [ ! -d "${RAM_Disk_Store}" ]; then
		mkdir -p "${RAM_Disk_Store}"
	fi

	/usr/bin/tar -czf "${RAM_Disk_Store}/logs.tgz" -C / "${DBPATH#/}/"

	echo "done.";
fi
