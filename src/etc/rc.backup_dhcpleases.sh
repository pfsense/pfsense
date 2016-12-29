#!/bin/sh

# Save the DHCP lease database to the config path.
if [ -d "/var/dhcpd/var/db" ]; then
	cd / && tar -czf /cf/conf/dhcpleases.tgz -C / var/dhcpd/var/db/
fi
