#!/bin/sh
#write the uptime in seconds to the persistent log in /conf/
/etc/rc.conf_mount_rw
/bin/echo `date -j +%Y.%m.%d-%H:%M:%S` $1 >> /conf/$2.log
/etc/rc.conf_mount_ro
