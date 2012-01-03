#!/bin/sh

# Update bogons file
# Part of the pfSense project
# www.pfsense.com

echo "rc.update_bogons.sh is starting up." | logger

# Sleep for that time, unless an argument is specified.

if [ "$1" = "" ]; then
    # Grab a random value  
    value=`od -A n -d -N2 /dev/random | awk '{ print $1 }'`
    echo "rc.update_bogons.sh is sleeping for $value" | logger
    sleep $value
fi    

echo "rc.update_bogons.sh is beginning the update cycle." | logger

# Mount RW if needed
/etc/rc.conf_mount_rw

/usr/bin/fetch -q -o /tmp/bogons "http://files.pfsense.org/mirrors/bogon-bn-nonagg.txt"
if [ ! -f /tmp/bogons ]; then
	echo "Could not download http://files.pfsense.org/mirrors/bogon-bn-nonagg.txt" | logger
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh & 
	exit
fi

/usr/bin/fetch -q -o /tmp/bogonsv6 "http://files.pfsense.org/mirrors/fullbogons-ipv6.txt"
if [ ! -f /tmp/bogonsv6 ]; then
	echo "Could not download http://files.pfsense.org/mirrors/fullbogons-ipv6.txt" | logger
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh & 
	exit
fi


BOGON_MD5=`/usr/bin/fetch -q -o - "http://files.pfsense.org/mirrors/bogon-bn-nonagg.txt.md5" | awk '{ print $4 }'`
ON_DISK_MD5=`md5 /tmp/bogons | awk '{ print $4 }'`
if [ "$BOGON_MD5" = "$ON_DISK_MD5" ]; then
	egrep -v "^192.168.0.0/16|^172.16.0.0/12|^10.0.0.0/8" /tmp/bogons > /etc/bogons
	/etc/rc.conf_mount_ro
	RESULT=`/sbin/pfctl -t bogons -T replace -f /etc/bogons 2>&1`
	rm /tmp/bogons
	echo "Bogons file downloaded:  $RESULT" | logger
else
	echo "Could not download http://files.pfsense.org/mirrors/bogon-bn-nonagg.txt.md5 (md5 mismatch)" | logger
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh & 	
fi

BOGON_MD5=`/usr/bin/fetch -q -o - "http://files.pfsense.org/mirrors/fullbogons-ipv6.txt.md5" | awk '{ print $4 }'`
ON_DISK_MD5=`md5 /tmp/bogonsv6 | awk '{ print $4 }'`
if [ "$BOGON_MD5" = "$ON_DISK_MD5" ]; then
	egrep -v "^#" /tmp/bogonsv6 > /etc/bogonsv6
	/etc/rc.conf_mount_ro
	RESULT=`/sbin/pfctl -t bogonsv6 -T replace -f /etc/bogonsv6 2>&1`
	rm /tmp/bogonsv6
	echo "Bogons files downloaded:  $RESULT" | logger
else
	echo "Could not download http://files.pfsense.org/mirrors/fullbogons-ipv6.txt.md5 (md5 mismatch)" | logger
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh & 	
fi

echo "rc.update_bogons.sh is ending the update cycle." | logger

