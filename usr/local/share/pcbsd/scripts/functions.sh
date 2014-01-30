#!/bin/sh
# Functions we can source for pc-bsd scripts
# Author: Kris Moore
# Copyright: 2012
# License: BSD
##############################################################

PCBSD_ETCCONF="/usr/local/etc/pcbsd.conf"

get_mirror() {

  # Check if we already looked up a mirror we can keep using
  if [ -n "$CACHED_PCBSD_MIRROR" ] ; then
     VAL="$CACHED_PCBSD_MIRROR"
     export VAL
     return
  fi

  # Set the mirror URL
  VAL="`cat ${PCBSD_ETCCONF} 2>/dev/null | grep 'PCBSD_MIRROR: ' | sed 's|PCBSD_MIRROR: ||g'`"
  if [ -n "$VAL" ] ; then
     echo "Using mirror: $VAL"
     CACHED_PCBSD_MIRROR="$VAL"
     export VAL CACHED_PCBSD_MIRROR
     return
  fi

  echo "Getting regional mirror..."
  . /etc/profile

  # No URL? Lets get one from the master server
  local mFile="${HOME}/.mirrorUrl.$$"
  touch $mFile
  fetch -o $mFile http://getmirror.pcbsd.org >/dev/null 2>/dev/null
  VAL="`cat $mFile | grep 'URL: ' | sed 's|URL: ||g'`"
  rm $mFile
  if [ -n "$VAL" ] ; then
     echo "Using mirror: $VAL"
     CACHED_PCBSD_MIRROR="$VAL"
     export VAL CACHED_PCBSD_MIRROR
     return
  fi

  # Still no mirror? Lets try the PC-BSD FTP server...
  VAL="ftp://ftp.pcbsd.org/pub/mirror"
  CACHED_PCBSD_MIRROR="$VAL"
  export VAL CACHED_PCBSD_MIRROR
  echo "Using mirror: $VAL"
  return 
}

# Function which returns the installed list of PC-BSD mirrors for use
# with the fetch command
# Will return just a single mirror, if the user has manually specified one
# in /usr/local/etc/pcbsd.conf
get_mirror_loc()
{
  if [ -z $1 ] ; then
     exit_err "Need to supply file to grab from mirrors..."
  fi
  if [ -z $2 ] ; then
     exit_err "Need to supply which mirror to fetch from..."
  fi

  case $2 in
    pkg) mirrorTag="PKG_MIRROR" 
         mirrorFile="/usr/local/share/pcbsd/conf/pkg-mirror"
         ;;
    pbi) mirrorTag="PBI_MIRROR" 
         mirrorFile="/usr/local/share/pcbsd/conf/pbi-mirror"
         ;;
    iso) mirrorTag="ISO_MIRROR" 
         mirrorFile="/usr/local/share/pcbsd/conf/iso-mirror"
         ;;
  update) mirrorTag="UPDATE_MIRROR" 
         mirrorFile="/usr/local/share/pcbsd/conf/update-mirror"
         ;;
    *) exit_err "Bad mirror type!" ;;
  esac

  # Set the mirror URL
  local VAL=`cat ${PCBSD_ETCCONF} 2>/dev/null | grep "^${mirrorTag}:" | sed "s|^${mirrorTag}: ||g"`
  if [ -n "$VAL" ] ; then
     echo "${VAL}${1}"
     return
  fi

  if [ ! -e "${mirrorFile}" ] ; then
     exit_err "Missing mirror list: ${mirrorFile}"
  fi

  # Build the mirror list
  while read line
  do
    VAL="${line}${1}"
    break
  done < ${mirrorFile}
  echo ${VAL}
}

