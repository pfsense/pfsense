#!/bin/sh

NOTSYNCED="true"

while [ "$NOTSYNCED" = "true" ]; do
	ntpdate "0.pfsense.pool.ntp.org"
	if [ "$?" = "0" ]; then
		NOTSYNCED="false"
	fi
done
