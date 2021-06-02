#!/bin/sh

clear_disk() {
	local _disk="${1}"
	local _mirror=$(/sbin/gmirror dump ${_disk} 2>/dev/null | /usr/bin/sed '/name: /!d; s,^.*: ,,')

	if [ -n "${_mirror}" ]; then
		/sbin/gmirror destroy -f ${_mirror} >/dev/null 2>&1
	fi
	/sbin/gmirror clear ${_disk} >/dev/null 2>&1
}

mirror="${1}"
disk1="${2}"
disk2="${3}"

if [ -z "${mirror}" -o -z "${disk1}" -o -z "${disk2}" ]; then
	echo "You must specify mirror name, and disks that should be cleared"
	exit 1
fi

/sbin/gmirror destroy -f ${mirror} >/dev/null 2>&1
clear_disk ${disk1}
clear_disk ${disk2}

exit 0
