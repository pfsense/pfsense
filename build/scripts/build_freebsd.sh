#!/bin/sh
#
# build_freebsd.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
Usage: $(basename $0) -s srcdir [-o objdir] [-h]

Options:
	-s srcdir  -- Path to src directory
	-o objdir  -- Obj directory used to build
	-W         -- Skip buildworld
	-K         -- Skip buildkernel
	-h         -- Show this help and exit

Environment:
	__MAKE_CONF      -- Path to make.conf
	SRCCONF          -- Path to src.conf
	SRC_ENV_CONF     -- Path to src-env.conf
	KERNCONF         -- Kernel names
	MODULES_OVERRIDE -- List of kernel modules to build
	TARGET           -- Machine hardware name
	TARGET_ARCH      -- Machine processor arquitecture name
END
	exit 1
}

unset skip_world
unset skip_kernel
while getopts s:o:WKh opt; do
	case "$opt" in
		s)
			srcdir=$OPTARG
			;;
		o)
			objdir=$OPTARG
			;;
		W)
			skip_world=1
			;;
		K)
			skip_kernel=1
			;;
		*)
			usage
			;;
	esac
done

[ -z "$srcdir" ] \
	&& err "source directory is not defined"

[ -e $srcdir -a ! -d $srcdir ] \
	&& err "source path already exists and is not a directory"

# Default obj dir to src/../obj
: ${objdir=${srcdir}/../obj}

[ -n "$objdir" -a -e "$objdir" -a ! -d "$objdir" ] \
	&& err "obj path already exists and is not a directory"

for env_var in __MAKE_CONF SRCCONF SRC_ENV_CONF; do
	eval "value=\${$env_var}"
	[ -n "${value}" -a ! -f "${value}" ] \
		&& err "${env_var} is pointing to a nonexistent file ${value}"
done

[ ! -f ${srcdir}/sys/sys/param.h ] \
	&& err "Source directory is missing sys/sys/param.h"

ncpu=$(sysctl -qn hw.ncpu)
njobs=$((ncpu*2))
j="-j${njobs}"

[ -n "${objdir}" ] \
	&& export MAKEOBJDIRPREFIX=${objdir}

[ -z "${skip_world}" ] \
	&& run "Building world" \
		"make -C ${srcdir} -s ${j} buildworld"

if [ -z "${skip_kernel}" ]; then
	for kernel in ${KERNCONF:-pfSense}; do
		run "Building kernel (${kernel})" \
			"make -C ${srcdir} -s ${j} KERNCONF=${kernel} buildkernel"
	done
fi

exit 0
