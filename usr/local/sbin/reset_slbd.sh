#!/bin/sh

if [ `ps awux | grep slbd | wc -l` -gt 0 ]; then
	killall slbd
	killall slbd
	/usr/local/sbin/slbd -c/var/etc/slbd.conf -r5000
fi
