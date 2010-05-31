#!/bin/sh

rm ${TMPDIR}/.tzonetmp >/dev/null 2>/dev/null

# Backend script which lists all the available timezones for front-ends to display
while read line
do
  echo "$line" | grep "^#" >/dev/null 2>/dev/null
  if [ "$?" != "0" ]
  then
    echo "$line" |  tr -s "\t" ":" | cut -d ":" -f 3-4 >>${TMPDIR}/.tzonetmp
  fi
done < /usr/share/zoneinfo/zone.tab

sort ${TMPDIR}/.tzonetmp
rm ${TMPDIR}/.tzonetmp >/dev/null 2>/dev/null

exit 0
