#!/bin/sh
#
# builder_defaults.sh
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

###########################################
# Product builder configuration file      #
# Please don't modify this file, you      #
# can put your settings and options       #
# in build.conf, which is sourced at the  #
# beginning of this file                  #
###########################################

if [ -z "${BUILDER_ROOT}" ]; then
	echo ">>> ERROR: BUILDER_ROOT must be defined by script that includes builder_defaults.sh"
	exit 1
fi

if [ ! -d "${BUILDER_ROOT}" ]; then
	echo ">>> ERROR: BUILDER_ROOT is invalid"
	exit 1
fi

export BUILDER_TOOLS=${BUILDER_TOOLS:-"${BUILDER_ROOT}/tools"}
export BUILDER_SCRIPTS=${BUILDER_SCRIPTS:-"${BUILDER_ROOT}/build/scripts"}

if [ ! -d "${BUILDER_TOOLS}" ]; then
	echo ">>> ERROR: BUILDER_TOOLS is invalid"
	exit 1
fi

BUILD_CONF=${BUILD_CONF:-"${BUILDER_ROOT}/build.conf"}

# Ensure file exists
if [ -f ${BUILD_CONF} ]; then
	. ${BUILD_CONF}
fi

# Make sure pkg will not be interactive
export ASSUME_ALWAYS_YES=true

# Architecture
export TARGET=${TARGET:-"$(uname -m)"}
export TARGET_ARCH=${TARGET_ARCH:-"$(uname -p)"}

# Directory to be used for writing temporary information
export SCRATCHDIR=${SCRATCHDIR:-"${BUILDER_ROOT}/tmp"}
if [ ! -d ${SCRATCHDIR} ]; then
	mkdir -p ${SCRATCHDIR}
fi

# Product details
export PRODUCT_NAME=${PRODUCT_NAME:-"nonSense"}
export PRODUCT_NAME_SUFFIX=${PRODUCT_NAME_SUFFIX:-"-CE"}
export REPO_BRANCH_PREFIX=${REPO_BRANCH_PREFIX:-""}
export PRODUCT_URL=${PRODUCT_URL:-""}
export PRODUCT_SRC=${PRODUCT_SRC:-"${BUILDER_ROOT}/src"}
export PRODUCT_EMAIL=${PRODUCT_EMAIL:-"coreteam@pfsense.org"}
export XML_ROOTOBJ=${XML_ROOTOBJ:-$(echo "${PRODUCT_NAME}" | tr '[[:upper:]]' '[[:lower:]]')}

if [ "${PRODUCT_NAME}" = "pfSense" -a "${BUILD_AUTHORIZED_BY_NETGATE}" != "yes" ]; then
	echo ">>>ERROR: According the following license, only Netgate can build genuine pfSense® software"
	echo ""
	cat ${BUILDER_ROOT}/LICENSE
	exit 1
fi

if [ -z "${PRODUCT_VERSION}" ]; then
	if [ ! -f ${PRODUCT_SRC}/etc/version ]; then
		echo ">>> ERROR: PRODUCT_VERSION is not defined and ${PRODUCT_SRC}/etc/version was not found"
		print_error_pfS
	fi

	export PRODUCT_VERSION=$(head -n 1 ${PRODUCT_SRC}/etc/version)
fi
export PRODUCT_REVISION=${PRODUCT_REVISION:-""}

# Product repository tag to build
_cur_git_repo_branch_or_tag=$(git -C ${BUILDER_ROOT} rev-parse --abbrev-ref HEAD)
if [ "${_cur_git_repo_branch_or_tag}" = "HEAD" ]; then
	# We are on a tag, lets find out its name
	export GIT_REPO_BRANCH_OR_TAG=$(git -C ${BUILDER_ROOT} describe --tags)
else
	export GIT_REPO_BRANCH_OR_TAG="${_cur_git_repo_branch_or_tag}"
