#!/bin/sh

NOTSYNCED="true"
SERVER=`cat /cf/conf/config.xml | grep timeservers | cut -d">" -f2 | cut -d"<" -f1`

while [ "$NOTSYNCED" = "true" ]; do
	ntpdate -s $SERVER
	if [ "$?" = "0" ]; then
		NOTSYNCED="false"
	fi
	sleep 5
done
