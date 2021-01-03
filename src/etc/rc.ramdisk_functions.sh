#!/bin/sh
#
# rc.ramdisk_functions.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2020-2021 Rubicon Communications, LLC (Netgate)
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

# Source like so:
#     . /etc/rc.ramdisk_functions.sh
# Then use these variables and functions wherever RAM disk operations are needed

RAMDISK_FLAG_FILE=/conf/ram_disks_failed
RAMDISK_DEFAULT_SIZE_tmp=40
RAMDISK_DEFAULT_SIZE_var=60

# Check if RAM disks are enabled in config.xml
ramdisk_check_enabled () {
	[ "$(/usr/local/sbin/read_xml_tag.sh boolean system/use_mfs_tmpvar)" = "true" ]
	return $?
}

# Checks that RAM disks are both enabled and that they have not failed
ramdisk_is_active () {
	ramdisk_check_enabled && [ ! -e ${RAMDISK_FLAG_FILE} ]
	return $?
}

# Checks if RAM disk setup failed
ramdisk_failed () {
	[ -e ${RAMDISK_FLAG_FILE} ]
	return $?
}

# Resets the RAM disk failure status
ramdisk_reset_status () {
	if [ -f ${RAMDISK_FLAG_FILE} ]; then
		rm -f ${RAMDISK_FLAG_FILE}
	fi
}

# Checks if RAM disks were active on a previous boot (or active now)
ramdisk_was_active() {
	# If /var is on a memory disk, then RAM disks are active now or were active and recently disabled
	DISK_NAME=`/bin/df /var/db/rrd | /usr/bin/tail -1 | /usr/bin/awk '{print $1;}'`
	DISK_TYPE=`/usr/bin/basename ${DISK_NAME} | /usr/bin/cut -c1-2`
	[ "${DISK_TYPE}" = "md" ]
	return $?
}

# Echos the effective size of the given RAM disk (var or tmp)
# If set, use that value. If unset, use the default value
# Usage example:
#   tmpsize = $( ramdisk_get_size tmp )
#   varsize = $( ramdisk_get_size var )
ramdisk_get_size () {
	NAME=${1}
	DEFAULT_SIZE=$(eval echo \${RAMDISK_DEFAULT_SIZE_${NAME}})

	SIZE=$(/usr/local/sbin/read_xml_tag.sh string system/use_mfs_${NAME}_size)
	if [ -n "${SIZE}" ] && [ ${SIZE} -gt 0 ]; then
		echo ${SIZE}
	else
		echo ${DEFAULT_SIZE}
	fi
	return 0
}

# Tests if the current total RAM disk size can fit in free kernel memory
ramdisk_check_size () {
	tmpsize=$( ramdisk_get_size tmp )
	varsize=$( ramdisk_get_size var )
	# Check available kmem
	KMEM_FREE=$( /sbin/sysctl -n vm.kmem_map_free )
	# Convert to MB
	KMEM_FREE=$( /bin/expr ${KMEM_FREE} / 1024 / 1024 )
	# Total size of desired RAM disks
	KMEM_NEED=$( /bin/expr ${tmpsize} + ${varsize} )
	# echo "FREE: ${KMEM_FREE} NEED: ${KMEM_NEED}"
	[ ${KMEM_FREE} -gt ${KMEM_NEED} ]
	return $?
}

# Attempt to mount the given RAM disk (var or tmp)
# Usage:
#   ramdisk_try_mount tmp
#   ramdisk_try_mount var
ramdisk_try_mount () {
	NAME=$1
	if [ ramdisk_check_size ]; then
		SIZE=$(eval echo \${${NAME}size})m
		/sbin/mdmfs -S -M -s ${SIZE} md /${NAME}
		return $?
	else
		return 1;
	fi
}

# If the install has RAM disks, or if the full install _was_ using RAM disks, make a backup.
ramdisk_make_backup () {
	if ramdisk_is_active || ramdisk_was_active; then
		echo "Backing up RAM disk contents"
		/etc/rc.backup_aliastables.sh
		/etc/rc.backup_rrd.sh
		/etc/rc.backup_dhcpleases.sh
		/etc/rc.backup_logs.sh
	fi
}

# Relocate the pkg database to a specific given location, either disk (/var) or
#   to its safe location for use with RAM disks (/root/var)
# Usage:
#   ramdisk_relocate_pkgdb disk
#   ramdisk_relocate_pkgdb ram
ramdisk_relocate_pkgdb () {
	if [ ${1} = "disk" ]; then
		local SRC=/root
		local DST=
	else
		local SRC=
		local DST=/root
	fi

	echo "Moving pkg database for ${1} storage"
	if [ -d ${SRC}/var/db/pkg ]; then
		echo "Clearing ${DST}/var/db/pkg"
		rm -rf ${DST}/var/db/pkg 2>/dev/null
		/bin/mkdir -p ${DST}/var/db
		echo "Moving ${SRC}/var/db/pkg to ${DST}/var/db/"
		mv -f ${SRC}/var/db/pkg ${DST}/var/db
	fi
	if [ -d ${SRC}/var/cache/pkg ]; then
		echo "Clearing ${DST}/var/cache/pkg"
		rm -rf ${DST}/var/cache/pkg 2>/dev/null
		/bin/mkdir -p ${DST}/var/cache
		echo "Moving ${SRC}/var/cache/pkg to ${DST}/var/cache/"
		mv -f ${SRC}/var/cache/pkg ${DST}/var/cache
	fi
}

# Relocate the pkg database as needed based on RAM disk options and status
ramdisk_relocate_pkgdb_all () {
	unset MOVE_PKG_DATA
	unset USE_RAMDISK
	if ramdisk_check_enabled; then
		USE_RAMDISK=true
	fi
	if [ -z "${USE_RAMDISK}" -a -f /root/var/db/pkg/local.sqlite ]; then
		if ramdisk_failed; then
			echo "Not relocating pkg db due to previous RAM disk failure."
			return 1
		fi
		# If RAM disks are disabled, move files back into place
		MOVE_PKG_DATA=1
		ramdisk_relocate_pkgdb disk
	elif [ -n "${USE_RAMDISK}" -a -f /var/db/pkg/local.sqlite ]; then
		# If RAM disks are enabled, move files to a safe place
		MOVE_PKG_DATA=1
		ramdisk_relocate_pkgdb ram
	fi
}

# Setup symbolic links for the pkg database
ramdisk_link_pkgdb () {
	/bin/mkdir -p /var/db /var/cache
	if [ ! -e /var/db/pkg ]; then
		echo "Creating pkg db symlink"
		ln -sf ../../root/var/db/pkg /var/db/pkg
	fi
	if [ ! -e /var/cache/pkg ]; then
		echo "Creating pkg cache symlink"
		ln -sf ../../root/var/cache/pkg /var/cache/pkg
	fi
}

# Either unmount or mount /var and /tmp in ZFS as needed to avoid conflicts with
#   active RAM disk options.
ramdisk_fixup_zfs () {
	# Mount /var and /tmp on ZFS filesystems when necessary
	if [ ${1} = "mount" ]; then
		echo "Remounting ZFS volumes"
		zfs mount -a
	else
		zfs list -H -o name,mountpoint |
		    while read volume mountpoint; do
			[ "${mountpoint}" != "/var" -a "${mountpoint}" != "/tmp" ] \
				&& continue
			echo "Dismounting ZFS volume ${volume} for RAM disk"
			/sbin/zfs umount ${volume}
		done
	fi
}