fi
# Use vX_Y instead of RELENG_X_Y for poudriere to make it shorter
# Replace . by _ to make tag names look correct
POUDRIERE_BRANCH=$(echo "${GIT_REPO_BRANCH_OR_TAG}" | sed 's,RELENG_,v,; s,\.,_,g')

GIT_REPO_BASE=$(git -C ${BUILDER_ROOT} config --get remote.$(git -C ${BUILDER_ROOT} remote).url | sed -e 's,/[^/]*$,,')

# This is used for using svn for retrieving src
export FREEBSD_REPO_BASE=${FREEBSD_REPO_BASE:-"${GIT_REPO_BASE}/freebsd-src.git"}
export FREEBSD_BRANCH=${FREEBSD_BRANCH:-"${REPO_BRANCH_PREFIX}devel-12"}
export FREEBSD_SRC_DIR=${FREEBSD_SRC_DIR:-"${SCRATCHDIR}/FreeBSD-src"}

export BUILD_KERNELS=${BUILD_KERNELS:-"${PRODUCT_NAME}"}

# XXX: Poudriere doesn't like ssh short form
case "${FREEBSD_REPO_BASE}" in
	git@*)
		export FREEBSD_REPO_BASE_POUDRIERE="ssh://$(echo ${FREEBSD_REPO_BASE} | sed 's,:,/,')"
		;;
	*)
		export FREEBSD_REPO_BASE_POUDRIERE="${FREEBSD_REPO_BASE}"
		;;
esac

# Leave this alone.
export SRCCONF=${SRCCONF:-"${FREEBSD_SRC_DIR}/release/conf/${PRODUCT_NAME}_src.conf"}
export SRC_ENV_CONF=${SRC_CONF:-"${FREEBSD_SRC_DIR}/release/conf/${PRODUCT_NAME}_src-env.conf"}
export __MAKE_CONF=${__MAKE_CONF:-"${FREEBSD_SRC_DIR}/release/conf/${PRODUCT_NAME}_make.conf"}

# Extra tools to be added to ITOOLS
export LOCAL_ITOOLS=${LOCAL_ITOOLS:-"uuencode uudecode ex"}

# Path to kernel files being built
export KERNEL_BUILD_PATH=${KERNEL_BUILD_PATH:-"${SCRATCHDIR}/kernels"}

# Do not touch builder /usr/obj
export MAKEOBJDIRPREFIX=${MAKEOBJDIRPREFIX:-"${SCRATCHDIR}/obj"}

if [ -z "${MODULES_OVERRIDE}" ]; then
	export MODULES_OVERRIDE_base="cc/cc_cdg cc/cc_chd cc/cc_cubic cc/cc_dctcp cc/cc_hd cc/cc_htcp cc/cc_vegas cryptodev dummynet fdescfs hwpmc i2c if_stf ipdivert ipfw ipfw_nat64 opensolaris usb/cdce usb/ipheth usb/ure usb/urndis zfs"
	export MODULES_OVERRIDE_amd64="${MODULES_OVERRIDE_base} aesni amdsmn amdtemp blake2 coretemp cpuctl drm2 ipmi ix ixv ndis nmdm sfxge vmm"
	export MODULES_OVERRIDE="${MODULES_OVERRIDE_amd64}"
fi

# gnid
export GNID_REPO_BASE=${GNID_REPO_BASE:-"${GIT_REPO_BASE}/gnid.git"}
export GNID_SRC_DIR=${GNID_SRC_DIR:-"${SCRATCHDIR}/gnid"}
export GNID_BRANCH=${GNID_BRANCH:-"master"}
export GNID_INCLUDE_DIR=${GNID_INCLUDE_DIR:-"${MAKEOBJDIRPREFIX}${FREEBSD_SRC_DIR}/${TARGET}.${TARGET_ARCH}/tmp/usr/include"}
export GNID_LIBCRYPTO_DIR=${GNID_LIBCRYPTO_DIR:-"${MAKEOBJDIRPREFIX}${FREEBSD_SRC_DIR}/${TARGET}.${TARGET_ARCH}/secure/lib/libcrypto"}

