#!/bin/sh

# Usage
if [ $# -ne 1 -a $# -ne 2 ]; then
        echo "Usage : $0 iface"
        exit
fi

# files paths
pid_file=/var/run/iftop_${1}.pid
cache_file=/var/db/iftop_${1}.log
awk_script=/usr/local/bin/iftop_parse.awk

# Binaries paths
DATE=/bin/date
STAT=/usr/bin/stat
CUT=/usr/bin/cut
PS=/bin/ps
GREP=/usr/bin/grep
CAT=/bin/cat
RM=/bin/rm
IFTOP=/usr/local/sbin/iftop
AWK=/usr/bin/awk

# test if pid file exist
if [ -f $pid_file ]; then
        # check how old is the file
        curTS=`$DATE +%s`
        pidTS=`$STAT -r $pid_file | $CUT -d " " -f 10`
        # if more than 10 seconds,
        # it must be a dead pid file (process killed?)
        # or a stucked process that we should kill
        if [ $(( curTS - pidTS )) -gt 10 ]; then
                oldPID=`$CAT $pid_file`
                # test if pid still exist
                run=`$PS -p $oldPID | $GREP -F $0`
                if [ "$run" != "" ]; then
                        kill -9 $oldPID
                fi
                $RM $pid_file
                $RM $cache_file 2>> /dev/null
        else
                if [ -s $cache_file ]; then
                        $CAT $cache_file
                fi
        fi
else
        echo -n $$ > $pid_file
        $IFTOP -nNb -i $1 -s 3 -o 2s -t 2>> /dev/null | $AWK -f $awk_script > ${cache_file}.tmp
        $CAT ${cache_file}.tmp > $cache_file
        $CAT $cache_file
        $RM $pid_file
fi