# Function to download a file from the pcbsd mirrors
# Arg1 = Remote File URL
# Arg2 = Where to save file
get_file_from_mirrors()
{
   _rf="${1}"
   _lf="${2}"
   _mtype="${3}"

   case $_mtype in
      iso|pbi|pkg|update) ;;
      *) exit_err "Fixme! Missing mirror type in get_file_from_mirrors" ;;
   esac

   # Get any proxy information
   . /etc/profile

   # Get mirror list
   local mirrorLoc="$(get_mirror_loc ${_rf} ${_mtype})"
   mirrorLoc="`echo $mirrorLoc | awk '{print $1}'`"

   # Running from a non GUI?
   if [ "$GUI_FETCH_PARSING" != "YES" -a "$PBI_FETCH_PARSING" != "YES" -a -z "$PCFETCHGUI" ] ; then
      fetch -o "${_lf}" ${mirrorLoc}
      return $?
   fi

   echo "FETCH: ${_rf}"

   # Doing a front-end download, parse the output of fetch
   _eFile="/tmp/.fetch-exit.$$"
   fetch -s ${mirrorLoc} > /tmp/.fetch-size.$$ 2>/dev/null
   _fSize=`cat /tmp/.fetch-size.$$ 2>/dev/null`
   _fSize="`expr ${_fSize} / 1024 2>/dev/null`"
   rm "/tmp/.fetch-size.$$" 2>/dev/null
   _time=1
   if [ -z "$_fSize" ] ; then _fSize=0; fi

   ( fetch -o ${_lf} ${mirrorLoc} >/dev/null 2>/dev/null ; echo "$?" > ${_eFile} ) &
   FETCH_PID=$!
   while : 
   do
      if [ -e "${_lf}" ] ; then
         sync
         _dSize=`du -k ${_lf} | tr -d '\t' | cut -d '/' -f 1`
         if [ $(is_num "$_dSize") ] ; then
            if [ ${_fSize} -lt ${_dSize} ] ; then _dSize="$_fSize" ; fi
	    _kbs=`expr ${_dSize} \/ $_time`
	    echo "SIZE: ${_fSize} DOWNLOADED: ${_dSize} SPEED: ${_kbs} KB/s"
  	 fi
      fi

      # Make sure download isn't finished
      jobs -l >/tmp/.jobProcess.$$
      cat /tmp/.jobProcess.$$ | awk '{print $3}' | grep -q ${FETCH_PID}
      if [ "$?" != "0" ] ; then rm /tmp/.jobProcess.$$ ; break ; fi
      sleep 1
      _time=`expr $_time + 1`
   done

   _err="`cat ${_eFile} 2>/dev/null`"
   if [ -z "$_err" ] ; then _err="0"; fi
   rm ${_eFile} 2>/dev/null
   if [ "$_err" = "0" ]; then echo "FETCHDONE" ; fi
   unset FETCH_PID
   return $_err

}

# Function to download a file from remote using fetch
# Arg1 = Remote File URL
# Arg2 = Where to save file
# Arg3 = Number of attempts to make before failing
get_file() {

	_rf="${1}"
	_lf="${2}"
        _ftries=${3}
	if [ -z "$_ftries" ] ; then _ftries=3; fi

        # Get any proxy information
        . /etc/profile

	if [ -e "${_lf}" ] ; then 
		echo "Resuming download of: ${_lf}"
	fi

	if [ "$GUI_FETCH_PARSING" != "YES" -a -z "$PCFETCHGUI" ] ; then
		fetch -r -o "${_lf}" "${_rf}"
		_err=$?
	else
		echo "FETCH: ${_rf}"

		# Doing a front-end download, parse the output of fetch
		_eFile="/tmp/.fetch-exit.$$"
		fetch -s "${_rf}" > /tmp/.fetch-size.$$ 2>/dev/null
		_fSize=`cat /tmp/.fetch-size.$$ 2>/dev/null`
		_fSize="`expr ${_fSize} / 1024 2>/dev/null`"
		rm "/tmp/.fetch-size.$$" 2>/dev/null
		_time=1
   		if [ -z "$_fSize" ] ; then _fSize=0; fi

		( fetch -r -o "${_lf}" "${_rf}" >/dev/null 2>/dev/null ; echo "$?" > ${_eFile} ) &
		FETCH_PID=`ps -auwwwx | grep -v grep | grep "fetch -r -o ${_lf}" | awk '{print $2}'`
		while : 
		do
			if [ -e "${_lf}" ] ; then
				_dSize=`du -k ${_lf} | tr -d '\t' | cut -d '/' -f 1`
				if [ $(is_num "$_dSize") ] ; then
					if [ ${_fSize} -lt ${_dSize} ] ; then _dSize="$_fSize" ; fi
					_kbs=`expr ${_dSize} \/ $_time`
					echo "SIZE: ${_fSize} DOWNLOADED: ${_dSize} SPEED: ${_kbs} KB/s"
				fi
			fi

			# Make sure download isn't finished
			ps -p $FETCH_PID >/dev/null 2>/dev/null
			if [ "$?" != "0" ] ; then break ; fi
			sleep 2
			_time=`expr $_time + 2`
		done

		_err="`cat ${_eFile} 2>/dev/null`"
		if [ -z "$_err" ] ; then _err="0"; fi
                rm ${_eFile} 2>/dev/null
		if [ "$_err" = "0" ]; then echo "FETCHDONE" ; fi
		unset FETCH_PID
	fi

	echo ""
	if [ $_err -ne 0 -a $_ftries -gt 0 ] ; then
		sleep 30
		_ftries=`expr $_ftries - 1`

		# Remove the local file if we failed
		if [ -e "${_lf}" ]; then rm "${_lf}"; fi

		get_file "${_rf}" "${_lf}" $_ftries	
		_err=$?
	fi
	return $_err
}

