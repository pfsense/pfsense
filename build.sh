#!/bin/sh -T
#
# build.sh
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

set +e
usage() {
	echo "Usage $0 [options] [ iso | ova | memstick | memstickserial | memstickadi | all | none ]"
	echo "		all = memstick memstickserial memstickadi"
	echo "		none = upgrade only pkg repo"
	echo "	[ options ]: "
	echo "		--no-buildworld|-c - Will set NO_BUILDWORLD NO_BUILDKERNEL to not build kernel and world"
	echo "		--no-cleanobjdir|-d - Will not clean FreeBSD object built dir to allow restarting a build with NO_CLEAN"
	echo "		--resume-image-build|-r - Includes -c -d and also will just move directly to image creation using pre-staged data"
	echo "		--setup - Install required repo and ports builder require to work"
	echo "		--update-sources - Refetch FreeBSD sources"
	echo "		--rsync-repos - rsync pkg repos"
	echo "		--rsync-snapshots - rsync snapshots images and pkg repos"
	echo "		--clean-builder - clean all builder used data/resources"
	echo "		--build-kernels - build all configured kernels"
	echo "		--build-kernel argument - build specified kernel. Example --build-kernel KERNEL_NAME"
	echo "		--install-extra-kernels argument - Put extra kernel(s) under /kernel image directory. Example --install-extra-kernels KERNEL_NAME_WRAP"
	echo "		--snapshots - Build snapshots"
	echo "		--poudriere-snapshots - Update poudriere packages and send them to PKG_RSYNC_HOSTNAME"
	echo "		--setup-poudriere - Install poudriere and create necessary jails and ports tree"
	echo "		--create-unified-patch - Create a big patch with all changes done on FreeBSD"
	echo "		--update-poudriere-jails [-a ARCH_LIST] - Update poudriere jails using current patch versions"
	echo "		--update-poudriere-ports [-a ARCH_LIST] - Update poudriere ports tree"
	echo "		--update-pkg-repo [-a ARCH_LIST]- Rebuild necessary ports on poudriere and update pkg repo"
	echo "		--upload|-U - Upload pkgs and/or snapshots"
	echo "		--skip-final-rsync|-i - Skip rsync to final server"
	echo "		-V VARNAME - print value of variable VARNAME"
	exit 1
}

export BUILDER_ROOT=$(realpath $(dirname ${0}))
export BUILDER_TOOLS="${BUILDER_ROOT}/tools"

unset _SKIP_REBUILD_PRESTAGE
unset _USE_OLD_DATESTRING
unset pfPORTTOBUILD
unset IMAGETYPE
unset UPLOAD
unset SNAPSHOTS
unset POUDRIERE_SNAPSHOTS
unset ARCH_LIST
BUILDACTION="images"

# Maybe use options for nocleans etc?
while test "$1" != ""; do
	case "${1}" in
		--no-buildworld|-c)
			export NO_BUILDWORLD=YES
			export NO_BUILDKERNEL=YES
			;;
		--no-cleanobjdir|-d)
			export NO_CLEAN_FREEBSD_OBJ=YES
			;;
		--resume-image-build|-r)
			export NO_BUILDWORLD=YES
			export NO_BUILDKERNEL=YES
			export NO_CLEAN_FREEBSD_OBJ=YES
			export DO_NOT_SIGN_PKG_REPO=YES
			_SKIP_REBUILD_PRESTAGE=YES
			_USE_OLD_DATESTRING=YES
			;;
		--setup)
			BUILDACTION="builder_setup"
			;;
		--rsync-repos)
			BUILDACTION="rsync_repos"
			export DO_NOT_SIGN_PKG_REPO=YES
			;;
		--rsync-snapshots)
			BUILDACTION="rsync_snapshots"
			export DO_NOT_SIGN_PKG_REPO=YES
			;;
		--build-kernels)
			BUILDACTION="buildkernels"
			;;
		--install-extra-kernels)
			shift
			if [ $# -eq 0 ]; then
				echo "--build-kernel needs extra parameter."
				echo
				usage
			fi
			export INSTALL_EXTRA_KERNELS="${1}"
			;;
		--snapshots)
			export SNAPSHOTS=1
			;;
		--poudriere-snapshots)
			export POUDRIERE_SNAPSHOTS=1
			;;
		--build-kernel)
			BUILDACTION="buildkernel"
			shift
			if [ $# -eq 0 ]; then
				echo "--build-kernel needs extra parameter."
				echo
				usage
			fi
			export BUILD_KERNELS="${1}"
			;;
		--update-sources)
			BUILDACTION="updatesources"
			;;
		--clean-builder)
			BUILDACTION="cleanbuilder"
			;;
		--setup-poudriere)
			BUILDACTION="setup_poudriere"
			;;
		--create-unified-patch)
			BUILDACTION="create_unified_patch"
			;;
		--update-poudriere-jails)
			BUILDACTION="update_poudriere_jails"
			;;
		-a)
			shift
			if [ $# -eq 0 ]; then
				echo "-a needs extra parameter."
				echo
				usage
			fi
			export ARCH_LIST="${1}"
			;;
		--update-poudriere-ports)
			BUILDACTION="update_poudriere_ports"
			;;
		--update-pkg-repo)
			BUILDACTION="update_pkg_repo"
			;;
		--upload|-U)
			export UPLOAD=1
			;;
		--skip-final-rsync|-i)
			export SKIP_FINAL_RSYNC=1
			;;
		all|none|*iso*|*ova*|*memstick*|*memstickserial*|*memstickadi*)
			BUILDACTION="images"
			IMAGETYPE="${1}"
			;;
		-V)
			_USE_OLD_DATESTRING=YES
			shift
			[ -n "${1}" ] \
				&& var_to_print="${1}"
			;;
		--snapshot-update-status)
			shift
			snapshot_status_message="${1}"
			BUILDACTION="snapshot_status_message"
			_USE_OLD_DATESTRING=YES
			;;
		*)
			_USE_OLD_DATESTRING=YES
			usage
	esac
	shift
