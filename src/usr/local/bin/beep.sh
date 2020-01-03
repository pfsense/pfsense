#!/bin/sh
#
# beep.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

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
HZ=`/sbin/sysctl -qn kern.hz`
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
