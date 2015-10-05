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

set +e
usage() {
	echo "Usage $0 [options] [ iso | nanobsd | ova | nanobsd-vga | memstick | memstickserial | memstickadi | fullupdate | all ]"
	echo "		all = iso nanobsd nanobsd-vga memstick memstickserial memstickadi fullupdate"
	echo "	[ options ]: "
	echo "		--flash-size|-f size(s) - a list of flash sizes to build with nanobsd i.e. '512m 1g'. Default: 512m"
	echo "		--no-buildworld|-c - Will set NO_BUILDWORLD NO_BUILDKERNEL to not build kernel and world"
	echo "		--no-cleanobjdir|--no-cleanrepos|-d - Will not clean FreeBSD object built dir to allow restarting a build with NO_CLEAN"
	echo "		--resume-image-build|-r - Includes -c -d and also will just move directly to image creation using pre-staged data"
	echo "		--setup - Install required repo and ports builder require to work"
	echo "		--update-sources - Refetch FreeBSD sources"
	echo "		--print-flags - Show current builder configuration"
	echo "		--clean-builder - clean all builder used data/resources"
	echo "		--build-kernels - build all configured kernels"
	echo "		--build-kernel argument - build specified kernel. Example --build-kernel KERNEL_NAME"
	echo "		--install-extra-kernels argument - Put extra kernel(s) under /kernel image directory. Example --install-extra-kernels KERNEL_NAME_WRAP"
	echo "		--snapshots - Build snapshots and upload them to RSYNCIP"
	echo "		--enable-memorydisks - This will put stage_dir and iso_dir as MFS filesystems"
	echo "		--disable-memorydisks - Will just teardown these filesystems created by --enable-memorydisks"
	echo "		--setup-poudriere - Install poudriere and create necessary jails and ports tree"
	echo "		--create-unified-patch - Create a big patch with all changes done on FreeBSD"
	echo "		--update-poudriere-jails [-a ARCH_LIST] - Update poudriere jails using current patch versions"
	echo "		--update-poudriere-ports [-a ARCH_LIST]- Update poudriere ports tree"
	echo "		--update-pkg-repo [-a ARCH_LIST]- Rebuild necessary ports on poudriere and update pkg repo"
	echo "		--do-not-upload|-u - Do not upload pkgs or snapshots"
	echo "		-V VARNAME - print value of variable VARNAME"
	exit 1
}

export BUILDER_ROOT=$(realpath $(dirname ${0}))
export BUILDER_TOOLS="${BUILDER_ROOT}/tools"

unset _SKIP_REBUILD_PRESTAGE
unset _USE_OLD_DATESTRING
unset pfPORTTOBUILD
unset IMAGETYPE
unset DO_NOT_UPLOAD
unset SNAPSHOTS
unset ARCH_LIST
BUILDACTION="images"

# Maybe use options for nocleans etc?
while test "$1" != ""; do
	case "${1}" in
		--no-buildworld|-c)
			export NO_BUILDWORLD=YES
			export NO_BUILDKERNEL=YES
			;;
		--no-cleanobjdir|--no-cleanrepos|-d)
			export NO_CLEAN_FREEBSD_OBJ=YES
			export NO_CLEAN_FREEBSD_SRC=YES
			;;
		--flash-size|-f)
			shift
			if [ $# -eq 0 ]; then
				echo "--flash-size needs extra parameter."
				echo
				usage
			fi
			export FLASH_SIZE="${1}"
			;;
		--resume-image-build|-r)
			export NO_BUILDWORLD=YES
			export NO_BUILDKERNEL=YES
			export NO_CLEAN_FREEBSD_OBJ=YES
			export NO_CLEAN_FREEBSD_SRC=YES
			_SKIP_REBUILD_PRESTAGE=YES
			_USE_OLD_DATESTRING=YES
			;;
		--setup)
			BUILDACTION="builder_setup"
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
			IMAGETYPE="all"
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
		--print-flags)
			BUILDACTION="printflags"
			;;
		--clean-builder)
			BUILDACTION="cleanbuilder"
			;;
		--enable-memorydisks)
			BUILDACTION="enablememorydisk"
			;;
		--disable-memorydisks)
			BUILDACTION="disablememorydisk"
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
		--do-not-upload|-u)
			export DO_NOT_UPLOAD=1
			;;
		all|*iso*|*ova*|*memstick*|*memstickserial*|*memstickadi*|*nanobsd*|*nanobsd-vga*|*fullupdate*)
			BUILDACTION="images"
			IMAGETYPE="${1}"
			;;
		-V)
			shift
			[ -n "${1}" ] \
				&& var_to_print="${1}"
			;;
		--snapshot-update-status)
			shift
			snapshot_status_message="${1}"
			BUILDACTION="snapshot_status_message"
			;;
		*)
			usage
	esac
	shift
done

# Suck in local vars
. ${BUILDER_TOOLS}/builder_defaults.sh

# Suck in script helper functions
. ${BUILDER_TOOLS}/builder_common.sh

# Print var required with -V and exit
if [ -n "${var_to_print}"  ]; then
	eval "echo \$${var_to_print}"
	exit 0
fi

# Update snapshot status and exit
if [ "${BUILDACTION}" = "snapshot_status_message" ]; then
	export SNAPSHOTS=1
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
	printflags)
		print_flags
	;;
	images|snapshots)
		# It will be handled below
	;;
	updatesources)
		update_freebsd_sources
	;;
	enablememorydisk)
		prestage_on_ram_setup
	;;
	disablememorydisk)
		prestage_on_ram_cleanup
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
	update_pkg_repo)
		if [ -z "${DO_NOT_UPLOAD}" -a ! -f /usr/local/bin/rsync ]; then
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

