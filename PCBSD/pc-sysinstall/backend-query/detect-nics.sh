#!/bin/sh

rm /tmp/netCards 2>/dev/null
touch /tmp/netCards

config="`ifconfig -l`"

for i in $config
do
 echo "${i}" | grep -e "lo0" -e "^fwe" -e "^fwip" -e "lo1" -e "^plip" -e "^pfsync" -e "^pflog" -e "^tun" >/dev/null 2>/dev/null
 if [ "$?" != "0" ]
 then
   IDENT="<`dmesg | grep ^${i} | grep -v "miibus" | grep '<' | cut -d '<' -f 2 | cut -d '>' -f 1 | head -1`>"
   echo "${i}: $IDENT"
 fi
done
