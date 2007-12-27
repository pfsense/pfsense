#!/bin/sh

# Standard note length
NOTELENGTH="25"

# Embedded uses 100HZ
if [ "$PFSENSETYPE" = "embedded" ]; then
	NOTELENGTH="10"
fi

# Check for different HZ 
HZ=`cat /boot/loader.conf | grep kern.hz | wc -l | awk '{ print $1 }'`
if [ "$HZ" = "1" ]; then
	NOTELENGTH="10"
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