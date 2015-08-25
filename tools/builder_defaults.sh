#!/bin/sh
#
# build.sh
#
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
#

###########################################
# Product builder configuration file      #
# Please don't modify this file, you      #
# can put your settings and options       #
# in build.conf, which is sourced at the  #
# beginning of this file                  #
###########################################

# Detect if this file is being sourced by a script from root or from tools
local _curdir=$(basename $(dirname ${0}))

if [ "${_curdir}" = "tools" ]; then
	export BUILDER_TOOLS=$(realpath ${_curdir})
	export BUILDER_ROOT=$(realpath "${_curdir}/..")
else
	export BUILDER_TOOLS=$(realpath "${_curdir}/tools")
	export BUILDER_ROOT=$(realpath "${_curdir}")
fi

BUILD_CONF="${BUILDER_ROOT}/build.conf"

# Ensure file exists
if [ -f ${BUILD_CONF} ]; then
	. ${BUILD_CONF}
fi

# Make sure pkg will not be interactive
export ASSUME_ALWAYS_YES=true

OIFS=$IFS
IFS=%

# Architecture, supported ARCH values are: 
#  Tier 1: i386, AMD64, and PC98
#  Tier 2: ARM, PowerPC, ia64, Sparc64 and sun4v
#  Tier 3: MIPS and S/390
#  Tier 4: None at the moment
#  Source: http://www.freebsd.org/doc/en/articles/committers-guide/archs.html
export TARGET=${TARGET:-"`uname -m`"}
export TARGET_ARCH=${TARGET_ARCH:-${TARGET}}
# Set TARGET_ARCH_CONF_DIR
if [ "$TARGET_ARCH" = "" ]; then
        export TARGET_ARCH=`uname -p`
fi
export BUILDER_HOST_TARGET=`uname -m`

# This is used for using svn for retrieving src
export FREEBSD_REPO_BASE=${FREEBSD_REPO_BASE:-"git@git.pfmechanics.com:pfsense/freebsd-src.git"}
export FREEBSD_BRANCH=${FREEBSD_BRANCH:-"devel"}
export FREEBSD_PARENT_BRANCH=${FREEBSD_PARENT_BRANCH:-"stable/10"}

# Product details
export PRODUCT_NAME=${PRODUCT_NAME:-pfSense}
export PRODUCT_URL=${PRODUCT_VERSION:-"https://www.pfsense.org/"}
export PRODUCT_SRC=${PRODUCT_SRC:-"${BUILDER_ROOT}/src"}

if [ -z "${PRODUCT_VERSION}" ]; then
	if [ ! -f ${PRODUCT_SRC}/etc/version ]; then
		echo ">>> ERROR: PRODUCT_VERSION is not defined and ${PRODUCT_SRC}/etc/version was not found"
		print_error_pfS
	fi

	export PRODUCT_VERSION=$(head -n 1 ${PRODUCT_SRC}/etc/version)
fi

# Product repository tag to build
local _cur_git_repo_branch_or_tag=$(git -C ${BUILDER_ROOT} rev-parse --abbrev-ref HEAD)
if [ "${_cur_git_repo_branch_or_tag}" = "HEAD" ]; then
	# We are on a tag, lets find out its name
	export GIT_REPO_BRANCH_OR_TAG=$(git -C ${BUILDER_ROOT} describe --tags)
else
	export GIT_REPO_BRANCH_OR_TAG="${_cur_git_repo_branch_or_tag}"
fi

# Directory to be used for writing temporary information
export SCRATCHDIR=${SCRATCHDIR:-"${BUILDER_ROOT}/tmp"}
if [ ! -d ${SCRATCHDIR} ]; then
	mkdir -p ${SCRATCHDIR}
fi

# Area that the final image will appear in
export IMAGES_FINAL_DIR=${IMAGES_FINAL_DIR:-${SCRATCHDIR}/${PRODUCT_NAME}/}

export BUILDER_LOGS=${BUILDER_LOGS:-${BUILDER_ROOT}/logs}
if [ ! -d ${BUILDER_LOGS} ]; then
	mkdir -p ${BUILDER_LOGS}
fi

# Poudriere
export ZFS_TANK=${ZFS_TANK:-"tank"}
export ZFS_ROOT=${ZFS_ROOT:-"/poudriere"}
export POUDRIERE_PORTS_NAME=${POUDRIERE_PORTS_NAME:-${PRODUCT_NAME}_${GIT_REPO_BRANCH_OR_TAG}}

export POUDRIERE_BULK=${POUDRIERE_BULK:-${BUILDER_TOOLS}/conf/pfPorts/poudriere_bulk}
export POUDRIERE_PORTS_GIT_URL=${POUDRIERE_PORTS_GIT_URL:-"git@git.pfmechanics.com:pfsense/freebsd-ports.git"}
export POUDRIERE_PORTS_GIT_BRANCH=${POUDRIERE_PORTS_GIT_BRANCH:-"devel"}

# This is where files will be staged
export STAGE_CHROOT_DIR=${STAGE_CHROOT_DIR:-/usr/local/stage-dir}