# Check if a value is a number
is_num()
{
        expr $1 + 1 2>/dev/null
        return $?
}

# Exit with a error message
exit_err() {
	if [ -n "${LOGFILE}" ] ; then
           echo "ERROR: $*" >> ${LOGFILE}
	fi
  	echo >&2 "ERROR: $*"
        exit 1
}


### Print an error on STDERR and bail out
printerror() {
  exit_err $*
}


# Check if the target directory is on ZFS
# Arg1 = The dir to check
# Arg2 = If set to 1, don't dig down to lower level directory
isDirZFS() {
  local _chkDir="$1"
  while :
  do
     # Is this dir a ZFS mount
     mount | grep -w "on $_chkDir " | grep -qw "(zfs," && return 0

     # If this directory is mounted, but NOT ZFS
     if [ "$2" != "1" ] ; then
       mount | grep -qw "on $_chkDir " && return 1
     fi
      
     # Quit if not walking down
     if [ "$2" = "1" ] ; then return 1 ; fi
  
     if [ "$_chkDir" = "/" ] ; then break ; fi
     _chkDir=`dirname $_chkDir`
  done
  
  return 1
}

# Gets the mount-point of a particular zpool / dataset
# Arg1 = zpool to check
getZFSMount() {
  local zpool="$1"
  local mnt=`mount | grep "^${zpool} on" | grep "(zfs," | awk '{print $3}'`
  if [ -n "$mnt" ] ; then
     echo "$mnt"
     return 0
  fi
  return 1
}

# Get the ZFS dataset of a particular directory
getZFSDataset() {
  local _chkDir="$1"
  while :
  do
    local zData=`mount | grep " on ${_chkDir} " | grep "(zfs," | awk '{print $1}'`
    if [ -n "$zData" ] ; then
       echo "$zData"
       return 0
    fi
    if [ "$2" != "rec" ] ; then return 1 ; fi
    if [ "$_chkDir" = "/" ] ; then return 1 ; fi
    _chkDir=`dirname $_chkDir`
  done
  return 1
}

# Get the ZFS tank name for a directory
# Arg1 = Directory to check
getZFSTank() {
  local _chkDir="$1"

  _chkdir=${_chkDir%/}
  while :
  do
     zpath=`zfs list | awk -v path="${_chkDir}" '$5 == path { print $1 }'`
     if [ -n "${zpath}" ] ; then
        echo $zpath | cut -f1 -d '/'
        return 0
     fi

     if [ "$_chkDir" = "/" ] ; then return 1 ; fi
     _chkDir=`dirname $_chkDir`
  done

  return 1
}

# Get the mountpoint for a ZFS name
# Arg1 = name
getZFSMountpoint() {
   local _chkName="${1}"
   if [ -z "${_chkName}" ]; then return 1 ; fi

   zfs list "${_chkName}" | tail -1 | awk '{ print $5 }'
}

# Get the ZFS relative path for a path
# Arg1 = Path
getZFSRelativePath() {
   local _chkDir="${1}"
   local _tank=`getZFSTank "$_chkDir"`
   local _mp=`getZFSMountpoint "${_tank}"`

   if [ -z "${_tank}" ] ; then return 1 ; fi

   local _name="${_chkDir#${_mp}}"

   # Make sure we have a '/' at the start of dataset
   if [ "`echo ${_name} | cut -c 1`" != "/" ] ; then _name="/${_name}"; fi

   echo "${_name}"
   return 0
}

# Check if an address is IPv6
isV6() {
  echo ${1} | grep -q ":"
  return $?
}
    