if [ -n "${SNAPSHOTS}" -a -z "${DO_NOT_UPLOAD}" ]; then
	_required=" \
		RSYNCIP \
		RSYNCUSER \
		RSYNCPATH \
		RSYNCLOGS \
		PKG_RSYNC_HOSTNAME \
		PKG_RSYNC_USERNAME \
		PKG_RSYNC_SSH_PORT \
		PKG_RSYNC_DESTDIR \
		PKG_REPO_SERVER \
		PKG_REPO_CONF_BRANCH"

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
if [ -z "${IMAGETYPE}" ]; then
	echo "ERROR: Need to specify image type to build."
	echo
	usage
fi

if [ "$IMAGETYPE" = "all" ]; then
	_IMAGESTOBUILD="iso fullupdate nanobsd nanobsd-vga memstick memstickserial"
	if [ "${TARGET}" = "amd64" ]; then
		_IMAGESTOBUILD="${_IMAGESTOBUILD} memstickadi"
	fi
else
	_IMAGESTOBUILD="${IMAGETYPE}"
fi

echo ">>> Building image type(s): ${_IMAGESTOBUILD}"

if [ -n "${SNAPSHOTS}" ]; then
	snapshots_rotate_logfile

	snapshots_update_status ">>> Starting snapshot build operations"

	if pkg update -r ${PRODUCT_NAME} >/dev/null 2>&1; then
		snapshots_update_status ">>> Updating builder packages... "
		pkg upgrade -r ${PRODUCT_NAME} -y -q >/dev/null 2>&1
	fi
fi

if [ -z "${_SKIP_REBUILD_PRESTAGE}" ]; then
	[ -n "${CORE_PKG_TMP}" -a -d "${CORE_PKG_TMP}" ] \
		&& rm -rf ${CORE_PKG_TMP}
	[ -n "${CORE_PKG_PATH}" -a -d "${CORE_PKG_PATH}" ] \
		&& rm -rf ${CORE_PKG_PATH}

	# Cleanup environment before start
	clean_builder

	# Make sure source directories are present.
	update_freebsd_sources
	git_last_commit

	# Ensure binaries are present that builder system requires
	builder_setup

	# Output build flags
	print_flags

	# Check to see if pre-staging will be hosted on ram
	prestage_on_ram_setup

	# Build world, kernel and install
	echo ">>> Building world for ISO... $FREEBSD_BRANCH ..."
	make_world

	# Build kernels
	echo ">>> Building kernel configs: $BUILD_KERNELS for FreeBSD: $FREEBSD_BRANCH ..."
	build_all_kernels

	# Prepare pre-final staging area
	clone_to_staging_area

	# Install packages needed for Product
	install_pkg_install_ports
fi

export DEFAULT_KERNEL=${DEFAULT_KERNEL_ISO:-"${PRODUCT_NAME}"}

# XXX: Figure out why wait is failing and proper fix
# Global variable to keep track of process running in bg
export _bg_pids=""

for _IMGTOBUILD in $_IMAGESTOBUILD; do
	# Clean up items that should be cleaned each run
	staginareas_clean_each_run

	if [ "${_IMGTOBUILD}" = "iso" ]; then
		create_iso_image
	elif [ "${_IMGTOBUILD}" = "memstick" ]; then
		create_memstick_image
	elif [ "${_IMGTOBUILD}" = "memstickserial" ]; then
		create_memstick_serial_image
	elif [ "${_IMGTOBUILD}" = "memstickadi" ]; then
		create_memstick_adi_image
	elif [ "${_IMGTOBUILD}" = "fullupdate" ]; then
		create_Full_update_tarball
	elif [ "${_IMGTOBUILD}" = "nanobsd" -o "${_IMGTOBUILD}" = "nanobsd-vga" ]; then
		if [ "${TARGET}" = "i386" -a "${_IMGTOBUILD}" = "nanobsd" ]; then
			export DEFAULT_KERNEL=${DEFAULT_KERNEL_NANOBSD:-"${PRODUCT_NAME}_wrap"}
		elif [ "${TARGET}" = "i386" -a "${_IMGTOBUILD}" = "nanobsd-vga" ]; then
			export DEFAULT_KERNEL=${DEFAULT_KERNEL_NANOBSDVGA:-"${PRODUCT_NAME}_wrap_vga"}
		elif [ "${TARGET}" = "amd64" ]; then
			export DEFAULT_KERNEL=${DEFAULT_KERNEL_NANOBSD:-"${PRODUCT_NAME}"}
		fi
		# Create the NanoBSD disk image
		create_nanobsd_diskimage ${_IMGTOBUILD} "${FLASH_SIZE}"
	elif [ "${_IMGTOBUILD}" = "ova" ]; then
		install_pkg_install_ports ${PRODUCT_NAME}-vmware
		create_ova_image
		install_pkg_install_ports
	fi
done

core_pkg_create_repo

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
	snapshots_copy_to_staging_iso_updates
	snapshots_copy_to_staging_nanobsd "${FLASH_SIZE}"
	# SCP files to snapshot web hosting area
	if [ -z "${DO_NOT_UPLOAD}" ]; then
		snapshots_scp_files
	fi
	# Alert the world that we have some snapshots ready.
	snapshots_update_status ">>> Builder run is complete."
fi

echo ">>> ${IMAGES_FINAL_DIR} now contains:"
ls -lah ${IMAGES_FINAL_DIR}

set -e
# Run final finish routines
finish
