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
# $FreeBSD: src/usr.sbin/pc-sysinstall/backend/functions-extractimage.sh,v 1.8 2010/10/21 23:08:42 imp Exp $

# Functions which perform the extraction / installation of system to disk

. ${BACKEND}/functions-mountoptical.sh

# Performs the extraction of data to disk from a uzip or tar archive
start_extract_uzip_tar()
{
  if [ -z "$INSFILE" ]
  then
    exit_err "ERROR: Called extraction with no install file set!"
  fi

  # Check if we have a .count file, and echo it out for a front-end to use in progress bars
  if [ -e "${INSFILE}.count" ]
  then
    echo "INSTALLCOUNT: `cat ${INSFILE}.count`"
  fi

  # Check if we are doing an upgrade, and if so use our exclude list
  if [ "${INSTALLMODE}" = "upgrade" ]
  then
   TAROPTS="-X ${PROGDIR}/conf/exclude-from-upgrade"
  else
   TAROPTS=""
  fi

  echo_log "pc-sysinstall: Starting Extraction"

  case ${PACKAGETYPE} in
    uzip)
	  # Start by mounting the uzip image
      MDDEVICE=`mdconfig -a -t vnode -o readonly -f ${INSFILE}`
      mkdir -p ${FSMNT}.uzip
      mount -r /dev/${MDDEVICE}.uzip ${FSMNT}.uzip
      if [ "$?" != "0" ]
      then
        exit_err "ERROR: Failed mounting the ${INSFILE}"
      fi
      cd ${FSMNT}.uzip

      # Copy over all the files now!
      tar cvf - . 2>/dev/null | tar -xpv -C ${FSMNT} ${TAROPTS} -f - 2>&1 | tee -a ${FSMNT}/.tar-extract.log
      if [ "$?" != "0" ]
      then
        cd /
        echo "TAR failure occurred:" >>${LOGOUT}
        cat ${FSMNT}/.tar-extract.log | grep "tar:" >>${LOGOUT}
        umount ${FSMNT}.uzip
        mdconfig -d -u ${MDDEVICE}
        exit_err "ERROR: Failed extracting the tar image"
      fi

      # All finished, now lets umount and cleanup
      cd /
      umount ${FSMNT}.uzip
      mdconfig -d -u ${MDDEVICE}
       ;;
    tar)
	  tar -xpv -C ${FSMNT} -f ${INSFILE} ${TAROPTS} >&1 2>&1
      if [ "$?" != "0" ]
      then
        exit_err "ERROR: Failed extracting the tar image"
      fi
      ;;
  esac

  # Check if this was a FTP download and clean it up now
  if [ "${INSTALLMEDIUM}" = "ftp" ]
  then
    echo_log "Cleaning up downloaded archive"
    rm ${INSFILE}
    rm ${INSFILE}.count >/dev/null 2>/dev/null
    rm ${INSFILE}.md5 >/dev/null 2>/dev/null
  fi

  echo_log "pc-sysinstall: Extraction Finished"

};

# Performs the extraction of data to disk from a directory with split files
start_extract_split()
{
  if [ -z "${INSDIR}" ]
  then
    exit_err "ERROR: Called extraction with no install directory set!"
  fi

  echo_log "pc-sysinstall: Starting Extraction"

  # Used by install.sh
  DESTDIR="${FSMNT}"
  export DESTDIR

  HERE=`pwd`
  DIRS=`ls -d ${INSDIR}/*|grep -Ev '(uzip|kernels|src)'`
  for dir in ${DIRS}
  do
    cd "${dir}"
    if [ -f "install.sh" ]
    then
      echo_log "Extracting" `basename ${dir}`
      echo "y" | sh install.sh >/dev/null
      if [ "$?" != "0" ]
      then
        exit_err "ERROR: Failed extracting ${dir}"
      fi
    else
      exit_err "ERROR: ${dir}/install.sh does not exist"
    fi
  done
  cd "${HERE}"

  KERNELS=`ls -d ${INSDIR}/*|grep kernels`
  cd "${KERNELS}"
  if [ -f "install.sh" ]
  then
    echo_log "Extracting" `basename ${KERNELS}`
    echo "y" | sh install.sh generic >/dev/null
    if [ "$?" != "0" ]
    then
      exit_err "ERROR: Failed extracting ${KERNELS}"
    fi
    rm -rf "${FSMNT}/boot/kernel"
    mv "${FSMNT}/boot/GENERIC" "${FSMNT}/boot/kernel"
  else
    exit_err "ERROR: ${KERNELS}/install.sh does not exist"
  fi
  cd "${HERE}"

  SOURCE=`ls -d ${INSDIR}/*|grep src`
  cd "${SOURCE}"
  if [ -f "install.sh" ]
  then
    echo_log "Extracting" `basename ${SOURCE}`
    echo "y" | sh install.sh all >/dev/null
    if [ "$?" != "0" ]
    then
      exit_err "ERROR: Failed extracting ${SOURCE}"
    fi
  else
    exit_err "ERROR: ${SOURCE}/install.sh does not exist"
  fi
  cd "${HERE}"

  echo_log "pc-sysinstall: Extraction Finished"
};

