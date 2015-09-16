#!/bin/sh

# Copyright (c) 2004-2015 Electric Sheep Fencing, LLC. All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in
#    the documentation and/or other materials provided with the
#    distribution.
#
# 3. All advertising materials mentioning features or use of this software
#    must display the following acknowledgment:
#    "This product includes software developed by the pfSense Project
#    for use in the pfSense® software distribution. (http://www.pfsense.org/).
#
# 4. The names "pfSense" and "pfSense Project" must not be used to
#    endorse or promote products derived from this software without
#    prior written permission. For written permission, please contact
#    coreteam@pfsense.org.
#
# 5. Products derived from this software may not be called "pfSense"
#    nor may "pfSense" appear in their names without prior written
#    permission of the Electric Sheep Fencing, LLC.
#
# 6. Redistributions of any form whatsoever must retain the following
#    acknowledgment:
#
# "This product includes software developed by the pfSense Project
# for use in the pfSense software distribution (http://www.pfsense.org/).
#
# THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
# EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
# PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
# ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
# SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
# NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
# STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
# OF THE POSSIBILITY OF SUCH DAMAGE.

# pkg should not ask for confirmations
export ASSUME_ALWAYS_YES=true

# Disable automatic update
export REPO_AUTOUPDATE=false

# Firmware lock subsystem
firmwarelock=/var/run/firmwarelock.dirty

# File used to detect second call, after kernel update and reboot
upgrade_in_progress="/cf/conf/upgrade_in_progress"

if [ -f "${firmwarelock}" ]; then
	_echo "ERROR: Another upgrade is running... aborting."
	exit 0
fi

stdout='/dev/null'
unset yes
while getopts dys opt; do
	case ${opt} in
		d)
			stdout=''
			;;
		y)
			yes=1
			;;
		*)
			usage
			exit 1
			;;
	esac
done

usage() {
	_echo "Usage: $(basename ${0}) [-d] [-y] [-c]"
}

_echo() {
	local _n=""
	if [ "${1}" = "-n" ]; then
		shift
		_n="-n"
	fi

	if [ -z "${logfile}" ]; then
		logfile=/dev/null
	fi

	echo ${_n} "${1}" | tee -a ${logfile}
}

_exec() {
	local _cmd="${1}"
	local _msg="${2}"
	local _mute="${3}"
	local _ignore_result="${4}"
	local _stdout="${stdout}"

	if [ -z "${_cmd}" -o -z "${_msg}" ]; then
		return 1
	fi

	if [ "${_mute}" != "mute" ]; then
		_stdout=''
	fi

	_echo -n ">>> ${_msg}... "
	if [ -z "${_stdout}" ]; then
		_echo ""
		${_cmd} 2>&1 | tee -a ${logfile}
	else
		${_cmd} >${_stdout} 2>&1 | tee -a ${logfile}
	fi
	local _result=$?

	if [ ${_result} -eq 0 -o -n "${_ignore_result}" ]; then
		[ -n "${_stdout}" ] \
			&& _echo "done."
		return 0
	else
		[ -n "${_stdout}" ] \
			&& _echo "failed."
		return 1
	fi
}

_exit() {
	if [ -n "${kernel_pkg}" ]; then
		_exec "pkg lock ${kernel_pkg}" "Locking kernel package" mute ignore_result
	fi
	if [ -f "${firmwarelock}" ]; then
		rm -f ${firmwarelock}
	fi
}

first_step() {
	_exec "pkg update" "Updating repositories" mute ignore_result

	# figure out which kernel variant is running
	kernel_pkg=$(pkg query %n $(pkg info pfSense-kernel-\*))

	if [ -z "${kernel_pkg}" ]; then
		_echo "ERROR: It was not possible to identify which pfSense kernel is installed"
		exit 1
	fi

	kernel_local=$(pkg query %v ${kernel_pkg})

	if [ -z "${kernel_local}" ]; then
		_echo "ERROR: It was not possible to determine pfSense kernel local version"
		exit 1
	fi

	kernel_remote=$(pkg rquery %v ${kernel_pkg})

	if [ -z "${kernel_remote}" ]; then
		_echo "ERROR: It was not possible to determine pfSense kernel remote version"
		exit 1
	fi

	kernel_version_compare=$(pkg version -t ${kernel_local} ${kernel_remote})

	if [ "${kernel_version_compare}" = "<" ]; then
		kernel_update=1
		# Make sure we lock kernel package again
		trap _exit 1 2 15 EXIT
		_exec "pkg unlock ${kernel_pkg}" "Unlocking kernel package" mute ignore_result
	elif [ "${kernel_version_compare}" = "=" ]; then
		kernel_update=0
	elif [ "${kernel_version_compare}" = ">" ]; then
		_echo "ERROR: You are using a newer kernel version than remote repository"
		exit 1
	else
		_echo "ERROR: Error comparing pfSense kernel local and remote versions"
		exit 1
	fi

	# XXX find a samrter way to do it
	l=$(pkg upgrade -nq | wc -l)
	if [ ${l} -eq 1 ]; then
		_echo "Your packages are up to date"
		exit 0
	fi

	if [ -z "${yes}" ]; then
		# Show user which packages are going to be upgraded
		pkg upgrade -nq 2>&1 | tee -a ${logfile}

		_echo ""
		if [ ${kernel_update} -eq 1 ]; then
			_echo "**** WARNING ****"
			_echo "Reboot will be required!!"
		fi
		_echo -n "Proceed with upgrade? (y/N) "
		read answer
		if [ "${answer}" != "y" ]; then
			_echo "Aborting..."
			exit 0
		fi
	fi

	_echo ">>> Downloading packages..."
	if ! pkg upgrade -F 2>&1 | tee -a ${logfile}; then
		_echo "ERROR: It was not possible to download packages"
		exit 1
	fi

	# Mark firmware subsystem dirty
	trap _exit 1 2 15 EXIT
	touch ${firmwarelock}

	# First upgrade kernel and reboot
	if [ ${kernel_update} -eq 1 ]; then
		_exec "pkg upgrade ${kernel_pkg}" "Upgrading pfSense kernel"
		touch ${upgrade_in_progress}
		_echo "Rebooting..."
		/etc/rc.reboot
	fi
}

second_step() {
	_echo "Upgrading necessary packages..."
	if ! pkg upgrade 2>&1 | tee -a ${logfile}; then
		_echo "ERROR: An error occurred when upgrade was running..."
		exit 1
	fi

	_exec "pkg autoremove" "Removing unnecessary packages" mute ignore_result
	_exec "pkg clean" "Cleanup pkg cache" mute ignore_result

	# cleanup caches

	rm -f ${upgrade_in_progress}
	rm -f ${firmwarelock}
}

logfile=/cf/conf/upgrade_log.txt

unset need_reboot
if [ ! -f "${upgrade_in_progress}" ]; then
	if [ -f "${logfile}" ]; then
		rm -f ${logfile}
	fi

	first_step
	need_reboot=1
fi

second_step

if [ -n "${need_reboot}" ]; then
	_echo "Rebooting..."
	/etc/rc.reboot
fi

exit 0
