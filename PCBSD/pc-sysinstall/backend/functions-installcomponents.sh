#!/bin/sh
# Functions which check and load any optional modules specified in the config

. ${BACKEND}/functions.sh
. ${BACKEND}/functions-parse.sh

copy_component()
{
  COMPONENT="$1"
  FAILED="0"
  CFILES=""

  # Check the type, and set the components subdir properly
  TYPE="`grep 'type:' ${COMPDIR}/${COMPONENT}/component.cfg | cut -d ' ' -f 2`"
  if [ "${TYPE}" = "PBI" ]
  then
    SUBDIR="PBI"
  else
    SUBDIR="components"
  fi

  # Lets start by downloading / copying the files this component needs
  while read line
  do
    CFILE="`echo $line | cut -d ':' -f 1`"
    CFILEMD5="`echo $line | cut -d ':' -f 2`"
    CFILE2MD5="`echo $line | cut -d ':' -f 3`"


    case ${INSTALLMEDIUM} in
   dvd|usb) # On both dvd / usb, we can just copy the file
            cp ${CDMNT}/${COMPFILEDIR}/${SUBDIR}/${CFILE} \
		 ${FSMNT}/${COMPTMPDIR} >>${LOGOUT} 2>>${LOGOUT}
	    RESULT="$?"
            ;;
       ftp) get_value_from_cfg ftpPath
            if [ -z "$VAL" ]
            then
              exit_err "ERROR: Install medium was set to ftp, but no ftpPath was provided!"
            fi
            FTPPATH="${VAL}" 

            fetch_file "${FTPPATH}/${COMPFILEDIR}/${SUBDIR}/${CFILE}" "${FSMNT}/${COMPTMPDIR}/${CFILE}" "0"
	    RESULT="$?"
            ;;
    esac

    if [ "${RESULT}" != "0" ]
    then
      echo_log "WARNING: Failed to copy ${CFILE}"
      FAILED="1"
    else
      # Now lets check the MD5 to confirm the file is valid
      CHECKMD5=`md5 -q ${FSMNT}/${COMPTMPDIR}/${CFILE}`
      if [ "${CHECKMD5}" != "${CFILEMD5}" -a "${CHECKMD5}" != "${CFILE2MD5}" ]
      then
        echo_log "WARNING: ${CFILE} failed md5 checksum"
        FAILED="1"
      else
        if [ -z "${CFILES}" ]
        then
          CFILES="${CFILE}" 
        else
          CFILES="${CFILES},${CFILE}"
        fi
      fi
    fi


  done < ${COMPDIR}/${COMPONENT}/distfiles
      
  if [ "${FAILED}" = "0" ]
  then
    # Now install the component
    run_component_install ${COMPONENT} ${CFILES}
  fi

};

run_component_install()
{
  COMPONENT="$1"
  CFILES="$1"

  # Lets install this component now 
  # Start by making a wrapper script which sets the variables
  # for the component to use
  echo "#!/bin/sh
COMPTMPDIR=\"${COMPTMPDIR}\"
export COMPTMPDIR
CFILE=\"${CFILE}\"
export CFILE

sh ${COMPTMPDIR}/install.sh

" >${FSMNT}/.componentwrapper.sh
   chmod 755 ${FSMNT}/.componentwrapper.sh
   
   # Copy over the install script for this component
   cp ${COMPDIR}/${COMPONENT}/install.sh ${FSMNT}/${COMPTMPDIR}/

   echo_log "INSTALL COMPONENT: ${i}"
   chroot ${FSMNT} /.componentwrapper.sh >>${LOGOUT} 2>>${LOGOUT}
   rm ${FSMNT}/.componentwrapper.sh


};

# Check for any modules specified, and begin loading them
install_components()
{
   # First, lets check and see if we even have any optional modules
   get_value_from_cfg installComponents
   if [ ! -z "${VAL}" ]
   then
      # Lets start by cleaning up the string and getting it ready to parse
      strip_white_space ${VAL}
      COMPONENTS=`echo ${VAL} | sed -e "s|,| |g"`
      for i in $COMPONENTS
      do
        if [ ! -e "${COMPDIR}/${i}/install.sh" -o ! -e "${COMPDIR}/${i}/distfiles" ]
        then
	  echo_log "WARNING: Component ${i} doesn't seem to exist"
        else

          # Make the tmpdir on the disk
          mkdir -p ${FSMNT}/${COMPTMPDIR} >>${LOGOUT} 2>>${LOGOUT}

          # Start by grabbing the component files
          copy_component ${i}

          # Remove the tmpdir now
          rm -rf ${FSMNT}/${COMPTMPDIR} >>${LOGOUT} 2>>${LOGOUT}

        fi
      done

   fi

};
