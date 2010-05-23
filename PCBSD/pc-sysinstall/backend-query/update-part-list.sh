#!/bin/sh

echo "Running: find-update-parts" >> ${LOGOUT}

rm ${TMPDIR}/AvailUpgrades >/dev/null 2>/dev/null

FSMNT="/mnt"

# Get the freebsd version on this partition
get_fbsd_ver() {

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
   DEVS="${DEVS} `ls /dev/${i}s*`" 
  fi

done

# Import any zpools
zpool import -a

for i in $DEVS
do
    if [ -e "${i}a.journal" ] ; then
      mount ${i}a.journal ${FSMNT} >>${LOGOUT} 2>>${LOGOUT}
      if [ "${?}" = "0" ] ; then
	get_fbsd_ver ${i}
        umount -f ${FSMNT} >/dev/null 2>/dev/null
      fi
    elif [ -e "${i}a" ]
    then
      mount ${i}a ${FSMNT} >>${LOGOUT} 2>>${LOGOUT}
      if [ "${?}" = "0" ] ; then
	get_fbsd_ver ${i}
        umount -f ${FSMNT} >/dev/null 2>/dev/null
      else
        # Lets try ZFS of this device
        ZNAME=`echo ${i} | cut -d '/' -f 3`
        mount -t zfs ${ZNAME}a ${FSMNT}
        if [ "${?}" = "0" ] ; then
	  get_fbsd_ver ${i}
          umount -f ${FSMNT} >/dev/null 2>/dev/null
        fi
      fi
    fi
done

# Export all zpools again, so that we can overwrite these partitions potentially
#for i in `zpool list -H -o name`
#do
#  zpool export -f ${i}
#done
