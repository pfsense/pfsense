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

if [ $# -lt 1 ]; then
	cat <<END_OF_USAGE 1>&2
Usage  : $0 BRANCH [FREEBSD_REPO_BASE]
Example: $0 master git@git.pfmechanics.com:pfsense/freebsd-src.git

BRANCH is required.
FREEBSD_REPO_BASE is required or the default of git@git.pfmechanics.com:pfsense/freebsd-src.git
will be used.

END_OF_USAGE
	exit 127
fi

export BUILDER_SCRIPTS=$(realpath $(dirname ${0}))

# Ensure file exists
rm -f ${BUILDER_SCRIPTS}/build.conf
touch ${BUILDER_SCRIPTS}/build.conf

# Source build.conf variables
. ${BUILDER_SCRIPTS}/builder_defaults.sh

# Default FREEBSD_REPO_BASE
if [ "$2" != "" ]; then
	FREEBSD_REPO_BASE="$2"
else
	echo "WARNING: Setting FREEBSD repository to host git@git.pfmechanics.com:pfsense/freebsd-src.git"
	echo
	FREEBSD_REPO_BASE="git@git.pfmechanics.com:pfsense/freebsd-src.git"
	sleep 2
fi

strip_build_conf() {
	# Strip dynamic values
	cat $BUILDER_SCRIPTS/build.conf | \
		grep -v FREEBSD_BRANCH | \
		grep -v FREEBSD_PARENT_BRANCH | \
		grep -v GIT_REPO_BRANCH_OR_TAG | \
		grep -v "set_version.sh" | \
		grep -v PRODUCT_VERSION > /tmp/build.conf
	mv /tmp/build.conf $BUILDER_SCRIPTS/build.conf
}

set_items() {
	strip_build_conf
	# Add our custom dynamic values
	echo "# set_version.sh generated defaults" >> $BUILDER_SCRIPTS/build.conf
	echo export PRODUCT_VERSION="${PRODUCT_VERSION}" >> $BUILDER_SCRIPTS/build.conf
	echo export GIT_REPO_BRANCH_OR_TAG="${GIT_REPO_BRANCH_OR_TAG}" >> $BUILDER_SCRIPTS/build.conf
	if [ -n "${FREEBSD_REPO_BASE}" ]; then
		echo "export FREEBSD_REPO_BASE=${FREEBSD_REPO_BASE}" >> $BUILDER_SCRIPTS/build.conf
	fi
	echo export FREEBSD_BRANCH="${FREEBSD_BRANCH}" >> $BUILDER_SCRIPTS/build.conf
	echo export FREEBSD_PARENT_BRANCH="${FREEBSD_PARENT_BRANCH}" >> $BUILDER_SCRIPTS/build.conf
	if [ -n "$GIT_FREEBSD_COSHA1}" ]; then
		echo "export GIT_FREEBSD_COSHA1=${GIT_FREEBSD_COSHA1}" >> $BUILDER_SCRIPTS/build.conf
	fi

	# To speedup builds and reduce internet traffic
	# Also recommended for snapshot builders
	echo "#export NO_CLEANFREEBSDOBJDIR=YES " >> $BUILDER_SCRIPTS/build.conf
	echo "#export NO_CLEANREPOS=YES " >> $BUILDER_SCRIPTS/build.conf

	# Output build.conf
	echo
	echo ">>> Custom build.conf contains:"
	echo "---------------------------------------------------------------------------------------"
	cat ${BUILDER_SCRIPTS}/build.conf
	echo "---------------------------------------------------------------------------------------"
	echo
	echo " NOTE: build.conf values updated.  These values override builder_defaults.sh !!"
	echo
}

echo

case $1 in
	HEAD|master)
		export PRODUCT_VERSION=2.3-DEVELOPMENT
		export GIT_REPO_BRANCH_OR_TAG=master
		export FREEBSD_BRANCH=devel
		export FREEBSD_PARENT_BRANCH=stable/10
		#export GIT_FREEBSD_COSHA1=30e366f556dde8950782845d6a3bdbc2c5a84b6f
		set_items
		;;
	*)
		echo "Invalid version."
		exit 1
esac

echo ">>> Setting builder environment to use ${GIT_REPO_BRANCH_OR_TAG} + ${FREEBSD_BRANCH} ..."

(cd ${BUILDER_SCRIPTS} && ./build.sh --clean-builder)

echo ">>> Please run './build.sh --setup' to get necessary packages installed"
