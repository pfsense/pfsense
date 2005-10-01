#!/bin/sh

if [ ! -x "`which beep`" ]; then
	exit0
fi

if [ "$1" = "start" ]; then
	beep -p 500 200
	beep -p 400 200
	beep -p 600 200
	beep -p 800 200
	beep -p 800 200
fi
if [ "$1" = "stop" ]; then
	beep -p 600 200
	beep -p 800 200
	beep -p 500 200
	beep -p 400 200
	beep -p 400 200
fi

