#!/bin/sh
#-
# Copyright (c) 2010 iXsystems, Inc.  All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.
#
# $FreeBSD: src/usr.sbin/pc-sysinstall/backend-query/update-part-list.sh,v 1.3 2010/08/24 06:11:46 imp Exp $

# Need access to a some unmount functions
. ${PROGDIR}/backend/functions-unmount.sh

echo "Running: find-update-parts" >> ${LOGOUT}

rm ${TMPDIR}/AvailUpgrades >/dev/null 2>/dev/null

FSMNT="/mnt"

# Get the freebsd version on this partition
get_fbsd_ver()
{

  VER="`file ${FSMNT}/bin/sh | grep 'for FreeBSD' | sed 's|for FreeBSD |;|g' | cut -d ';' -f 2 | cut -d ',' -f 1`"
  if [ "$?" = "0" ] ; then
    file ${FSMNT}/bin/sh | grep '32-bit' >/dev/null 2>/dev/null
    if [ "${?}" = "0" ] ; then
      echo "${1}: FreeBSD ${VER} (32bit)"
    else
      echo "${1}: FreeBSD ${VER} (64bit)"
    fi
  fi

}

# Create our device listing
SYSDISK="`sysctl kern.disks | cut -d ':' -f 2 | sed 's/^[ \t]*//'`"
DEVS=""

# Now loop through these devices, and list the disk drives
for i in ${SYSDISK}
do

  # Get the current device
  DEV="${i}"
  # Make sure we don't find any cd devices
  echo "${DEV}" | grep -e "^acd[0-9]" -e "^cd[0-9]" -e "^scd[0-9]" >/dev/null 2>/dev/null
  if [ "$?" != "0" ] ; then
    DEVS="${DEVS} `ls /dev/${i}*`"
  fi

done

# Search for regular UFS / Geom Partitions to upgrade
for i in $DEVS
do
  if [ ! -e "${i}a.journal" -a ! -e "${i}a" -a ! -e "${i}p2" -a ! -e "${i}p2.journal" ] ; then
    continue
  fi

  if [ -e "${i}a.journal" ] ; then
    _dsk="${i}a.journal"
  elif [ -e "${i}a" ] ; then
    _dsk="${i}a"
  elif [ -e "${i}p2" ] ; then
    _dsk="${i}p2"
  elif [ -e "${i}p2.journal" ] ; then
    _dsk="${i}p2.journal"
  fi

  mount -o ro ${_dsk} ${FSMNT} >>${LOGOUT} 2>>${LOGOUT}
  if [ "${?}" = "0" -a -e "${FSMNT}/bin/sh" ] ; then
    get_fbsd_ver "`echo ${_dsk} | sed 's|/dev/||g'`"
    umount -f ${FSMNT} >/dev/null 2>/dev/null
  fi
done

# Now search for any ZFS root partitions
zpool import -o altroot=${FSMNT} -a

# Unmount any auto-mounted stuff
umount_all_dir "${FSMNT}"

# Get pools
_zps="`zpool list | grep -v 'NAME' | cut -d ' ' -f 1`"
for _zpools in ${_zps}
do
  mount -o ro -t zfs ${_zpools} ${FSMNT} >>${LOGOUT} 2>>${LOGOUT}
  if [ "${?}" = "0" -a -e "${FSMNT}/bin/sh" ] ; then
    get_fbsd_ver "${_zpools}"
    umount -f ${FSMNT} >/dev/null 2>/dev/null
  fi
done
