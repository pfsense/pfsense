#!/bin/sh

for items in `ps auxcwwl | awk '/slbd/{print $3}'|awk -F"." '{print $1}'`
do
        if [ "$items" -gt "86" ]; then
                killall slbd
				sleep 2
                killall -9 slbd
                echo "Resetting slbd due to high cpu usage: ${items}%" | logger
                /usr/local/sbin/slbd -c/var/etc/slbd.conf -r5000
        fi
done
