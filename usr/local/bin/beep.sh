#!/bin/sh

# Standard note length
NOTELENGTH="25"

# Embedded uses 100HZ
if [ "$PFSENSETYPE" = "embedded" ]; then
	NOTELENGTH="10"
fi

# this is super annoying in VMware, exit if in VMware
VMWCOUNT=`dmesg -a | grep VMware | wc -l | awk '{ print $1 }'`
if [ $VMWCOUNT -gt 0 ]; then
    exit;
fi

# Check for different HZ 
if [ -f /boot/loader.conf ]; then
	HZ=`grep kern.hz /boot/loader.conf | wc -l | awk '{ print $1 }'`
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