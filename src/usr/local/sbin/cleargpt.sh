#!/bin/sh
DISK=$1
if [ -z "$DISK" ]; then
	echo "ERROR: The disk that should be formatted/cleared must be specified."
	exit 1
fi
for PART in `/sbin/gpart show "$DISK" | /usr/bin/grep -v '=>' | /usr/bin/awk '{ print $3 }'`; do
	if [ -n "$PART" ]; then
		/sbin/gpart delete -i "$PART" "$DISK" >/dev/null
	fi
done
/sbin/gpart destroy "$DISK" >/dev/null
exit 0
