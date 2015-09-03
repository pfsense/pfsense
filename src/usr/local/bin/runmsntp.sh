#!/bin/sh

# write our PID to file
echo $$ > $1

# execute msntp in endless loop; restart if it
# exits (wait 1 second to avoid restarting too fast in case
# the network is not yet setup)
while true; do
	/usr/local/bin/msntp -v -r -P no -l $2 -x $3 $4 2>&1 | logger -p daemon.info -i -t msntp
	sleep 60
done
