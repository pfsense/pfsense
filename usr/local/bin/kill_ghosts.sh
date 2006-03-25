#!/bin/sh
# Kill ghost shells

/bin/kill -9 `/bin/ps awux | grep "(sh)" | /usr/bin/grep -v grep | /usr/bin/cut -d" " -f5`

