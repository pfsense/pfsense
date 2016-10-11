#!/bin/sh
# openvpn learn-address script maintaining DNS entries of connected clients in
# unbound config.

DOMAIN="$1"
OP="$2"
IP="$3"
CN="$4"

case "$OP" in

	add|update)
		CONF="/var/unbound/openvpn.client.$IP.conf"

		# Remove all configs which mention the FQDN
		grep -l -null -F "local-data: \"$CN.$DOMAIN A $IP\"" /var/unbound/openvpn.client.*.conf | xargs -0 rm
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
			echo "include: $TMPCONF"
		) > "$TMPSRV"

		/usr/local/sbin/unbound-checkconf "$TMPSRV" && mv "$TMPCONF" "$CONF"
		rm -f "$TMPCONF" "$TMPSRV"

		/bin/pkill -HUP -F /var/run/unbound.pid
	;;

	delete)
		# CN is not set on delete.
		rm -f /var/unbound/openvpn.client.$IP.conf && /bin/pkill -HUP -F /var/run/unbound.pid
	;;

esac

exit 0
