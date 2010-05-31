#!/bin/sh
# Query a disk for partitions and display them
#############################

. ${PROGDIR}/backend/functions.sh

if [ -z "${1}" ] ; then
  echo "Error: No disk specified!"
  exit 1
fi

if [ -z "${2}" ] ; then
  echo "Error: No size specified!"
  exit 1
fi

if [ ! -e "/dev/${1}" ] ; then
  echo "Error: Disk /dev/${1} does not exist!"
  exit 1
fi

DISK="${1}"
MB="${2}"

TOTALBLOCKS="`expr $MB \* 2048`"


# Lets figure out what number this slice will be
LASTSLICE="`fdisk -s /dev/${DISK} 2>/dev/null | grep -v ${DISK} | grep ':' | tail -n 1 | cut -d ':' -f 1 | tr -s '\t' ' ' | tr -d ' '`"
if [ -z "${LASTSLICE}" ] ; then
  LASTSLICE="1"
else
  LASTSLICE="`expr $LASTSLICE + 1`"
fi

if [ ${LASTSLICE} -gt "4" ] ; then
  echo "Error: FreeBSD MBR setups can only have a max of 4 slices"
  exit 1
fi


SLICENUM="${LASTSLICE}"

# Lets get the starting block
if [ "${SLICENUM}" = "1" ] ; then
  STARTBLOCK="63"
else
  # Lets figure out where the prior slice ends
  checkslice="`expr ${SLICENUM} - 1`"

  # Get starting block of this slice
  fdisk -s /dev/${DISK} | grep -v "${DISK}:" | grep "${checkslice}:" | tr -s " " >${TMPDIR}/pfdisk
  pstartblock="`cat ${TMPDIR}/pfdisk | cut -d ' ' -f 3`"
  psize="`cat ${TMPDIR}/pfdisk | cut -d ' ' -f 4`"
  STARTBLOCK="`expr ${pstartblock} + ${psize}`"
fi


# If this is an empty disk, see if we need to create a new MBR scheme for it
gpart show ${DISK} >/dev/null 2>/dev/null
if [ "$?" != "0" -a "${SLICENUM}" = "1" ] ; then
 gpart create -s mbr ${DISK}
fi

gpart add -b ${STARTBLOCK} -s ${TOTALBLOCKS} -t freebsd -i ${SLICENUM} ${DISK}
exit "$?"
