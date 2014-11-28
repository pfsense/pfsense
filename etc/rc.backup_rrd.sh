#!/bin/sh

: ${RRDDBPATH:=/var/db/rrd}
: ${CF_CONF_PATH:=/cf/conf}

# Save the rrd databases to the config path.
if [ -d "${RRDDBPATH}" ]; then
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_rw
	USE_ROOTFS_FOR_RRD_XML=`/usr/bin/grep -c use_rootfs_for_rrd_xml /cf/conf/config.xml`
	if [ ${USE_ROOTFS_FOR_RRD_XML} -gt 0 ]; then
		rrd_xml_path="/rrdxmltmp"
		mkdir -p ${rrd_xml_path}
	else
		rrd_xml_path="${RRDDBPATH}"
	fi
	for rrdfile in "${RRDDBPATH}"/*.rrd ; do
		xmlfiletmp="${rrdfile%.rrd}.xml"
		xmlfile="${rrd_xml_path}/${xmlfiletmp##*/}"
		/usr/bin/nice -n20 /usr/local/bin/rrdtool dump "$rrdfile" "$xmlfile"
	done
	cd / && tar -czf "${CF_CONF_PATH}"/rrd.tgz -C / "${rrd_xml_path#/}"/*.xml
	rm "${rrd_xml_path}"/*.xml
	[ -z "$NO_REMOUNT" ] && /etc/rc.conf_mount_ro
fi