# Is a mount point, or any of its parent directories, a symlink?
is_symlinked_mountpoint()
{
        local _dir
        _dir=$1
        [ -L "$_dir" ] && return 0
        [ "$_dir" = "/" ] && return 1
        is_symlinked_mountpoint `dirname $_dir`
        return $?
}

# Function to ask the user to press Return to continue
rtn()
{
  echo -e "Press ENTER to continue\c";
  read garbage
};

# Function to check if an IP address passes a basic sanity test
check_ip()
{
  ip="$1"
  
  # If this is a V6 address, skip validation for now
  isV6 "${ip}"
  if [ $? -eq 0 ] ; then return ; fi

  # Check if we can cut this IP into the right segments 
  SEG="`echo $ip | cut -d '.' -f 1 2>/dev/null`"
  echo $SEG | grep -E "^[0-9]+$" >/dev/null 2>/dev/null
  if [ "$?" != "0" ]
  then
     return 1
  fi
  if [ $SEG -gt 255 -o $SEG -lt 0 ]
  then
     return 1
  fi
  
  # Second segment
  SEG="`echo $ip | cut -d '.' -f 2 2>/dev/null`"
  echo $SEG | grep -E "^[0-9]+$" >/dev/null 2>/dev/null
  if [ "$?" != "0" ]
  then
     return 1
  fi
  if [ $SEG -gt 255 -o $SEG -lt 0 ]
  then
     return 1
  fi

  # Third segment
  SEG="`echo $ip | cut -d '.' -f 3 2>/dev/null`"
  echo $SEG | grep -E "^[0-9]+$" >/dev/null 2>/dev/null
  if [ "$?" != "0" ]
  then
     return 1
  fi
  if [ $SEG -gt 255 -o $SEG -lt 0 ]
  then
     return 1
  fi
  
  # Fourth segment
  SEG="`echo $ip | cut -d '.' -f 4 2>/dev/null`"
  echo $SEG | grep -E "^[0-9]+$" >/dev/null 2>/dev/null
  if [ "$?" != "0" ]
  then
     return 1
  fi
  if [ $SEG -gt 255 -o $SEG -lt 0 ]
  then
     return 1
  fi

  return 0
};

check_pkg_conflicts()
{

  if [ -z "$EVENT_PIPE" ] ; then unset EVENT_PIPE ; fi

  # Lets test if we have any conflicts
  pkg-static ${1} | tee /tmp/.pkgConflicts.$$
  if [ $? -eq 0 ] ; then rm /tmp/.pkgConflicts.$$ ; return ; fi

 
  # Found conflicts, suprise suprise, yet another reason I hate packages
  # Lets start building a list of the old packages we can prompt to remove

  # Nice ugly sed line, sure this can be neater
  cat /tmp/.pkgConflicts.$$ | grep 'WARNING: locally installed' \
	| sed 's|.*installed ||g' | sed 's| conflicts.*||g' | sort | uniq \
	> /tmp/.pkgConflicts.$$.2

  # Check how many conflicts we found
  found=`wc -l /tmp/.pkgConflicts.$$.2 | awk '{print $1}'`
  if [ "$found" = "0" ] ; then
     rm /tmp/.pkgConflicts.$$
     rm /tmp/.pkgConflicts.$$.2
     return 0
  fi

  while read line
  do
    cList="$line $cList"
  done < /tmp/.pkgConflicts.$$.2
  rm /tmp/.pkgConflicts.$$.2 
  rm /tmp/.pkgConflicts.$$

  if [ "$GUI_FETCH_PARSING" != "YES" -a "$PBI_FETCH_PARSING" != "YES" -a -z "$PCFETCHGUI" ] ; then
        echo "The following packages will conflict with your pkg command:"
        echo "-------------------------------------"
        echo "$cList" | more
	echo "Do you wish to remove them automatically?"
	echo -e "Default yes: (y/n)\c"
        read tmp
	if [ "$tmp" != "y" -a "$tmp" != "Y" -a -n "$tmp" ] ; then return 1 ; fi
  else
	echo "PKGCONFLICTS: $cList"
	echo "PKGREPLY: /tmp/pkgans.$$"
	while : 
        do
	  if [ -e "/tmp/pkgans.$$" ] ; then
	    ans=`cat /tmp/pkgans.$$`
            if [ "$ans" = "yes" ] ; then 
	       break
            else
               return 1
            fi
          fi 
	  sleep 3
	done
  fi

  # Lets auto-resolve these bad-boys
  # Right now the logic is pretty simple, you conflict, you die
  for bPkg in $cList
  do
     # Nuked!
     echo "Removing conflicting package: $bPkg"

     # If EVENT_PIPE is set, unset it, seems to cause some weird crash in pkgng 1.2.3
     if [ -n "$EVENT_PIPE" ] ; then
        oEP="$EVENT_PIPE"
        unset EVENT_PIPE
     fi

     # Delete the package now
     pkg delete -q -y -f ${bPkg}

     # Reset EVENT_PIPE if we need to
     if [ -n "$oEP" ] ; then
        EVENT_PIPE="$oEP"; export EVENT_PIPE
        unset oEP
     fi
  done

  # Lets test if we still have any conflicts
  pkg-static ${1} 2>/dev/null >/dev/null
  if [ $? -eq 0 ] ; then return 0; fi

  # Crapola, we still have conflicts, lets warn and bail
  echo "ERROR: pkg ${1} is still reporting conflicts... Resolve these manually and try again"
  return 1
}

