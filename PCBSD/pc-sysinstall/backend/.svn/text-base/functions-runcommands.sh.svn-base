#!/bin/sh
# Functions which runs commands on the system

. ${BACKEND}/functions.sh
. ${BACKEND}/functions-parse.sh

run_chroot_cmd()
{
  CMD="$@"
  echo_log "Running chroot command: ${CMD}"
  echo "$CMD" >${FSMNT}/.runcmd.sh
  chmod 755 ${FSMNT}/.runcmd.sh
  chroot ${FSMNT} sh /.runcmd.sh
  rm ${FSMNT}/.runcmd.sh
};

run_chroot_script()
{
  SCRIPT="$@"
  SBASE=`basename $SCRIPT`

  cp ${SCRIPT} ${FSMNT}/.$SBASE
  chmod 755 ${FSMNT}/.${SBASE}

  echo_log "Running chroot script: ${SCRIPT}"
  chroot ${FSMNT} /.${SBASE}

  rm ${FSMNT}/.${SBASE}
};


run_ext_cmd()
{
  CMD="$@"
  # Make sure to export FSMNT, in case cmd needs it
  export FSMNT
  echo_log "Running external command: ${CMD}"
  echo "${CMD}"> ${TMPDIR}/.runcmd.sh
  chmod 755 ${TMPDIR}/.runcmd.sh
  sh ${TMPDIR}/.runcmd.sh
  rm ${TMPDIR}/.runcmd.sh
};


# Starts the user setup
run_commands()
{
  while read line
  do
    # Check if we need to run any chroot command
    echo $line | grep ^runCommand= >/dev/null 2>/dev/null
    if [ "$?" = "0" ]
    then
      get_value_from_string "$line"
      run_chroot_cmd "$VAL"
    fi

    # Check if we need to run any chroot script
    echo $line | grep ^runScript= >/dev/null 2>/dev/null
    if [ "$?" = "0" ]
    then
      get_value_from_string "$line"
      run_chroot_script "$VAL"
    fi

    # Check if we need to run any chroot command
    echo $line | grep ^runExtCommand= >/dev/null 2>/dev/null
    if [ "$?" = "0" ]
    then
      get_value_from_string "$line"
      run_ext_cmd "$VAL"
    fi

  done <${CFGF}

};
