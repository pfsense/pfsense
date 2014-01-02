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
#  Field 8:  Address family

# Read in ipsec ping hosts and check the CARP status
if [ -f /var/db/ipsecpinghosts ]; then
	IPSECHOSTS="/var/db/ipsecpinghosts"
	CURRENTIPSECHOSTS="/var/db/currentipsecpinghosts"
	IFVPNSTATE=`ifconfig $IFVPN | grep "carp: BACKUP vhid" | wc -l`
	if [ $IFVPNSTATE -gt 1 ]; then
		echo -e "CARP interface in BACKUP (not pinging ipsec hosts)"
		rm -f $CURRENTIPSECHOSTS
		touch $CURRENTIPSECHOSTS
	else
		echo -e "CARP interface is MASTER or non CARP (pinging ipsec hosts)"
		cat < $IPSECHOSTS > $CURRENTIPSECHOSTS
	fi
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
	AF=`echo $TOPING | cut -d"|" -f8`
	if [ "$AF" == "inet6" ]; then
		PINGCMD=ping6
	else
		PINGCMD=ping
	fi
	echo Processing $DSTIP
	# Look for a service being down
	# Read in previous status
	PREVIOUSSTATUS=""
	if [ -f "/var/db/pingstatus/${DSTIP}" ]; then
		PREVIOUSSTATUS=`cat /var/db/pingstatus/$DSTIP`
	fi
	$PINGCMD -c $COUNT -S $SRCIP $DSTIP
	if [ $? -eq 0 ]; then
		# Host is up
		if [ "$PREVIOUSSTATUS" != "UP" ]; then
			# Service restored
			if [ "$SERVICERESTOREDSCRIPT" != "" ]; then
				echo "$DSTIP is UP, previous state was DOWN .. Running $SERVICERESTOREDSCRIPT"
				echo "$DSTIP is UP, previous state was DOWN .. Running $SERVICERESTOREDSCRIPT" | logger -p daemon.info -i -t PingMonitor
				sh -c $SERVICERESTOREDSCRIPT
			fi
			echo "UP" > /var/db/pingstatus/$DSTIP
		fi
	else
		# Host is down
		if [ "$PREVIOUSSTATUS" != "DOWN" ]; then
			# Service is down
			if [ "$FAILURESCRIPT" != "" ]; then
				echo "$DSTIP is DOWN, previous state was UP ..  Running $FAILURESCRIPT"
				echo "$DSTIP is DOWN, previous state was UP ..  Running $FAILURESCRIPT" | logger -p daemon.info -i -t PingMonitor
				sh -c $FAILURESCRIPT
			fi
			echo "DOWN" > /var/db/pingstatus/$DSTIP
		fi
	fi
	echo "Checking ping time $DSTIP"
	# Look at ping values themselves
	PINGTIME=`$PINGCMD -c 1 -S $SRCIP $DSTIP | awk '{ print $7 }' | grep time | cut -d "=" -f2`
	echo "Ping returned $?"
	echo $PINGTIME > /var/db/pingmsstatus/$DSTIP
	if [ "$THRESHOLD" != "" ]; then
		if [ $(echo "${PINGTIME} > ${THRESHOLD}" | /usr/bin/bc) -eq 1 ]; then
			echo "$DSTIP has exceeded ping threshold $PINGTIME / $THRESHOLD .. Running $FAILURESCRIPT"
			echo "$DSTIP has exceeded ping threshold $PINGTIME / $THRESHOLD .. Running $FAILURESCRIPT" | logger -p daemon.info -i -t PingMonitor
			sh -c $FAILURESCRIPT
		fi
	fi
	# Wan ping time threshold
	#WANTIME=`rrdtool fetch /var/db/rrd/wan-quality.rrd AVERAGE -r 120 -s -1min -e -1min | grep ":" | cut -f3 -d" " | cut -d"e" -f1`
	echo "Checking wan ping time $WANTIME"
	echo $WANTIME > /var/db/wanaverage
	if [ "$WANTHRESHOLD" != "" -a "$WANTIME" != "" ]; then
		if [ $(echo "${WANTIME} > ${WANTHRESHOLD}" | /usr/bin/bc) -eq 1 ]; then
			echo "$DSTIP has exceeded wan ping threshold $WANTIME / $WANTHRESHOLD .. Running $FAILURESCRIPT"
			echo "$DSTIP has exceeded wan ping threshold $WANTIME / $WANTHRESHOLD .. Running $FAILURESCRIPT" | logger -p daemon.info -i -t PingMonitor
			sh -c $FAILURESCRIPT
		fi
	fi
	sleep 1
done

exit 0

