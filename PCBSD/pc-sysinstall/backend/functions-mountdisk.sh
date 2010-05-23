#!/bin/sh
# Functions related mounting the newly formatted disk partitions

# Mounts all the specified partition to the mount-point
mount_partition()
{
  if [ -z "${1}" -o -z "${2}" -o -z "${3}" ]
  then
    exit_err "ERROR: Missing arguments for mount_partition"
  fi

  PART="${1}"
  PARTFS="${2}"
  MNTPOINT="${3}"
  MNTFLAGS="${4}"

  # Setup the MNTOPTS
  if [ -z "${MNTOPTS}" ]
  then
    MNTFLAGS="-o rw"
  else
    MNTFLAGS="-o rw,${MNTFLAGS}"
  fi


  #We are on ZFS, lets setup this mount-point
  if [ "${PARTFS}" = "ZFS" ]
  then
     ZPOOLNAME=$(get_zpool_name "${PART}")

     # Check if we have multiple zfs mounts specified
     for ZMNT in `echo ${MNTPOINT} | sed 's|,| |g'`
     do
       # First make sure we create the mount point
       if [ ! -d "${FSMNT}${ZMNT}" ] ; then
         mkdir -p ${FSMNT}${ZMNT} >>${LOGOUT} 2>>${LOGOUT}
       fi

       if [ "${ZMNT}" = "/" ] ; then
         ZNAME=""
       else
         ZNAME="${ZMNT}"
         echo_log "zfs create -p ${ZPOOLNAME}${ZNAME}"
         rc_halt "zfs create -p ${ZPOOLNAME}${ZNAME}"
       fi
       sleep 2
       rc_halt "zfs set mountpoint=${FSMNT}${ZNAME} ${ZPOOLNAME}${ZNAME}"

       # Disable atime for this zfs partition, speed increase
       rc_nohalt "zfs set atime=off ${ZPOOLNAME}${ZNAME}"
     done 

  else
  # If we are not on ZFS, lets do the mount now
    # First make sure we create the mount point
    if [ ! -d "${FSMNT}${MNTPOINT}" ]
    then
      mkdir -p ${FSMNT}${MNTPOINT} >>${LOGOUT} 2>>${LOGOUT}
    fi

    echo_log "mount ${MNTFLAGS} /dev/${PART} -> ${FSMNT}${MNTPOINT}"
    sleep 2
    rc_halt "mount ${MNTFLAGS} /dev/${PART} ${FSMNT}${MNTPOINT}"
  fi

};

# Mounts all the new file systems to prepare for installation
mount_all_filesystems()
{
   # Make sure our mount point exists
   mkdir -p ${FSMNT} >/dev/null 2>/dev/null

   # First lets find and mount the / partition
   #########################################################
   for PART in `ls ${PARTDIR}`
   do
     if [ ! -e "/dev/${PART}" ]
     then
       exit_err "ERROR: The partition ${PART} does not exist. Failure in bsdlabel?"
     fi 

    PARTFS="`cat ${PARTDIR}/${PART} | cut -d ':' -f 1`"
    PARTMNT="`cat ${PARTDIR}/${PART} | cut -d ':' -f 2`"
    PARTENC="`cat ${PARTDIR}/${PART} | cut -d ':' -f 3`"

    if [ "${PARTENC}" = "ON" ]
    then
      EXT=".eli"
    else
      EXT=""
    fi

    # Check for root partition for mounting, including ZFS "/,/usr" type 
    echo "$PARTMNT" | grep "/," >/dev/null
    if [ "$?" = "0" -o "$PARTMNT" = "/" ]
    then
      case ${PARTFS} in
         UFS) mount_partition ${PART}${EXT} ${PARTFS} ${PARTMNT} "noatime"
              ;;
       UFS+S) mount_partition ${PART}${EXT} ${PARTFS} ${PARTMNT} "noatime"
              ;;
       UFS+J) mount_partition ${PART}${EXT}.journal ${PARTFS} ${PARTMNT} "async,noatime"
              ;;
         ZFS) mount_partition ${PART} ${PARTFS} ${PARTMNT}
              ;;
           *) exit_err "ERROR: Got unknown file-system type $PARTFS" ;;
      esac

    fi
     
   done

   # Now that we've mounted "/" lets do any other remaining mount-points
   ##################################################################
   for PART in `ls ${PARTDIR}`
   do
     if [ ! -e "/dev/${PART}" ]
     then
       exit_err "ERROR: The partition ${PART} does not exist. Failure in bsdlabel?"
     fi 
     
     PARTFS="`cat ${PARTDIR}/${PART} | cut -d ':' -f 1`"
     PARTMNT="`cat ${PARTDIR}/${PART} | cut -d ':' -f 2`"
     PARTENC="`cat ${PARTDIR}/${PART} | cut -d ':' -f 3`"

     if [ "${PARTENC}" = "ON" ]
     then
       EXT=".eli"
     else
       EXT=""
     fi

     # Check if we've found "/" again, don't need to mount it twice
     echo "$PARTMNT" | grep "/," >/dev/null
     if [ "$?" != "0" -a "$PARTMNT" != "/" ]
     then
       case ${PARTFS} in
         UFS) mount_partition ${PART}${EXT} ${PARTFS} ${PARTMNT} "noatime"
              ;;
       UFS+S) mount_partition ${PART}${EXT} ${PARTFS} ${PARTMNT} "noatime"
              ;;
       UFS+J) mount_partition ${PART}${EXT}.journal ${PARTFS} ${PARTMNT} "async,noatime"
              ;;
         ZFS) mount_partition ${PART} ${PARTFS} ${PARTMNT}
              ;;
        SWAP) # Lets enable this swap now
              if [ "$PARTENC" = "ON" ]
              then
                echo_log "Enabling encrypted swap on /dev/${PART}"
                rc_halt "geli onetime -d -e 3des ${PART}"
                sleep 5
                rc_halt "swapon /dev/${PART}.eli"
              else
                echo_log "swapon ${PART}"
                sleep 5
                rc_halt "swapon /dev/${PART}"
              fi
              ;;
          *) exit_err "ERROR: Got unknown file-system type $PARTFS" ;;
       esac
     fi
   done
};
