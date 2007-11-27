#!/bin/sh

# Update bogons file
# Part of the pfSense project
# www.pfsense.com

# Grab a random value 
value=`hexdump -n1 -e\"%u\" /dev/random`

# Sleep for that time.
sleep $value

/etc/rc.conf_mount_rw
/usr/bin/fetch -q -o /tmp/bogons "http://www.pfsense.com/mirrors/bogon-bn-nonagg.txt"
if [ ! -f /tmp/bogons ]; then
	echo "Could not download http://www.pfsense.com/mirrors/bogon-bn-nonagg.txt" | logger
	exit
fi
egrep -v "^192.168.0.0/16|^172.16.0.0/12|^10.0.0.0/8" /tmp/bogons > /etc/bogons
/etc/rc.conf_mount_ro
RESULT=`/sbin/pfctl -t bogons -T replace -f /etc/bogons 2>&1`
rm /tmp/bogons
echo "Bogons file downloaded:  $RESULT" | logger