# Function which will attempt to fetch the install file before we start
# the install
fetch_install_file()
{
  get_value_from_cfg ftpPath
  if [ -z "$VAL" ]
  then
    exit_err "ERROR: Install medium was set to ftp, but no ftpPath was provided!"
  fi

  FTPPATH="${VAL}"

  # Check if we have a /usr partition to save the download
  if [ -d "${FSMNT}/usr" ]
  then
    OUTFILE="${FSMNT}/usr/.fetch-${INSFILE}"
  else
    OUTFILE="${FSMNT}/.fetch-${INSFILE}"
  fi

  # Do the fetch of the archive now
  fetch_file "${FTPPATH}/${INSFILE}" "${OUTFILE}" "1"

  # Check to see if there is a .count file for this install
  fetch_file "${FTPPATH}/${INSFILE}.count" "${OUTFILE}.count" "0"

  # Check to see if there is a .md5 file for this install
  fetch_file "${FTPPATH}/${INSFILE}.md5" "${OUTFILE}.md5" "0"

  # Done fetching, now reset the INSFILE to our downloaded archived
  INSFILE="${OUTFILE}" ; export INSFILE

};

# Function which will download freebsd install files
fetch_split_files()
{
  get_ftpHost
  if [ -z "$VAL" ]
  then
    exit_err "ERROR: Install medium was set to ftp, but no ftpHost was provided!"
  fi
  FTPHOST="${VAL}"

  get_ftpDir
  if [ -z "$VAL" ]
  then
    exit_err "ERROR: Install medium was set to ftp, but no ftpDir was provided!"
  fi
  FTPDIR="${VAL}"

  # Check if we have a /usr partition to save the download
  if [ -d "${FSMNT}/usr" ]
  then
    OUTFILE="${FSMNT}/usr/.fetch-${INSFILE}"
  else
    OUTFILE="${FSMNT}/.fetch-${INSFILE}"
  fi

  DIRS="base catpages dict doc games info manpages proflibs kernels src"
  if [ "${FBSD_ARCH}" = "amd64" ]
  then
    DIRS="${DIRS} lib32"
  fi

  for d in ${DIRS}
  do
    mkdir -p "${OUTFILE}/${d}"
  done


  NETRC="${OUTFILE}/.netrc"
  cat<<EOF>"${NETRC}"
machine ${FTPHOST}
login anonymous
password anonymous
macdef INSTALL
bin
prompt
EOF

  for d in ${DIRS}
  do
    cat<<EOF>>"${NETRC}"
cd ${FTPDIR}/${d}
lcd ${OUTFILE}/${d}
mreget *
EOF
  done

  cat<<EOF>>"${NETRC}"
bye


EOF

  # Fetch the files via ftp
  echo "$ INSTALL" | ftp -N "${NETRC}" "${FTPHOST}"

  # Done fetching, now reset the INSFILE to our downloaded archived
  INSFILE="${OUTFILE}" ; export INSFILE
}

# Function which does the rsync download from the server specified in cfg
start_rsync_copy()
{
  # Load our rsync config values
  get_value_from_cfg rsyncPath
  if [ -z "${VAL}" ]; then
    exit_err "ERROR: rsyncPath is unset! Please check your config and try again."
  fi
  RSYNCPATH="${VAL}" ; export RSYNCPATH

  get_value_from_cfg rsyncHost
  if [  -z "${VAL}" ]; then
    exit_err "ERROR: rsyncHost is unset! Please check your config and try again."
  fi
  RSYNCHOST="${VAL}" ; export RSYNCHOST

  get_value_from_cfg rsyncUser
  if [ -z "${VAL}" ]; then
    exit_err "ERROR: rsyncUser is unset! Please check your config and try again."
  fi
  RSYNCUSER="${VAL}" ; export RSYNCUSER

  get_value_from_cfg rsyncPort
  if [ -z "${VAL}" ]; then
    exit_err "ERROR: rsyncPort is unset! Please check your config and try again."
  fi
  RSYNCPORT="${VAL}" ; export RSYNCPORT

  COUNT="1"
  while
  z=1
  do
    if [ ${COUNT} -gt ${RSYNCTRIES} ]
    then
     exit_err "ERROR: Failed rsync command!"
     break
    fi

    rsync -avvzHsR \
    --rsync-path="rsync --fake-super" \
    -e "ssh -p ${RSYNCPORT}" \
    ${RSYNCUSER}@${RSYNCHOST}:${RSYNCPATH}/./ ${FSMNT}
    if [ "$?" != "0" ]
    then
      echo "Rsync failed! Tries: ${COUNT}"
    else
      break
    fi

    COUNT="`expr ${COUNT} + 1`"
  done

};

