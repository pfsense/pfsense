#!/bin/sh
# Script which lists the available components for this release
###########################################################################

. ${PROGDIR}/backend/functions.sh

echo "Available Components:"

cd ${COMPDIR}
for i in `ls -d *`
do
  if [ -e "${i}/component.cfg" -a -e "${i}/install.sh" -a -e "${i}/distfiles" ]
  then
    NAME="`grep 'name:' ${i}/component.cfg | cut -d ':' -f 2`"
    DESC="`grep 'description:' ${i}/component.cfg | cut -d ':' -f 2`"
    TYPE="`grep 'type:' ${i}/component.cfg | cut -d ':' -f 2`"
    echo " "
    echo "name: ${i}"
    echo "desc:${DESC}"
    echo "type:${TYPE}"
    if [ -e "${i}/component.png" ]
    then
      echo "icon: ${COMPDIR}/${i}/component.png"
    fi
  fi

done