# Area that the final image will appear in
export IMAGES_FINAL_DIR=${IMAGES_FINAL_DIR:-"${SCRATCHDIR}/${PRODUCT_NAME}/"}

export BUILDER_LOGS=${BUILDER_LOGS:-"${BUILDER_ROOT}/logs"}
if [ ! -d ${BUILDER_LOGS} ]; then
	mkdir -p ${BUILDER_LOGS}
fi

# This is where files will be staged
export INSTALLER_CHROOT_DIR=${INSTALLER_CHROOT_DIR:-"${SCRATCHDIR}/installer-dir"}

# This is where files will be staged
export STAGE_CHROOT_DIR=${STAGE_CHROOT_DIR:-"${SCRATCHDIR}/stage-dir"}

# Directory that will clone to in order to create
# iso staging area.
export FINAL_CHROOT_DIR=${FINAL_CHROOT_DIR:-"${SCRATCHDIR}/final-dir"}

# OVF/vmdk parms
# Name of ovf file included inside OVA archive
export OVFTEMPLATE=${OVFTEMPLATE:-"${BUILDER_TOOLS}/templates/ovf/${PRODUCT_NAME}.ovf"}
# / partition to be used by mkimg
export OVFUFS=${OVFUFS:-"${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-disk1.ufs"}
# Raw disk to be converted to vmdk
export OVFRAW=${OVFRAW:-"${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-disk1.raw"}
# On disk name of VMDK file included in OVA
export OVFVMDK=${OVFVMDK:-"${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-disk1.vmdk"}
# 8 gigabyte on disk VMDK size
export VMDK_DISK_CAPACITY_IN_GB=${VMDK_DISK_CAPACITY_IN_GB:-"8"}
# swap partition size (freebsd-swap)
export OVA_SWAP_PART_SIZE_IN_GB=${OVA_SWAP_PART_SIZE_IN_GB:-"0"}
# Temporary place to save files
export OVA_TMP=${OVA_TMP:-"${SCRATCHDIR}/ova_tmp"}
# end of OVF

# NOTE: Date string is used for creating file names of images
#       The file is used for sharing the same value with build_snapshots.sh
export DATESTRINGFILE=${DATESTRINGFILE:-"$SCRATCHDIR/version.snapshots"}
if [ -z "${DATESTRING}" ]; then
	if [ -f "${DATESTRINGFILE}" -a -n "${_USE_OLD_DATESTRING}" ]; then
		export DATESTRING=$(cat $DATESTRINGFILE)
	else
		export DATESTRING=$(date "+%Y%m%d-%H%M")
	fi
fi
echo "$DATESTRING" > $DATESTRINGFILE

# NOTE: Date string is placed on the final image etc folder to help detect new updates
#       The file is used for sharing the same value with build_snapshots.sh
export BUILTDATESTRINGFILE=${BUILTDATESTRINGFILE:-"$SCRATCHDIR/version.buildtime"}
if [ -z "${BUILTDATESTRING}" ]; then
	if [ -f "${BUILTDATESTRINGFILE}" -a -n "${_USE_OLD_DATESTRING}" ]; then
		export BUILTDATESTRING=$(cat $BUILTDATESTRINGFILE)
	else
		export BUILTDATESTRING=$(date "+%a %b %d %T %Z %Y")
	fi
fi
echo "$BUILTDATESTRING" > $BUILTDATESTRINGFILE

# Poudriere
export ZFS_TANK=${ZFS_TANK:-"zroot"}
export ZFS_ROOT=${ZFS_ROOT:-"/poudriere"}

export POUDRIERE_BULK=${POUDRIERE_BULK:-"${BUILDER_TOOLS}/conf/pfPorts/poudriere_bulk"}
if [ -z "${REPO_BRANCH_PREFIX}" ]; then
	export POUDRIERE_PORTS_GIT_URL=${POUDRIERE_PORTS_GIT_URL:-"${GIT_REPO_BASE}/freebsd-ports.git"}
