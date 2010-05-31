#!/bin/sh
# Script which lists the backups present on a server
###########################################################################

. ${PROGDIR}/backend/functions.sh

SSHUSER=$1
SSHHOST=$2
SSHPORT=$3

if [ -z "${SSHHOST}" -o -z "${SSHPORT}" ]
then
  echo "ERROR: Usage list-rsync-backups.sh <user> <host> <port>"
  exit 150
fi

# Look for full-system backups, needs at minimum a kernel to be bootable
FINDCMD="find . -type d -maxdepth 6 -name 'kernel' | grep '/boot/kernel'"

# Get a listing of the number of full backups saved
OLDBACKUPS=`ssh -o 'BatchMode=yes' -p ${SSHPORT} ${SSHUSER}@${SSHHOST} "${FINDCMD}"`
if [ "$?" = "0" ]
then
  for i in ${OLDBACKUPS}
  do
    BACKPATH="`echo ${i} | sed 's|/boot/.*||g' | sed 's|^./||g'`"
    if [ -z "${BACKLIST}" ]
    then
      BACKLIST="${BACKPATH}"
    else
      BACKLIST="${BACKLIST}:${BACKPATH}"
    fi
  done

  if [ -z "${BACKLIST}" ]
  then
    echo "NONE"
  else
    echo "$BACKLIST"
  fi

else
  echo "FAILED"  
fi
