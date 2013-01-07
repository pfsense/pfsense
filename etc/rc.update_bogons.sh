#!/bin/sh

# Update bogons file
# Part of the pfSense project
# www.pfsense.com

echo "rc.update_bogons.sh is starting up." | logger

# Sleep for some time, unless an argument is specified.
if [ "$1" = "" ]; then
    # Grab a random value  
    value=`od -A n -d -N2 /dev/random | awk '{ print $1 }'`
    echo "rc.update_bogons.sh is sleeping for $value" | logger
    sleep $value
fi    

echo "rc.update_bogons.sh is beginning the update cycle." | logger

# Load custom bogon configuration
if [ -f /var/etc/bogon_custom ]; then
	. /var/etc/bogon_custom
fi

# Set default values if not overriden
v4url=${v4url:-"http://files.pfsense.org/lists/fullbogons-ipv4.txt"}
v6url=${v6url:-"http://files.pfsense.org/lists/fullbogons-ipv6.txt"}
v4urlcksum=${v4urlcksum:-"${v4url}.md5"}
v6urlcksum=${v6urlcksum:-"${v6url}.md5"}

/usr/bin/fetch -q -o /tmp/bogons "${v4url}"
/usr/bin/fetch -q -o /tmp/bogonsv6 "${v6url}"
if [ ! -f /tmp/bogons ]; then
	echo "Could not download ${v4url}" | logger
	dl_error="true"
fi
if [ ! -f /tmp/bogonsv6 ]; then
	echo "Could not download ${v6url}" | logger
	dl_error="true"
fi

if [ "$dl_error" != "" ];then
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh & 
	exit
fi

BOGON_V4_CKSUM=`/usr/bin/fetch -q -o - "${v4urlcksum}" | awk '{ print $4 }'`
ON_DISK_V4_CKSUM=`md5 /tmp/bogons | awk '{ print $4 }'`
BOGON_V6_CKSUM=`/usr/bin/fetch -q -o - "${v6urlcksum}" | awk '{ print $4 }'`
ON_DISK_V6_CKSUM=`md5 /tmp/bogonsv6 | awk '{ print $4 }'`

if [ "$BOGON_V4_CKSUM" = "$ON_DISK_V4_CKSUM" ] || [ "$BOGON_V6_CKSUM" = "$ON_DISK_V6_CKSUM" ]; then
	# At least one of the downloaded MD5s matches, so mount RW
	/etc/rc.conf_mount_rw
fi

if [ "$BOGON_V4_CKSUM" = "$ON_DISK_V4_CKSUM" ]; then
	egrep -v "^192.168.0.0/16|^172.16.0.0/12|^10.0.0.0/8" /tmp/bogons > /etc/bogons
	RESULT=`/sbin/pfctl -t bogons -T replace -f /etc/bogons 2>&1`
	rm /tmp/bogons
	echo "$RESULT" |awk '{ print "Bogons V4 file downloaded: " $0 }' | logger
else
	echo "Could not download ${v4urlcksum} (checksum mismatch)" | logger
	checksum_error="true"
fi

if [ "$BOGON_V6_CKSUM" = "$ON_DISK_V6_CKSUM" ]; then
	egrep -v "^fc00::/7" /tmp/bogonsv6 > /etc/bogonsv6
	RESULT=`/sbin/pfctl -t bogonsv6 -T replace -f /etc/bogonsv6 2>&1`
	rm /tmp/bogonsv6
	echo "$RESULT" |awk '{ print "Bogons V6 file downloaded: " $0 }' | logger
else
	echo "Could not download ${v6urlcksum} (checksum mismatch)" | logger
	checksum_error="true"
fi

if [ "$BOGON_V4_CKSUM" = "$ON_DISK_V4_CKSUM" ] || [ "$BOGON_V6_CKSUM" = "$ON_DISK_V6_CKSUM" ]; then
	# We mounted RW, so switch back to RO
	/etc/rc.conf_mount_ro
fi

if [ "$checksum_error" != "" ];then
	# Relaunch and sleep
	sh /etc/rc.update_bogons.sh & 
	exit
fi

echo "rc.update_bogons.sh is ending the update cycle." | logger
