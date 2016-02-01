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

usage() {
	echo "Usage: $(basename $0) [-l] [-r] [-u] [-p]"
	echo "	-l: Build looped operations"
	echo "	-p: Update poudriere repo"
	echo "	-r: Do not reset local changes"
	echo "	-u: Do not upload snapshots"
}

export BUILDER_TOOLS=$(realpath $(dirname ${0}))
export BUILDER_ROOT=$(realpath "${BUILDER_TOOLS}/..")

NO_RESET=""
NO_UPLOAD=""
LOOPED_SNAPSHOTS=""
POUDRIERE_SNAPSHOTS=""

# Handle command line arguments
while getopts lpru opt; do
	case ${opt} in
		l)
			LOOPED_SNAPSHOTS=1
			;;
		p)
			POUDRIERE_SNAPSHOTS=--poudriere-snapshots
			;;
		r)
			NO_RESET=1
			;;
		u)
			NO_UPLOAD="-u"
			;;
		*)
			usage
			exit 1
			;;
	esac
done

if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
	export minsleepvalue=${minsleepvalue:-"360"}
else
	export minsleepvalue=${minsleepvalue:-"28800"}
fi
export maxsleepvalue=${maxsleepvalue:-"86400"}

# Keeps track of how many time builder has looped
export BUILDCOUNTER=0
export COUNTER=0

# Global variable used to control SIGINFO action
export _sleeping=0

snapshot_update_status() {
	${BUILDER_ROOT}/build.sh ${NO_UPLOAD} ${POUDRIERE_SNAPSHOTS} \
		--snapshot-update-status "$*"
}

git_last_commit() {
	[ -z "${NO_RESET}" ] \
		&& git -C "${BUILDER_ROOT}" reset --hard >/dev/null 2>&1
	git -C "${BUILDER_ROOT}" pull -q
	if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
		local _remote_repo=$(${BUILDER_ROOT}/build.sh -V POUDRIERE_PORTS_GIT_URL)
		local _remote_branch=$(${BUILDER_ROOT}/build.sh -V POUDRIERE_PORTS_GIT_BRANCH)
		export CURRENT_COMMIT=$(git ls-remote ${_remote_repo} ${_remote_branch} | cut -f1)
	else
		export CURRENT_COMMIT=$(git -C ${BUILDER_ROOT} log -1 --format='%H')
	fi
}

restart_build() {
	if [ ${_sleeping} -ne 0 ]; then
		snapshot_update_status ">>> SIGNINFO received, restarting build"
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

	snapshot_update_status ">>> Sleeping for at least $minsleepvalue," \
		"at most $maxsleepvalue in between snapshot builder runs."
	snapshot_update_status ">>> Last known commit: ${LAST_COMMIT}"
	snapshot_update_status ">>> Freezing build process at $(date)"
	echo ">>> Press ctrl+T to start a new build"
	COUNTER=0
	_sleeping=1
	while [ ${COUNTER} -lt ${minsleepvalue} ]; do
		sleep 1
		COUNTER=$((COUNTER + 1))
	done

	if [ ${COUNTER} -lt ${maxsleepvalue} ]; then
		snapshot_update_status ">>> Thawing build process and" \
			"resuming checks for pending commits at $(date)."
		echo ">>> Press ctrl+T to start a new build"
	fi

	while [ $COUNTER -lt $maxsleepvalue ]; do
		sleep 1
		COUNTER=$(($COUNTER + 1))
		# Update this repo each 60 seconds
		if [ "$((${COUNTER} % 60))" != "0" ]; then
			continue
		fi
		git_last_commit
		if [ "${LAST_COMMIT}" != "${CURRENT_COMMIT}" ]; then
			snapshot_update_status ">>> New commit:" \
				"$CURRENT_COMMIT " \
				".. No longer sleepy."
			COUNTER=$(($maxsleepvalue + 60))
			export LAST_COMMIT="${CURRENT_COMMIT}"
		fi
	done
	_sleeping=0

	if [ $COUNTER -ge $maxsleepvalue ]; then
		snapshot_update_status ">>> Sleep timer expired." \
			"Restarting build."
		COUNTER=0
	fi

	trap "-" SIGINFO
}

# Main builder loop
while [ /bin/true ]; do
	BUILDCOUNTER=$((${BUILDCOUNTER}+1))

	git_last_commit

	if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
		(${BUILDER_ROOT}/build.sh --update-poudriere-ports 2>&1) \
		    | while read -r LINE; do
			snapshot_update_status "${LINE}"
		done

		(${BUILDER_ROOT}/build.sh ${NO_UPLOAD} --update-pkg-repo 2>&1) \
		    | while read -r LINE; do
			snapshot_update_status "${LINE}"
		done
	else
		(${BUILDER_ROOT}/build.sh --clean-builder 2>&1) \
		    | while read -r LINE; do
			snapshot_update_status "${LINE}"
		done

		(${BUILDER_ROOT}/build.sh ${NO_UPLOAD} --flash-size '2g 4g' \
		    --snapshots 2>&1) | while read -r LINE; do
			snapshot_update_status "${LINE}"
		done
	fi

	if [ -z "${LOOPED_SNAPSHOTS}" ]; then
		# only one build required, exiting
		exit
	fi

	# Count some sheep or wait until a new commit turns up
	# for one days time.  We will wake up if a new commit
	# is detected during sleepy time.
	snapshots_sleep_between_runs
done
