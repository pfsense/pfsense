#!/bin/sh

# Save the rrd databases to the config path.
if [ -d "/var/db/rrd" ]; then
	/etc/rc.conf_mount_rw
	cd / && tar -czf /cf/conf/rrd.tgz -C / var/db/rrd/*.rrd
	/etc/rc.conf_mount_ro
fi
