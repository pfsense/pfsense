#!/bin/sh

: ${RRDDBPATH:=/var/db/rrd}
: ${CF_CONF_PATH:=/cf/conf}

# Save the rrd databases to the config path.
if [ -d "${RRDDBPATH}" ]; then
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_rw
	for rrdfile in "${RRDDBPATH}"/*.rrd ; do
		xmlfile="${rrdfile%.rrd}.xml"
		/usr/bin/nice -n20 /usr/local/bin/rrdtool dump "$rrdfile" "$xmlfile"
	done
	cd / && tar -czf "${CF_CONF_PATH}"/rrd.tgz -C / "${RRDDBPATH#/}"/*.xml
	rm "${RRDDBPATH}"/*.xml
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_ro
fi

