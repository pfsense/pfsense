#!/bin/sh

: ${RRDDBPATH:=/var/db/rrd}
: ${CF_CONF_PATH:=/cf/conf}

# Save the rrd databases to the config path.
if [ -d "${RRDDBPATH}" ]; then
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_rw
	[ -f "${CF_CONF_PATH}/rrd.tgz" ] && /bin/rm -f "${CF_CONF_PATH}"/rrd.tgz
		
	for rrdfile in "${RRDDBPATH}"/*.rrd ; do
		xmlfile="${rrdfile%.rrd}.xml"
		/usr/bin/nice -n20 /usr/local/bin/rrdtool dump "$rrdfile" "$xmlfile"
		cd / && /usr/bin/tar -rf "${CF_CONF_PATH}"/rrd.tar -C / "${RRDDBPATH#/}"/*.xml
		/bin/rm -f "${RRDDBPATH}"/*.xml
	done
	if [ -f "${CF_CONF_PATH}/rrd.tar" ]; then
		/usr/bin/gzip "${CF_CONF_PATH}/rrd.tar"
		/bin/mv "${CF_CONF_PATH}/rrd.tar.gz" "${CF_CONF_PATH}/rrd.tgz"
	fi
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_ro
fi

