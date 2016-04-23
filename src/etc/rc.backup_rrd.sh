#!/bin/sh

: ${DBPATH:=/var/db/rrd}
: ${CF_CONF_PATH:=/cf/conf}

: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store}

# Save the rrd databases to the RAM disk store.
if [ -d "${DBPATH}" ]; then
	echo -n "Saving RRD to RAM disk store...";

	[ -f "${RAM_Disk_Store}/rrd.tgz" ] && /bin/rm -f "${RAM_Disk_Store}"/rrd.tgz

	if [ ! -d "${RAM_Disk_Store}" ]; then
		mkdir -p "${RAM_Disk_Store}"
	fi

	tgzlist=""

	for rrdfile in "${DBPATH}"/*.rrd ; do
		xmlfile="${rrdfile%.rrd}.xml"
		tgzfile="${rrdfile%.rrd}.tgz"
		/usr/bin/nice -n20 /usr/local/bin/rrdtool dump "$rrdfile" "$xmlfile"
		/usr/bin/tar -czf "${tgzfile}" -C / ${xmlfile#/}
		/bin/rm -f ${xmlfile}
		tgzlist="${tgzlist} @${tgzfile}"
	done

	if [ -n "${tgzlist}" ]; then
		/usr/bin/tar -czf "${CF_CONF_PATH}/rrd.tgz" -C / ${tgzlist}
		/bin/rm -f "${DBPATH}"/*.tgz
	fi

	echo "done.";
fi
