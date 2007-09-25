#!/bin/sh

mkdir -p cd /var/db/dnscache 

while [ /bin/true ]; do
        cd /var/db/dnscache 
        needsfilterreload=0
        for FILE in *; do
                OLDIP=`cat $FILE`
                NEWIP=`host $FILE | grep -v NXDOMAIN | awk '{ print $4 }'`
                if [ "$OLDIP" != "$NEWIP" ]; then
                        needsfilterreload=1
                fi
        done
        if [ "$needsfilterreload" -gt 0 ]; then
                /etc/rc.filter_configure_sync
        fi
        sleep 480
done