export SRCDIR=${SRCDIR:-/usr/${PRODUCT_NAME}src/src.${GIT_REPO_BRANCH_OR_TAG}}

# 400M is not enough for amd64
export MEMORYDISK_SIZE=${MEMORYDISK_SIZE:-"768M"}

# OVF/vmdk parms
export OVFPATH=${OVFPATH:-${IMAGES_FINAL_DIR}}
# Name of ovf file included inside OVA archive
export OVFFILE=${OVFFILE:-${PRODUCT_NAME}.ovf}
# On disk name of VMDK file included in OVA
export OVFVMDK=${OVFVMDK:-${PRODUCT_NAME}.vmdk}
# optional
export OVFCERT=${OVFCERT:-""}
# 10 gigabyte on disk VMDK size
export OVADISKSIZE=${OVADISKSIZE:-"10737418240"}
# dd buffering size when creating raw backed VMDK
export OVABLOCKSIZE=${OVABLOCKSIZE:-"409600"}
# first partition size (freebsd-ufs) GPT
export OVA_FIRST_PART_SIZE=${OVA_FIRST_PART_SIZE:-"8G"}
# swap partition size (freebsd-swap) GPT -
# remaining space of 10G-8G - 128 block beginning/loader
export OVA_SWAP_PART_SIZE=${OVA_SWAP_PART_SIZE:-"4193725"}
# 10737254400 = 10240MB = virtual box vmdk file size XXX grab this value from vbox creation
export OVA_DISKSECTIONALLOCATIONUNITS=${OVA_DISKSECTIONALLOCATIONUNITS:-"10737254400"}
# end of OVF

# Leave this alone.
export SRC_CONF=${SRC_CONF:-"${SRCDIR}/release/conf/${PRODUCT_NAME}_src.conf"}
export MAKE_CONF=${MAKE_CONF:-"${SRCDIR}/release/conf/${PRODUCT_NAME}_make.conf"}

# Extra tools to be added to ITOOLS
export EXTRA_TOOLS=${EXTRA_TOOLS:-"uuencode uudecode ex"}

# Path to kernel files being built
export KERNEL_BUILD_PATH=${KERNEL_BUILD_PATH:-"${SCRATCHDIR}/kernels"}

# Controls how many concurrent make processes are run for each stage
if [ "${NO_MAKEJ}" = "" ]; then
	CPUS=`sysctl -n kern.smp.cpus`
	CPUS=`expr $CPUS '*' 2`
	export MAKEJ_WORLD=${MAKEJ_WORLD:-"-j$CPUS"}
	export MAKEJ_KERNEL=${MAKEJ_KERNEL:-"-j$CPUS"}
else
	export MAKEJ_WORLD=${MAKEJ_WORLD:-""}
	export MAKEJ_KERNEL=${MAKEJ_KERNEL:-""}
fi
if [ "${TARGET}" = "i386" ]; then
	export MODULES_OVERRIDE=${MODULES_OVERRIDE:-"i2c ipmi ndis ipfw ipdivert dummynet fdescfs opensolaris zfs glxsb if_stf coretemp amdtemp hwpmc"}
else
	export MODULES_OVERRIDE=${MODULES_OVERRIDE:-"i2c ipmi ndis ipfw ipdivert dummynet fdescfs opensolaris zfs glxsb if_stf coretemp amdtemp aesni sfxge hwpmc"}
fi

# Number of code images on media (1 or 2)
export NANO_IMAGES=2
# 0 -> Leave second image all zeroes so it compresses better.
# 1 -> Initialize second image with a copy of the first
export NANO_INIT_IMG2=1
export NANO_WITH_VGA=${NANO_WITH_VGA:-""}
export NANO_NEWFS="-b 4096 -f 512 -i 8192 -O1"
export FLASH_SIZE=${FLASH_SIZE:-"1g"}
# Size of code file system in 512 bytes sectors
# If zero, size will be as large as possible.
export NANO_CODESIZE=0
# Size of data file system in 512 bytes sectors
# If zero: no partition configured.
# If negative: max size possible
export NANO_DATASIZE=0
# Size of Product /conf partition  # 102400 = 50 megabytes.
export NANO_CONFSIZE=102400
# packet is OK for 90% of embedded
export NANO_BOOT0CFG="-o packet -s 1 -m 3"

# " - UNBREAK TEXTMATE FORMATTING - PLEASE LEAVE.

# Host to rsync pkg repos from poudriere
export PKG_RSYNC_HOSTNAME=${PKG_RSYNC_HOSTNAME:-"beta.pfsense.org"}
export PKG_RSYNC_USERNAME=${PKG_RSYNC_USERNAME:-"wwwsync"}
export PKG_RSYNC_SSH_PORT=${PKG_RSYNC_SSH_PORT:-"22"}
export PKG_RSYNC_DESTDIR=${PKG_RSYNC_DESTDIR:-"/usr/local/www/beta/packages"}
export PKG_REPO_SERVER=${PKG_REPO_SERVER:-"pkg+http://beta.pfsense.org/packages"}
export PKG_REPO_CONF_BRANCH=${PKG_REPO_CONF_BRANCH:-"${GIT_REPO_BRANCH_OR_TAG}"}

