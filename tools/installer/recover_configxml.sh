#!/bin/sh
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2017-2021 Rubicon Communications, LLC (Netgate)
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
#
# $FreeBSD$

# Recover config.xml

# Create a mount point and a place to store the recovered configuration
recovery_mount=/tmp/mnt_recovery
recovery_dir=/tmp/recovered_config
mkdir -p ${recovery_mount}
mkdir -p ${recovery_dir}

# Find list of potential target disks, which must be FreeBSD and either UFS or ZFS
target_disks=`/sbin/gpart show -p | /usr/bin/awk '/(freebsd-ufs|freebsd-zfs)/ {print $3;}'`

target_list=""
for try_device in ${target_disks} ; do
	# Add filesystem details (type and size)
	fs_details="`/sbin/gpart show -p | /usr/bin/grep \"[[:space:]]${try_device}[[:space:]]\" | /usr/bin/awk '{print $4, $5;}'`"

	# Add this disk to the list of potential targets
	target_list="${target_list} \"${try_device}\" \"${fs_details}\""
done

# Display a menu with all of the disk choices located above
if [ -n "${target_list}" ]; then
	exec 3>&1
	recover_disk_choice=`echo ${target_list} | xargs dialog --backtitle "pfSense Installer" \
		--title "Recover config.xml" \
		--menu "Select the partition containing config.xml" \
		0 0 0 2>&1 1>&3` || exit 1
	exec 3>&-
else
	echo "No suitable disk partitions found."
fi

recover_disk=${recover_disk_choice}

# If the user made a choice, try to recover
if [ -n "${recover_disk}" ] ; then
	# Find the filesystem type of the selected partition
	fs_type="`/sbin/gpart show -p | /usr/bin/grep \"[[:space:]]${recover_disk}[[:space:]]\" | /usr/bin/awk '{print $4;}'`"
	# Remove "freebsd-", leaving us with either "ufs" or "zfs".
	fs_type=${fs_type#freebsd-}

	echo "Attempting to recover config.xml from ${recover_disk}."
	if [ "${fs_type}" == "ufs" ]; then
		# UFS Recovery, attempt to mount but also attempt cleanup if it fails.

		mount_command="/sbin/mount -t ${fs_type} /dev/${recover_disk} ${recovery_mount}"
		${mount_command} 2>/dev/null
		mount_rc=$?
		attempts=0

		# Try to run fsck up to 10 times and remount, in case the parition is dirty and needs cleanup
		while [ ${mount_rc} -ne 0 -a ${attempts} -lt 10 ]; do
			echo "Unable to mount ${recover_disk}, running a disk check and retrying."
			/sbin/fsck -y -t ${fs_type} ${recover_disk}
			${mount_command} 2>/dev/null
			mount_rc=$?
			attempts=$((attempts+1))
		done
		if [ ${mount_rc} -ne 0 ]; then
			echo "Unable to mount ${recover_disk} for config.xml recovery."
			exit 1
		fi
	else
		# ZFS Recovery works different than UFS, needs special handling
		if [ "${fs_type}" == "zfs" ]; then
			# Load KLD for ZFS support
			/sbin/kldload zfs
			# Import pool (name=zroot) with alternate mount
			/sbin/zpool import -R ${recovery_mount} -f zroot
			# Mount the default root directory of the previous install
			/sbin/mount -t zfs zroot/ROOT/default ${recovery_mount}
		fi
	fi

	# In either FS type case, the previous root is now mounted under ${recovery_mount}, so check for a config
	if [ -r ${recovery_mount}/cf/conf/config.xml -a -s ${recovery_mount}/cf/conf/config.xml ]; then
		/bin/cp ${recovery_mount}/cf/conf/config.xml ${recovery_dir}/config.xml
		echo "Recovered config.xml from ${recover_disk}, stored in ${recovery_dir}."
	else
		echo "${recover_disk} does not contain a readable config.xml for recovery."
		exit 1
	fi

	# Cleanup. Unmount the disk partition.
	/sbin/umount ${recovery_mount}

	# ZFS cleanup, export the pool and then unload ZFS KLD.
	if [ "${fs_type}" == "zfs" ]; then
		/sbin/zpool export -f zroot
		/sbin/kldunload zfs
	fi
fi
