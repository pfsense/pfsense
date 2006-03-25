#!/bin/sh
# Kill ghost shells

/bin/kill -9 `/bin/ps awux | grep "(sh)" | /usr/bin/grep -v grep | ps awux | grep "(sh)" | grep -v grep | /usr/bin/awk '{ print $2 }'`

