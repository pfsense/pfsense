#!/bin/sh
# Functions which runs commands on the system

. ${BACKEND}/functions.sh
. ${BACKEND}/functions-parse.sh


# Function which checks and sets up auto-login for a user if specified
check_autologin()
{
  get_value_from_cfg autoLoginUser
  if [ ! -z "${VAL}"  -a "${INSTALLTYPE}" = "PCBSD" ]
  then
    AUTOU="${VAL}"
    # Add the auto-login user line
    sed -i.bak "s/AutoLoginUser=/AutoLoginUser=${AUTOU}/g" ${FSMNT}/usr/local/kde4/share/config/kdm/kdmrc

    # Add the auto-login user line
    sed -i.bak "s/AutoLoginEnable=false/AutoLoginEnable=true/g" ${FSMNT}/usr/local/kde4/share/config/kdm/kdmrc

  fi
};

# Function which actually runs the adduser command on the filesystem
add_user()
{
 ARGS="${1}"

 if [ -e "${FSMNT}/.tmpPass" ]
 then
   # Add a user with a supplied password
   run_chroot_cmd "cat /.tmpPass | pw useradd ${ARGS}"
   rc_halt "rm ${FSMNT}/.tmpPass"
 else
   # Add a user with no password
   run_chroot_cmd "cat /.tmpPass | pw useradd ${ARGS}"
 fi

};

# Function which reads in the config, and adds any users specified
setup_users()
{

  # We are ready to start setting up the users, lets read the config
  while read line
  do

     echo $line | grep "^userName=" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       get_value_from_string "${line}"
       USERNAME="$VAL"
     fi

     echo $line | grep "^userComment=" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       get_value_from_string "${line}"
       USERCOMMENT="$VAL"
     fi

     echo $line | grep "^userPass=" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       get_value_from_string "${line}"
       USERPASS="$VAL"
     fi

     echo $line | grep "^userShell=" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       get_value_from_string "${line}"
       strip_white_space "$VAL"
       USERSHELL="$VAL"
     fi

     echo $line | grep "^userHome=" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       get_value_from_string "${line}"
       USERHOME="$VAL"
     fi

     echo $line | grep "^userGroups=" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       get_value_from_string "${line}"
       USERGROUPS="$VAL"
     fi


     echo $line | grep "^commitUser" >/dev/null 2>/dev/null
     if [ "$?" = "0" ]
     then
       # Found our flag to commit this user, lets check and do it
       if [ ! -z "${USERNAME}" ]
       then

         # Now add this user to the system, by building our args list
         ARGS="-n ${USERNAME}"

         if [ ! -z "${USERCOMMENT}" ]
         then
           ARGS="${ARGS} -c \"${USERCOMMENT}\""
         fi
         
         if [ ! -z "${USERPASS}" ]
         then
           ARGS="${ARGS} -h 0"
           echo "${USERPASS}" >${FSMNT}/.tmpPass
         else
           ARGS="${ARGS} -h -"
           rm ${FSMNT}/.tmpPass 2>/dev/null 2>/dev/null
         fi

         if [ ! -z "${USERSHELL}" ]
         then
           ARGS="${ARGS} -s \"${USERSHELL}\""
         else
           ARGS="${ARGS} -s \"/nonexistant\""
         fi
         
         if [ ! -z "${USERHOME}" ]
         then
           ARGS="${ARGS} -m -d \"${USERHOME}\""
         fi

         if [ ! -z "${USERGROUPS}" ]
         then
           ARGS="${ARGS} -G \"${USERGROUPS}\""
         fi

         add_user "${ARGS}"

         # Unset our vars before looking for any more users
         unset USERNAME USERCOMMENT USERPASS USERSHELL USERHOME USERGROUPS
       else
         exit_err "ERROR: commitUser was called without any userName= entry!!!" 
       fi
     fi

  done <${CFGF}


  # Check if we need to enable a user to auto-login to the desktop
  check_autologin

};
