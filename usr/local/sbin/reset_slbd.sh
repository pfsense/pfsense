#!/bin/sh

FAILURE=0

for items in `ps auxcwwl | awk '/slbd/{print $3}'|awk -F"." '{print $1}'`
do
        if [ "$items" -ge "20" ]; then
                FAILURE=`expr $FAILURE + 1`
        fi
done

if [ "$FAILURE" -ge "1" ]; then
	killall -9 slbd	
	sleep 2
	echo "Resetting slbd due to high cpu usage: ${items}%" | logger
	/usr/local/sbin/slbd -c/var/etc/slbd.conf -r5000
	FAILURE=0
fi