else
	export POUDRIERE_PORTS_GIT_URL=${POUDRIERE_PORTS_GIT_URL:-"${GIT_REPO_BASE}/${REPO_BRANCH_PREFIX}ports.git"}
fi
export POUDRIERE_PORTS_GIT_BRANCH=${POUDRIERE_PORTS_GIT_BRANCH:-"${REPO_BRANCH_PREFIX}devel"}

# Use vX_Y instead of RELENG_X_Y for poudriere to make it shorter
POUDRIERE_PORTS_BRANCH=$(echo "${POUDRIERE_PORTS_GIT_BRANCH}" | sed 's,RELENG_,v,' | sed 's,-,_,')

export POUDRIERE_PORTS_NAME=${POUDRIERE_PORTS_NAME:-"${PRODUCT_NAME}_${POUDRIERE_PORTS_BRANCH}"}

# XXX: Poudriere doesn't like ssh short form
case "${POUDRIERE_PORTS_GIT_URL}" in
	git@*)
		POUDRIERE_PORTS_GIT_URL="ssh://$(echo ${POUDRIERE_PORTS_GIT_URL} | sed 's,:,/,')"
		;;
esac

unset _IS_RELEASE
unset _IS_RC
unset CORE_PKG_DATESTRING
export TIMESTAMP_SUFFIX="-${DATESTRING}"
# pkg doesn't like - as version separator, use . instead
export PKG_DATESTRING=$(echo "${DATESTRING}" | sed 's,-,.,g')
case "${PRODUCT_VERSION##*-}" in
	RELEASE)
		export _IS_RELEASE=yes
		unset TIMESTAMP_SUFFIX
		;;
	ALPHA|DEVELOPMENT)
		export CORE_PKG_DATESTRING=".a.${PKG_DATESTRING}"
		;;
	BETA*)
		export CORE_PKG_DATESTRING=".b.${PKG_DATESTRING}"
		;;
	RC*)
		export _IS_RC=yes
		export CORE_PKG_DATESTRING=".r.${PKG_DATESTRING}"
		;;
	*)
		echo ">>> ERROR: Invalid PRODUCT_VERSION format ${PRODUCT_VERSION}"
		exit 1
esac

export BUILDER_PKG_DEPENDENCIES="devel/git ftp/curl net/rsync sysutils/screen \
    sysutils/vmdktool security/sudo www/nginx emulators/qemu-user-static \
    archivers/gtar textproc/xmlstarlet"

STAGING_HOSTNAME=${STAGING_HOSTNAME:-"release-staging.nyi.netgate.com"}

# Host to rsync pkg repos from poudriere
export PKG_RSYNC_HOSTNAME=${PKG_RSYNC_HOSTNAME:-"nfs1.nyi.netgate.com"}
export PKG_RSYNC_USERNAME=${PKG_RSYNC_USERNAME:-"wwwsync"}
export PKG_RSYNC_SSH_PORT=${PKG_RSYNC_SSH_PORT:-"22"}
export PKG_RSYNC_DESTDIR=${PKG_RSYNC_DESTDIR:-"/storage/files/release-staging/ce/packages"}

# Final packages server
if [ -n "${_IS_RELEASE}" -o -n "${_IS_RC}" ]; then
	export PKG_FINAL_RSYNC_HOSTNAME=${PKG_FINAL_RSYNC_HOSTNAME:-"nfs1.nyi.netgate.com"}
	export PKG_FINAL_RSYNC_DESTDIR=${PKG_FINAL_RSYNC_DESTDIR:-"/storage/files/pkg"}
else
	export PKG_FINAL_RSYNC_HOSTNAME=${PKG_FINAL_RSYNC_HOSTNAME:-"nfs1.nyi.netgate.com"}
	export PKG_FINAL_RSYNC_DESTDIR=${PKG_FINAL_RSYNC_DESTDIR:-"/storage/files/beta/packages"}
