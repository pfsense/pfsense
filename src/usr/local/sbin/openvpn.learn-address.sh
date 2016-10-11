#!/bin/sh
# openvpn learn-address script maintaining DNS entries of connected clients in
# unbound config.

DOMAIN="$1"
OP="$2"
IP="$3"
CN="$4"

DIR="/var/unbound"
CONF="$DIR/openvpn.client.$IP.conf"
PIDFILE="/var/run/unbound.pid"

case "$OP" in

	add|update)
		# Remove all configs which mention the FQDN
		grep -l -null -F "local-data: \"$CN.$DOMAIN A $IP\"" $DIR/openvpn.client.*.conf | xargs -0 rm
		rm -f "$CONF"

		TMPCONF=$(mktemp "$CONF.XXXXXX")
		TMPSRV=$(mktemp "$CONF.XXXXXX")

		# Add new local-data entry.
		(
			echo "local-data-ptr: \"$IP $CN.$DOMAIN\"" &&
			echo "local-data: \"$CN.$DOMAIN A $IP\"" &&
			echo "local-data: \"$CN A $IP\""
		) > "$TMPCONF"

		# Check syntax, install configuration and restart unbound.
		(
			echo "server:" &&
			echo "chroot: $DIR" &&
			echo "directory: $DIR" &&
			echo "include: $TMPCONF"
		) > "$TMPSRV"

		chmod 644 "$TMPCONF" "$TMPSRV"
		/usr/local/sbin/unbound-checkconf "$TMPSRV" && mv "$TMPCONF" "$CONF"
		rm -f "$TMPCONF" "$TMPSRV"

		/bin/pkill -HUP -F "$PIDFILE"
	;;

	delete)
		# CN is not set on delete.
		rm -f "$CONF" && /bin/pkill -HUP -F "$PIDFILE"
	;;

esac

exit 0
