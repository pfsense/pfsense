#!/bin/sh

# Update bogons file
# Part of the pfSense project
# www.pfsense.com

echo "rc.update_bogons.sh is starting up." | logger

# Sleep for that time, unless an argument is specified.
if [ "$1" = "" ]; then
	if [ ! -f /var/run/donotsleep_bogons ]; then
		# Grab a random value  
		value=`od -A n -d -N2 /dev/random | awk '{ print $1 }'`
		echo "rc.update_bogons.sh is sleeping for $value" | logger
    	sleep $value
	fi
fi    

echo "rc.update_bogons.sh is beginning the update cycle." | logger

/etc/rc.conf_mount_rw
/usr/bin/fetch -q -o /tmp/bogons "http://files.pfsense.org/bogon-bn-nonagg.txt"
if [ ! -f /tmp/bogons ]; then
	echo "Could not download http://files.pfsense.org/bogon-bn-nonagg.txt" | logger
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh & 
	exit
fi
egrep -v "^192.168.0.0/16|^172.16.0.0/12|^10.0.0.0/8" /tmp/bogons > /etc/bogons
/etc/rc.conf_mount_ro
RESULT=`/sbin/pfctl -t bogons -T replace -f /etc/bogons 2>&1`
rm /tmp/bogons
echo "Bogons file downloaded:  $RESULT" | logger
