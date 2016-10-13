#!/bin/sh

: ${DBPATH:=/var/log}
: ${CF_CONF_PATH:=/cf/conf}

: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store}

# Save the logs database to the RAM disk store.
if [ -d "${DBPATH}" ]; then
	echo -n "Saving Logs to RAM disk store...";

	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_rw
	[ -f "${RAM_Disk_Store}/logs.tgz" ] && /bin/rm -f "${RAM_Disk_Store}/logs.tgz"

	if [ ! -d "${RAM_Disk_Store}" ]; then
		mkdir -p "${RAM_Disk_Store}"
	fi

	cd / && /usr/bin/tar -czf "${RAM_Disk_Store}/logs.tgz" -C / "${DBPATH#/}/"

	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_ro
	echo "done.";
fi
