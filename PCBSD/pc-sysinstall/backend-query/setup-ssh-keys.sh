#!/bin/sh
# Script which sets up password-less logins for ssh host
###########################################################################

. ${PROGDIR}/backend/functions.sh

SSHUSER=$1
SSHHOST=$2
SSHPORT=$3

if [ -z "${SSHUSER}" -o -z "${SSHHOST}" -o -z "${SSHPORT}" ]
then
  echo "ERROR: Usage setup-ssh-keys <user> <host> <port>"
  exit 150
fi

cd ~

echo "Preparing to setup SSH key authorization..."
echo "When prompted, enter your password for ${SSHUSER}@${SSHHOST}"

if [ ! -e ".ssh/id_rsa.pub" ]
then
  mkdir .ssh >/dev/null 2>/dev/null
  ssh-keygen -q -t rsa -N '' -f .ssh/id_rsa
  sleep 1
fi

if [ ! -e ".ssh/id_rsa.pub" ]
then
  echo "ERROR: Failed creating .ssh/id_rsa.pub"
  exit 150
fi

# Get the .pub key
PUBKEY="`cat .ssh/id_rsa.pub`"

ssh -p ${SSHPORT} ${SSHUSER}@${SSHHOST} "mkdir .ssh ; echo $PUBKEY >> .ssh/authorized_keys; chmod 600 .ssh/authorized_keys ; echo $PUBKEY >> .ssh/authorized_keys2; chmod 600 .ssh/authorized_keys2"
