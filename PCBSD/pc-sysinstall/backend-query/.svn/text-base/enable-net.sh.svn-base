#!/bin/sh
# Script which enables networking with specified options
###########################################################################

. ${PROGDIR}/backend/functions.sh
. ${PROGDIR}/conf/pc-sysinstall.conf
. ${BACKEND}/functions-networking.sh
. ${BACKEND}/functions-parse.sh


NIC="$1"
IP="$2"
NETMASK="$3"
DNS="$4"
GATEWAY="$5"
MIRRORFETCH="$6"

if [ -z "${NIC}" ]
then
  echo "ERROR: Usage enable-net <nic> <ip> <netmask> <dns> <gateway>"
  exit 150
fi

if [ "$NIC" = "AUTO-DHCP" ]
then
  enable_auto_dhcp
else
  echo "Enabling NIC: $NIC"
  ifconfig ${NIC} ${IP} ${NETMASK}

  echo "nameserver ${DNS}" >/etc/resolv.conf

  route add default ${GATE}
fi

case ${MIRRORFETCH} in
   ON|on|yes|YES) fetch -o /tmp/mirrors-list.txt ${MIRRORLIST} >/dev/null 2>/dev/null;;
   *) ;;
esac
