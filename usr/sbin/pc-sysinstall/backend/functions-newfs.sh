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
# $FreeBSD: src/usr.sbin/pc-sysinstall/backend/functions-newfs.sh,v 1.6 2010/10/09 08:52:09 imp Exp $

# Functions related to disk operations using newfs


# Function which performs the ZFS magic
setup_zfs_filesystem()
{
  PART="$1"
  PARTFS="$2"
  PARTMNT="$3"
  EXT="$4"
  PARTGEOM="$5"
  ZPOOLOPTS="$6"
  ROOTSLICE="`echo ${PART} | rev | cut -b 2- | rev`"
  ZPOOLNAME=$(get_zpool_name "${PART}")

  # Sleep a few moments, let the disk catch its breath
  sleep 5
  sync

  # Check if we have some custom zpool arguments and use them if so
  if [ ! -z "${ZPOOLOPTS}" ] ; then
    rc_halt "zpool create -m none -f ${ZPOOLNAME} ${ZPOOLOPTS}"
  else
    # No zpool options, create pool on single device
    rc_halt "zpool create -m none -f ${ZPOOLNAME} ${PART}${EXT}"
  fi

  # Disable atime for this zfs partition, speed increase
  rc_nohalt "zfs set atime=off ${ZPOOLNAME}"

  # Check if we have multiple zfs mounts specified
  for i in `echo ${PARTMNT} | sed 's|,| |g'`
  do
    # Check if we ended up with needing a zfs bootable partition
    if [ "${i}" = "/" -o "${i}" = "/boot" ]
    then
      if [ "$HAVEBOOT" = "YES" ] ; then continue ; fi
      if [ "${PARTGEOM}" = "MBR" ]
      then
        # Lets stamp the proper ZFS boot loader
        echo_log "Setting up ZFS boot loader support"
        rc_halt "zpool set bootfs=${ZPOOLNAME} ${ZPOOLNAME}"
        rc_halt "zpool export ${ZPOOLNAME}"
        rc_halt "dd if=/boot/zfsboot of=/dev/${ROOTSLICE} count=1"
        rc_halt "dd if=/boot/zfsboot of=/dev/${PART}${EXT} skip=1 seek=1024"
        rc_halt "zpool import ${ZPOOLNAME}"
      fi
    fi
  done

};

