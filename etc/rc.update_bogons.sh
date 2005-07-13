#!/bin/sh

# Update bogons file
# Part of the pfSense project
# www.pfsense.com

/etc/rc.conf_mount_rw
/usr/bin/fetch -q -o /etc/bogons "http://www.cymru.com/Documents/bogon-bn-nonagg.txt"
/etc/rc.conf_mount_ro
/sbin/pfctl -t bogons -T replace -f /etc/bogons
