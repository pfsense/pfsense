#!/bin/sh
# openvpn learn-address script maintaining DNS entries of connected clients in
# unbound config.

DOMAIN="${1}"
OP="${2}"
IP="${3}"
CN="${4}"

DIR="/var/unbound"
PIDFILE="/var/run/unbound.pid"

if [ -n "${IP}" -a "$(/usr/bin/basename ${IP})" = "${IP}" ]; then
	CONF="${DIR}/openvpn.client.${IP}.conf"

	case "${OP}" in

		add|update)
			TMPCONF=$(/usr/bin/mktemp "${CONF}.XXXXXX")
			TMPSRV=$(/usr/bin/mktemp "${CONF}.XXXXXX")

			if [ -f "${TMPCONF}" -a -f "${TMPSRV}" ]; then
				# Remove all configs which mention the FQDN
				/usr/bin/grep -l -null "^local-data: \"${CN}.${DOMAIN} A " ${DIR}/openvpn.client.*.conf | /usr/bin/xargs -0 /bin/rm
				/bin/test -f "${CONF}" && /bin/rm "${CONF}"

				# Add new local-data entry.
				(
					echo "local-data-ptr: \"${IP} ${CN}.${DOMAIN}\"" &&
					echo "local-data: \"${CN}.${DOMAIN} A ${IP}\"" &&
					echo "local-data: \"${CN} A ${IP}\""
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

				/bin/pkill -HUP -F "${PIDFILE}"
			fi

			/bin/test -f "${TMPCONF}" && /bin/rm "${TMPCONF}"
			/bin/test -f "${TMPSRV}" && /bin/rm "${TMPSRV}"
		;;

		delete)
			# CN is not set on delete.
			/bin/test -f "${CONF}" && /bin/rm "${CONF}" && /bin/pkill -HUP -F "${PIDFILE}"
		;;

	esac
fi

exit 0
