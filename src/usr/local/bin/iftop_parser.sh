#!/bin/sh

# Usage
if [ $# -ne 2 ]; then
        echo "Usage : $0 iface {all|hidesource|hidedestination}"
        exit
fi

if [ "$2" != "all" ] && [ "$2" != "hidesource" ] && [ "$2" != "hidedestination" ]; then
        echo "Usage : $0 iface {all|hidesource|hidedestination}"
        exit
fi

# files paths
pid_dir=/var/run/iftop
pid_file=${pid_dir}/${1}_${2}.pid
data_dir=/var/tmp/iftop
data_file=${data_dir}/${1}_${2}.txt
awk_script=/usr/local/bin/iftop_parse.awk
iftop_config=/usr/local/etc/iftop/filter_${2}.conf

# Binaries paths
MKDIR=/bin/mkdir
DATE=/bin/date
STAT=/usr/bin/stat
CUT=/usr/bin/cut
PS=/bin/ps
GREP=/usr/bin/grep
CAT=/bin/cat
RM=/bin/rm
IFTOP=/usr/local/sbin/iftop
AWK=/usr/bin/awk

$MKDIR -p $pid_dir
$MKDIR -p $data_dir

# test if pid file exist
if [ -f $pid_file ]; then
        # check how old is the file
        curTS=`$DATE +%s`
        pidTS=`$STAT -r $pid_file | $CUT -d " " -f 10`
        # if more than 10 seconds,
        # it must be a dead pid file (process killed?)
        # or a stuck process that we should kill
        if [ $(( curTS - pidTS )) -gt 10 ]; then
                oldPID=`$CAT $pid_file`
                # test if pid still exist
                run=`$PS -p $oldPID | $GREP -F $0`
                if [ "$run" != "" ]; then
                        kill -9 $oldPID
                fi
                $RM $pid_file
        fi
else
        echo -n $$ > $pid_file
        $IFTOP -nNb -i $1 -s 2 -o 2s -t -c $iftop_config 2>> /dev/null | $AWK -f $awk_script > ${data_file}.tmp
        $CAT ${data_file}.tmp > $data_file
        $RM ${data_file}.tmp
        $RM $pid_file
fi