# Runs newfs on all the partiions which we've setup with bsdlabel
setup_filesystems()
{

  # Create the keydir
  rm -rf ${GELIKEYDIR} >/dev/null 2>/dev/null
  mkdir ${GELIKEYDIR}

  # Lets go ahead and read through the saved partitions we created, and determine if we need to run
  # newfs on any of them
  for PART in `ls ${PARTDIR}`
  do
    if [ ! -e "/dev/${PART}" ]
    then
      exit_err "ERROR: The partition ${PART} does not exist. Failure in bsdlabel?"
    fi

    PARTFS="`cat ${PARTDIR}/${PART} | cut -d ':' -f 1`"
    PARTMNT="`cat ${PARTDIR}/${PART} | cut -d ':' -f 2`"
    PARTENC="`cat ${PARTDIR}/${PART} | cut -d ':' -f 3`"
    PARTLABEL="`cat ${PARTDIR}/${PART} | cut -d ':' -f 4`"
    PARTGEOM="`cat ${PARTDIR}/${PART} | cut -d ':' -f 5`"
    PARTXTRAOPTS="`cat ${PARTDIR}/${PART} | cut -d ':' -f 6`"
    PARTIMAGE="`cat ${PARTDIR}/${PART} | cut -d ':' -f 7`"

    # Make sure journaling isn't enabled on this device
    if [ -e "/dev/${PART}.journal" ]
    then
      rc_nohalt "gjournal stop -f ${PART}.journal"
      rc_nohalt "gjournal clear ${PART}"
    fi

    # Setup encryption if necessary
    if [ "${PARTENC}" = "ON" -a "${PARTFS}" != "SWAP" ]
    then
      echo_log "Creating geli provider for ${PART}"
      rc_halt "dd if=/dev/random of=${GELIKEYDIR}/${PART}.key bs=64 count=1"
      rc_halt "geli init -b -s 4096 -P -K ${GELIKEYDIR}/${PART}.key /dev/${PART}"
      rc_halt "geli attach -p -k ${GELIKEYDIR}/${PART}.key /dev/${PART}"

      EXT=".eli"
    else
      # No Encryption
      EXT=""
    fi

    case ${PARTFS} in
      UFS)
        echo_log "NEWFS: /dev/${PART} - ${PARTFS}"
        sleep 2
        rc_halt "newfs /dev/${PART}${EXT}"
        sleep 2
        rc_halt "sync"
        rc_halt "glabel label ${PARTLABEL} /dev/${PART}${EXT}"
        rc_halt "sync"

        # Set flag that we've found a boot partition
        if [ "$PARTMNT" = "/boot" -o "${PARTMNT}" = "/" ] ; then
		  HAVEBOOT="YES"
        fi
        sleep 2
        ;;

      UFS+S)
        echo_log "NEWFS: /dev/${PART} - ${PARTFS}"
        sleep 2
        rc_halt "newfs -U /dev/${PART}${EXT}"
        sleep 2
        rc_halt "sync"
        rc_halt "glabel label ${PARTLABEL} /dev/${PART}${EXT}"
        rc_halt "sync"
	    # Set flag that we've found a boot partition
	    if [ "$PARTMNT" = "/boot" -o "${PARTMNT}" = "/" ] ; then
          HAVEBOOT="YES"
        fi
        sleep 2
        ;;

      UFS+SUJ)
        echo_log "NEWFS: /dev/${PART} - ${PARTFS}"
        sleep 2
        rc_halt "newfs -U /dev/${PART}${EXT}"
        sleep 2
        rc_halt "sync"
        rc_halt "tunefs -j enable /dev/${PART}${EXT}"
        sleep 2
        rc_halt "sync"
        rc_halt "glabel label ${PARTLABEL} /dev/${PART}${EXT}"
        rc_halt "sync"
	    # Set flag that we've found a boot partition
	    if [ "$PARTMNT" = "/boot" -o "${PARTMNT}" = "/" ] ; then
          HAVEBOOT="YES"
        fi
        sleep 2
        ;;


      UFS+J)
        echo_log "NEWFS: /dev/${PART} - ${PARTFS}"
        sleep 2
        rc_halt "newfs /dev/${PART}${EXT}"
        sleep 2
        rc_halt "gjournal label -f /dev/${PART}${EXT}"
        sleep 2
        rc_halt "newfs -O 2 -J /dev/${PART}${EXT}.journal"
        sleep 2
        rc_halt "sync"
        rc_halt "glabel label ${PARTLABEL} /dev/${PART}${EXT}.journal"
        rc_halt "sync"
	    # Set flag that we've found a boot partition
	    if [ "$PARTMNT" = "/boot" -o "${PARTMNT}" = "/" ] ; then
          HAVEBOOT="YES"
  	    fi
        sleep 2
        ;;

      ZFS)
        echo_log "NEWFS: /dev/${PART} - ${PARTFS}"
        setup_zfs_filesystem "${PART}" "${PARTFS}" "${PARTMNT}" "${EXT}" "${PARTGEOM}" "${PARTXTRAOPTS}"
        ;;

      SWAP)
        rc_halt "sync"
        rc_halt "glabel label ${PARTLABEL} /dev/${PART}${EXT}"
        rc_halt "sync"
        sleep 2
        ;;

      IMAGE)
        write_image "${PARTIMAGE}" "${PART}"
        sleep 2
        ;;

      *) exit_err "ERROR: Got unknown file-system type $PARTFS" ;;
    esac

  done
};
