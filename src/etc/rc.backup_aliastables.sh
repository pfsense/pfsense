#!/bin/sh

: ${DBPATH:=/var/db/aliastables}
: ${CF_CONF_PATH:=/cf/conf}

: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store}

# Save the alias tables database to the RAM disk store.
if [ -d "${DBPATH}" ]; then
	echo -n "Saving Alias Tables to RAM disk store...";

	if [ ! -d "${RAM_Disk_Store}" ]; then
		mkdir -p "${RAM_Disk_Store}"
	fi

	for aliastablefile in "${DBPATH}"/* ; do
		filename="$(basename ${aliastablefile})"
		if [ ! -f "${RAM_Disk_Store}/${filename}.tgz" -o "${RAM_Disk_Store}/${filename}.tgz" -ot "${DBPATH#/}/${filename}" ]; then
			[ -f "${RAM_Disk_Store}/${filename}.tgz" ] && /bin/rm -f "${RAM_Disk_Store}/${filename}.tgz"
			/usr/bin/tar -czf "${RAM_Disk_Store}/${filename}.tgz" -C / "${DBPATH#/}/${filename}"
		fi
	done

	echo "done.";
fi
