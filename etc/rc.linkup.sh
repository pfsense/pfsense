#!/bin/sh

LINKUP=`cat /tmp/rc.linkup`
/usr/local/bin/php /etc/rc.linkup $LINKUP
rm /tmp/rc.linkup
