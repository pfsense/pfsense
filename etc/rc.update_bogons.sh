#!/bin/sh

# Update bogons file
# Part of the pfSense project
# www.pfsense.com

/etc/rc.conf_mount_rw
/usr/bin/fetch -q -o /tmp/bogons "http://www.cymru.com/Documents/bogon-bn-nonagg.txt"
egrep -v "^192.168.0.0/16|^172.16.0.0/12|^10.0.0.0/8" /tmp/bogons > /etc/bogons
/etc/rc.conf_mount_ro
/sbin/pfctl -t bogons -T replace -f /etc/bogons