# Run the first boot wizard
# Should be called from a .xinitrc script, after fluxbox is already running
run_firstboot()
{
  # Is the trigger file set?
  if [ ! -e "/var/.pcbsd-firstgui" ] ; then return; fi

  # Set all our path variables
  PATH="/sbin:/bin:/usr/sbin:/usr/bin:/root/bin:/usr/local/bin:/usr/local/sbin"
  HOME="/root"
  export PATH HOME

  # Unset the PROGDIR variable
  PROGDIR=""
  export PROGDIR

  if [ -e "/root/.xprofile" ] ; then . /root/.xprofile ; fi

  # Figure out which intro video to play
  res=`xdpyinfo | grep dimensions: | awk "{print $2}"`
  h=`echo $res | cut -d "x" -f 1`
  w=`echo $res | cut -d "x" -f 2`
  h=`expr 100 \* $h`
  ratio=`expr $h \/ $w | cut -c 1-2`
  case $ratio in
    13) mov="PCBSD9_4-3_UXGA.flv";;
    16) mov="PCBSD9_16-10_WUXGA.flv";;
    17) mov="PCBSD9_16-9_1080p.flv";;
     *) mov="PCBSD9_4-3_UXGA.flv";;
  esac

  # Play the video now
  # NO Movie for 10, if we end up with one, replace this
  #mplayer -fs -nomouseinput -zoom /usr/local/share/pcbsd/movies/$mov

  # Setting a language
  if [ -e "/etc/pcbsd-lang" ] ; then
    LANG=`cat /etc/pcbsd-lang`
    export LANG
  fi

  # Start first-boot wizard
  /usr/local/bin/pc-firstboot >/var/log/pc-firstbootwiz 2>/var/log/pc-firstbootwiz
  if [ $? -eq 0 ] ; then
    rm /var/.pcbsd-firstgui
  fi
}

# Run-command, don't halt if command exits with non-0
rc_nohalt()
{
  CMD="$1"

  if [ -z "${CMD}" ] ; then
    exit_err "Error: missing argument in rc_nohalt()"
  fi

  ${CMD}
}

# Run-command, halt if command exits with non-0
rc_halt()
{
  CMD="$@"

  if [ -z "${CMD}" ] ; then
    exit_err "Error: missing argument in rc_halt()"
  fi

  ${CMD}
  STATUS=$?
  if [ ${STATUS} -ne 0 ] ; then
    exit_err "Error ${STATUS}: ${CMD}"
  fi
}

# Run-command silently, only display / halt if command exits with non-0
rc_halt_s()
{
  CMD="$@"

  if [ -z "${CMD}" ] ; then
    exit_err "Error: missing argument in rc_halt()"
  fi

  TMPRCLOG=`mktemp /tmp/.rc_halt.XXXXXX`
  ${CMD} >${TMPRCLOG} 2>${TMPRCLOG}
  STATUS=$?
  if [ ${STATUS} -ne 0 ] ; then
    cat ${TMPRCLOG}
    rm ${TMPRCLOG}
    exit_err "Error ${STATUS}: ${CMD}"
  fi
  rm ${TMPRCLOG}
}
