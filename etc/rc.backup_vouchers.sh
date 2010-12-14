#!/bin/sh

# Save the voucher databases to the config path.
if [ -d "/var/db/" ]; then
	/etc/rc.conf_mount_rw
	cd / && tar -czf /cf/conf/vouchers.tgz -C / var/db/voucher_*.db
	/etc/rc.conf_mount_ro
fi
