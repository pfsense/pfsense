#!/bin/sh
#
# ping_hosts.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2006-2016 Electric Sheep Fencing, LLC
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in
#    the documentation and/or other materials provided with the
#    distribution.
#
# 3. All advertising materials mentioning features or use of this software
#    must display the following acknowledgment:
#    "This product includes software developed by the pfSense Project
#    for use in the pfSense® software distribution. (http://www.pfsense.org/).
#
# 4. The names "pfSense" and "pfSense Project" must not be used to
#    endorse or promote products derived from this software without
#    prior written permission. For written permission, please contact
#    coreteam@pfsense.org.
#
# 5. Products derived from this software may not be called "pfSense"
#    nor may "pfSense" appear in their names without prior written
#    permission of the Electric Sheep Fencing, LLC.
#
# 6. Redistributions of any form whatsoever must retain the following
#    acknowledgment:
#
# "This product includes software developed by the pfSense Project
# for use in the pfSense software distribution (http://www.pfsense.org/).
#
# THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
# EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
# PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
# ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
# SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
# NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
# STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
# OF THE POSSIBILITY OF SUCH DAMAGE.

# Format of file should be delimited by |
#  Field 1:  Source IP
#  Field 2:  Destination IP
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

cat $PKGHOSTS $HOSTS $CURRENTIPSECHOSTS >/tmp/tmpHOSTS

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
			echo "UP" > /var/db/pingstatus/$DSTIP
			if [ "$SERVICERESTOREDSCRIPT" != "" ]; then
				echo "$DSTIP is UP, previous state was DOWN .. Running $SERVICERESTOREDSCRIPT"
				echo "$DSTIP is UP, previous state was DOWN .. Running $SERVICERESTOREDSCRIPT" | logger -p daemon.info -i -t PingMonitor
				sh -c $SERVICERESTOREDSCRIPT
			fi
		fi
	else
		# Host is down
		if [ "$PREVIOUSSTATUS" != "DOWN" ]; then
			# Service is down
			echo "DOWN" > /var/db/pingstatus/$DSTIP
			if [ "$FAILURESCRIPT" != "" ]; then
				echo "$DSTIP is DOWN, previous state was UP ..  Running $FAILURESCRIPT"
				echo "$DSTIP is DOWN, previous state was UP ..  Running $FAILURESCRIPT" | logger -p daemon.info -i -t PingMonitor
				sh -c $FAILURESCRIPT
			fi
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

