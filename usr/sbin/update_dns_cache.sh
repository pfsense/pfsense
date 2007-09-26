#!/bin/sh

mkdir -p /var/db/dnscache 
cd /var/db/dnscache 

while [ /bin/true ]; do
	cd /var/db/dnscache 
	needsfilterreload=0
	for FILE in *; do
	DNSENTRIES=`host -t A $FILE | awk '/address/ {print $4}' | sort`
		echo "$DNSENTRIES" > /tmp/Njkd98u79.tmp
		CURRENTNR=`cat /tmp/Njkd98u79.tmp | wc -l | awk '{print $1}'`
		OLDNR=`cat $FILE | wc -l | awk '{print $1}'`
		if [ "$CURRENTNR" != "$OLDNR" ]; then
			needsfilterreload=1
			# if the number of hosts is different we reload and skip the rest
			continue
		fi
		# We need to compare the files.
		# We need to compare the files.
		cmp -s /tmp/Njkd98u79.tmp $FILE
		if [ "$?" -gt 0 ]; then
			needsfilterreload=1
		fi
	done
	if [ "$needsfilterreload" -gt 0 ]; then
		/etc/rc.filter_configure_sync
	fi
	sleep 480
done
