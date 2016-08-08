#!/bin/sh

# This script gets executed whenever a gateway status changes

gwname="$1" # Contains the gateway name
status="$2" # Contains the gateway status ("up"/"down")

if [ "$status" = "down" ]; then
	# Insert code here, gets executed when gateway goes down
	:

elif [ "$status" == "up" ]; then
	# Insert code here, gets executed multiple times when gateway goes up
	:

fi
