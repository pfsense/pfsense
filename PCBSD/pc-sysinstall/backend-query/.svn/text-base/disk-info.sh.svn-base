#!/bin/sh
# Query a disk for partitions and display them
#############################

. ${PROGDIR}/backend/functions.sh
. ${PROGDIR}/backend/functions-disk.sh

if [ -z "${1}" ]
then
  echo "Error: No disk specified!"
  exit 1
fi

if [ ! -e "/dev/${1}" ]
then
  echo "Error: Disk /dev/${1} does not exist!"
  exit 1
fi

DISK="${1}"

get_disk_cyl "${DISK}"
CYLS="${VAL}"

get_disk_heads "${DISK}"
HEADS="${VAL}"

get_disk_sectors "${DISK}"
SECS="${VAL}"

echo "cylinders=${CYLS}"
echo "heads=${HEADS}"
echo "sectors=${SECS}"

# Now get the disks size in MB
KB="`diskinfo -v ${1} | grep 'bytes' | cut -d '#' -f 1 | tr -s '\t' ' ' | tr -d ' '`"
MB=$(convert_byte_to_megabyte ${KB})
echo "size=$MB"

# Now get the Controller Type
CTYPE="`dmesg | grep "^${1}:" | grep "B <" | cut -d '>' -f 2 | cut -d ' ' -f 3-10`"
echo "type=$CTYPE"
