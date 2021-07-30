#!/bin/sh

: ${DBPATH:=/var/db}
: ${CF_CONF_PATH:=/cf/conf}

: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store}

# Save the Captive Portals DB and Vouchers to the RAM disk store.
for dbname in captiveportal voucher ; do
	if ls ${DBPATH}/${dbname}*.db >/dev/null 2>&1; then
		if [ $dbname = "captiveportal" ]; then
			echo -n "Saving Captive Portal DB to RAM disk store..."
		else
			echo -n "Saving Captive Portal Vouchers to RAM disk store..."
		fi

		mkdir -p "${RAM_Disk_Store}"

		for cpfile in ${DBPATH}/${dbname}*.db ; do
			filename=$(basename ${cpfile})
			if [ ! -f "${RAM_Disk_Store}/${filename}.tgz" -o "${RAM_Disk_Store}/${filename}.tgz" -ot "${DBPATH#/}/${filename}" ]; then
				/bin/rm -f "${RAM_Disk_Store}/${filename}.tgz"
				/usr/bin/tar -czf "${RAM_Disk_Store}/${filename}.tgz" -C / "${DBPATH#/}/${filename}"
			fi
		done

		echo "done."
	fi
done
