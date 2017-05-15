#!/bin/sh
# openvpn learn-address script maintaining DNS entries of connected clients in
# unbound config.

DOMAIN="$1"
OP="$2"
IP="$3"
CN="$4"

DIR="/var/unbound"
PIDFILE="/var/run/unbound.pid"

if [ -n "$IP" -a "$(basename $IP)" = "$IP" ]; then
	CONF="${DIR}/openvpn.client.${IP}.conf"

	case "$OP" in

		add|update)
			# Remove all configs which mention the FQDN
			/usr/bin/grep -l -null "^local-data: \"${CN}.${DOMAIN} A " $DIR/openvpn.client.*.conf | /usr/bin/xargs -0 /bin/rm
			/bin/rm -f "$CONF"

			TMPCONF=$(mktemp "$CONF.XXXXXX")
			TMPSRV=$(mktemp "$CONF.XXXXXX")

			# Add new local-data entry.
			(
				echo "local-data-ptr: \"${IP} ${CN}.${DOMAIN}\"" &&
				echo "local-data: \"${CN}.${DOMAIN} A ${IP}\"" &&
				echo "local-data: \"${CN} A ${IP}\""
			) > "$TMPCONF"

			# Check syntax, install configuration and restart unbound.
			(
				echo "server:" &&
				echo "chroot: ${DIR}" &&
				echo "directory: ${DIR}" &&
				echo "include: ${TMPCONF}"
			) > "$TMPSRV"

			/bin/chmod 644 "$TMPCONF" "$TMPSRV"
			/usr/local/sbin/unbound-checkconf "$TMPSRV" && /bin/mv "$TMPCONF" "$CONF"
			/bin/rm -f "$TMPCONF" "$TMPSRV"

			/bin/pkill -HUP -F "$PIDFILE"
		;;

		delete)
			# CN is not set on delete.
			/bin/rm -f "$CONF" && /bin/pkill -HUP -F "$PIDFILE"
		;;

	esac
fi

exit 0
