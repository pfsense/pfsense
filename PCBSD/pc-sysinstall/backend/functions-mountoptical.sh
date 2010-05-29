#!/bin/sh
# Functions which perform mounting / unmounting and switching of optical / usb media

. ${BACKEND}/functions.sh
. ${BACKEND}/functions-parse.sh

# Displays an optical failure message
opt_fail()
{
   # If we got here, we must not have a DVD/USB we can find :(
   get_value_from_cfg installInteractive
   if [ "${VAL}" = "yes" ]
   then
     # We are running interactive, and didn't find a DVD, prompt user again
     echo_log "DISK ERROR: Unable to find installation disk!"
     echo_log "Please insert the installation disk and press enter."
     read tmp
   else
    exit_err "ERROR: Unable to locate installation DVD/USB"
   fi
};

# Performs the extraction of data to disk
opt_mount()
{
 FOUND="0"

 # Ensure we have a directory where its supposed to be
 if [ ! -d "${CDMNT}" ]
 then
   mkdir -p ${CDMNT}
 fi


 # Start by checking if we already have a cd mounted at CDMNT
 mount | grep "${CDMNT} " >/dev/null 2>/dev/null
 if [ "$?" = "0" ]
 then
   if [ -e "${CDMNT}/${INSFILE}" ]
   then
     echo "MOUNTED" >${TMPDIR}/cdmnt
     echo_log "FOUND DVD: MOUNTED"
     FOUND="1"
     return
   fi

   # failed to find optical disk
   opt_fail
   return
 fi

# Setup our loop to search for installation media
 while
 z=1
 do

   # Loop though and look for an installation disk
   for i in `ls -1 /dev/acd* /dev/cd* /dev/scd* /dev/rscd* 2>/dev/null`
   do
     # Find the CD Device
     /sbin/mount_cd9660 $i ${CDMNT}

     # Check the package type to see if we have our install data
     if [ -e "${CDMNT}/${INSFILE}" ]
     then
       echo "${i}" >${TMPDIR}/cdmnt
       echo_log "FOUND DVD: ${i}"
       FOUND="1"
       break
     fi
     /sbin/umount ${CDMNT} >/dev/null 2>/dev/null
   done

   # If no DVD found, try USB
   if [ "$FOUND" != "1" ]
   then
     # Loop though and look for an installation disk
     for i in `ls -1 /dev/da* 2>/dev/null`
     do
       # Check if we can mount this device UFS
       /sbin/mount -r $i ${CDMNT}

       # Check the package type to see if we have our install data
       if [ -e "${CDMNT}/${INSFILE}" ]
       then
         echo "${i}" >${TMPDIR}/cdmnt
         echo_log "FOUND USB: ${i}"
         FOUND="1"
         break
       fi
       /sbin/umount ${CDMNT} >/dev/null 2>/dev/null

       # Also check if it is a FAT mount
       /sbin/mount -r -t msdosfs $i ${CDMNT}

       # Check the package type to see if we have our install data
       if [ -e "${CDMNT}/${INSFILE}" ]
       then
         echo "${i}" >${TMPDIR}/cdmnt
         echo_log "FOUND USB: ${i}"
         FOUND="1"
         break
       fi
       /sbin/umount ${CDMNT} >/dev/null 2>/dev/null
     done
   fi # End of USB Check


   if [ "$FOUND" = "1" ]
   then
     break
   fi
   
   # Failed to find a disk, take action now
   opt_fail

 done

};

# Function to unmount optical media
opt_umount()
{
  /sbin/umount ${CDMNT} >/dev/null 2>/dev/null
};

