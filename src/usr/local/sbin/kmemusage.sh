#!/bin/sh -

TEXT=`/sbin/kldstat | /usr/bin/awk 'BEGIN {print "16i 0";} NR>1 {print toupper($4) "+"} END {print "p"}' | /usr/bin/dc`
DATA=`/usr/bin/vmstat -m | /usr/bin/sed -Ee '1s/.*/0/;s/.* ([0-9]+)K.*/\1+/;$s/$/1024*p/' | /usr/bin/dc`
TOTAL=$((DATA + TEXT))

echo TEXT=$TEXT, `echo $TEXT | /usr/bin/awk '{print $1/1048576 " MB"}'`
echo DATA=$DATA, `echo $DATA | /usr/bin/awk '{print $1/1048576 " MB"}'`
echo TOTAL=$TOTAL, `echo $TOTAL | /usr/bin/awk '{print $1/1048576 " MB"}'`