done

# Suck in local vars
. ${BUILDER_TOOLS}/builder_defaults.sh

# Let user define ARCH_LIST in build.conf
[ -z "${ARCH_LIST}" -a -n "${DEFAULT_ARCH_LIST}" ] \
	&& ARCH_LIST="${DEFAULT_ARCH_LIST}"

# Suck in script helper functions
. ${BUILDER_TOOLS}/builder_common.sh

# Print var required with -V and exit
if [ -n "${var_to_print}"  ]; then
	eval "echo \$${var_to_print}"
	exit 0
fi

# Update snapshot status and exit
if [ "${BUILDACTION}" = "snapshot_status_message" ]; then
	if [ -z "${POUDRIERE_SNAPSHOTS}" ]; then
		export SNAPSHOTS=1
	fi
	snapshots_update_status "${snapshot_status_message}"
	exit 0
fi

# This should be run first
launch

case $BUILDACTION in
	builder_setup)
		builder_setup
	;;
	buildkernels)
		update_freebsd_sources
		build_all_kernels
	;;
	buildkernel)
		update_freebsd_sources
		build_all_kernels
	;;
	cleanbuilder)
		clean_builder
	;;
	images)
		# It will be handled below
	;;
	updatesources)
		update_freebsd_sources
	;;
	setup_poudriere)
		poudriere_init
	;;
	create_unified_patch)
		poudriere_create_patch
	;;
	update_poudriere_jails)
		poudriere_update_jails
	;;
	update_poudriere_ports)
		poudriere_update_ports
	;;
	rsync_repos)
		export UPLOAD=1
		pkg_repo_rsync "${CORE_PKG_PATH}"
	;;
	rsync_snapshots)
		export UPLOAD=1
		snapshots_scp_files
	;;
	update_pkg_repo)
		if [ -n "${UPLOAD}" -a ! -f /usr/local/bin/rsync ]; then
			echo "ERROR: rsync is not installed, aborting..."
			exit 1
		fi
		poudriere_bulk
	;;
	*)
		usage
	;;
esac

if [ "${BUILDACTION}" != "images" ]; then
	finish
	exit 0
fi

if [ -n "${SNAPSHOTS}" -a -n "${UPLOAD}" ]; then
	_required=" \
		RSYNCIP \
		RSYNCUSER \
		RSYNCPATH \
		PKG_RSYNC_HOSTNAME \
		PKG_RSYNC_USERNAME \
		PKG_RSYNC_SSH_PORT \
		PKG_RSYNC_DESTDIR \
		PKG_REPO_SERVER_DEVEL \
		PKG_REPO_SERVER_RELEASE \
		PKG_REPO_SERVER_STAGING \
		PKG_REPO_BRANCH_DEVEL \
		PKG_REPO_BRANCH_RELEASE"

	for _var in ${_required}; do
		eval "_value=\${$_var}"
		if [ -z "${_value}" ]; then
			echo ">>> ERROR: ${_var} is not defined"
			exit 1
		fi
	done

	if [ ! -f /usr/local/bin/rsync ]; then
		echo "ERROR: rsync is not installed, aborting..."
		exit 1
	fi
fi

