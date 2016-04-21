#!/bin/sh

: ${DBPATH:=/var/db/aliastables}
: ${CF_CONF_PATH:=/cf/conf}

: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store/${DBPATH}}

# Save the alias tables database to the RAM disk store.
if [ -d "${DBPATH}" ]; then
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_rw

	if [ ! -d "${RAM_Disk_Store}" ]; then
		mkdir -p "${RAM_Disk_Store}"
	fi

	for aliastablefile in "${DBPATH}"/* ; do
		filename="$(basename ${aliastablefile})"
		if [ ! -f "${RAM_Disk_Store}/${filename}.tgz" ]; then
			cd / && /usr/bin/tar -czf "${RAM_Disk_Store}/${filename}.tgz" -C / "${DBPATH}/${filename}"
		fi
	done

	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_ro
fi
