#!/bin/sh

gwname="$1"
status="$2"

if [ "$status" = "down" ]; then
	# Insert code here, gets executed when gateway goes down
	:

elif [ "$status" == "up" ]; then
	# Insert code here, gets executed multiple times when gateway goes up
	:

fi
