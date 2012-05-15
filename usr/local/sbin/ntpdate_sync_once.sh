#!/bin/sh

NOTSYNCED="true"
SERVER=`cat /cf/conf/config.xml | grep timeservers | cut -d">" -f2 | cut -d"<" -f1`
pkill -f ntpdate_sync_once.sh

while [ "$NOTSYNCED" = "true" ]; do
	# Ensure that ntpd and ntpdate are not running so that the socket we want will be free.
	killall ntpd 2>/dev/null
	killall ntpdate
	sleep 1
	ntpdate -s -t 5 $SERVER
	if [ "$?" = "0" ]; then
		NOTSYNCED="false"
	fi
	sleep 5
done

/usr/local/sbin/ntpd -g -c /var/etc/ntpd.conf