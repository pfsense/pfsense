#!/bin/sh
# Functions which unmount all mounted disk filesystems

# Script that adds our gmirror devices for syncing
start_gmirror_sync()
{

 cd ${MIRRORCFGDIR}
  for DISK in `ls *`
  do
    MIRRORDISK="`cat ${DISK} | cut -d ':' -f 1`"
    MIRRORBAL="`cat ${DISK} | cut -d ':' -f 2`"
    MIRRORNAME="`cat ${DISK} | cut -d ':' -f 3`"
   
    # Start the mirroring service
    rc_halt "gmirror insert ${MIRRORNAME} /dev/${MIRRORDISK}"

  done

};

# Unmounts all our mounted file-systems
unmount_all_filesystems()
{
   # Copy the logfile to disk before we unmount
   cp ${LOGOUT} ${FSMNT}/root/pc-sysinstall.log
   cd /

   # Start by unmounting any ZFS partitions
   zfs_cleanup_unmount

   # Lets read our partition list, and unmount each
   ##################################################################
   for PART in `ls ${PARTDIR}`
   do
     
     PARTFS="`cat ${PARTDIR}/${PART} | cut -d ':' -f 1`"
     PARTMNT="`cat ${PARTDIR}/${PART} | cut -d ':' -f 2`"
     PARTENC="`cat ${PARTDIR}/${PART} | cut -d ':' -f 3`"
     PARTLABEL="`cat ${PARTDIR}/${PART} | cut -d ':' -f 4`"

     if [ "${PARTENC}" = "ON" ]
     then
       EXT=".eli"
     else
       EXT=""
     fi

     #if [ "${PARTFS}" = "SWAP" ]
     #then
     #  rc_nohalt "swapoff /dev/${PART}${EXT}"
     #fi

     # Check if we've found "/", and unmount that last
     if [ "$PARTMNT" != "/" -a "${PARTMNT}" != "none" -a "${PARTFS}" != "ZFS" ]
     then
       rc_halt "umount -f /dev/${PART}${EXT}"

       # Re-check if we are missing a label for this device and create it again if so
       if [ ! -e "/dev/label/${PARTLABEL}" ]
       then
         case ${PARTFS} in
             UFS) glabel label ${PARTLABEL} /dev/${PART}${EXT} ;;
           UFS+S) glabel label ${PARTLABEL} /dev/${PART}${EXT} ;;
           UFS+J) glabel label ${PARTLABEL} /dev/${PART}${EXT}.journal ;;
               *) ;;
         esac 
       fi
     fi

     # Check if we've found "/" and make sure the label exists
     if [ "$PARTMNT" = "/" -a "${PARTFS}" != "ZFS" ]
     then
       if [ ! -e "/dev/label/${PARTLABEL}" ]
       then
         case ${PARTFS} in
             UFS) ROOTRELABEL="glabel label ${PARTLABEL} /dev/${PART}${EXT}" ;;
           UFS+S) ROOTRELABEL="glabel label ${PARTLABEL} /dev/${PART}${EXT}" ;;
           UFS+J) ROOTRELABEL="glabel label ${PARTLABEL} /dev/${PART}${EXT}.journal" ;;
               *) ;;
         esac 
       fi
     fi
   done

   # Last lets the /mnt partition
   #########################################################
   # rc_nohalt "umount -f ${FSMNT}"

    # If are using a ZFS on "/" set it to legacy
   if [ ! -z "${FOUNDZFSROOT}" ]
   then
     rc_halt "zfs set mountpoint=legacy ${FOUNDZFSROOT}"
   fi

   # If we need to relabel "/" do it now
   if [ ! -z "${ROOTRELABEL}" ]
   then
     ${ROOTRELABEL}
   fi

   # Unmount our CDMNT
   # rc_nohalt "umount -f ${CDMNT}"

   # Check if we need to run any gmirror syncing
   ls ${MIRRORCFGDIR}/* >/dev/null 2>/dev/null
   if [ "$?" = "0" ]
   then
     # Lets start syncing now
     start_gmirror_sync
   fi

   # Import any pools, so they are active at shutdown and ready to boot potentially
   zpool import -a

};

# Unmounts any filesystems after a failure
unmount_all_filesystems_failure()
{
  cd /

  # if we did a fresh install, start unmounting
  if [ "${INSTALLMODE}" = "fresh" ]
  then

    # Lets read our partition list, and unmount each
    ##################################################################
    if [ -d "${PARTDIR}" ]
    then
    for PART in `ls ${PARTDIR}`
    do
     
       PARTFS="`cat ${PARTDIR}/${PART} | cut -d ':' -f 1`"
       PARTMNT="`cat ${PARTDIR}/${PART} | cut -d ':' -f 2`"
       PARTENC="`cat ${PARTDIR}/${PART} | cut -d ':' -f 3`"

       #if [ "${PARTFS}" = "SWAP" ]
       #then
       #  if [ "${PARTENC}" = "ON" ]
       #  then
       #    rc_nohalt "swapoff /dev/${PART}.eli"
       #  else
       #    rc_nohalt "swapoff /dev/${PART}"
       #  fi
       #fi

       # Check if we've found "/" again, don't need to mount it twice
       if [ "$PARTMNT" != "/" -a "${PARTMNT}" != "none" -a "${PARTFS}" != "ZFS" ]
       then
         rc_nohalt "umount -f /dev/${PART}"
         rc_nohalt "umount -f ${FSMNT}${PARTMNT}"
       fi
     done

     # Last lets the /mnt partition
     #########################################################
     rc_nohalt "umount -f ${FSMNT} 2>/dev/null"

    fi
   else
     # We are doing a upgrade, try unmounting any of these filesystems
     chroot ${FSMNT} /sbin/umount -a >>${LOGOUT} >>${LOGOUT}
     umount -f ${FSMNT}/usr >>${LOGOUT} 2>>${LOGOUT}
     umount -f ${FSMNT}/dev >>${LOGOUT} 2>>${LOGOUT}
     umount -f ${FSMNT} >>${LOGOUT} 2>>${LOGOUT}
     rc_nohalt "sh ${TMPDIR}/.upgrade-unmount"
   fi
   
   # Unmount our CDMNT
   rc_nohalt "umount ${CDMNT} 2>/dev/null"

   # Import any pools, so they are active at shutdown and ready to boot potentially
   zpool import -a
};