fi
export PKG_FINAL_RSYNC_USERNAME=${PKG_FINAL_RSYNC_USERNAME:-"wwwsync"}
export PKG_FINAL_RSYNC_SSH_PORT=${PKG_FINAL_RSYNC_SSH_PORT:-"22"}
export SKIP_FINAL_RSYNC=${SKIP_FINAL_RSYNC:-}

# pkg repo variables
export USE_PKG_REPO_STAGING="1"
export PKG_REPO_SERVER_DEVEL=${PKG_REPO_SERVER_DEVEL:-"pkg+https://packages-beta.netgate.com/packages"}
export PKG_REPO_SERVER_RELEASE=${PKG_REPO_SERVER_RELEASE:-"pkg+https://packages.netgate.com"}
export PKG_REPO_SERVER_STAGING=${PKG_REPO_SERVER_STAGING:-"pkg+http://${STAGING_HOSTNAME}/ce/packages"}

if [ -n "${_IS_RELEASE}" -o -n "${_IS_RC}" ]; then
	export PKG_REPO_BRANCH_RELEASE=${PKG_REPO_BRANCH_RELEASE:-"${REPO_BRANCH_PREFIX}v2_5_2"}
	export PKG_REPO_BRANCH_DEVEL=${PKG_REPO_BRANCH_DEVEL:-${POUDRIERE_BRANCH}}
	export PKG_REPO_BRANCH_STAGING=${PKG_REPO_BRANCH_STAGING:-${PKG_REPO_BRANCH_RELEASE}}
else
	export PKG_REPO_BRANCH_RELEASE=${PKG_REPO_BRANCH_RELEASE:-"${REPO_BRANCH_PREFIX}v2_5_2"}
	export PKG_REPO_BRANCH_DEVEL=${PKG_REPO_BRANCH_DEVEL:-${POUDRIERE_BRANCH}}
	export PKG_REPO_BRANCH_STAGING=${PKG_REPO_BRANCH_STAGING:-${PKG_REPO_BRANCH_DEVEL}}
fi

if [ -n "${_IS_RELEASE}" -o -n "${_IS_RC}" ]; then
	export PKG_REPO_SIGN_KEY=${PKG_REPO_SIGN_KEY:-"release${PRODUCT_NAME_SUFFIX}"}
else
	export PKG_REPO_SIGN_KEY=${PKG_REPO_SIGN_KEY:-"beta${PRODUCT_NAME_SUFFIX}"}
fi
# Command used to sign pkg repo
: ${PKG_REPO_SIGNING_COMMAND="ssh -o StrictHostKeyChecking=no sign@codesigner.netgate.com sudo ./sign.sh ${PKG_REPO_SIGN_KEY}"}
export PKG_REPO_SIGNING_COMMAND
export DO_NOT_SIGN_PKG_REPO=${DO_NOT_SIGN_PKG_REPO:-}

# Define base package version, based on date for snaps
export CORE_PKG_VERSION="${PRODUCT_VERSION%%-*}${CORE_PKG_DATESTRING}${PRODUCT_REVISION:+_}${PRODUCT_REVISION}"
export CORE_PKG_PATH=${CORE_PKG_PATH:-"${SCRATCHDIR}/${PRODUCT_NAME}_${POUDRIERE_BRANCH}_${TARGET_ARCH}-core"}
export CORE_PKG_REAL_PATH="${CORE_PKG_PATH}/.real_${DATESTRING}"
export CORE_PKG_ALL_PATH="${CORE_PKG_PATH}/All"

export PKG_REPO_BASE=${PKG_REPO_BASE:-"${BUILDER_TOOLS}/templates/pkg_repos"}
export PFSENSE_DEFAULT_REPO="${PRODUCT_NAME}-repo-devel"
export PKG_REPO_DEFAULT=${PKG_REPO_DEFAULT:-"${PKG_REPO_BASE}/${PFSENSE_DEFAULT_REPO}.conf"}
export PFSENSE_BUILD_REPO="${PFSENSE_DEFAULT_REPO}"
export PKG_REPO_BUILD=${PKG_REPO_BUILD:-"${PKG_REPO_BASE}/${PFSENSE_BUILD_REPO}.conf"}
export PKG_REPO_PATH=${PKG_REPO_PATH:-"/usr/local/etc/pkg/repos/${PRODUCT_NAME}.conf"}

