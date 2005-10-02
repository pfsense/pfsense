#!/bin/sh

if [ "$1" = "start" ]; then
	/usr/local/bin/beep -p 500 200
	/usr/local/bin/beep -p 400 200
	/usr/local/bin/beep -p 600 200
	/usr/local/bin/beep -p 800 200
	/usr/local/bin/beep -p 800 200
fi
if [ "$1" = "stop" ]; then
	/usr/local/bin/beep -p 600 200
	/usr/local/bin/beep -p 800 200
	/usr/local/bin/beep -p 500 200
	/usr/local/bin/beep -p 400 200
	/usr/local/bin/beep -p 400 200
fi