if [ $# -gt 1 ]; then
	echo "ERROR: Too many arguments given."
	echo
	usage
fi

if [ -n "${SNAPSHOTS}" -a -z "${IMAGETYPE}" ]; then
	IMAGETYPE="all"
fi

if [ -z "${IMAGETYPE}" ]; then
	echo "ERROR: Need to specify image type to build."
	echo
	usage
fi

if [ "$IMAGETYPE" = "none" ]; then
	_IMAGESTOBUILD=""
elif [ "$IMAGETYPE" = "all" ]; then
	_IMAGESTOBUILD="memstick memstickserial"
	if [ "${TARGET}" = "amd64" ]; then
		_IMAGESTOBUILD="${_IMAGESTOBUILD} memstickadi"
		if [ -n "${_IS_RELEASE}"  ]; then
			_IMAGESTOBUILD="${_IMAGESTOBUILD} ova"
		fi
	fi
else
	_IMAGESTOBUILD="${IMAGETYPE}"
fi

echo ">>> Building image type(s): ${_IMAGESTOBUILD}"

if [ -n "${SNAPSHOTS}" ]; then
	snapshots_update_status ">>> Starting snapshot build operations"

	if pkg update -r ${PRODUCT_NAME} >/dev/null 2>&1; then
		snapshots_update_status ">>> Updating builder packages... "
		pkg upgrade -r ${PRODUCT_NAME} -y -q >/dev/null 2>&1
	fi
fi

if [ -z "${_SKIP_REBUILD_PRESTAGE}" ]; then
	[ -n "${CORE_PKG_PATH}" -a -d "${CORE_PKG_PATH}" ] \
		&& rm -rf ${CORE_PKG_PATH}

	# Cleanup environment before start
	clean_builder

	# Make sure source directories are present.
	update_freebsd_sources
	git_last_commit

	# Ensure binaries are present that builder system requires
	depend_check

	# Build world, kernel and install
	make_world

	# Build kernels
	build_all_kernels

	# Install kernel on installer
	installkernel ${INSTALLER_CHROOT_DIR} ${PRODUCT_NAME}

	# Prepare pre-final staging area
	clone_to_staging_area

	# Install packages needed for Product
	install_pkg_install_ports

	# Create core repo
	core_pkg_create_repo
fi

# Send core repo to staging area
pkg_repo_rsync "${CORE_PKG_PATH}" ignore_final_rsync

export DEFAULT_KERNEL=${DEFAULT_KERNEL_ISO:-"${PRODUCT_NAME}"}

# XXX: Figure out why wait is failing and proper fix
# Global variable to keep track of process running in bg
export _bg_pids=""

for _IMGTOBUILD in $_IMAGESTOBUILD; do
	# Clean up items that should be cleaned each run
	staginareas_clean_each_run

	case "${_IMGTOBUILD}" in
		iso)
			if [ -n "${ISO_VARIANTS}" ]; then
				for _variant in ${ISO_VARIANTS}; do
					create_iso_image ${_variant}
				done
			else
				create_iso_image
			fi
			;;
		memstick)
			if [ -n "${MEMSTICK_VARIANTS}" ]; then
				for _variant in ${MEMSTICK_VARIANTS}; do
					create_memstick_image ${_variant}
				done
			else
				create_memstick_image
			fi
			;;
		memstickserial)
			create_memstick_serial_image
			;;
		memstickadi)
			create_memstick_adi_image
			;;
		ova)
			old_custom_package_list="${custom_package_list}"
			export custom_package_list="${custom_package_list} ${PRODUCT_NAME}-pkg-Open-VM-Tools"
			install_pkg_install_ports
			create_ova_image
			export custom_package_list="${old_custom_package_list}"
			install_pkg_install_ports
			;;
	esac
done

if [ -n "${_bg_pids}" ]; then
	if [ -n "${SNAPSHOTS}" ]; then
		snapshots_update_status ">>> NOTE: waiting for jobs: ${_bg_pids} to finish..."
	else
		echo ">>> NOTE: waiting for jobs: ${_bg_pids} to finish..."
	fi
	wait

	# XXX: For some reason wait is failing, workaround it tracking all PIDs
	while [ -n "${_bg_pids}" ]; do
		_tmp_pids="${_bg_pids}"
		unset _bg_pids
		for p in ${_tmp_pids}; do
			[ -z "${p}" ] \
				&& continue

			kill -0 ${p} >/dev/null 2>&1 \
				&& _bg_pids="${_bg_pids}${_bg_pids:+ }${p}"
		done
		[ -n "${_bg_pids}" ] \
			&& sleep 1
	done
fi

if [ -n "${SNAPSHOTS}" ]; then
	if [ "${IMAGETYPE}" = "none" -a -n "${UPLOAD}" ]; then
		pkg_repo_rsync "${CORE_PKG_PATH}"
	elif [ "${IMAGETYPE}" != "none" ]; then
		snapshots_create_sha256
		# SCP files to snapshot web hosting area
		if [ -n "${UPLOAD}" ]; then
			snapshots_scp_files
		fi
	fi
	# Alert the world that we have some snapshots ready.
	snapshots_update_status ">>> Builder run is complete."
fi

echo ">>> ${IMAGES_FINAL_DIR} now contains:"
(cd ${IMAGES_FINAL_DIR} && find ${IMAGES_FINAL_DIR} -type f)

set -e
# Run final finish routines
finish
