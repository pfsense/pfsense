#!/bin/sh
#
# pfSense-nanobsd_to_fullinstall.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2015-2016 Rubicon Communications, LLC (Netgate)
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

export PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin

platform=$(cat /etc/platform)

if [ "${platform}" != "nanobsd" ]; then
	echo "ERROR: This script is supposed to run on a nanobsd installation"
	exit 1
fi

if [ "$(id -u)" != "0" ]; then
	echo "Please run as root/admin"
	exit 1
fi

cur_partition=$(mount -p / | cut -f1)

if ! echo "${cur_partition}" | grep -iq "pfsense0"; then
	echo "You are trying to run this script on a system that booted from"
	echo "secondary partition and it will not work."
	echo ""
	echo "On GUI, go to Diagnostics -> NanoBSD, duplicate primary slice"
	echo "to secondary and then Switch Bootup slice. Reboot the system"
	echo "and try again."

	exit 1
fi

update_partition=$(echo ${cur_partition} \
    | sed -e 's,0$,2,; s,1$,0,; s,2$,1,')

if [ ! -e "${update_partition}" ]; then
	echo "Secondary partition (${update_partition}) not found"
	exit 1
fi

# Remove /dev
update_partition=$(echo ${update_partition} | sed 's,^/dev/,,')
update_dev=$(glabel status -s \
    | awk "\$1 == \"${update_partition}\" { print \$3 }" | sed 's/s2a//')

if [ -z "${update_dev}" -o ! -e "/dev/${update_dev}" ]; then
	echo "Device (${update_dev}) not found"
	exit 1
fi

update_dev="/dev/${update_dev}"

cur_repo=/usr/local/etc/pkg/repos/pfSense.conf

/etc/rc.conf_mount_rw

if ! grep -q 'v2_4' $cur_repo; then
	next_repo=/usr/local/share/pfSense/pkg/repos/pfSense-repo-next.conf
	if [ ! -f $next_repo ]; then
		echo "Next Major version repository is missing"
		exit 1
	fi

	if [ "$(realpath $cur_repo)" != $next_repo ]; then
		echo "On GUI, go to System -> Update -> Update Settings and"
		echo "chose 'Next major version (2.4)' on 'Branch' option then"
		echo "try again"
		exit 1
	fi
fi

pkg set -y -o security/pfSense-base-nanobsd:security/pfSense-base \
    pfSense-base-nanobsd
pkg set -y -n pfSense-base-nanobsd:pfsense-base \
    pfSense-base-nanobsd

echo pfSense > /etc/platform

sed -i .bkp -e 's/ro,sync/rw/' /etc/fstab

gpart delete -i 2 ${update_dev}

touch /root/force_growfs

pfSense-upgrade -y
