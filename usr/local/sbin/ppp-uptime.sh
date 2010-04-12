#!/bin/sh
#get ppp uptime from age of /tmp/{interface}up file
[ -f /tmp/$1up ] && /bin/echo $((`date -j +%s` - `/usr/bin/stat -f %m /tmp/$1up`))