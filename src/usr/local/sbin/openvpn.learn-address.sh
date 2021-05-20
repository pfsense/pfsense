#!/bin/sh
# openvpn learn-address script maintaining DNS entries of connected clients in
# unbound config.

DOMAIN="${1}"
OP="${2}"
IP="${3}"
CN="${4}"

# Trim domain off to avoid duplication if the CN is an FQDN
CN=${CN%%.${DOMAIN}}

DIR="/var/unbound"
PIDFILE="/var/run/unbound.pid"
IPV4REGEX='^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$'

if [ -n "${IP}" -a "$(/usr/bin/basename ${IP})" = "${IP}" ]; then
	if [ $(expr "${IP}" : ${IPV4REGEX}) -ne 0 ]; then
		ARECORD='A' 
		PTRRECORD=$(/bin/echo ${IP} | /usr/bin/awk -F . '{print $4"."$3"."$2"."$1".in-addr.arpa."}')
	else
		ARECORD='AAAA' 
		PTRRECORD=$(/bin/echo ${IP} | /usr/bin/awk -F: 'BEGIN {OFS=""; }{addCount = 9 - NF; for(i=1; i<=NF;i++){if(length($i) == 0){ for(j=1;j<=addCount;j++){$i = ($i "0000");} } else { $i = substr(("0000" $i), length($i)+5-4);}}; print}'| /usr/bin/rev | /usr/bin/sed -e "s/./&./g;s/.*/&ip6.arpa/")
	fi
	CONF="${DIR}/openvpn.client.${IP}.conf"

	case "${OP}" in

		add|update)
			TMPCONF=$(/usr/bin/mktemp "${CONF}.XXXXXX")
			TMPSRV=$(/usr/bin/mktemp "${CONF}.XXXXXX")

			if [ -f "${TMPCONF}" -a -f "${TMPSRV}" ]; then
				# Remove all configs which mention the FQDN
				/usr/bin/grep -l -null "^local-data: \"${CN}.${DOMAIN} ${ARECORD} " ${DIR}/openvpn.client.*.conf | /usr/bin/xargs -0 /bin/rm
				/bin/test -f "${CONF}" && /bin/rm "${CONF}"

				# Add new local-data entry.
				(
					echo "local-data-ptr: \"${IP} ${CN}.${DOMAIN}\"" &&
					echo "local-data: \"${CN}.${DOMAIN} ${ARECORD} ${IP}\"" &&
					echo "local-data: \"${CN} ${ARECORD} ${IP}\""
				) > "${TMPCONF}"

				# Check syntax, install configuration and restart unbound.
				(
					echo "server:" &&
					echo "chroot: ${DIR}" &&
					echo "directory: ${DIR}" &&
					echo "include: ${TMPCONF}"
				) > "${TMPSRV}"

				/bin/chmod 644 "${TMPCONF}" "${TMPSRV}"
				/usr/local/sbin/unbound-checkconf "${TMPSRV}" && /bin/mv "${TMPCONF}" "${CONF}"

				# do not restart unbound on connect, see https://redmine.pfsense.org/issues/11129
				/usr/bin/su -m unbound -c "/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf local_data ${CN}.${DOMAIN} ${ARECORD} ${IP}"
				/usr/bin/su -m unbound -c "/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf local_data ${CN} ${ARECORD} ${IP}"
				/usr/bin/su -m unbound -c "/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf local_data ${PTRRECORD} PTR ${CN}.${DOMAIN}"

			fi

			/bin/test -f "${TMPCONF}" && /bin/rm "${TMPCONF}"
			/bin/test -f "${TMPSRV}" && /bin/rm "${TMPSRV}"
		;;

		delete)
			# CN is not set on delete
			if [ -f "${CONF}" ]; then
				CN=`/usr/bin/sed -nr "s/(local-data-ptr\:) "\""(.*) (.*).${DOMAIN}"\""/\3/p" ${CONF}` &&
				/usr/bin/su -m unbound -c "/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf local_data_remove ${CN}.${DOMAIN}" &&
				/usr/bin/su -m unbound -c "/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf local_data_remove ${CN}" &&
				/usr/bin/su -m unbound -c "/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf local_data_remove ${PTRRECORD}"
				/bin/rm "${CONF}"
			fi
		;;

	esac
fi

exit 0
