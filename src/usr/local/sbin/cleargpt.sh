#!/bin/sh
DISK=$1
if [ "$DISK" = "" ]; then
	echo "You must specify the disk that should be formatted/cleared."
	exit 1
fi
for PART in `/sbin/gpart show $DISK | /usr/bin/grep -v '=>' | /usr/bin/awk '{ print $3 }'`; do
	if [ "$PART" != "" ]; then
		/sbin/gpart delete -i $PART $DISK >/dev/null
	fi
done
/sbin/gpart destroy $DISK >/dev/null
exit 0
