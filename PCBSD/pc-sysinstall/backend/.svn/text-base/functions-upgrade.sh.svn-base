#!/bin/sh
# Functions which perform the mounting / unmount for upgrades


mount_target_slice()
{
  MPART="${1}"

  # Import any zpools
  zpool import -a
  
  # Set a variable of files we want to make backups of before doing upgrade
  BKFILES="/etc/rc.conf /boot/loader.conf"

  if [ -e "/dev/${MPART}a.journal" ]
  then
    rc_halt "mount /dev/${MPART}.journal ${FSMNT}"
  elif [ -e "/dev/${MPART}a" ]
  then
    mount /dev/${MPART}a ${FSMNT}
    if [ "$?" != "0" ]
    then
      # Try ZFS on this slice
      mount -t zfs ${MPART}a ${FSMNT}
      if [ "${?}" != "0" ]
      then
        exit_err "Failed to mount ${MPART}"
      fi
    fi
  fi

  mount -t devfs devfs ${FSMNT}/dev
  chroot ${FSMNT} /sbin/mount -a >>${LOGOUT} 2>>${LOGOUT}
  chroot ${FSMNT} umount /proc >/dev/null 2>/dev/null 
  chroot ${FSMNT} umount /compat/linux/proc  >/dev/null 2>/dev/null

  # Save which partition was mounted, so we may unmount it later
  echo "umount -f /dev/${MPART}a" >>${TMPDIR}/.upgrade-unmount

  # Now before we start the upgrade, make sure we set our noschg flags
  echo_log "Cleaning up old filesystem... Please wait..."
  rc_halt "chflags -R noschg ${FSMNT}"

  # Make backup copies of some files
  for i in ${BKFILES}
  do
    cp ${FSMNT}${i} ${FSMNT}${i}.preUpgrade >/dev/null 2>/dev/null
  done

  # Remove some old dirs
  rm -rf ${FSMNT}/etc/rc.d >/dev/null 2>/dev/null

  # If we are doing PC-BSD install, lets cleanup old pkgs on disk
  if [ "$INSTALLTYPE" != "FreeBSD" ]
  then
    echo_log "Removing old packages, this may take a while... Please wait..."
    echo '#/bin/sh
for i in `pkg_info -E \*`
do
  echo "Uninstalling package: ${i}"
  pkg_delete -f ${i} >/dev/null 2>/dev/null
done
' >${FSMNT}/.cleanPkgs.sh
    chmod 755 ${FSMNT}/.cleanPkgs.sh
    chroot ${FSMNT} /.cleanPkgs.sh
    rm ${FSMNT}/.cleanPkgs.sh
    run_chroot_cmd "pkg_delete -f \*" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /usr/PCBSD" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /PCBSD" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /var/db/pkgs" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /usr/local32" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /usr/sbin" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /usr/lib" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /usr/bin" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /boot/kernel" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /sbin" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /bin" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /lib" >/dev/null 2>/dev/null
    run_chroot_cmd "rm -rf /libexec" >/dev/null 2>/dev/null
  fi

}

# Mount the target upgrade partitions
mount_upgrade()
{

  # Start with disk0
  disknum="0"

  # Make sure we remove the old upgrade-mount script
  rm -rf ${TMPDIR}/.upgrade-unmount >/dev/null 2>/dev/null

  # We are ready to start mounting, lets read the config and do it
  while read line
  do
     echo $line | grep "^disk${disknum}=" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then

       # Found a disk= entry, lets get the disk we are working on
       get_value_from_string "${line}"
       strip_white_space "$VAL"
       DISK="$VAL"
     
       # Before we go further, lets confirm this disk really exists
       if [ ! -e "/dev/${DISK}" ]
       then
         exit_err "ERROR: The disk ${DISK} does not exist!"
       fi
     fi

     echo $line | grep "^partition=" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       # Found a partition= entry, lets read / set it 
       get_value_from_string "${line}"
       strip_white_space "$VAL"
       PTYPE="$VAL"

       # We are using free space, figure out the slice number
       if [ "${PTYPE}" = "free" -o "${PTYPE}" = "all" ]
       then
           exit_err "ERROR: Invalid upgrade partition=${PTYPE}"
       fi
     fi

     echo $line | grep "^commitDiskPart" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       # Found our flag to commit this disk setup / lets do sanity check and do it
       if [ ! -z "${DISK}" -a ! -z "${PTYPE}" ]
       then
         case ${PTYPE} in
           s1|s2|s3|s4) tmpSLICE="${DISK}${PTYPE}a" ;;
                     *) exit_err "ERROR: Unknown PTYPE: $PTYPE" ;;
         esac

         if [ ! -e "/dev/${tmpSLICE}" ]
         then
           exit_err "ERROR: /dev/${tmpSLICE} does not exist! Mount failed!"
         fi

         # Start mounting this slice
         mount_target_slice "${DISK}${PTYPE}" 

         # Increment our disk counter to look for next disk and unset
         unset PTYPE DISK
         disknum="`expr $disknum + 1`"
       else
         exit_err "ERROR: commitDiskPart was called without procceding disk<num>= and partition= entries!!!" 
       fi
     fi

  done <${CFGF}

};

