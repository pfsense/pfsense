#!/bin/sh

export PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin

clear_disk() {
	local _disk="${1}"
	local _mirror=$(gmirror dump ${_disk} 2>/dev/null | \
	    sed '/name: /!d; s,^.*: ,,')

	if [ -n "${_mirror}" ]; then
		gmirror destroy -f ${_mirror} >/dev/null 2>&1
	fi
	gmirror clear ${_disk} >/dev/null 2>&1
}

if="*"
if ! pgrep -q dhclient; then
	boardpn=""
	arch=$(uname -p)
	if [ "${arch}" == "armv6" -a -f /usr/local/sbin/u-boot-env ]; then
		boardpn=$(/usr/local/sbin/u-boot-env boardpn)
	fi
	if [ "${_boardpn%-*}" == "80500-0148" ]; then
		if="mvneta2"
	else
		# First, find a connected interface
		if=$(ifconfig \
		    | sed -E '/^([a-z]|[[:blank:]]*status: )/!d; /^lo0:/d' \
		    | sed -e 'N; s/\n/ /' \
		    | egrep 'status: *active' \
		    | sed 's,:.*,,' \
		    | head -n 1)
	fi

	# If we couldn't, just abort
	if [ -z "${if}" ]; then
		exit 0
	fi

	# Use a custom dhclient.conf to obtain 'user-class' and detect custom
	# installation
	echo "request user-class, classless-routes, domain-name-servers;" \
	    > /tmp/dhclient.conf

	# Then, try to obtain an IP address to it running dhclient
	# if it fails, abort
	if ! dhclient -c /tmp/dhclient.conf ${if}; then
		exit 0
	fi
	if=".${if}"
fi

# Check if we are in buildroom, if not, abort
grep -q 'option classless-routes 32,1,2,3,4,127,0,0,1' \
    /var/db/dhclient.leases${if} \
	&& touch /tmp/buildroom \
	|| rm -f /tmp/buildroom

# Check for 'user-class'
custom=$(grep user-class /var/db/dhclient.leases${if} 2>/dev/null | \
    tail -n 1 | cut -d'"' -f2)

[ -n "${custom}" ] \
	&& touch /tmp/custom \
	|| rm -f /tmp/custom

if [ ! -f /tmp/buildroom ]; then
	exit 0
fi

disks=$(sysctl -n kern.disks)

d1="1"
d2="2"
if echo "${disks}" | grep -q ada0 && echo "${disks}" | grep -q ada1; then
	d1=$(diskinfo ada0 | sed "s/ada0//")
	d2=$(diskinfo ada1 | sed "s/ada1//")
fi

if [ "${d1}" != "${d2}" ]; then
	exit 0
fi

gmirror destroy -f pfSenseMirror >/dev/null 2>&1
clear_disk ada0
clear_disk ada1

gmirror label -b split pfSenseMirror ada0 ada1
exit $?
