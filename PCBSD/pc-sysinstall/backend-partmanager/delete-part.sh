#!/bin/sh
# Delete a specified partition, takes effect immediately
########################################################

. ${PROGDIR}/backend/functions.sh
. ${PROGDIR}/backend/functions-disk.sh

if [ -z "${1}" ]
then
  echo "Error: No partition specified!"
  exit 1
fi

if [ ! -e "/dev/${1}" ]
then
  echo "Error: Partition /dev/${1} does not exist!"
  exit 1
fi

PARTITION="${1}"

# First lets figure out the partition number for the given device
##################################################################

# Get the number of characters in this dev
CHARS="`echo $PARTITION | wc -c`"

PARTINDEX=""

# Lets read through backwards until we get the part number
while 
z=1
do
  CHARS=`expr $CHARS - 1`
  LAST_CHAR=`echo "${PARTITION}" | cut -c $CHARS`
  echo "${LAST_CHAR}" | grep "^[0-9]$" >/dev/null 2>/dev/null
  if [ "$?" = "0" ] ; then
    PARTINDEX="${LAST_CHAR}${PARTINDEX}"
  else
    break
  fi
done

# Now get current disk we are working on
CHARS=`expr $CHARS - 1`
DISK="`echo $PARTITION | cut -c 1-${CHARS}`"

# Make sure we have a valid disk name still
if [ ! -e "/dev/${DISK}" ] ; then
  echo "Error: Disk: ${DISK} doesnt exist!"
  exit 1
fi

echo "Running: gpart delete -i ${PARTINDEX} ${DISK}"
gpart delete -i ${PARTINDEX} ${DISK} >/dev/null 2>/dev/null

# Check if this was the last partition and destroy the disk geom if so
get_disk_partitions "${DISK}"
if [ -z "${VAL}" ] ; then
  gpart destroy ${DISK}  
fi

exit "$?"