copy_skel_files_upgrade()
{

    # Now make sure we fix any user profile scripts, which cause problems from 7.x->8.x
    echo '#!/bin/sh

cd /home
for i in `ls`
do

  # Backup the old profile dirs
  if [ -d "${i}" ]
  then
    mv /home/${i}/.kde4 /home/${i}/.kde4.preUpgrade >/dev/null 2>/dev/null
    mv /home/${i}/.kde /home/${i}/.kde.preUpgrade >/dev/null 2>/dev/null
    mv /home/${i}/.fluxbox /home/${i}/.fluxbox.preUpgrade >/dev/null 2>/dev/null

    # Copy over the skel directories
    tar cv --exclude "./dot.*" -f - -C /usr/share/skel . 2>/dev/null | tar xvf - -C /home/${i} 2>/dev/null

    for j in `ls /usr/share/skel/dot*`
    do
      dname=`echo ${j} | sed s/dot//`
      cp /usr/share/skel/${j} /home/${i}/${dname}
    done

    chown -R ${i}:${i} /home/${i}
  fi

done
' >${FSMNT}/.fixUserProfile.sh  
    chmod 755 ${FSMNT}/.fixUserProfile.sh
    chroot ${FSMNT} /.fixUserProfile.sh >/dev/null 2>/dev/null
    rm ${FSMNT}/.fixUserProfile.sh



    # if the user wants to keep their original .kde4 profile
    ###########################################################################
    get_value_from_cfg "upgradeKeepDesktopProfile"
    if [ "$VAL" = "YES" -o "$VAL" = "yes" ] ; then
      echo '#!/bin/sh
      cd /home
for i in `ls`
do
  # Import the old config again
  if [ -d "${i}/.kde4.preUpgrade" ]
  then
    # Copy over the skel directories
    tar cv -f - -C /home/${i}/.kde4.preUpgrade . 2>/dev/null | tar xvf - -C /home/${i}/.kde4 2>/dev/null
    chown -R ${i}:${i} /home/${i}/.kde4
  fi
done
' >${FSMNT}/.fixUserProfile.sh
      chmod 755 ${FSMNT}/.fixUserProfile.sh
      chroot ${FSMNT} /.fixUserProfile.sh >/dev/null 2>/dev/null
      rm ${FSMNT}/.fixUserProfile.sh

    fi

};

# Function which merges some configuration files with the new defaults
merge_old_configs()
{

  # Merge the loader.conf with old
  cp ${FSMNT}/boot/loader.conf ${FSMNT}/boot/loader.conf.new
  merge_config "${FSMNT}/boot/loader.conf.preUpgrade" "${FSMNT}/boot/loader.conf.new" "${FSMNT}/boot/loader.conf"
  rm ${FSMNT}/boot/loader.conf.new

  # Merge the rc.conf with old
  cp ${FSMNT}/etc/rc.conf ${FSMNT}/etc/rc.conf.new
  merge_config "${FSMNT}/etc/rc.conf.preUpgrade" "${FSMNT}/etc/rc.conf.new" "${FSMNT}/etc/rc.conf"
  rm ${FSMNT}/etc/rc.conf.new

};

# Function which unmounts all the mounted file-systems
unmount_upgrade()
{

   # If on PC-BSD, make sure we copy any fixed skel files
   if [ "$INSTALLTYPE" != "FreeBSD" ] ; then
     copy_skel_files_upgrade
   fi

   cd /

   # Unmount FS
   chroot ${FSMNT} /sbin/umount -a >/dev/null 2>/dev/null
   umount ${FSMNT}/usr >/dev/null 2>/dev/null
   umount ${FSMNT}/var >/dev/null 2>/dev/null
   umount ${FSMNT}/dev >/dev/null 2>/dev/null
   umount ${FSMNT} >/dev/null 2>/dev/null

   # Run our saved unmount script for these file-systems
   rc_nohalt "sh ${TMPDIR}/.upgrade-unmount"
 
   umount ${CDMNT} 
};
