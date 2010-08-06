#!/bin/sh

# Save the DHCP lease database to the config path.
/etc/rc.conf_mount_rw
cd / && tar -czf /cf/conf/dhcpleases.tgz -C / var/dhcpd/var/db/
/etc/rc.conf_mount_ro