# Package overlay. This gives people a chance to build product
# installable image that already contains certain extra packages.
#
# Needs to contain comma separated package names. Of course
# package names must be valid. Using non existent
# package name would yield an error.
#
#export custom_package_list=""

# Directory that will clone to in order to create
# iso staging area.
export FINAL_CHROOT_DIR=${FINAL_CHROOT_DIR:-/usr/local/final-dir}

# NOTE: Date string is used for creating file names of images
#       The file is used for sharing the same value with build_snapshots.sh
local _BUILDER_EPOCH=$(date +"%s")
export DATESTRINGFILE=${DATESTRINGFILE:-$SCRATCHDIR/version.snapshots}
if [ "${DATESTRING}" = "" ]; then
	if [ -f $DATESTRINGFILE ]; then
		# If the file is more than 30 minutes old regenerate it
		TMPDATESTRINGFILE=$(($_BUILDER_EPOCH - `stat -f %m $DATESTRINGFILE`))
		if [ -z "${_USE_OLD_DATESTRING}" -a $TMPDATESTRINGFILE -gt 1800 ]; then
			export DATESTRING=`date "+%Y%m%d-%H%M"`
		else
			export DATESTRING=`cat $DATESTRINGFILE`
		fi
		unset TMPDATESTRINGFILE
	else
		export DATESTRING=`date "+%Y%m%d-%H%M"`
	fi
	echo "$DATESTRING" > $DATESTRINGFILE
fi

# NOTE: Date string is placed on the final image etc folder to help detect new updates
#       The file is used for sharing the same value with build_snapshots.sh
export BUILTDATESTRINGFILE=${BUILTDATESTRINGFILE:-$SCRATCHDIR/version.buildtime}
if [ "${BUILTDATESTRING}" = "" ]; then
	if [ -f $BUILTDATESTRINGFILE ]; then
		# If the file is more than 30 minutes old regenerate it
		TMPBUILTDATESTRINGFILE=$(($_BUILDER_EPOCH - `stat -f %m $BUILTDATESTRINGFILE`))
		if [ $TMPBUILTDATESTRINGFILE -gt 1800 ]; then
			export BUILTDATESTRING=`date "+%a %b %d %T %Z %Y"`
		else
			export BUILTDATESTRING=`cat $BUILTDATESTRINGFILE`
		fi
		unset TMPBUILTDATESTRINGFILE
	else
		export BUILTDATESTRING=`date "+%a %b %d %T %Z %Y"`
	fi
	echo "$BUILTDATESTRING" > $BUILTDATESTRINGFILE
fi

# Define base package version, based on date for snaps
CORE_PKG_VERSION=${PRODUCT_VERSION%%-*}
if echo "${PRODUCT_VERSION}" | grep -qv -- '-RELEASE'; then
	CORE_PKG_VERSION="${CORE_PKG_VERSION}.${DATESTRING}"
fi
export CORE_PKG_PATH=${CORE_PKG_PATH:-"${SCRATCHDIR}/core_pkg"}
export CORE_PKG_TMP=${CORE_PKG_TMP:-"${SCRATCHDIR}/core_pkg_tmp"}

# General builder output filenames
export UPDATESDIR=${UPDATESDIR:-${IMAGES_FINAL_DIR}/updates}
export ISOPATH=${ISOPATH:-${IMAGES_FINAL_DIR}/${PRODUCT_NAME}-LiveCD-${PRODUCT_VERSION}-${TARGET}-${DATESTRING}.iso}
export MEMSTICKPATH=${MEMSTICKPATH:-${IMAGES_FINAL_DIR}/${PRODUCT_NAME}-memstick-${PRODUCT_VERSION}-${TARGET}-${DATESTRING}.img}
export MEMSTICKSERIALPATH=${MEMSTICKSERIALPATH:-${IMAGES_FINAL_DIR}/${PRODUCT_NAME}-memstick-serial-${PRODUCT_VERSION}-${TARGET}-${DATESTRING}.img}
export MEMSTICKADIPATH=${MEMSTICKADIPATH:-${IMAGES_FINAL_DIR}/${PRODUCT_NAME}-memstick-ADI-${PRODUCT_VERSION}-${TARGET}-${DATESTRING}.img}

# set full-update update filename
export UPDATES_TARBALL_FILENAME=${UPDATES_TARBALL_FILENAME:-"${UPDATESDIR}/${PRODUCT_NAME}-Full-Update-${PRODUCT_VERSION}-${TARGET}-${DATESTRING}.tgz"}

# " - UNBREAK TEXTMATE FORMATTING - PLEASE LEAVE.

if [ "${TARGET}" = "i386" ]; then
	export BUILD_KERNELS=${BUILD_KERNELS:-"${PRODUCT_NAME} ${PRODUCT_NAME}_WRAP ${PRODUCT_NAME}_WRAP_VGA"}
else
	export BUILD_KERNELS=${BUILD_KERNELS:-"${PRODUCT_NAME}"}
fi

# This needs to be at the very end of the file.
IFS=$OIFS
