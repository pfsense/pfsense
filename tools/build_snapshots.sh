#!/bin/sh
#
# build_snapshots.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

usage() {
	echo "Usage: $(basename $0) [-a ARCH] [-l] [-n] [-r] [-U] [-p] [-i]"
	echo "	-a: Only build ARCH"
	echo "	-l: Build looped operations"
	echo "	-n: Do not build images, only core pkg repo"
	echo "	-p: Update poudriere repo"
	echo "	-r: Do not reset local changes"
	echo "	-U: Upload snapshots"
	echo "	-i: Skip rsync to final server"
}

export BUILDER_TOOLS=$(realpath $(dirname ${0}))
export BUILDER_ROOT=$(realpath "${BUILDER_TOOLS}/..")

IMAGES="all"
NO_RESET=""
UPLOAD=""
_SKIP_FINAL_RSYNC=""
LOOPED_SNAPSHOTS=""
POUDRIERE_SNAPSHOTS=""

# Handle command line arguments
while getopts a:lnprUi opt; do
	case ${opt} in
		a)
			ARCH=$OPTARG
			;;
		n)
			IMAGES="none"
			;;
		l)
			LOOPED_SNAPSHOTS=1
			;;
		p)
			POUDRIERE_SNAPSHOTS=--poudriere-snapshots
			;;
		r)
			NO_RESET=1
			;;
		U)
			UPLOAD="-U"
			;;
		i)
			_SKIP_FINAL_RSYNC="-i"
			;;
		*)
			usage
			exit 1
			;;
	esac
done

unset ARCH_PARAM
if [ -n "${ARCH}" ]; then
	ARCH_PARAM="-a ${ARCH}"
fi

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
	${BUILDER_ROOT}/build.sh ${_SKIP_FINAL_RSYNC} ${UPLOAD} \
		${POUDRIERE_SNAPSHOTS} --snapshot-update-status "$*"
}

exec_and_update_status() {
	local _cmd="${@}"

	[ -z "${_cmd}" ] \
		&& return 1

	# Ref. https://stackoverflow.com/a/30658405
	exec 4>&1
	local _result=$( \
	    { { ${_cmd} 2>&1 3>&-; printf $? 1>&3; } 4>&- \
	    | while read -r LINE; do \
	    snapshot_update_status "${LINE}"; done 1>&4; } 3>&1)
	exec 4>&-

	return ${_result}
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

	OIFS=${IFS}
	IFS="
"
	if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
		exec_and_update_status \
		    ${BUILDER_ROOT}/build.sh --update-poudriere-ports \
		    ${ARCH_PARAM}
		rc=$?

		if [ $rc -eq 0 ]; then
			exec_and_update_status \
			    ${BUILDER_ROOT}/build.sh ${_SKIP_FINAL_RSYNC} \
			    ${UPLOAD} --update-pkg-repo ${ARCH_PARAM}
			rc=$?
		fi
	else
		exec_and_update_status \
		    ${BUILDER_ROOT}/build.sh --clean-builder
		rc=$?

		if [ $rc -eq 0 ]; then
			exec_and_update_status \
			    ${BUILDER_ROOT}/build.sh ${_SKIP_FINAL_RSYNC} \
			    ${UPLOAD} --snapshots ${IMAGES} ${ARCH_PARAM}
			rc=$?
		fi
	fi
	IFS=${OIFS}

	if [ -z "${LOOPED_SNAPSHOTS}" ]; then
		# only one build required, exiting
		exit ${rc}
	fi

	# Count some sheep or wait until a new commit turns up
	# for one days time.  We will wake up if a new commit
	# is detected during sleepy time.
	snapshots_sleep_between_runs
done
