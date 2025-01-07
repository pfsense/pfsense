#!/bin/sh
#
# rc.backup_dhcpleases.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2024-2025 Rubicon Communications, LLC (Netgate)
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

BACKUPTABLE="
#   PATH				PATTERN		RECURSE		DESCRIPTION
	/var/dhcpd/var/db	*			N			DHCPD leases
	/var/lib/kea		*			N			Kea leases
"

: ${CF_CONF_PATH:="/cf/conf"}
: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store}
: ${BACKUP_FILE:="${RAM_Disk_Store}/dhcpleases.tgz"}

FIND=/usr/bin/find
MKTEMP=/usr/bin/mktemp
RM=/bin/rm
TAR=/usr/bin/tar
GZIP=/usr/bin/gzip

mkdir_quiet() { /bin/mkdir $@ >/dev/null 2>&1; }

# Create a temporary tarball for appending files
TMP_FILE="$(${MKTEMP})" || {
	echo "mktemp: unable to create temporary archive"
	exit 1
}

# Make sure we cleanup after ourselves
trap "${RM} -f ${TMP_FILE} >/dev/null 2>&1;" EXIT INT TERM

# Read backup table and append files to tarball as needed
echo "${BACKUPTABLE}" | \
while IFS=$'\t' read -r _path _pattern _recurse _desc; do
	case "${_path}" in "f#"*|"") continue;; esac
	if [ -d "${_path}" ]; then
		_files="$(${FIND} "${_path}" \
			$(case "${_recurse}" in [nN]) echo "-maxdepth 1"; esac) \
			-name "${_pattern}" \
			-type f \
			-print)"
		if [ -n "${_files}" ]; then
			echo -n "Backing up ${_desc} to RAM disk store..."
			echo "${_files}" | \
			${TAR} -rf "${TMP_FILE}" -T - 2>/dev/null || {
				echo "failed."
				continue
			}
			echo "done."
		fi
	fi
done

# Compress resulting tarball
if [ -s "${TMP_FILE}" ]; then
	echo -n "Compressing RAM disk store..."
	[ -d "${BACKUP_FILE%/*}" ] || \
		mkdir_quiet -p "${BACKUP_FILE%/*}" && \
			${GZIP} -c "${TMP_FILE}" > "${BACKUP_FILE}" || {
				echo "failed."
				exit 2
	}
	echo "done."
fi
