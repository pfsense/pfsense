#!/bin/sh

# pfSense ping helper
# written by Scott Ullrich
# (C)2006 Scott Ullrich
# All rights reserved.

# Format of file should be deliminted by |
#  Field 1:  Source ip
#  Field 2:  Destination ip
#  Field 3:  Ping count
#  Field 4:  Script to run when service is down
#  Field 5:  Script to run once service is restored
#  Field 6:  Ping time threshold
#  Field 7:  Wan ping time threshold

# Read in ipsec ping hosts and check the CARP status
if [ -f /var/db/ipsecpinghosts ]; then
	IPSECHOSTS="/var/db/ipsecpinghosts"
	CURRENTIPSECHOSTS="/var/db/currentipsecpinghosts"
	echo -e "" > $CURRENTIPSECHOSTS
	while read configline
	do
		if [ "$configline" = "<tunnel>" ]; then
			VPNENABLED=1
			while [ "$configline" != "</tunnel>" ];
			do
				if ! read configline ; then
					break;
				fi
				if [ "$configline" = "<disabled/>" ]; then
					VPNENABLED=0
				elif [ -n "`echo -e "$configline" | grep "<interface>"`" ]; then
					IFVPN=`echo -e "$configline" | sed -e 's/<[a-z]*>//' -e 's/<\/[a-z]*>//'`
				elif [ -n "`echo -e "$configline" | grep "<pinghost>"`" ]; then
					PINGIPVPN=`echo -e "$configline" | sed -e 's/<[a-z]*>//' -e 's/<\/[a-z]*>//'`
				fi
			done
			if [ $VPNENABLED -eq 1 ]; then
				IFVPNSTATE=`ifconfig $IFVPN | grep "carp: BACKUP vhid" | wc -l`
				if [ $IFVPNSTATE -eq 1 ]; then
					echo -e "$PINGIPVPN -> $IFVPN is BACKUP (not added)"
				else
					echo -e "$PINGIPVPN -> $IFVPN is MASTER or non CARP (added)"
					while read dbipsechosts
					do
						if [ -n "`echo -e "$dbipsechosts" | grep "$PINGIPVPN"`" ]; then
							echo -e "$dbipsechosts" >> $CURRENTIPSECHOSTS
						fi
					done < $IPSECHOSTS
				fi
			fi
		fi
	done < /conf/config.xml
	IPSECHOSTS=$CURRENTIPSECHOSTS
fi

# General file meant for user consumption
if [ -f /var/db/hosts ]; then
	HOSTS="/var/db/hosts"
fi

# Package specific ping requests
if [ -f /var/db/pkgpinghosts ]; then
	PKGHOSTS="/var/db/pkgpinghosts"
fi

cat $PKGHOSTS $HOSTS $IPSECHOSTS >/tmp/tmpHOSTS

if [ ! -d /var/db/pingstatus ]; then
	/bin/mkdir -p /var/db/pingstatus
fi

if [ ! -d /var/db/pingmsstatus ]; then
	/bin/mkdir -p /var/db/pingmsstatus
fi

PINGHOSTS=`cat /tmp/tmpHOSTS`

PINGHOSTCOUNT=`cat /tmp/tmpHOSTS | wc -l`

if [ "$PINGHOSTCOUNT" -lt "1" ]; then
	exit
fi

for TOPING in $PINGHOSTS ; do
	echo "PROCESSING $TOPING"
	SRCIP=`echo $TOPING | cut -d"|" -f1`
	DSTIP=`echo $TOPING | cut -d"|" -f2`
	COUNT=`echo $TOPING | cut -d"|" -f3`
	FAILURESCRIPT=`echo $TOPING | cut -d"|" -f4`
	SERVICERESTOREDSCRIPT=`echo $TOPING | cut -d"|" -f5`
	THRESHOLD=`echo $TOPING | cut -d"|" -f6`
	WANTHRESHOLD=`echo $TOPING | cut -d"|" -f7`
	echo Processing $DSTIP
	# Look for a service being down
	ping -c $COUNT -S $SRCIP $DSTIP
	if [ $? -eq 0 ]; then
		# Host is up
		# Read in previous status
		PREVIOUSSTATUS=`cat /var/db/pingstatus/$DSTIP`
		if [ "$PREVIOUSSTATUS" = "DOWN" ]; then
			# Service restored
			if [ "$SERVICERESTOREDSCRIPT" != "" ]; then
				echo "UP" > /var/db/pingstatus/$DSTIP
				echo "$DSTIP is UP, previous state was DOWN .. Running $SERVICERESTOREDSCRIPT"
				echo "$DSTIP is UP, previous state was DOWN .. Running $SERVICERESTOREDSCRIPT" | logger -p daemon.info -i -t PingMonitor
				sh -c $SERVICERESTOREDSCRIPT
			fi
		fi
		echo "UP" > /var/db/pingstatus/$DSTIP
	else
		# Host is down
		PREVIOUSSTATUS=`cat /var/db/pingstatus/$DSTIP`
		if [ "$PREVIOUSSTATUS" = "UP" ]; then
			# Service is down
			if [ "$FAILURESCRIPT" != "" ]; then
				echo "DOWN" > /var/db/pingstatus/$DSTIP
				echo "$DSTIP is DOWN, previous state was UP ..  Running $FAILURESCRIPT"
				echo "$DSTIP is DOWN, previous state was UP ..  Running $FAILURESCRIPT" | logger -p daemon.info -i -t PingMonitor
				sh -c $FAILURESCRIPT
			fi
		fi
		echo "DOWN" > /var/db/pingstatus/$DSTIP
	fi
	echo "Checking ping time $DSTIP"
	# Look at ping values themselves
	PINGTIME=`ping -c 1 -S $SRCIP $DSTIP | awk '{ print $7 }' | grep time | cut -d "=" -f2`
	echo "Ping returned $?"
	echo $PINGTIME > /var/db/pingmsstatus/$DSTIP
	if [ "$THRESHOLD" != "" ]; then
		if [ "$PINGTIME" -gt "$THRESHOLD" ]; then
			echo "$DSTIP has exceeded ping threshold $PINGTIME / $THRESHOLD .. Running $FAILURESCRIPT"
			echo "$DSTIP has exceeded ping threshold $PINGTIME / $THRESHOLD .. Running $FAILURESCRIPT" | logger -p daemon.info -i -t PingMonitor
			sh -c $FAILURESCRIPT
		fi
	fi
	# Wan ping time threshold
	WANTIME=`rrdtool fetch /var/db/rrd/wan-quality.rrd AVERAGE -r 120 -s -1min -e -1min | grep ":" | cut -f3 -d" " | cut -d"e" -f1`
	echo "Checking wan ping time $WANTIME"
	echo $WANTIME > /var/db/wanaverage
	if [ "$WANTHRESHOLD" != "" ]; then
		if [ "$WANTIME" -gt "$WANTHRESHOLD" ]; then
			echo "$DSTIP has exceeded wan ping threshold $WANTIME / $WANTHRESHOLD .. Running $FAILURESCRIPT"
			echo "$DSTIP has exceeded wan ping threshold $WANTIME / $WANTHRESHOLD .. Running $FAILURESCRIPT" | logger -p daemon.info -i -t PingMonitor
			sh -c $FAILURESCRIPT
		fi
	fi
	sleep 1
done

exit 0