start_image_install()
{
  if [ -z "${IMAGE_FILE}" ]
  then
    exit_err "ERROR: installMedium set to image but no image file specified!"
  fi

  # We are ready to start mounting, lets read the config and do it
  while read line
  do
    echo $line | grep "^disk0=" >/dev/null 2>/dev/null
    if [ "$?" = "0" ]
    then
      # Found a disk= entry, lets get the disk we are working on
      get_value_from_string "${line}"
      strip_white_space "$VAL"
      DISK="$VAL"
    fi

    echo $line | grep "^commitDiskPart" >/dev/null 2>/dev/null
    if [ "$?" = "0" ]
    then
      # Found our flag to commit this disk setup / lets do sanity check and do it
      if [ ! -z "${DISK}" ]
      then

        # Write the image
        write_image "${IMAGE_FILE}" "${DISK}"

        # Increment our disk counter to look for next disk and unset
        unset DISK
        break

      else
        exit_err "ERROR: commitDiskPart was called without procceding disk<num>= and partition= entries!!!"
      fi
    fi

  done <${CFGF}
};

# Entrance function, which starts the installation process
init_extraction()
{
  # Figure out what file we are using to install from via the config
  get_value_from_cfg installFile

  if [ ! -z "${VAL}" ]
  then
    INSFILE="${VAL}" ; export INSFILE
  else
    # If no installFile specified, try our defaults
    if [ "$INSTALLTYPE" = "FreeBSD" ]
    then
      case $PACKAGETYPE in
        uzip) INSFILE="${FBSD_UZIP_FILE}" ;;
        tar) INSFILE="${FBSD_TAR_FILE}" ;;
        split)
          INSDIR="${FBSD_BRANCH_DIR}"

          # This is to trick opt_mount into not failing
          INSFILE="${INSDIR}"
          ;;
      esac
    else
      case $PACKAGETYPE in
        uzip) INSFILE="${UZIP_FILE}" ;;
        tar) INSFILE="${TAR_FILE}" ;;
      esac
    fi
    export INSFILE
  fi

  # Lets start by figuring out what medium we are using
  case ${INSTALLMEDIUM} in
  LiveCD) # Copies files using cpdup. Ideal for pre-staged fs
          if [ ! -f /usr/local/bin/cpdup ]
          then
            echo "Could not locate cpdup binary" >>${LOGOUT}
            exit_err "Could not locate cpdup binary (pkg_add -r cpdup)?"
          fi
          get_value_from_cfg cpdupPathsPrefix
          if [ ! -z "${VAL}" ]
            CPDUPPATHPREFIX=""
          then
            CPDUPPATHPREFIX="${VAL}" ; export CPDUPPATHPREFIX
          fi
          get_value_from_cfg cpdupPaths
          if [ ! -z "${VAL}" ]
          then
            CPDUPDIR="${VAL}" ; export CPDUPDIR
          fi
          oIFS=$IFS
          IFS=","
          for FILE in $CPDUPDIR; do
            echo_log "pc-sysinstall: Running cpdup -o ${CPDUPPATHPREFIX}/${FILE} /mnt/${FILE}"
            /usr/local/bin/cpdup -o ${CPDUPPATHPREFIX}/${FILE} /mnt/${FILE} >&1 2>&1
            if [ "$?" != "0" ]
            then
              echo "cpdup failure occurred:" >>${LOGOUT}
              exit_err "ERROR: Error occurred during cpdup"
            fi
          done
          IFS=$oIFS
          return
          ;;
 dvd|usb) # Lets start by mounting the disk
          opt_mount
          if [ ! -z "${INSDIR}" ]
          then
            INSDIR="${CDMNT}/${INSDIR}" ; export INSDIR
            start_extract_split
          else
            INSFILE="${CDMNT}/${INSFILE}" ; export INSFILE
            start_extract_uzip_tar
          fi
          ;;
     ftp) fetch_install_file
          start_extract_uzip_tar
          ;;
     rsync) start_rsync_copy
          ;;
       *) exit_err "ERROR: Unknown install medium" ;;
  esac

};