export PRODUCT_SHARE_DIR=${PRODUCT_SHARE_DIR:-"/usr/local/share/${PRODUCT_NAME}"}

# Package overlay. This gives people a chance to build product
# installable image that already contains certain extra packages.
#
# Needs to contain comma separated package names. Of course
# package names must be valid. Using non existent
# package name would yield an error.
#
#export custom_package_list=""

# General builder output filenames
export ISOPATH=${ISOPATH:-"${IMAGES_FINAL_DIR}/installer/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.iso"}
export MEMSTICKPATH=${MEMSTICKPATH:-"${IMAGES_FINAL_DIR}/installer/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-memstick-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.img"}
export MEMSTICKSERIALPATH=${MEMSTICKSERIALPATH:-"${IMAGES_FINAL_DIR}/installer/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-memstick-serial-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.img"}
export MEMSTICKADIPATH=${MEMSTICKADIPATH:-"${IMAGES_FINAL_DIR}/installer/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-memstick-ADI-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.img"}
export OVAPATH=${OVAPATH:-"${IMAGES_FINAL_DIR}/virtualization/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.ova"}
export MEMSTICK_VARIANTS=${MEMSTICK_VARIANTS:-}
export VARIANTIMAGES=""
export VARIANTUPDATES=""

# Rsync data to send snapshots
if [ -n "${_IS_RELEASE}" -o -n "${SKIP_FINAL_RSYNC}" ]; then
	export RSYNCIP=${RSYNCIP:-"nfs1.nyi.netgate.com"}
	export RSYNCUSER=${RSYNCUSER:-"wwwsync"}
	export RSYNCPATH=${RSYNCPATH:-"/storage/files/release-staging/ce/images"}
else
	export RSYNCIP=${RSYNCIP:-"nfs1.nyi.netgate.com"}
	export RSYNCUSER=${RSYNCUSER:-"wwwsync"}
	export RSYNCPATH=${RSYNCPATH:-"/storage/files/snapshots/${TARGET}/${PRODUCT_NAME}_${GIT_REPO_BRANCH_OR_TAG}"}
fi

export SNAPSHOTSLOGFILE=${SNAPSHOTSLOGFILE:-"${SCRATCHDIR}/snapshots-build.log"}
export SNAPSHOTSLASTUPDATE=${SNAPSHOTSLASTUPDATE:-"${SCRATCHDIR}/snapshots-lastupdate.log"}

if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
	export SNAPSHOTS_RSYNCIP=${PKG_RSYNC_HOSTNAME}
	export SNAPSHOTS_RSYNCUSER=${PKG_RSYNC_USERNAME}
else
	export SNAPSHOTS_RSYNCIP=${RSYNCIP}
	export SNAPSHOTS_RSYNCUSER=${RSYNCUSER}
fi

if [ "${PRODUCT_NAME}" = "pfSense" ]; then
	export VENDOR_NAME=${VENDOR_NAME:-"Rubicon Communications, LLC (Netgate)"}
	export OVF_INFO=${OVF_INFO:-"pfSense is a free, open source customized distribution of FreeBSD tailored for use as a firewall and router. In addition to being a powerful, flexible firewalling and routing platform, it includes a long list of related features and a package system allowing further expandability without adding bloat and potential security vulnerabilities to the base distribution. pfSense is a popular project with more than 1 million downloads since its inception, and proven in countless installations ranging from small home networks protecting a PC and an Xbox to large corporations, universities and other organizations protecting thousands of network devices."}
else
	export VENDOR_NAME=${VENDOR_NAME:-"nonSense"}
	export OVF_INFO=${OVF_INFO:-"none"}
fi
