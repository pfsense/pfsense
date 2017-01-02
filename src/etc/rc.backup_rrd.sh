#!/bin/sh

: ${RRDDBPATH:=/var/db/rrd}
: ${CF_CONF_PATH:=/cf/conf}

# Save the rrd databases to the config path.
if [ -d "${RRDDBPATH}" ]; then
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_rw
	[ -f "${CF_CONF_PATH}/rrd.tgz" ] && /bin/rm -f "${CF_CONF_PATH}"/rrd.tgz

	tgzlist=""

	for rrdfile in "${RRDDBPATH}"/*.rrd ; do
		xmlfile="${rrdfile%.rrd}.xml"
		tgzfile="${rrdfile%.rrd}.tgz"
		/usr/bin/nice -n20 /usr/local/bin/rrdtool dump "$rrdfile" "$xmlfile"
		/usr/bin/tar -czf "${tgzfile}" -C / ${xmlfile#/}
		/bin/rm -f ${xmlfile}
		tgzlist="${tgzlist} @${tgzfile}"
	done

	if [ -n "${tgzlist}" ]; then
		/usr/bin/tar -czf "${CF_CONF_PATH}/rrd.tgz" -C / ${tgzlist}
		/bin/rm -f "${RRDDBPATH}"/*.tgz
	fi
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_ro
fi

