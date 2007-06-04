#!/bin/sh

CHECKRELOADSTATUS=`pgrep check_reload_status | grep -v grep | wc -l`
DATE=`date +%m-%d-%Y_at_%H%M%S`
OUTFILE=/root/check_reload_status.log

if [ ${CHECKRELOADSTATUS} -lt 1 ] ; then
        echo "${DATE}: Re-starting check_reload_status, it died for some reason..." >> ${OUTFILE}
        nohup /usr/bin/nice -n20 /usr/local/sbin/check_reload_status &
fi

if [ ${CHECKRELOADSTATUS} -gt 1 ] ; then
        echo "${DATE} There appears to be 2 or more check_reload_status processes. Forcing kill and restart of all now..." >> ${OUTFILE}
        kill -9 `pgrep check_reload_status`
		nohup /usr/bin/nice -n20 /usr/local/sbin/check_reload_status &
fi
