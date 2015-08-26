#!/bin/sh

NOTSYNCED="true"
MAX_ATTEMPTS=3
SERVER=`/bin/cat /cf/conf/config.xml | /usr/bin/grep timeservers | /usr/bin/cut -d">" -f2 | /usr/bin/cut -d"<" -f1`
if [ "${SERVER}" = "" ]; then
	exit
fi

/bin/pkill -f ntpdate_sync_once.sh

ATTEMPT=1
# Loop until we're synchronized, but for a set number of attempts so we don't get stuck here forever.
while [ "$NOTSYNCED" = "true" ] && [ ${ATTEMPT} -le ${MAX_ATTEMPTS} ]; do
	# Ensure that ntpd and ntpdate are not running so that the socket we want will be free.
	while [ true ]; do
		/usr/bin/killall ntpdate 2>/dev/null
		/bin/pgrep ntpd
		if [ $? -eq 0 ]; then
			/usr/bin/killall ntpd 2>/dev/null
		else
			break
		fi
	done
	sleep 1
	/usr/local/sbin/ntpdate -s -t 5 ${SERVER}
	if [ "$?" = "0" ]; then
		NOTSYNCED="false"
	else
		sleep 5
		ATTEMPT=`expr ${ATTEMPT} + 1`
	fi
done

if [ "$NOTSYNCED" = "true" ]; then
	echo "Giving up on time sync after ${MAX_ATTEMPTS} attempts." | /usr/bin/logger -t ntp;
else
	echo "Successfully synced time after ${ATTEMPT} attempts." | /usr/bin/logger -t ntp;
fi

if [ -f /var/etc/ntpd.conf ]; then
	echo "Starting NTP Daemon." | /usr/bin/logger -t ntp;
	/usr/local/sbin/ntpd -g -c /var/etc/ntpd.conf -p /var/run/ntpd.pid
else
	echo "NTP configuration file missing, not starting daemon." | /usr/bin/logger -t ntp;
fi
