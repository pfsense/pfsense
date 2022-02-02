#!/bin/sh
#
# git_checkout.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

export PATH=/usr/local/bin:/usr/local/sbin:/usr/bin:/usr/sbin:/bin:/sbin

scripts_path=$(dirname $(realpath $0))

if [ ! -f "${scripts_path}/common.subr" ]; then
	echo >&2 "ERROR: common.subr is missing"
	exit 1
fi

. ${scripts_path}/common.subr

usage() {
	cat >&2 <<END
Usage: $(basename $0) -r repo_url -d destdir [-b branch] [-h]

Options:
	-r repo_url -- URL of desired git repository
	-d destdir  -- Directory to clone
	-b branch   -- Branch or tag to clone (default: master)
	-h          -- Show this help and exit

Environment:
	GIT_BIN     -- Path to git binary
END
	exit 1
}

branch="master"
while getopts r:d:b:h opt; do
	case "$opt" in
		r)
			repo_url=$OPTARG
			;;
		d)
			destdir=$OPTARG
			;;
		b)
			branch=$OPTARG
			;;
		*)
			usage
			;;
	esac
done

[ -z "$repo_url" ] \
	&& err "repository URL is not defined"

[ -z "$destdir" ] \
	&& err "destdir is not defined"

[ -e $destdir -a ! -d $destdir ] \
	&& err "destdir already exists and is not a directory"

git=${GIT_BIN:-$(which git)}
if [ ! -x "${git}" ]; then
	err "git binary is missing"
fi

if [ -d "${destdir}/.git" ]; then
	current_url=$(${git} -C ${destdir} config --get remote.origin.url)

	[ "${current_url}" != "${repo_url}" ] \
		&& err \
		"destination directory contains a different git repository"

	run "Removing local changes from git repo ${repo_url} (${branch})" \
		"${git} -C ${destdir} reset -q --hard"
	run "Removing leftovers from git repo ${repo_url} (${branch})" \
		"${git} -C ${destdir} clean -qfd"
	run "Retrieving updates from git repo ${repo_url} (${branch})" \
		"${git} -C ${destdir} fetch -q origin"
	run "Updating git repo ${repo_url} (${branch})" \
		"git -C ${destdir} checkout -q ${branch}"

	# Detect if it's a branch and rebase it
	if ${git} -C ${destdir} show-ref -q --verify refs/heads/${branch}; then
		run "Rebasing git repo ${repo_url} (${branch})" \
			"git -C ${destdir} rebase -q origin/${branch}"
	fi
else
	run "Cloning git repository ${repo_url} (${branch})" \
		"git clone -q -b ${branch} ${repo_url} ${destdir}"
fi

exit 0
