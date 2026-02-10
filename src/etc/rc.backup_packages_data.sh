#!/bin/sh
#
# rc.backup_packages_data.sh
#

: ${DBPATH:=/var/db}
: ${CF_CONF_PATH:=/cf/conf}

: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store}


# Get the internal name of packages.
xml_rootobj=$(/usr/local/bin/php -n /usr/local/sbin/read_global_var xml_rootobj pfsense 2>/dev/null)
path="${xml_rootobj}/installedpackages/package/ramdisk_dir_names"
config=${config:-"/cf/conf/config.xml"}
DIR_NAMES=$(/usr/local/bin/xmllint --xpath "//${path}/text()" "${config}" 2>/dev/null | tr -d '\r\n' | tr -d "'*$\`\"\\")

result=""
for NAME in ${DIR_NAMES}; do
	# Avoid duplicates.
	NAME=$(basename "${NAME}")
	case " ${result} " in
		*" ${NAME} "*) continue;;
		*) result="${result} ${NAME}";;
	esac

	# Save package databases to the RAM disk store.
	if [ -d "${DBPATH}/${NAME}" ]; then
		echo -n "Saving ${NAME} data to RAM disk store...";
		
		[ -f "${RAM_Disk_Store}/${NAME}.tgz" ] && /bin/rm -f "${RAM_Disk_Store}/${NAME}.tgz"

		if [ ! -d "${RAM_Disk_Store}" ]; then
			mkdir -p "${RAM_Disk_Store}"
		fi

		/usr/bin/tar -czf "${RAM_Disk_Store}/${NAME}.tgz" -C / "${DBPATH#/}/${NAME}/"

		echo "done.";
	fi
done
