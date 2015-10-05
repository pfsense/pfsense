#!/bin/sh


BEEP=$(/usr/local/sbin/read_xml_tag.sh boolean system/disablebeep)
if [ "$BEEP" = "true" ]; then
	exit;
fi

# Standard note length
NOTELENGTH="25"

# this is super annoying in VMware, exit if in VMware
if [ -f /var/log/dmesg.boot ]; then
	VMWCOUNT=`/usr/bin/grep -c VMware /var/log/dmesg.boot`
	if [ $VMWCOUNT -gt 0 ]; then
		exit;
	fi
fi

# Check for different HZ
if [ -f /boot/loader.conf ]; then
	HZ=`/usr/bin/grep -c kern.hz /boot/loader.conf`
	if [ "$HZ" = "1" ]; then
		NOTELENGTH="10"
	fi
fi

if [ -c "/dev/speaker" ]; then
	if [ "$1" = "start" ]; then
		/usr/local/bin/beep -p 500 $NOTELENGTH
		/usr/local/bin/beep -p 400 $NOTELENGTH
		/usr/local/bin/beep -p 600 $NOTELENGTH
		/usr/local/bin/beep -p 800 $NOTELENGTH
		/usr/local/bin/beep -p 800 $NOTELENGTH
	fi
	if [ "$1" = "stop" ]; then
		/usr/local/bin/beep -p 600 $NOTELENGTH
		/usr/local/bin/beep -p 800 $NOTELENGTH
		/usr/local/bin/beep -p 500 $NOTELENGTH
		/usr/local/bin/beep -p 400 $NOTELENGTH
		/usr/local/bin/beep -p 400 $NOTELENGTH
	fi
fi
