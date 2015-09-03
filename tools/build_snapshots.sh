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
BUILDCOUNTER=0

git_last_commit() {
	export CURRENT_COMMIT=$(git -C ${BUILDER_ROOT} log -1 --format='%H')
	export CURRENT_AUTHOR=$(git -C ${BUILDER_ROOT} log -1 --format='%an')
}

# This routine is called in between runs. We
# will sleep for a bit and check for new commits
# in between sleeping for short durations.
snapshots_sleep_between_runs() {
	COUNTER=0
	while [ $COUNTER -lt $maxsleepvalue ]; do
		sleep 60
		# Update this repo
		git -C "${BUILDER_ROOT}" pull -q
		git_last_commit
		if [ "${LAST_COMMIT}" != "${CURRENT_COMMIT}" ]; then
			${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> New commit: $CURRENT_AUTHOR - $CURRENT_COMMIT .. No longer sleepy."
			COUNTER=$(($maxsleepvalue + 60))
			export LAST_COMMIT="${CURRENT_COMMIT}"
		fi
		COUNTER=$(($COUNTER + 60))
	done
	if [ $COUNTER -ge $maxsleepvalue ]; then
		${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Sleep timer expired. Restarting build."
		maxsleepvalue=0
		COUNTER=0
	fi
}

git_last_commit

# Main builder loop
while [ /bin/true ]; do
	BUILDCOUNTER=$((${BUILDCOUNTER}+1))

	${BUILDER_ROOT}/build.sh --clean-builder | while read LINE; do
		${BUILDER_ROOT}/build.sh --snapshot-update-status "${LINE}"
	done

	${BUILDER_ROOT}/build.sh ${NO_UPLOAD} --flash-size '1g 2g 4g' --snapshots | while read LINE; do
		${BUILDER_ROOT}/build.sh --snapshot-update-status "${LINE}"
	done

	if [ -z "${LOOPED_SNAPSHOTS}" ]; then
		# only one build required, exiting
		exit
	fi

	# Initialize variables that keep track of last commit
	[ -z "${LAST_COMMIT}" ] \
		&& export LAST_COMMIT=${CURRENT_COMMIT}

	${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Sleeping for at least $minsleepvalue, at most $maxsleepvalue in between snapshot builder runs.  Last known commit ${LAST_COMMIT}"
	${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Freezing build process at $(date)."
	sleep $minsleepvalue
	${BUILDER_ROOT}/build.sh --snapshot-update-status ">>> Thawing build process and resuming checks for pending commits at $(date)."

	# Count some sheep or wait until a new commit turns up
	# for one days time.  We will wake up if a new commit
	# is detected during sleepy time.
	snapshots_sleep_between_runs $maxsleepvalue
done
