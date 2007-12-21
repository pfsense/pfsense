#!/bin/sh
# Illustrates use of a while loop to read a file

cat - |   \
while read line
do
	echo "$line"
	sleep 0.01
done
