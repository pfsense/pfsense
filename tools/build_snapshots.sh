#!/bin/sh
#
# build_snapshots.sh
#
# Copyright (c) 2007-2015 Electric Sheep Fencing, LLC
# All rights reserved
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
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
#

# Use an env var to let build.sh know we are running build_snapshots.sh
# This will avoid build.sh to run in interactive mode and wait a key
# to be pressed when something goes wrong
export NOT_INTERACTIVE=1

export BUILDER_TOOLS=$(realpath $(dirname ${0}))
export BUILDER_ROOT=$(realpath "${BUILDER_TOOLS}/..")

NO_UPLOAD=""
LOOPED_SNAPSHOTS=""
export minsleepvalue=${minsleepvalue:-"28800"}
export maxsleepvalue=${maxsleepvalue:-"86400"}

# Handle command line arguments
while test "$1" != "" ; do
	case $1 in
	--noupload|-u)
		NO_UPLOAD="-u"
		;;
	--looped|-l)
		LOOPED_SNAPSHOTS="true"
	esac
	shift
done

# Keeps track of how many time builder has looped
export BUILDCOUNTER=0
export COUNTER=0

# Global variable used to control SIGINFO action
export _sleeping=0

git_last_commit() {
	export CURRENT_COMMIT=$(git -C ${BUILDER_ROOT} log -1 --format='%H')
	export CURRENT_AUTHOR=$(git -C ${BUILDER_ROOT} log -1 --format='%an')
}

restart_build() {
	if [ ${_sleeping} -ne 0 ]; then
		${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> SIGNINFO received, restarting build"
		COUNTER=$((maxsleepvalue + 60))
	fi
}

# This routine is called in between runs. We
# will sleep for a bit and check for new commits
# in between sleeping for short durations.
snapshots_sleep_between_runs() {
	# Handle SIGINFO (ctrl+T) and restart build
	trap restart_build SIGINFO

	# Initialize variables that keep track of last commit
	[ -z "${LAST_COMMIT}" ] \
		&& export LAST_COMMIT=${CURRENT_COMMIT}

	${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Sleeping for at least $minsleepvalue, at most $maxsleepvalue in between snapshot builder runs."
	${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Last known commit: ${LAST_COMMIT}"
	${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Freezing build process at $(date)"
	echo ">>> Press ctrl+T to start a new build"
	COUNTER=0
	_sleeping=1
	while [ ${COUNTER} -lt ${minsleepvalue} ]; do
		sleep 1
		COUNTER=$((COUNTER + 1))
	done

	if [ ${COUNTER} -lt ${maxsleepvalue} ]; then
		${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Thawing build process and resuming checks for pending commits at $(date)."
		echo ">>> Press ctrl+T to start a new build"
	fi

	while [ $COUNTER -lt $maxsleepvalue ]; do
		sleep 1
		# Update this repo each 60 seconds
		if [ "$((${COUNTER} % 60))" = "0" ]; then
			git -C "${BUILDER_ROOT}" reset --hard
			git -C "${BUILDER_ROOT}" pull -q
			git_last_commit
			if [ "${LAST_COMMIT}" != "${CURRENT_COMMIT}" ]; then
				${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> New commit: $CURRENT_AUTHOR - $CURRENT_COMMIT .. No longer sleepy."
				COUNTER=$(($maxsleepvalue + 60))
				export LAST_COMMIT="${CURRENT_COMMIT}"
			fi
		fi
		COUNTER=$(($COUNTER + 1))
	done
	_sleeping=0

	if [ $COUNTER -ge $maxsleepvalue ]; then
		${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Sleep timer expired. Restarting build."
		COUNTER=0
	fi

	trap "-" SIGINFO
}

git_last_commit

# Main builder loop
while [ /bin/true ]; do
	BUILDCOUNTER=$((${BUILDCOUNTER}+1))

	${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Updating ${PRODUCT_NAME} repository."
	git -C "${BUILDER_ROOT}" reset --hard
	git -C "${BUILDER_ROOT}" pull -q

	(${BUILDER_ROOT}/build.sh --clean-builder 2>&1) | while read -r LINE; do
		${BUILDER_ROOT}/build.sh --snapshot-update-status "${LINE}"
	done

	(${BUILDER_ROOT}/build.sh ${NO_UPLOAD} --flash-size '1g 2g 4g' --snapshots 2>&1) | while read -r LINE; do
		${BUILDER_ROOT}/build.sh --snapshot-update-status "${LINE}"
	done

	if [ -z "${LOOPED_SNAPSHOTS}" ]; then
		# only one build required, exiting
		exit
	fi

	# Count some sheep or wait until a new commit turns up
	# for one days time.  We will wake up if a new commit
	# is detected during sleepy time.
	snapshots_sleep_between_runs
done
