#!/bin/sh
#
# builder_common.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
# All rights reserved.
#
# NanoBSD portions of the code
# Copyright (c) 2005 Poul-Henning Kamp.
# and copied from nanobsd.sh
# All rights reserved.
#
# FreeSBIE portions of the code
# Copyright (c) 2005 Dario Freni
# and copied from FreeSBIE project
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

if [ -z "${IMAGES_FINAL_DIR}" -o "${IMAGES_FINAL_DIR}" = "/" ]; then
	echo "IMAGES_FINAL_DIR is not defined"
	print_error_pfS
fi

kldload filemon >/dev/null 2>&1

lc() {
	echo "${1}" | tr '[[:upper:]]' '[[:lower:]]'
}

git_last_commit() {
	export CURRENT_COMMIT=$(git -C ${BUILDER_ROOT} log -1 --format='%H')
	export CURRENT_AUTHOR=$(git -C ${BUILDER_ROOT} log -1 --format='%an')
	echo ">>> Last known commit $CURRENT_AUTHOR - $CURRENT_COMMIT"
	echo "$CURRENT_COMMIT" > $SCRATCHDIR/build_commit_info.txt
}

# Create core pkg repository
core_pkg_create_repo() {
	if [ ! -d "${CORE_PKG_REAL_PATH}/All" ]; then
		return
	fi

	############ ATTENTION ##############
	#
	# For some reason pkg-repo fail without / in the end of directory name
	# so removing it will break command
	#
	# https://github.com/freebsd/pkg/issues/1364
	#
	echo -n ">>> Creating core packages repository... "
	if pkg repo -q "${CORE_PKG_REAL_PATH}/"; then
		echo "Done!"
	else
		echo "Failed!"
		print_error_pfS
	fi

	# Use the same directory structure as poudriere does to avoid
	# breaking snapshot repositories during rsync
	ln -sf $(basename ${CORE_PKG_REAL_PATH}) ${CORE_PKG_PATH}/.latest
	ln -sf .latest/All ${CORE_PKG_ALL_PATH}
	ln -sf .latest/digests.txz ${CORE_PKG_PATH}/digests.txz
	ln -sf .latest/meta.txz ${CORE_PKG_PATH}/meta.txz
	ln -sf .latest/packagesite.txz ${CORE_PKG_PATH}/packagesite.txz
}

# Create core pkg (base, kernel)
core_pkg_create() {
	local _template="${1}"
	local _flavor="${2}"
	local _version="${3}"
	local _root="${4}"
	local _filter="${5}"

	[ -d "${CORE_PKG_TMP}" ] \
		&& rm -rf ${CORE_PKG_TMP}

	local _templates_path=${BUILDER_TOOLS}/templates/core_pkg/${_template}
	local _template_metadir=${_templates_path}/metadir
	local _metadir=${CORE_PKG_TMP}/${_template}_metadir

	if [ ! -d ${_template_metadir} ]; then
		echo "ERROR: Template dir not found for pkg ${_template}"
		exit
	fi

	mkdir -p ${CORE_PKG_TMP}

	cp -r ${_template_metadir} ${_metadir}

	local _manifest=${_metadir}/+MANIFEST
	local _plist=${CORE_PKG_TMP}/${_template}_plist
	local _exclude_plist=${CORE_PKG_TMP}/${_template}_exclude_plist

	if [ -f "${_templates_path}/pkg-plist" ]; then
		cp ${_templates_path}/pkg-plist ${_plist}
	else
		if [ -n "${_filter}" ]; then
			_filter="-name ${_filter}"
		fi
		(cd ${_root} && find . ${_filter} -type f -or -type l | sed 's,^.,,' | sort -u) > ${_plist}
	fi

	if [ -f "${_templates_path}/exclude_plist" ]; then
		cp ${_templates_path}/exclude_plist ${_exclude_plist}
	else
		touch ${_exclude_plist}
	fi

	sed \
		-i '' \
		-e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" \
		-e "s,%%PRODUCT_URL%%,${PRODUCT_URL},g" \
		-e "s,%%FLAVOR%%,${_flavor:+-}${_flavor},g" \
		-e "s,%%FLAVOR_DESC%%,${_flavor:+ (${_flavor})},g" \
		-e "s,%%VERSION%%,${_version},g" \
		${_metadir}/* \
		${_plist} \
		${exclude_plist}

	if [ -f "${_exclude_plist}" ]; then
		sort -u ${_exclude_plist} > ${_plist}.exclude
		mv ${_plist} ${_plist}.tmp
		comm -23 ${_plist}.tmp ${_plist}.exclude > ${_plist}
		rm -f ${_plist}.tmp ${plist}.exclude
	fi

	# Add license information
	local _portname=$(sed '/^name: /!d; s,^[^"]*",,; s,",,' ${_metadir}/+MANIFEST)
	local _licenses_dir="/usr/local/share/licenses/${_portname}-${_version}"
	mkdir -p ${_root}${_licenses_dir}
	cp ${BUILDER_ROOT}/LICENSE ${_root}${_licenses_dir}/APACHE20
	echo "This package has a single license: APACHE20 (Apache License 2.0)." \
		> ${_root}${_licenses_dir}/LICENSE
	cat <<EOF >${_root}${_licenses_dir}/catalog.mk
_LICENSE=APACHE20
_LICENSE_NAME=Apache License 2.0
_LICENSE_PERMS=dist-mirror dist-sell pkg-mirror pkg-sell auto-accept
_LICENSE_GROUPS=FSF OSI
_LICENSE_DISTFILES=
EOF
	cat <<EOF >>${_plist}
${_licenses_dir}/catalog.mk
${_licenses_dir}/LICENSE
${_licenses_dir}/APACHE20
EOF

	mkdir -p ${CORE_PKG_REAL_PATH}/All
	if ! pkg create -o ${CORE_PKG_REAL_PATH}/All -p ${_plist} -r ${_root} -m ${_metadir}; then
		echo ">>> ERROR: Error building package ${_template} ${_flavor}"
		print_error_pfS
	fi

	# Cleanup _licenses_dir
	rm -rf ${_root}${_licenses_dir}
}

# This routine will output that something went wrong
print_error_pfS() {
	echo
	echo "####################################"
	echo "Something went wrong, check errors!" >&2
	echo "####################################"
	echo
	echo "NOTE: a lot of times you can run './build.sh --clean-builder' to resolve."
	echo
	if [ "$1" != "" ]; then
		echo $1
	fi
	[ -n "${LOGFILE}" -a -f "${LOGFILE}" ] && \
		echo "Log saved on ${LOGFILE}" && \
		tail -n20 ${LOGFILE} >&2
	echo
	kill $$
	exit 1
}

# This routine will verify that the kernel has been
# installed OK to the staging area.
ensure_kernel_exists() {
	if [ ! -f "$1/boot/kernel/kernel.gz" ]; then
		echo ">>> ERROR: Could not locate $1/boot/kernel.gz"
		print_error_pfS
	fi
	KERNEL_SIZE=$(stat -f "%z" $1/boot/kernel/kernel.gz)
	if [ "$KERNEL_SIZE" -lt 3500 ]; then
		echo ">>> ERROR: Kernel $1/boot/kernel.gz appears to be smaller than it should be: $KERNEL_SIZE"
		print_error_pfS
	fi
}

get_pkg_name() {
	echo "${PRODUCT_NAME}-${1}-${CORE_PKG_VERSION}"
}

# This routine builds all related kernels
build_all_kernels() {
	# Set KERNEL_BUILD_PATH if it has not been set
	if [ -z "${KERNEL_BUILD_PATH}" ]; then
		KERNEL_BUILD_PATH=$SCRATCHDIR/kernels
		echo ">>> KERNEL_BUILD_PATH has not been set. Setting to ${KERNEL_BUILD_PATH}!"
	fi

	[ -d "${KERNEL_BUILD_PATH}" ] \
		&& rm -rf ${KERNEL_BUILD_PATH}

	# Build embedded kernel
	for BUILD_KERNEL in $BUILD_KERNELS; do
		unset KERNCONF
		unset KERNEL_DESTDIR
		unset KERNELCONF
		unset KERNEL_NAME
		export KERNCONF=$BUILD_KERNEL
		export KERNEL_DESTDIR="$KERNEL_BUILD_PATH/$BUILD_KERNEL"
		export KERNELCONF="${FREEBSD_SRC_DIR}/sys/${TARGET}/conf/$BUILD_KERNEL"
		export KERNEL_NAME=${BUILD_KERNEL}

		LOGFILE="${BUILDER_LOGS}/kernel.${KERNCONF}.${TARGET}.log"
		echo ">>> Building $BUILD_KERNEL kernel."  | tee -a ${LOGFILE}

		if [ ! -e "${FREEBSD_SRC_DIR}/sys/${TARGET}/conf/${BUILD_KERNEL}" ]; then
			echo ">>> ERROR: Could not find $KERNELCONF"
			print_error_pfS
		fi

		if [ -n "${NO_BUILDKERNEL}" -a -f "${CORE_PKG_ALL_PATH}/$(get_pkg_name kernel-${KERNEL_NAME}).txz" ]; then
			echo ">>> NO_BUILDKERNEL set, skipping build" | tee -a ${LOGFILE}
			continue
		fi

		buildkernel

		echo ">>> Staging $BUILD_KERNEL kernel..." | tee -a ${LOGFILE}
		installkernel

		ensure_kernel_exists $KERNEL_DESTDIR

		echo -n ">>> Creating pkg of $KERNEL_NAME-debug kernel to staging area... "  | tee -a ${LOGFILE}
		core_pkg_create kernel-debug ${KERNEL_NAME} ${CORE_PKG_VERSION} ${KERNEL_DESTDIR} \*.symbols
		find ${KERNEL_DESTDIR} -name '*.symbols' -type f -delete
		echo " Done" | tee -a ${LOGFILE}

		echo -n ">>> Creating pkg of $KERNEL_NAME kernel to staging area... "  | tee -a ${LOGFILE}
		core_pkg_create kernel ${KERNEL_NAME} ${CORE_PKG_VERSION} ${KERNEL_DESTDIR}

		rm -rf $KERNEL_DESTDIR 2>&1 1>/dev/null

		echo " Done" | tee -a ${LOGFILE}
	done
}

install_default_kernel() {
	if [ -z "${1}" ]; then
		echo ">>> ERROR: install_default_kernel called without a kernel config name"| tee -a ${LOGFILE}
		print_error_pfS
	fi

	export KERNEL_NAME="${1}"

	echo -n ">>> Installing kernel to be used by image ${KERNEL_NAME}..." | tee -a ${LOGFILE}

	# Copy kernel package to chroot, otherwise pkg won't find it to install
	if ! pkg_chroot_add ${FINAL_CHROOT_DIR} kernel-${KERNEL_NAME}; then
		echo ">>> ERROR: Error installing kernel package $(get_pkg_name kernel-${KERNEL_NAME}).txz" | tee -a ${LOGFILE}
		print_error_pfS
	fi

	# Lock kernel to avoid user end up removing it for any reason
	pkg_chroot ${FINAL_CHROOT_DIR} lock -q -y $(get_pkg_name kernel-${KERNEL_NAME})

	if [ ! -f $FINAL_CHROOT_DIR/boot/kernel/kernel.gz ]; then
		echo ">>> ERROR: No kernel installed on $FINAL_CHROOT_DIR and the resulting image will be unusable. STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi
	mkdir -p $FINAL_CHROOT_DIR/pkgs
	if [ -z "${2}" -o -n "${INSTALL_EXTRA_KERNELS}" ]; then
		cp ${CORE_PKG_ALL_PATH}/$(get_pkg_name kernel-${KERNEL_NAME}).txz $FINAL_CHROOT_DIR/pkgs
		if [ -n "${INSTALL_EXTRA_KERNELS}" ]; then
			for _EXTRA_KERNEL in $INSTALL_EXTRA_KERNELS; do
				_EXTRA_KERNEL_PATH=${CORE_PKG_ALL_PATH}/$(get_pkg_name kernel-${_EXTRA_KERNEL}).txz
				if [ -f "${_EXTRA_KERNEL_PATH}" ]; then
					echo -n ". adding ${_EXTRA_KERNEL_PATH} on image /pkgs folder"
					cp ${_EXTRA_KERNEL_PATH} $FINAL_CHROOT_DIR/pkgs
				else
					echo ">>> ERROR: Requested kernel $(get_pkg_name kernel-${_EXTRA_KERNEL}).txz was not found to be put on image /pkgs folder!"
					print_error_pfS
				fi
			done
		fi
	fi
	echo "Done." | tee -a ${LOGFILE}

	unset KERNEL_NAME
}

# This builds FreeBSD (make buildworld)
# Imported from FreeSBIE
make_world() {
	LOGFILE=${BUILDER_LOGS}/buildworld.${TARGET}
	echo ">>> LOGFILE set to $LOGFILE." | tee -a ${LOGFILE}
	if [ -n "${NO_BUILDWORLD}" ]; then
		echo ">>> NO_BUILDWORLD set, skipping build" | tee -a ${LOGFILE}
		return
	fi

	makeargs="${MAKEJ}"
	echo ">>> Building world for ${TARGET} architecture... (Starting - $(LC_ALL=C date))" | tee -a ${LOGFILE}
	echo ">>> Builder is running the command: script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} buildworld" | tee -a ${LOGFILE}
	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} buildworld || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	echo ">>> Building world for ${TARGET} architecture... (Finished - $(LC_ALL=C date))" | tee -a ${LOGFILE}

	LOGFILE=${BUILDER_LOGS}/installworld.${TARGET}
	echo ">>> LOGFILE set to $LOGFILE." | tee -a ${LOGFILE}

	[ -d "${INSTALLER_CHROOT_DIR}" ] \
		|| mkdir -p ${INSTALLER_CHROOT_DIR}

	makeargs="${MAKEJ} DESTDIR=${INSTALLER_CHROOT_DIR}"
	echo ">>> Installing world for ${TARGET} architecture... (Starting - $(LC_ALL=C date))" | tee -a ${LOGFILE}
	echo ">>> Builder is running the command: script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} installworld" | tee -a ${LOGFILE}
	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} installworld || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	cp ${FREEBSD_SRC_DIR}/release/rc.local ${INSTALLER_CHROOT_DIR}/etc
	echo ">>> Installing world for ${TARGET} architecture... (Finished - $(LC_ALL=C date))" | tee -a ${LOGFILE}

	echo ">>> Distribution world for ${TARGET} architecture... (Starting - $(LC_ALL=C date))" | tee -a ${LOGFILE}
	echo ">>> Builder is running the command: script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} distribution " | tee -a ${LOGFILE}
	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} distribution  || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	echo ">>> Distribution world for ${TARGET} architecture... (Finished - $(LC_ALL=C date))" | tee -a ${LOGFILE}

	makeargs="${MAKEJ} WITHOUT_BSDINSTALL=1 DESTDIR=${STAGE_CHROOT_DIR}"
	echo ">>> Installing world for ${TARGET} architecture... (Starting - $(LC_ALL=C date))" | tee -a ${LOGFILE}
	echo ">>> Builder is running the command: script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} installworld" | tee -a ${LOGFILE}
	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} installworld || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	echo ">>> Installing world for ${TARGET} architecture... (Finished - $(LC_ALL=C date))" | tee -a ${LOGFILE}

	echo ">>> Distribution world for ${TARGET} architecture... (Starting - $(LC_ALL=C date))" | tee -a ${LOGFILE}
	echo ">>> Builder is running the command: script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} distribution " | tee -a ${LOGFILE}
	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} distribution  || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	echo ">>> Distribution world for ${TARGET} architecture... (Finished - $(LC_ALL=C date))" | tee -a ${LOGFILE}

	[ -d "${STAGE_CHROOT_DIR}/usr/local/bin" ] \
		|| mkdir -p ${STAGE_CHROOT_DIR}/usr/local/bin
	makeargs="${MAKEJ} DESTDIR=${STAGE_CHROOT_DIR}"
	echo ">>> Building and installing crypto tools and athstats for ${TARGET} architecture... (Starting - $(LC_ALL=C date))" | tee -a ${LOGFILE}
	echo ">>> Builder is running the command: script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR}/tools/tools/crypto ${makeargs} clean all install " | tee -a ${LOGFILE}
	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR}/tools/tools/crypto ${makeargs} clean all install || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	# XXX FIX IT
#	echo ">>> Builder is running the command: script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR}/tools/tools/ath/athstats ${makeargs} clean all install" | tee -a ${LOGFILE}
#	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR}/tools/tools/ath/athstats ${makeargs} clean all install || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	echo ">>> Building and installing crypto tools and athstats for ${TARGET} architecture... (Finished - $(LC_ALL=C date))" | tee -a ${LOGFILE}

	unset makeargs
}

nanobsd_image_filename() {
	local _size="$1"
	local _type="$2"

	echo "$NANOBSD_IMG_TEMPLATE" | sed \
		-e "s,%%SIZE%%,${_size},g" \
		-e "s,%%TYPE%%,${_type},g"

	return 0
}

# This routine originated in nanobsd.sh
nanobsd_set_flash_details () {
	a1=$(echo $1 | tr '[:upper:]' '[:lower:]')

	# Source:
	#	SanDisk CompactFlash Memory Card
	#	Product Manual
	#	Version 10.9
	#	Document No. 20-10-00038
	#	April 2005
	# Table 2-7
	# NB: notice math error in SDCFJ-4096-388 line.
	#
	case "${a1}" in
		2048|2048m|2048mb|2g)
			NANO_MEDIASIZE=$((1989999616/512))
			;;
		4096|4096m|4096mb|4g)
			NANO_MEDIASIZE=$((3989999616/512))
			;;
		8192|8192m|8192mb|8g)
			NANO_MEDIASIZE=$((7989999616/512))
			;;
		16384|16384m|16384mb|16g)
			NANO_MEDIASIZE=$((15989999616/512))
			;;
		*)
			echo "Unknown Flash capacity"
			exit 2
			;;
	esac

	NANO_HEADS=16
	NANO_SECTS=63

	echo ">>> [nanoo] $1"
	echo ">>> [nanoo] NANO_MEDIASIZE: $NANO_MEDIASIZE"
	echo ">>> [nanoo] NANO_HEADS: $NANO_HEADS"
	echo ">>> [nanoo] NANO_SECTS: $NANO_SECTS"
	echo ">>> [nanoo] NANO_BOOT0CFG: $NANO_BOOT0CFG"
}

# This routine originated in nanobsd.sh
create_nanobsd_diskimage () {
	if [ -z "${1}" ]; then
		echo ">>> ERROR: Type of image has not been specified"
		print_error_pfS
	fi
	if [ -z "${2}" ]; then
		echo ">>> ERROR: Size of image has not been specified"
		print_error_pfS
	fi

	if [ "${1}" = "nanobsd" ]; then
		# It's serial
		export NANO_BOOTLOADER="boot/boot0sio"
	elif [ "${1}" = "nanobsd-vga" ]; then
		# It's vga
		export NANO_BOOTLOADER="boot/boot0"
	else
		echo ">>> ERROR: Type of image to create unknown"
		print_error_pfS
	fi

	if [ -z "${2}" ]; then
		echo ">>> ERROR: Media size(s) not specified."
		print_error_pfS
	fi

	if [ -z "${2}" ]; then
		echo ">>> ERROR: FLASH_SIZE is not set."
		print_error_pfS
	fi

	LOGFILE=${BUILDER_LOGS}/${1}.${TARGET}
	# Prepare folder to be put in image
	customize_stagearea_for_image "${1}"
	install_default_kernel ${DEFAULT_KERNEL} "no"

	echo ">>> Fixing up NanoBSD Specific items..." | tee -a ${LOGFILE}

	local BOOTCONF=${FINAL_CHROOT_DIR}/boot.config
	local LOADERCONF=${FINAL_CHROOT_DIR}/boot/loader.conf

	if [ "${1}" = "nanobsd" ]; then
		# Tell loader to use serial console early.
		echo "-S115200 -h" >> ${BOOTCONF}

		# Remove old console options if present.
		[ -f "${LOADERCONF}" ] \
			&& sed -i "" -Ee "/(console|boot_multicons|boot_serial|hint.uart)/d" ${LOADERCONF}
		# Activate serial console+video console in loader.conf
		echo 'loader_color="NO"' >> ${LOADERCONF}
		echo 'beastie_disable="YES"' >> ${LOADERCONF}
		echo 'boot_serial="YES"' >> ${LOADERCONF}
		echo 'console="comconsole"' >> ${LOADERCONF}
		echo 'comconsole_speed="115200"' >> ${LOADERCONF}
	fi
	echo 'autoboot_delay="5"' >> ${LOADERCONF}

	# Old systems will run (pre|post)_upgrade_command from /tmp
	if [ -f ${FINAL_CHROOT_DIR}${PRODUCT_SHARE_DIR}/pre_upgrade_command ]; then
		cp -p \
			${FINAL_CHROOT_DIR}${PRODUCT_SHARE_DIR}/pre_upgrade_command \
			${FINAL_CHROOT_DIR}/tmp
	fi
	if [ -f ${FINAL_CHROOT_DIR}${PRODUCT_SHARE_DIR}/post_upgrade_command ]; then
		cp -p \
			${FINAL_CHROOT_DIR}${PRODUCT_SHARE_DIR}/post_upgrade_command \
			${FINAL_CHROOT_DIR}/tmp
	fi

	mkdir -p ${IMAGES_FINAL_DIR}/nanobsd

	for _NANO_MEDIASIZE in ${2}; do
		if [ -z "${_NANO_MEDIASIZE}" ]; then
			continue;
		fi

		echo ">>> building NanoBSD(${1}) disk image with size ${_NANO_MEDIASIZE} for platform (${TARGET})..." | tee -a ${LOGFILE}
		echo "" > $BUILDER_LOGS/nanobsd_cmds.sh

		IMG="${IMAGES_FINAL_DIR}/nanobsd/$(nanobsd_image_filename ${_NANO_MEDIASIZE} ${1})"

		nanobsd_set_flash_details ${_NANO_MEDIASIZE}

		# These are defined in FlashDevice and on builder_default.sh
		echo $NANO_MEDIASIZE \
			$NANO_IMAGES \
			$NANO_SECTS \
			$NANO_HEADS \
			$NANO_CODESIZE \
			$NANO_CONFSIZE \
			$NANO_DATASIZE |
awk '
{
	printf "# %s\n", $0

	# size of cylinder in sectors
	cs = $3 * $4

	# number of full cylinders on media
	cyl = int ($1 / cs)

	# output fdisk geometry spec, truncate cyls to 1023
	if (cyl <= 1023)
		print "g c" cyl " h" $4 " s" $3
	else
		print "g c" 1023 " h" $4 " s" $3

	if ($7 > 0) {
		# size of data partition in full cylinders
		dsl = int (($7 + cs - 1) / cs)
	} else {
		dsl = 0;
	}

	# size of config partition in full cylinders
	csl = int (($6 + cs - 1) / cs)

	if ($5 == 0) {
		# size of image partition(s) in full cylinders
		isl = int ((cyl - dsl - csl) / $2)
	} else {
		isl = int (($5 + cs - 1) / cs)
	}

	# First image partition start at second track
	print "p 1 165 " $3, isl * cs - $3
	c = isl * cs;

	# Second image partition (if any) also starts offset one
	# track to keep them identical.
	if ($2 > 1) {
		print "p 2 165 " $3 + c, isl * cs - $3
		c += isl * cs;
	}

	# Config partition starts at cylinder boundary.
	print "p 3 165 " c, csl * cs
	c += csl * cs

	# Data partition (if any) starts at cylinder boundary.
	if ($7 > 0) {
		print "p 4 165 " c, dsl * cs
	} else if ($7 < 0 && $1 > c) {
		print "p 4 165 " c, $1 - c
	} else if ($1 < c) {
		print "Disk space overcommitted by", \
		    c - $1, "sectors" > "/dev/stderr"
		exit 2
	}

	# Force slice 1 to be marked active. This is necessary
	# for booting the image from a USB device to work.
	print "a 1"
}
	' > ${SCRATCHDIR}/_.fdisk

		MNT=${SCRATCHDIR}/_.mnt
		mkdir -p ${MNT}

		dd if=/dev/zero of=${IMG} bs=${NANO_SECTS}b \
			count=0 seek=$((${NANO_MEDIASIZE}/${NANO_SECTS})) 2>&1 >> ${LOGFILE}

		MD=$(mdconfig -a -t vnode -f ${IMG} -x ${NANO_SECTS} -y ${NANO_HEADS})
		trap "mdconfig -d -u ${MD}; return" 1 2 15 EXIT

		fdisk -i -f ${SCRATCHDIR}/_.fdisk ${MD} 2>&1 >> ${LOGFILE}
		fdisk ${MD} 2>&1 >> ${LOGFILE}

		boot0cfg -t 100 -B -b ${FINAL_CHROOT_DIR}/${NANO_BOOTLOADER} ${NANO_BOOT0CFG} ${MD} 2>&1 >> ${LOGFILE}

		# Create first image
		bsdlabel -m i386 -w -B -b ${FINAL_CHROOT_DIR}/boot/boot ${MD}s1 2>&1 >> ${LOGFILE}
		bsdlabel -m i386 ${MD}s1 2>&1 >> ${LOGFILE}
		local _label=$(lc ${PRODUCT_NAME})
		newfs -L ${_label}0 ${NANO_NEWFS} /dev/${MD}s1a 2>&1 >> ${LOGFILE}
		mount /dev/ufs/${_label}0 ${MNT}
		if [ $? -ne 0 ]; then
			echo ">>> ERROR: Something wrong happened during mount of first slice image creation. STOPPING!" | tee -a ${LOGFILE}
			print_error_pfS
		fi
		# Consider the unmounting as well
		trap "umount /dev/ufs/${_label}0; mdconfig -d -u ${MD}; return" 1 2 15 EXIT

		clone_directory_contents ${FINAL_CHROOT_DIR} ${MNT}

		# Set NanoBSD image size
		echo "${_NANO_MEDIASIZE}" > ${MNT}/etc/nanosize.txt

		echo "/dev/ufs/${_label}0 / ufs ro,sync,noatime 1 1" > ${MNT}/etc/fstab
		if [ $NANO_CONFSIZE -gt 0 ] ; then
			echo "/dev/ufs/cf /cf ufs ro,sync,noatime 1 1" >> ${MNT}/etc/fstab
		fi

		umount ${MNT}
		# Restore the original trap
		trap "mdconfig -d -u ${MD}; return" 1 2 15 EXIT

		# Setting NANO_IMAGES to 1 and NANO_INIT_IMG2 will tell
		# NanoBSD to only create one partition.  We default to 2
		# partitions in case anything happens to the first the
		# operator can boot from the 2nd and should be OK.

		# Before just going to use dd for duplicate think!
		# The images are created as sparse so lets take advantage
		# of that by just exec some commands.
		if [ $NANO_IMAGES -gt 1 -a $NANO_INIT_IMG2 -gt 0 ] ; then
			# Duplicate to second image (if present)
			echo ">>> Creating NanoBSD second slice by duplicating first slice." | tee -a ${LOGFILE}
			# Create second image
			dd if=/dev/${MD}s1 of=/dev/${MD}s2 conv=sparse bs=64k 2>&1 >> ${LOGFILE}
			tunefs -L ${_label}1 /dev/${MD}s2a 2>&1 >> ${LOGFILE}
			mount /dev/ufs/${_label}1 ${MNT}
			if [ $? -ne 0 ]; then
				echo ">>> ERROR: Something wrong happened during mount of second slice image creation. STOPPING!" | tee -a ${LOGFILE}
				print_error_pfS
			fi
			# Consider the unmounting as well
			trap "umount /dev/ufs/${_label}1; mdconfig -d -u ${MD}; return" 1 2 15 EXIT

			echo "/dev/ufs/${_label}1 / ufs ro,sync,noatime 1 1" > ${MNT}/etc/fstab
			if [ $NANO_CONFSIZE -gt 0 ] ; then
				echo "/dev/ufs/cf /cf ufs ro,sync,noatime 1 1" >> ${MNT}/etc/fstab
			fi

			umount ${MNT}
			# Restore the trap back
			trap "mdconfig -d -u ${MD}; return" 1 2 15 EXIT
		fi

		# Create Data slice, if any.
		# Note the changing of the variable to NANO_CONFSIZE
		# from NANO_DATASIZE.  We also added glabel support
		# and populate the Product configuration from the /cf
		# directory located in FINAL_CHROOT_DIR
		if [ $NANO_CONFSIZE -gt 0 ] ; then
			echo ">>> Creating /cf area to hold config.xml"
			newfs -L cf ${NANO_NEWFS} /dev/${MD}s3 2>&1 >> ${LOGFILE}
			# Mount data partition and copy contents of /cf
			# Can be used later to create custom default config.xml while building
			mount /dev/ufs/cf ${MNT}
			if [ $? -ne 0 ]; then
				echo ">>> ERROR: Something wrong happened during mount of cf slice image creation. STOPPING!" | tee -a ${LOGFILE}
				print_error_pfS
			fi
			# Consider the unmounting as well
			trap "umount /dev/ufs/cf; mdconfig -d -u ${MD}; return" 1 2 15 EXIT

			clone_directory_contents ${FINAL_CHROOT_DIR}/cf ${MNT}

			umount ${MNT}
			# Restore the trap back
			trap "mdconfig -d -u ${MD}; return" 1 2 15 EXIT
		else
			">>> [nanoo] NANO_CONFSIZE is not set. Not adding a /conf partition.. You sure about this??" | tee -a ${LOGFILE}
		fi

		mdconfig -d -u $MD
		# Restore default action
		trap "-" 1 2 15 EXIT

		# Check each image and ensure that they are over
		# 3 megabytes.  If either image is under 20 megabytes
		# in size then error out.
		IMGSIZE=$(stat -f "%z" ${IMG})
		CHECKSIZE="20040710"
		if [ "$IMGSIZE" -lt "$CHECKSIZE" ]; then
			echo ">>> ERROR: Something went wrong when building NanoBSD.  The image size is under 20 megabytes!" | tee -a ${LOGFILE}
			print_error_pfS
		fi

		# Wrap up the show, Johnny
		echo ">>> NanoBSD Image completed for size: $_NANO_MEDIASIZE." | tee -a ${LOGFILE}

		gzip -qf $IMG &
		_bg_pids="${_bg_pids}${_bg_pids:+ }$!"
	done

	unset IMG
	unset IMGSIZE
}

# This routine creates a ova image that contains
# a ovf and vmdk file. These files can be imported
# right into vmware or virtual box.
# (and many other emulation platforms)
# http://www.vmware.com/pdf/ovf_whitepaper_specification.pdf
create_ova_image() {
	# XXX create a .ovf php creator that you can pass:
	#     1. populatedSize
	#     2. license
	#     3. product name
	#     4. version
	#     5. number of network interface cards
	#     6. allocationUnits
	#     7. capacity
	#     8. capacityAllocationUnits

	LOGFILE=${BUILDER_LOGS}/ova.${TARGET}.log

	if [ -d "${OVA_TMP}" ]; then
		chflags -R noschg ${OVA_TMP}
		rm -rf ${OVA_TMP}
	fi

	mkdir -p $(dirname ${OVAPATH})

	local _mntdir=${OVA_TMP}/mnt
	mkdir -p ${_mntdir}

	if [ -z "${OVA_SWAP_PART_SIZE_IN_GB}" -o "${OVA_SWAP_PART_SIZE_IN_GB}" = "0" ]; then
		# first partition size (freebsd-ufs)
		local OVA_FIRST_PART_SIZE_IN_GB=${VMDK_DISK_CAPACITY_IN_GB}
		# Calculate real first partition size, removing 128 blocks (65536 bytes) beginning/loader
		local OVA_FIRST_PART_SIZE=$((${OVA_FIRST_PART_SIZE_IN_GB}*1024*1024*1024-65536))
		# Unset swap partition size variable
		unset OVA_SWAP_PART_SIZE
		# Parameter used by mkimg
		unset OVA_SWAP_PART_PARAM
	else
		# first partition size (freebsd-ufs)
		local OVA_FIRST_PART_SIZE_IN_GB=$((VMDK_DISK_CAPACITY_IN_GB-OVA_SWAP_PART_SIZE_IN_GB))
		# Use first partition size in g
		local OVA_FIRST_PART_SIZE="${OVA_FIRST_PART_SIZE_IN_GB}g"
		# Calculate real swap size, removing 128 blocks (65536 bytes) beginning/loader
		local OVA_SWAP_PART_SIZE=$((${OVA_SWAP_PART_SIZE_IN_GB}*1024*1024*1024-65536))
		# Parameter used by mkimg
		local OVA_SWAP_PART_PARAM="-p freebsd-swap/swap0::${OVA_SWAP_PART_SIZE}"
	fi

	# Prepare folder to be put in image
	customize_stagearea_for_image "ova"
	install_default_kernel ${DEFAULT_KERNEL} "no"

	# Fill fstab
	echo ">>> Installing platform specific items..." | tee -a ${LOGFILE}
	echo "/dev/gpt/${PRODUCT_NAME}	/	ufs		rw	1	1" > ${FINAL_CHROOT_DIR}/etc/fstab
	if [ -n "${OVA_SWAP_PART_SIZE}" ]; then
		echo "/dev/gpt/swap0	none	swap	sw	0	0" >> ${FINAL_CHROOT_DIR}/etc/fstab
	fi

	# Create / partition
	echo -n ">>> Creating / partition... " | tee -a ${LOGFILE}
	truncate -s ${OVA_FIRST_PART_SIZE} ${OVA_TMP}/${OVFUFS}
	local _md=$(mdconfig -a -f ${OVA_TMP}/${OVFUFS})
	trap "mdconfig -d -u ${_md}; return" 1 2 15 EXIT

	newfs -L ${PRODUCT_NAME} -j /dev/${_md} 2>&1 >>${LOGFILE}

	if ! mount /dev/${_md} ${_mntdir} 2>&1 >>${LOGFILE}; then
		echo "Failed!" | tee -a ${LOGFILE}
		echo ">>> ERROR: Error mounting temporary vmdk image. STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi
	trap "umount ${_mntdir}; mdconfig -d -u ${_md}; return" 1 2 15 EXIT

	echo "Done!" | tee -a ${LOGFILE}

	clone_directory_contents ${FINAL_CHROOT_DIR} ${_mntdir}

	sync
	umount ${_mntdir} 2>&1 >>${LOGFILE}
	mdconfig -d -u ${_md}
	trap "-" 1 2 15 EXIT

	# Create raw disk
	echo -n ">>> Creating raw disk... " | tee -a ${LOGFILE}
	mkimg \
		-s gpt \
		-f raw \
		-b ${FINAL_CHROOT_DIR}/boot/pmbr \
		-p freebsd-boot:=${FINAL_CHROOT_DIR}/boot/gptboot \
		-p freebsd-ufs/${PRODUCT_NAME}:=${OVA_TMP}/${OVFUFS} \
		${OVA_SWAP_PART_PARAM} \
		-o ${OVA_TMP}/${OVFRAW} 2>&1 >> ${LOGFILE}

	if [ $? -ne 0 -o ! -f ${OVA_TMP}/${OVFRAW} ]; then
		if [ -f ${OVA_TMP}/${OVFUFS} ]; then
			rm -f ${OVA_TMP}/${OVFUFS}
		fi
		if [ -f ${OVA_TMP}/${OVFRAW} ]; then
			rm -f ${OVA_TMP}/${OVFRAW}
		fi
		echo "Failed!" | tee -a ${LOGFILE}
		echo ">>> ERROR: Error creating temporary vmdk image. STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi
	echo "Done!" | tee -a ${LOGFILE}

	# We don't need it anymore
	rm -f ${OVA_TMP}/${OVFUFS} >/dev/null 2>&1

	# Convert raw to vmdk
	echo -n ">>> Creating vmdk disk... " | tee -a ${LOGFILE}
	vmdktool -z9 -v ${OVA_TMP}/${OVFVMDK} ${OVA_TMP}/${OVFRAW}

	if [ $? -ne 0 -o ! -f ${OVA_TMP}/${OVFVMDK} ]; then
		if [ -f ${OVA_TMP}/${OVFRAW} ]; then
			rm -f ${OVA_TMP}/${OVFRAW}
		fi
		if [ -f ${OVA_TMP}/${OVFVMDK} ]; then
			rm -f ${OVA_TMP}/${OVFVMDK}
		fi
		echo "Failed!" | tee -a ${LOGFILE}
		echo ">>> ERROR: Error creating vmdk image. STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi
	echo "Done!" | tee -a ${LOGFILE}

	rm -f ${OVA_TMP}/i${OVFRAW}

	ova_setup_ovf_template

	echo -n ">>> Writing final ova image... " | tee -a ${LOGFILE}
	# Create OVA file for vmware
	gtar -C ${OVA_TMP} -cpf ${OVAPATH} ${PRODUCT_NAME}.ovf ${OVFVMDK}
	echo "Done!" | tee -a ${LOGFILE}
	rm -f ${OVA_TMP}/${OVFVMDK} >/dev/null 2>&1

	echo ">>> OVA created: $(LC_ALL=C date)" | tee -a ${LOGFILE}
}

# called from create_ova_image
ova_setup_ovf_template() {
	if [ ! -f ${OVFTEMPLATE} ]; then
		echo ">>> ERROR: OVF template file (${OVFTEMPLATE}) not found."
		print_error_pfS
	fi

	#  OperatingSystemSection (${PRODUCT_NAME}.ovf)
	#  42   FreeBSD 32-Bit
	#  78   FreeBSD 64-Bit
	if [ "${TARGET}" = "amd64" ]; then
		local _os_id="78"
		local _os_type="freebsd64Guest"
		local _os_descr="FreeBSD 64-Bit"
	else
		echo ">>> ERROR: Platform not supported for OVA (${TARGET})"
		print_error_pfS
	fi

	local POPULATED_SIZE=$(du -d0 -k $FINAL_CHROOT_DIR | cut -f1)
	local POPULATED_SIZE_IN_BYTES=$((${POPULATED_SIZE}*1024))
	local VMDK_FILE_SIZE=$(stat -f "%z" ${OVA_TMP}/${OVFVMDK})

	sed \
		-e "s,%%VMDK_FILE_SIZE%%,${VMDK_FILE_SIZE},g" \
		-e "s,%%VMDK_DISK_CAPACITY_IN_GB%%,${VMDK_DISK_CAPACITY_IN_GB},g" \
		-e "s,%%POPULATED_SIZE_IN_BYTES%%,${POPULATED_SIZE_IN_BYTES},g" \
		-e "s,%%OS_ID%%,${_os_id},g" \
		-e "s,%%OS_TYPE%%,${_os_type},g" \
		-e "s,%%OS_DESCR%%,${_os_descr},g" \
		-e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" \
		-e "s,%%PRODUCT_NAME_SUFFIX%%,${PRODUCT_NAME_SUFFIX},g" \
		-e "s,%%PRODUCT_VERSION%%,${PRODUCT_VERSION},g" \
		-e "s,%%PRODUCT_URL%%,${PRODUCT_URL},g" \
		-e "s#%%VENDOR_NAME%%#${VENDOR_NAME}#g" \
		-e "s#%%OVF_INFO%%#${OVF_INFO}#g" \
		-e "/^%%PRODUCT_LICENSE%%/r ${BUILDER_ROOT}/LICENSE" \
		-e "/^%%PRODUCT_LICENSE%%/d" \
		${OVFTEMPLATE} > ${OVA_TMP}/${PRODUCT_NAME}.ovf
}

# Cleans up previous builds
clean_builder() {
	# Clean out directories
	echo ">>> Cleaning up previous build environment...Please wait!"

	staginareas_clean_each_run

	if [ -d "${STAGE_CHROOT_DIR}" ]; then
		echo -n ">>> Cleaning ${STAGE_CHROOT_DIR}... "
		chflags -R noschg ${STAGE_CHROOT_DIR} 2>&1 >/dev/null
		rm -rf ${STAGE_CHROOT_DIR}/* 2>/dev/null
		echo "Done."
	fi

	if [ -d "${INSTALLER_CHROOT_DIR}" ]; then
		echo -n ">>> Cleaning ${INSTALLER_CHROOT_DIR}... "
		chflags -R noschg ${INSTALLER_CHROOT_DIR} 2>&1 >/dev/null
		rm -rf ${INSTALLER_CHROOT_DIR}/* 2>/dev/null
		echo "Done."
	fi

	if [ -z "${NO_CLEAN_FREEBSD_OBJ}" -a -d "${FREEBSD_SRC_DIR}" ]; then
		OBJTREE=$(make -C ${FREEBSD_SRC_DIR} -V OBJTREE)
		if [ -d "${OBJTREE}" ]; then
			echo -n ">>> Cleaning FreeBSD objects dir staging..."
			echo -n "."
			chflags -R noschg ${OBJTREE} 2>&1 >/dev/null
			echo -n "."
			rm -rf ${OBJTREE}/*
			echo "Done!"
		fi
		if [ -d "${KERNEL_BUILD_PATH}" ]; then
			echo -n ">>> Cleaning previously built kernel stage area..."
			rm -rf $KERNEL_BUILD_PATH/*
			echo "Done!"
		fi
	fi
	mkdir -p $KERNEL_BUILD_PATH

	echo -n ">>> Cleaning previously built images..."
	rm -rf $IMAGES_FINAL_DIR/*
	echo "Done!"

	if [ -z "${NO_CLEAN_FREEBSD_SRC}" ]; then
		if [ -d "$FREEBSD_SRC_DIR" ]; then
			echo -n ">>> Ensuring $FREEBSD_SRC_DIR is clean..."
			rm -rf ${FREEBSD_SRC_DIR}
			echo "Done!"
		fi
	fi

	echo -n ">>> Cleaning previous builder logs..."
	if [ -d "$BUILDER_LOGS" ]; then
		rm -rf ${BUILDER_LOGS}
	fi
	mkdir -p ${BUILDER_LOGS}

	echo "Done!"

	echo ">>> Cleaning of builder environment has finished."
}

clone_directory_contents() {
	if [ ! -e "$2" ]; then
		mkdir -p "$2"
	fi
	if [ ! -d "$1" -o ! -d "$2" ]; then
		if [ -z "${LOGFILE}" ]; then
			echo ">>> ERROR: Argument $1 supplied is not a directory!"
		else
			echo ">>> ERROR: Argument $1 supplied is not a directory!" | tee -a ${LOGFILE}
		fi
		print_error_pfS
	fi
	echo -n ">>> Using TAR to clone $1 to $2 ..."
	tar -C ${1} -c -f - . | tar -C ${2} -x -p -f -
	echo "Done!"
}

clone_to_staging_area() {
	# Clone everything to the final staging area
	echo -n ">>> Cloning everything to ${STAGE_CHROOT_DIR} staging area..."
	LOGFILE=${BUILDER_LOGS}/cloning.${TARGET}.log

	tar -C ${PRODUCT_SRC} -c -f - . | \
		tar -C ${STAGE_CHROOT_DIR} -x -p -f -

	if [ "${PRODUCT_NAME}" != "pfSense" ]; then
		mv ${STAGE_CHROOT_DIR}/usr/local/sbin/pfSense-upgrade \
			${STAGE_CHROOT_DIR}/usr/local/sbin/${PRODUCT_NAME}-upgrade
	fi

	mkdir -p ${STAGE_CHROOT_DIR}/etc/mtree
	mtree -Pcp ${STAGE_CHROOT_DIR}/var > ${STAGE_CHROOT_DIR}/etc/mtree/var.dist
	mtree -Pcp ${STAGE_CHROOT_DIR}/etc > ${STAGE_CHROOT_DIR}/etc/mtree/etc.dist
	if [ -d ${STAGE_CHROOT_DIR}/usr/local/etc ]; then
		mtree -Pcp ${STAGE_CHROOT_DIR}/usr/local/etc > ${STAGE_CHROOT_DIR}/etc/mtree/localetc.dist
	fi

	## Add buildtime and lastcommit information
	# This is used for detecting updates.
	echo "$BUILTDATESTRING" > $STAGE_CHROOT_DIR/etc/version.buildtime
	# Record last commit info if it is available.
	if [ -f $SCRATCHDIR/build_commit_info.txt ]; then
		cp $SCRATCHDIR/build_commit_info.txt $STAGE_CHROOT_DIR/etc/version.lastcommit
	fi

	local _exclude_files="${CORE_PKG_TMP}/base_exclude_files"
	sed \
		-e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" \
		-e "s,%%VERSION%%,${_version},g" \
		${BUILDER_TOOLS}/templates/core_pkg/base/exclude_files \
		> ${_exclude_files}

	mkdir -p ${STAGE_CHROOT_DIR}${PRODUCT_SHARE_DIR} >/dev/null 2>&1

	# Include a sample pkg stable conf to base
	setup_pkg_repo \
		${PKG_REPO_DEFAULT} \
		${STAGE_CHROOT_DIR}${PRODUCT_SHARE_DIR}/${PRODUCT_NAME}-repo.conf \
		${TARGET} \
		${TARGET_ARCH}

	mtree \
		-c \
		-k uid,gid,mode,size,flags,sha256digest \
		-p ${STAGE_CHROOT_DIR} \
		-X ${_exclude_files} \
		> ${STAGE_CHROOT_DIR}${PRODUCT_SHARE_DIR}/base.mtree
	tar \
		-C ${STAGE_CHROOT_DIR} \
		-cJf ${STAGE_CHROOT_DIR}${PRODUCT_SHARE_DIR}/base.txz \
		-X ${_exclude_files} \
		.

	local _share_repos_path="${SCRATCHDIR}/repo-tmp/${PRODUCT_SHARE_DIR}/pkg/repos"
	rm -rf ${SCRATCHDIR}/repo-tmp >/dev/null 2>&1
	mkdir -p ${_share_repos_path} >/dev/null 2>&1

	setup_pkg_repo \
		${PKG_REPO_DEFAULT} \
		${_share_repos_path}/${PRODUCT_NAME}-repo.conf \
		${TARGET} \
		${TARGET_ARCH}

	cp -f ${PKG_REPO_DEFAULT%%.conf}.descr ${_share_repos_path}

	# Add additional repos
	for _template in ${PKG_REPO_BASE}/${PRODUCT_NAME}-repo-*.conf; do
		_template_filename=$(basename ${_template})
		setup_pkg_repo \
			${_template} \
			${_share_repos_path}/${_template_filename} \
			${TARGET} \
			${TARGET_ARCH}
		cp -f ${_template%%.conf}.descr ${_share_repos_path}
	done

	core_pkg_create repo "" ${CORE_PKG_VERSION} ${SCRATCHDIR}/repo-tmp

	core_pkg_create rc "" ${CORE_PKG_VERSION} ${STAGE_CHROOT_DIR}
	core_pkg_create base "" ${CORE_PKG_VERSION} ${STAGE_CHROOT_DIR}
	core_pkg_create base-nanobsd "" ${CORE_PKG_VERSION} ${STAGE_CHROOT_DIR}
	core_pkg_create default-config "" ${CORE_PKG_VERSION} ${STAGE_CHROOT_DIR}

	local DEFAULTCONF=${STAGE_CHROOT_DIR}/conf.default/config.xml

	# Save current WAN and LAN if value
	local _old_wan_if=$(xml sel -t -v "${XML_ROOTOBJ}/interfaces/wan/if" ${DEFAULTCONF})
	local _old_lan_if=$(xml sel -t -v "${XML_ROOTOBJ}/interfaces/lan/if" ${DEFAULTCONF})

	# Change default interface names to match vmware driver
	xml ed -P -L -u "${XML_ROOTOBJ}/interfaces/wan/if" -v "vmx0" ${DEFAULTCONF}
	xml ed -P -L -u "${XML_ROOTOBJ}/interfaces/lan/if" -v "vmx1" ${DEFAULTCONF}
	core_pkg_create default-config "vmware" ${CORE_PKG_VERSION} ${STAGE_CHROOT_DIR}

	# Restore default values to be used by serial package
	xml ed -P -L -u "${XML_ROOTOBJ}/interfaces/wan/if" -v "${_old_wan_if}" ${DEFAULTCONF}
	xml ed -P -L -u "${XML_ROOTOBJ}/interfaces/lan/if" -v "${_old_lan_if}" ${DEFAULTCONF}

	# Activate serial console in config.xml
	xml ed -L -P -d "${XML_ROOTOBJ}/system/enableserial" ${DEFAULTCONF}
	xml ed -P -s "${XML_ROOTOBJ}/system" -t elem -n "enableserial" \
		${DEFAULTCONF} > ${DEFAULTCONF}.tmp
	xml fo -t ${DEFAULTCONF}.tmp > ${DEFAULTCONF}
	rm -f ${DEFAULTCONF}.tmp

	echo force > ${STAGE_CHROOT_DIR}/cf/conf/enableserial_force

	core_pkg_create default-config-serial "" ${CORE_PKG_VERSION} ${STAGE_CHROOT_DIR}

	rm -f ${STAGE_CHROOT_DIR}/cf/conf/enableserial_force
	rm -f ${STAGE_CHROOT_DIR}/cf/conf/config.xml

	# Make sure pkg is present
	pkg_bootstrap ${STAGE_CHROOT_DIR}

	echo "Done!"
}

create_final_staging_area() {
	if [ -z "${FINAL_CHROOT_DIR}" ]; then
		echo ">>> ERROR: FINAL_CHROOT_DIR is not set, cannot continue!" | tee -a ${LOGFILE}
		print_error_pfS
	fi

	if [ -d "${FINAL_CHROOT_DIR}" ]; then
		echo -n ">>> Previous ${FINAL_CHROOT_DIR} detected cleaning up..." | tee -a ${LOGFILE}
		chflags -R noschg ${FINAL_CHROOT_DIR} 2>&1 1>/dev/null
		rm -rf ${FINAL_CHROOT_DIR}/* 2>&1 1>/dev/null
		echo "Done." | tee -a ${LOGFILE}
	fi

	echo ">>> Preparing Final image staging area: $(LC_ALL=C date)" 2>&1 | tee -a ${LOGFILE}
	echo ">>> Cloning ${STAGE_CHROOT_DIR} to ${FINAL_CHROOT_DIR}" 2>&1 | tee -a ${LOGFILE}
	clone_directory_contents ${STAGE_CHROOT_DIR} ${FINAL_CHROOT_DIR}

	if [ ! -f $FINAL_CHROOT_DIR/sbin/init ]; then
		echo ">>> ERROR: Something went wrong during cloning -- Please verify!" 2>&1 | tee -a ${LOGFILE}
		print_error_pfS
	fi
}

customize_stagearea_for_image() {
	local _image_type="$1"
	local _default_config="" # filled with $2 below
	local _image_variant="$3"

	if [ -n "$2" ]; then
		_default_config="$2"
	elif [ "${_image_type}" = "nanobsd" -o \
	     "${_image_type}" = "memstickserial" -o \
	     "${_image_type}" = "memstickadi" ]; then
		_default_config="default-config-serial"
	elif [ "${_image_type}" = "ova" ]; then
		_default_config="default-config-vmware"
	else
		_default_config="default-config"
	fi

	# Prepare final stage area
	create_final_staging_area

	pkg_chroot_add ${FINAL_CHROOT_DIR} rc
	pkg_chroot_add ${FINAL_CHROOT_DIR} repo

	if [ "${_image_type}" = "nanobsd" -o \
	     "${_image_type}" = "nanobsd-vga" ]; then

		mkdir -p ${FINAL_CHROOT_DIR}/root/var/db \
			 ${FINAL_CHROOT_DIR}/root/var/cache \
			 ${FINAL_CHROOT_DIR}/var/db/pkg \
			 ${FINAL_CHROOT_DIR}/var/cache/pkg
		mv -f ${FINAL_CHROOT_DIR}/var/db/pkg ${FINAL_CHROOT_DIR}/root/var/db
		mv -f ${FINAL_CHROOT_DIR}/var/cache/pkg ${FINAL_CHROOT_DIR}/root/var/cache
		ln -sf ../../root/var/db/pkg ${FINAL_CHROOT_DIR}/var/db/pkg
		ln -sf ../../root/var/cache/pkg ${FINAL_CHROOT_DIR}/var/cache/pkg

		pkg_chroot_add ${FINAL_CHROOT_DIR} base-nanobsd
	else
		pkg_chroot_add ${FINAL_CHROOT_DIR} base
	fi

	if [ "${_image_type}" = "iso" -o \
	     "${_image_type}" = "memstick" -o \
	     "${_image_type}" = "memstickserial" -o \
	     "${_image_type}" = "memstickadi" ]; then
		mkdir -p ${FINAL_CHROOT_DIR}/pkgs
		cp ${CORE_PKG_ALL_PATH}/*default-config*.txz ${FINAL_CHROOT_DIR}/pkgs
	fi

	pkg_chroot_add ${FINAL_CHROOT_DIR} ${_default_config}

	# XXX: Workaround to avoid pkg to complain regarding release
	#      repo on first boot since packages are installed from
	#      staging server during build phase
	if [ -n "${USE_PKG_REPO_STAGING}" ]; then
		_read_cmd="select value from repodata where key='packagesite'"
		if [ -n "${_IS_RELEASE}" ]; then
			local _tgt_server="${PKG_REPO_SERVER_RELEASE}"
		else
			local _tgt_server="${PKG_REPO_SERVER_DEVEL}"
		fi
		for _db in ${FINAL_CHROOT_DIR}/var/db/pkg/repo-*sqlite; do
			_cur=$(/usr/local/bin/sqlite3 ${_db} "${_read_cmd}")
			_new=$(echo "${_cur}" | sed -e "s,^${PKG_REPO_SERVER_STAGING},${_tgt_server},")
			/usr/local/bin/sqlite3 ${_db} "update repodata set value='${_new}' where key='packagesite'"
		done
	fi

	if [ -n "$_image_variant" -a \
	    -d ${BUILDER_TOOLS}/templates/custom_logos/${_image_variant} ]; then
		mkdir -p ${FINAL_CHROOT_DIR}/usr/local/share/${PRODUCT_NAME}/custom_logos
		cp -f \
			${BUILDER_TOOLS}/templates/custom_logos/${_image_variant}/*.png \
			${FINAL_CHROOT_DIR}/usr/local/share/${PRODUCT_NAME}/custom_logos
	fi
}

create_distribution_tarball() {
	mkdir -p ${INSTALLER_CHROOT_DIR}/usr/freebsd-dist

	tar -C ${FINAL_CHROOT_DIR} --exclude ./install --exclude ./pkgs \
		-cJf ${INSTALLER_CHROOT_DIR}/usr/freebsd-dist/base.txz .

	(cd ${INSTALLER_CHROOT_DIR}/usr/freebsd-dist && \
		sh ${FREEBSD_SRC_DIR}/release/scripts/make-manifest.sh base.txz) \
		> ${INSTALLER_CHROOT_DIR}/usr/freebsd-dist/MANIFEST
}

create_iso_image() {
	LOGFILE=${BUILDER_LOGS}/isoimage.${TARGET}
	echo ">>> Building bootable ISO image for ${TARGET}" | tee -a ${LOGFILE}
	if [ -z "${DEFAULT_KERNEL}" ]; then
		echo ">>> ERROR: Could not identify DEFAULT_KERNEL to install on image!" | tee -a ${LOGFILE}
		print_error_pfS
	fi

	mkdir -p $(dirname ${ISOPATH})

	customize_stagearea_for_image "iso"
	install_default_kernel ${DEFAULT_KERNEL}

	echo cdrom > $FINAL_CHROOT_DIR/etc/platform

	FSLABEL=$(echo ${PRODUCT_NAME} | tr '[:lower:]' '[:upper:]')
	echo "/dev/iso9660/${FSLABEL} / cd9660 ro 0 0" > ${FINAL_CHROOT_DIR}/etc/fstab

	# This check is for supporting create memstick/ova images
	echo -n ">>> Running command: script -aq $LOGFILE makefs -t cd9660 -o bootimage=\"i386;${FINAL_CHROOT_DIR}/boot/cdboot \"-o no-emul-boot -o rockridge " | tee -a ${LOGFILE}
	echo "-o label=${FSLABEL} -o publisher=\"${PRODUCT_NAME} project.\" $ISOPATH ${FINAL_CHROOT_DIR}" | tee -a ${LOGFILE}

	create_distribution_tarball

	# Remove /rescue from iso since cd9660 cannot deal with hardlinks
	rm -rf ${FINAL_CHROOT_DIR}/rescue

	makefs -t cd9660 -o bootimage="i386;${FINAL_CHROOT_DIR}/boot/cdboot" -o no-emul-boot -o rockridge \
		-o label=${FSLABEL} -o publisher="${PRODUCT_NAME} project." $ISOPATH ${FINAL_CHROOT_DIR} 2>&1 >> ${LOGFILE}
	if [ $? -ne 0 -o ! -f $ISOPATH ]; then
		if [ -f ${ISOPATH} ]; then
			rm -f $ISOPATH
		fi
		echo ">>> ERROR: Something wrong happened during ISO image creation. STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi
	gzip -qf $ISOPATH &
	_bg_pids="${_bg_pids}${_bg_pids:+ }$!"

	echo ">>> ISO created: $(LC_ALL=C date)" | tee -a ${LOGFILE}
}

create_memstick_image() {
	local _variant="$1"

	LOGFILE=${BUILDER_LOGS}/memstick.${TARGET}
	if [ "${MEMSTICKPATH}" = "" ]; then
		echo ">>> MEMSTICKPATH is empty skipping generation of memstick image!" | tee -a ${LOGFILE}
		return
	fi

	mkdir -p $(dirname ${MEMSTICKPATH})

	local _image_path=${MEMSTICKPATH}
	if [ -n "${_variant}" ]; then
		_image_path=$(echo "$_image_path" | \
			sed "s/-memstick-/-memstick-${_variant}-/")
		VARIANTIMAGES="${VARIANTIMAGES}${VARIANTIMAGES:+ }${_image_path}"
	fi

	customize_stagearea_for_image "memstick" "" $_variant
	install_default_kernel ${DEFAULT_KERNEL}

	echo ">>> Creating memstick to ${_image_path}." 2>&1 | tee -a ${LOGFILE}
	echo "kern.cam.boot_delay=10000" >> ${FINAL_CHROOT_DIR}/boot/loader.conf.local

	BOOTCONF=${INSTALLER_CHROOT_DIR}/boot.config
	LOADERCONF=${INSTALLER_CHROOT_DIR}/boot/loader.conf

	rm -f ${LOADERCONF} ${BOOTCONF} >/dev/null 2>&1

	touch ${FINAL_CHROOT_DIR}/boot/loader.conf

	create_distribution_tarball

	sh ${FREEBSD_SRC_DIR}/release/${TARGET}/make-memstick.sh \
		${INSTALLER_CHROOT_DIR} \
		${_image_path}

	gzip -qf $_image_path &
	_bg_pids="${_bg_pids}${_bg_pids:+ }$!"

	echo ">>> MEMSTICK created: $(LC_ALL=C date)" | tee -a ${LOGFILE}
}

create_memstick_serial_image() {
	LOGFILE=${BUILDER_LOGS}/memstickserial.${TARGET}
	if [ "${MEMSTICKSERIALPATH}" = "" ]; then
		echo ">>> MEMSTICKSERIALPATH is empty skipping generation of memstick image!" | tee -a ${LOGFILE}
		return
	fi

	mkdir -p $(dirname ${MEMSTICKSERIALPATH})

	customize_stagearea_for_image "memstickserial"
	install_default_kernel ${DEFAULT_KERNEL}

	echo ">>> Creating serial memstick to ${MEMSTICKSERIALPATH}." 2>&1 | tee -a ${LOGFILE}
	echo "kern.cam.boot_delay=10000" >> ${FINAL_CHROOT_DIR}/boot/loader.conf.local

	BOOTCONF=${INSTALLER_CHROOT_DIR}/boot.config
	LOADERCONF=${INSTALLER_CHROOT_DIR}/boot/loader.conf

	echo ">>> Activating serial console..." 2>&1 | tee -a ${LOGFILE}
	echo "-S115200 -D" > ${BOOTCONF}

	# Activate serial console+video console in loader.conf
	echo 'boot_multicons="YES"' >  ${LOADERCONF}
	echo 'boot_serial="YES"' >> ${LOADERCONF}
	echo 'console="comconsole,vidconsole"' >> ${LOADERCONF}
	echo 'comconsole_speed="115200"' >> ${LOADERCONF}

	cat ${BOOTCONF} >> ${FINAL_CHROOT_DIR}/boot.config
	cat ${LOADERCONF} >> ${FINAL_CHROOT_DIR}/boot/loader.conf

	create_distribution_tarball

	sh ${FREEBSD_SRC_DIR}/release/${TARGET}/make-memstick.sh \
		${INSTALLER_CHROOT_DIR} \
		${MEMSTICKSERIALPATH}

	gzip -qf $MEMSTICKSERIALPATH &
	_bg_pids="${_bg_pids}${_bg_pids:+ }$!"

	echo ">>> MEMSTICKSERIAL created: $(LC_ALL=C date)" | tee -a ${LOGFILE}
}

create_memstick_adi_image() {
	LOGFILE=${BUILDER_LOGS}/memstickadi.${TARGET}
	if [ "${MEMSTICKADIPATH}" = "" ]; then
		echo ">>> MEMSTICKADIPATH is empty skipping generation of memstick image!" | tee -a ${LOGFILE}
		return
	fi

	mkdir -p $(dirname ${MEMSTICKADIPATH})

	customize_stagearea_for_image "memstickadi"
	install_default_kernel ${DEFAULT_KERNEL}

	echo ">>> Creating serial memstick to ${MEMSTICKADIPATH}." 2>&1 | tee -a ${LOGFILE}
	echo "kern.cam.boot_delay=10000" >> ${FINAL_CHROOT_DIR}/boot/loader.conf.local

	BOOTCONF=${INSTALLER_CHROOT_DIR}/boot.config
	LOADERCONF=${INSTALLER_CHROOT_DIR}/boot/loader.conf

	echo ">>> Activating serial console..." 2>&1 | tee -a ${LOGFILE}
	echo "-S115200 -h" > ${BOOTCONF}

	# Activate serial console+video console in loader.conf
	echo 'boot_serial="YES"' > ${LOADERCONF}
	echo 'console="comconsole"' >> ${LOADERCONF}
	echo 'comconsole_speed="115200"' >> ${LOADERCONF}
	echo 'comconsole_port="0x2F8"' >> ${LOADERCONF}
	echo 'hint.uart.0.flags="0x00"' >> ${LOADERCONF}
	echo 'hint.uart.1.flags="0x10"' >> ${LOADERCONF}

	cat ${BOOTCONF} >> ${FINAL_CHROOT_DIR}/boot.config
	cat ${LOADERCONF} >> ${FINAL_CHROOT_DIR}/boot/loader.conf

	create_distribution_tarball

	sh ${FREEBSD_SRC_DIR}/release/${TARGET}/make-memstick.sh \
		${INSTALLER_CHROOT_DIR} \
		${MEMSTICKADIPATH}

	gzip -qf $MEMSTICKADIPATH &
	_bg_pids="${_bg_pids}${_bg_pids:+ }$!"

	echo ">>> MEMSTICKADI created: $(LC_ALL=C date)" | tee -a ${LOGFILE}
}

# Create pkg conf on desired place with desired arch/branch
setup_pkg_repo() {
	if [ -z "${4}" ]; then
		return
	fi

	local _template="${1}"
	local _target="${2}"
	local _arch="${3}"
	local _target_arch="${4}"
	local _staging="${5}"

	if [ -z "${_template}" -o ! -f "${_template}" ]; then
		echo ">>> ERROR: It was not possible to find pkg conf template ${_template}"
		print_error_pfS
	fi

	if [ -n "${_staging}" -a -n "${USE_PKG_REPO_STAGING}" ]; then
		local _pkg_repo_server_devel=${PKG_REPO_SERVER_STAGING}
		local _pkg_repo_branch_devel=${PKG_REPO_BRANCH_STAGING}
		local _pkg_repo_server_release=${PKG_REPO_SERVER_STAGING}
		local _pkg_repo_branch_release=${PKG_REPO_BRANCH_STAGING}
	else
		local _pkg_repo_server_devel=${PKG_REPO_SERVER_DEVEL}
		local _pkg_repo_branch_devel=${PKG_REPO_BRANCH_DEVEL}
		local _pkg_repo_server_release=${PKG_REPO_SERVER_RELEASE}
		local _pkg_repo_branch_release=${PKG_REPO_BRANCH_RELEASE}
	fi

	mkdir -p $(dirname ${_target}) >/dev/null 2>&1

	sed \
		-e "s/%%ARCH%%/${_target_arch}/" \
		-e "s/%%PKG_REPO_BRANCH_DEVEL%%/${_pkg_repo_branch_devel}/g" \
		-e "s/%%PKG_REPO_BRANCH_RELEASE%%/${_pkg_repo_branch_release}/g" \
		-e "s,%%PKG_REPO_SERVER_DEVEL%%,${_pkg_repo_server_devel},g" \
		-e "s,%%PKG_REPO_SERVER_RELEASE%%,${_pkg_repo_server_release},g" \
		-e "s,%%POUDRIERE_PORTS_NAME%%,${POUDRIERE_PORTS_NAME},g" \
		-e "s/%%PRODUCT_NAME%%/${PRODUCT_NAME}/g" \
		${_template} \
		> ${_target}
}

# This routine ensures any ports / binaries that the builder
# system needs are on disk and ready for execution.
builder_setup() {
	# If Product-builder is already installed, just leave
	if pkg info -e -q ${PRODUCT_NAME}-builder; then
		return
	fi

	if [ ! -f ${PKG_REPO_PATH} ]; then
		[ -d $(dirname ${PKG_REPO_PATH}) ] \
			|| mkdir -p $(dirname ${PKG_REPO_PATH})

		update_freebsd_sources

		local _arch=$(uname -m)
		setup_pkg_repo \
			${PKG_REPO_DEFAULT} \
			${PKG_REPO_PATH} \
			${_arch} \
			${_arch} \
			"staging"

		# Use fingerprint keys from repo
		sed -i '' -e "/fingerprints:/ s,\"/,\"${BUILDER_ROOT}/src/," \
			${PKG_REPO_PATH}
	fi

	pkg install ${PRODUCT_NAME}-builder
}

# Updates FreeBSD sources
update_freebsd_sources() {
	if [ "${1}" = "full" ]; then
		local _full=1
		local _clone_params=""
	else
		local _full=0
		local _clone_params="--depth 1 --single-branch"
	fi

	if [ ! -d "${FREEBSD_SRC_DIR}" ]; then
		mkdir -p ${FREEBSD_SRC_DIR}
	fi

	if [ -n "${NO_BUILDWORLD}" -a -n "${NO_BUILDKERNEL}" ]; then
		echo ">>> NO_BUILDWORLD and NO_BUILDKERNEL set, skipping update of freebsd sources" | tee -a ${LOGFILE}
		return
	fi

	echo -n ">>> Obtaining FreeBSD sources ${FREEBSD_BRANCH}..."
	local _FREEBSD_BRANCH=${FREEBSD_BRANCH:-"devel"}
	local _CLONE=1

	if [ -d "${FREEBSD_SRC_DIR}/.git" ]; then
		CUR_BRANCH=$(cd ${FREEBSD_SRC_DIR} && git branch | grep '^\*' | cut -d' ' -f2)
		if [ ${_full} -eq 0 -a "${CUR_BRANCH}" = "${_FREEBSD_BRANCH}" ]; then
			_CLONE=0
			( cd ${FREEBSD_SRC_DIR} && git clean -fd; git fetch origin; git reset --hard origin/${_FREEBSD_BRANCH} ) 2>&1 | grep -C3 -i -E 'error|fatal'
		else
			rm -rf ${FREEBSD_SRC_DIR}
		fi
	fi

	if [ ${_CLONE} -eq 1 ]; then
		( git clone --branch ${_FREEBSD_BRANCH} ${_clone_params} ${FREEBSD_REPO_BASE} ${FREEBSD_SRC_DIR} ) 2>&1 | grep -C3 -i -E 'error|fatal'
	fi

	if [ ! -d "${FREEBSD_SRC_DIR}/.git" ]; then
		echo ">>> ERROR: It was not possible to clone FreeBSD src repo"
		print_error_pfS
	fi

	if [ -n "${GIT_FREEBSD_COSHA1}" ]; then
		( cd ${FREEBSD_SRC_DIR} && git checkout ${GIT_FREEBSD_COSHA1} ) 2>&1 | grep -C3 -i -E 'error|fatal'
	fi
	echo "Done!"
}

pkg_chroot() {
	local _root="${1}"
	shift

	if [ $# -eq 0 ]; then
		return -1
	fi

	if [ -z "${_root}" -o "${_root}" = "/" -o ! -d "${_root}" ]; then
		return -1
	fi

	mkdir -p \
		${SCRATCHDIR}/pkg_cache \
		${_root}/var/cache/pkg \
		${_root}/dev

	/sbin/mount -t nullfs ${SCRATCHDIR}/pkg_cache ${_root}/var/cache/pkg
	/sbin/mount -t devfs devfs ${_root}/dev
	cp -f /etc/resolv.conf ${_root}/etc/resolv.conf
	touch ${BUILDER_LOGS}/install_pkg_install_ports.txt
	script -aq ${BUILDER_LOGS}/install_pkg_install_ports.txt pkg -c ${_root} $@ >/dev/null 2>&1
	local result=$?
	rm -f ${_root}/etc/resolv.conf
	/sbin/umount -f ${_root}/dev
	/sbin/umount -f ${_root}/var/cache/pkg

	return $result
}


pkg_chroot_add() {
	if [ -z "${1}" -o -z "${2}" ]; then
		return 1
	fi

	local _target="${1}"
	local _pkg="$(get_pkg_name ${2}).txz"

	if [ ! -d "${_target}" ]; then
		echo ">>> ERROR: Target dir ${_target} not found"
		print_error_pfS
	fi

	if [ ! -f ${CORE_PKG_ALL_PATH}/${_pkg} ]; then
		echo ">>> ERROR: Package ${_pkg} not found"
		print_error_pfS
	fi

	cp ${CORE_PKG_ALL_PATH}/${_pkg} ${_target}
	pkg_chroot ${_target} add /${_pkg}
	rm -f ${_target}/${_pkg}
}

pkg_bootstrap() {
	local _root=${1:-"${STAGE_CHROOT_DIR}"}

	setup_pkg_repo \
		${PKG_REPO_DEFAULT} \
		${_root}${PKG_REPO_PATH} \
		${TARGET} \
		${TARGET_ARCH} \
		"staging"

	pkg_chroot ${_root} bootstrap -f
}

# This routine assists with installing various
# freebsd ports files into the pfsense-fs staging
# area.
install_pkg_install_ports() {
	local MAIN_PKG="${1}"

	if [ -z "${MAIN_PKG}" ]; then
		MAIN_PKG=${PRODUCT_NAME}
	fi

	echo ">>> Installing pkg repository in chroot (${STAGE_CHROOT_DIR})..."

	[ -d ${STAGE_CHROOT_DIR}/var/cache/pkg ] || \
		mkdir -p ${STAGE_CHROOT_DIR}/var/cache/pkg

	[ -d ${SCRATCHDIR}/pkg_cache ] || \
		mkdir -p ${SCRATCHDIR}/pkg_cache

	echo -n ">>> Installing built ports (packages) in chroot (${STAGE_CHROOT_DIR})... "
	# First mark all packages as automatically installed
	pkg_chroot ${STAGE_CHROOT_DIR} set -A 1 -a
	# Install all necessary packages
	if ! pkg_chroot ${STAGE_CHROOT_DIR} install ${MAIN_PKG} ${custom_package_list}; then
		echo "Failed!"
		print_error_pfS
	fi
	# Make sure required packages are set as non-automatic
	pkg_chroot ${STAGE_CHROOT_DIR} set -A 0 pkg ${MAIN_PKG} ${custom_package_list}
	# Remove unnecessary packages
	pkg_chroot ${STAGE_CHROOT_DIR} autoremove
	echo "Done!"
}

staginareas_clean_each_run() {
	echo -n ">>> Cleaning build directories: "
	if [ -d "${FINAL_CHROOT_DIR}" ]; then
		BASENAME=$(basename ${FINAL_CHROOT_DIR})
		echo -n "$BASENAME "
		chflags -R noschg ${FINAL_CHROOT_DIR} 2>&1 >/dev/null
		rm -rf ${FINAL_CHROOT_DIR}/* 2>/dev/null
	fi
	echo "Done!"
}

# Imported from FreeSBIE
buildkernel() {
	if [ -n "${NO_BUILDKERNEL}" ]; then
		echo ">>> NO_BUILDKERNEL set, skipping build" | tee -a ${LOGFILE}
		return
	fi

	if [ -z "${KERNCONF}" ]; then
		echo ">>> ERROR: No kernel configuration defined probably this is not what you want! STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi

	if [ -n "${KERNELCONF}" ]; then
		export KERNCONFDIR=$(dirname ${KERNELCONF})
		export KERNCONF=$(basename ${KERNELCONF})
	fi

	echo ">>> KERNCONFDIR: ${KERNCONFDIR}"
	echo ">>> ARCH:        ${TARGET_ARCH}"

	makeargs="${MAKEJ}"
	echo ">>> Builder is running the command: script -aq $LOGFILE make $makeargs buildkernel KERNCONF=${KERNCONF}" | tee -a $LOGFILE
	(script -q $LOGFILE make -C ${FREEBSD_SRC_DIR} $makeargs buildkernel KERNCONF=${KERNCONF} || print_error_pfS;) | egrep '^>>>'
}

# Imported from FreeSBIE
installkernel() {
	local _destdir=${1:-${KERNEL_DESTDIR}}

	if [ -z "${KERNCONF}" ]; then
		echo ">>> ERROR: No kernel configuration defined probably this is not what you want! STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi

	if [ -n "${KERNELCONF}" ]; then
		export KERNCONFDIR=$(dirname ${KERNELCONF})
		export KERNCONF=$(basename ${KERNELCONF})
	fi

	mkdir -p ${STAGE_CHROOT_DIR}/boot
	makeargs="${MAKEJ} DESTDIR=${_destdir}"
	echo ">>> Builder is running the command: script -aq $LOGFILE make ${makeargs} installkernel KERNCONF=${KERNCONF}"  | tee -a $LOGFILE
	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR} ${makeargs} installkernel KERNCONF=${KERNCONF} || print_error_pfS;) | egrep '^>>>'
	gzip -f9 ${_destdir}/boot/kernel/kernel
}

# Launch is ran first to setup a few variables that we need
# Imported from FreeSBIE
launch() {
	if [ "$(id -u)" != "0" ]; then
		echo "Sorry, this must be done as root."
	fi

	echo ">>> Operation $0 has started at $(date)"
}

finish() {
	echo ">>> Operation $0 has ended at $(date)"
}

pkg_repo_rsync() {
	local _repo_path_param="${1}"
	local _ignore_final_rsync="${2}"

	if [ -z "${_repo_path_param}" -o ! -d "${_repo_path_param}" ]; then
		return
	fi

	if [ -n "${SKIP_FINAL_RSYNC}" ]; then
		_ignore_final_rsync="1"
	fi

	# Sanitize path
	_repo_path=$(realpath ${_repo_path_param})

	local _repo_dir=$(dirname ${_repo_path})
	local _repo_base=$(basename ${_repo_path})

	# Add ./ it's an rsync trick to make it chdir to directory before sending it
	_repo_path="${_repo_dir}/./${_repo_base}"

	if [ -z "${LOGFILE}" ]; then
		local _logfile="/dev/null"
	else
		local _logfile="${LOGFILE}"
	fi

	if [ -n "${PKG_REPO_SIGNING_COMMAND}" ]; then

		# Detect poudriere directory structure
		if [ -L "${_repo_path}/.latest" ]; then
			local _real_repo_path=$(readlink -f ${_repo_path}/.latest)
		else
			local _real_repo_path=${_repo_path}
		fi

		echo -n ">>> Signing repository... " | tee -a ${_logfile}
		############ ATTENTION ##############
		#
		# For some reason pkg-repo fail without / in the end of directory name
		# so removing it will break command
		#
		# https://github.com/freebsd/pkg/issues/1364
		#
		if script -aq ${_logfile} pkg repo ${_real_repo_path}/ \
		    signing_command: ${PKG_REPO_SIGNING_COMMAND} >/dev/null 2>&1; then
			echo "Done!" | tee -a ${_logfile}
		else
			echo "Failed!" | tee -a ${_logfile}
			echo ">>> ERROR: An error occurred trying to sign repo"
			print_error_pfS
		fi

		local _pkgfile="${_repo_path}/Latest/pkg.txz"
		if [ -e ${_pkgfile} ]; then
			echo -n ">>> Signing Latest/pkg.txz for bootstraping... " | tee -a ${_logfile}

			if sha256 -q ${_pkgfile} | ${PKG_REPO_SIGNING_COMMAND} \
			    > ${_pkgfile}.sig 2>/dev/null; then
				echo "Done!" | tee -a ${_logfile}
			else
				echo "Failed!" | tee -a ${_logfile}
				echo ">>> ERROR: An error occurred trying to sign Latest/pkg.txz"
				print_error_pfS
			fi
		fi
	fi

	if [ -n "${DO_NOT_UPLOAD}" ]; then
		return
	fi

	# Make sure destination directory exist
	ssh -p ${PKG_RSYNC_SSH_PORT} \
		${PKG_RSYNC_USERNAME}@${PKG_RSYNC_HOSTNAME} \
		"mkdir -p ${PKG_RSYNC_DESTDIR}"

	echo -n ">>> Sending updated repository to ${PKG_RSYNC_HOSTNAME}... " | tee -a ${_logfile}
	if script -aq ${_logfile} rsync -Have "ssh -p ${PKG_RSYNC_SSH_PORT}" \
		--timeout=60 --delete-delay ${_repo_path} \
		${PKG_RSYNC_USERNAME}@${PKG_RSYNC_HOSTNAME}:${PKG_RSYNC_DESTDIR} >/dev/null 2>&1
	then
		echo "Done!" | tee -a ${_logfile}
	else
		echo "Failed!" | tee -a ${_logfile}
		echo ">>> ERROR: An error occurred sending repo to remote hostname"
		print_error_pfS
	fi

	if [ -z "${USE_PKG_REPO_STAGING}" -o -n "${_ignore_final_rsync}" ]; then
		return
	fi

	if [ -n "${_IS_RELEASE}" -o "${_repo_path_param}" = "${CORE_PKG_PATH}" ]; then
		# Send .real* directories first to prevent having a broken repo while transfer happens
		local _cmd="rsync -Have \"ssh -p ${PKG_FINAL_RSYNC_SSH_PORT}\" \
			--timeout=60 ${PKG_RSYNC_DESTDIR}/./${_repo_base%%-core}* \
			--include=\"/*\" --include=\"*/.real*\" --include=\"*/.real*/***\" \
			--exclude=\"*\" \
			${PKG_FINAL_RSYNC_USERNAME}@${PKG_FINAL_RSYNC_HOSTNAME}:${PKG_FINAL_RSYNC_DESTDIR}"

		echo -n ">>> Sending updated packages to ${PKG_FINAL_RSYNC_HOSTNAME}... " | tee -a ${_logfile}
		if script -aq ${_logfile} ssh -p ${PKG_RSYNC_SSH_PORT} \
			${PKG_RSYNC_USERNAME}@${PKG_RSYNC_HOSTNAME} ${_cmd} >/dev/null 2>&1; then
			echo "Done!" | tee -a ${_logfile}
		else
			echo "Failed!" | tee -a ${_logfile}
			echo ">>> ERROR: An error occurred sending repo to final hostname"
			print_error_pfS
		fi

		_cmd="rsync -Have \"ssh -p ${PKG_FINAL_RSYNC_SSH_PORT}\" \
			--timeout=60 --delete-delay ${PKG_RSYNC_DESTDIR}/./${_repo_base%%-core}* \
			${PKG_FINAL_RSYNC_USERNAME}@${PKG_FINAL_RSYNC_HOSTNAME}:${PKG_FINAL_RSYNC_DESTDIR}"

		echo -n ">>> Sending updated repositories metadata to ${PKG_FINAL_RSYNC_HOSTNAME}... " | tee -a ${_logfile}
		if script -aq ${_logfile} ssh -p ${PKG_RSYNC_SSH_PORT} \
			${PKG_RSYNC_USERNAME}@${PKG_RSYNC_HOSTNAME} ${_cmd} >/dev/null 2>&1; then
			echo "Done!" | tee -a ${_logfile}
		else
			echo "Failed!" | tee -a ${_logfile}
			echo ">>> ERROR: An error occurred sending repo to final hostname"
			print_error_pfS
		fi
	fi
}

poudriere_possible_archs() {
	local _arch=$(uname -m)
	local _archs=""

	# If host is amd64, we'll create both repos, and if possible armv6
	if [ "${_arch}" = "amd64" ]; then
		_archs="amd64.amd64"

		if [ -f /usr/local/bin/qemu-arm-static ]; then
			# Make sure binmiscctl is ok
			/usr/local/etc/rc.d/qemu_user_static forcestart >/dev/null 2>&1

			if binmiscctl lookup armv6 >/dev/null 2>&1; then
				_archs="${_archs} arm.armv6"
			fi
		fi
	fi

	if [ -n "${ARCH_LIST}" ]; then
		local _found=0
		for _desired_arch in ${ARCH_LIST}; do
			_found=0
			for _possible_arch in ${_archs}; do
				if [ "${_desired_arch}" = "${_possible_arch}" ]; then
					_found=1
					break
				fi
			done
			if [ ${_found} -eq 0 ]; then
				echo ">>> ERROR: Impossible to build for arch: ${_desired_arch}"
				print_error_pfS
			fi
		done
		_archs="${ARCH_LIST}"
	fi

	echo ${_archs}
}

poudriere_jail_name() {
	local _jail_arch="${1}"

	if [ -z "${_jail_arch}" ]; then
		return 1
	fi

	# Remove arch
	echo "${PRODUCT_NAME}_${POUDRIERE_BRANCH}_${_jail_arch##*.}"
}

poudriere_rename_ports() {
	if [ "${PRODUCT_NAME}" = "pfSense" ]; then
		return;
	fi

	LOGFILE=${BUILDER_LOGS}/poudriere.log

	local _ports_dir="/usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}"

	echo -n ">>> Renaming product ports on ${POUDRIERE_PORTS_NAME}... " | tee -a ${LOGFILE}
	for d in $(find ${_ports_dir} -depth 2 -type d -name '*pfSense*'); do
		local _pdir=$(dirname ${d})
		local _pname=$(echo $(basename ${d}) | sed "s,pfSense,${PRODUCT_NAME},")
		local _plist=""

		if [ -e ${_pdir}/${_pname} ]; then
			rm -rf ${_pdir}/${_pname}
		fi

		cp -r ${d} ${_pdir}/${_pname}

		if [ -f ${_pdir}/${_pname}/pkg-plist ]; then
			_plist=${_pdir}/${_pname}/pkg-plist
		fi

		sed -i '' -e "s,pfSense,${PRODUCT_NAME},g" \
			  -e "s,https://www.pfsense.org,${PRODUCT_URL},g" \
			  -e "/^MAINTAINER=/ s,^.*$,MAINTAINER=	${PRODUCT_EMAIL}," \
			${_pdir}/${_pname}/Makefile \
			${_pdir}/${_pname}/pkg-descr ${_plist}

		# PHP module is special
		if echo "${_pname}" | grep -q "^php[0-9]*-${PRODUCT_NAME}-module"; then
			local _product_capital=$(echo ${PRODUCT_NAME} | tr '[a-z]' '[A-Z]')
			sed -i '' -e "s,PHP_PFSENSE,PHP_${_product_capital},g" \
				  -e "s,PFSENSE_SHARED_LIBADD,${_product_capital}_SHARED_LIBADD,g" \
				  -e "s,pfSense,${PRODUCT_NAME},g" \
				  -e "s,${PRODUCT_NAME}\.c,pfSense.c,g" \
				${_pdir}/${_pname}/files/config.m4

			sed -i '' -e "s,COMPILE_DL_PFSENSE,COMPILE_DL_${_product_capital}," \
				  -e "s,pfSense_module_entry,${PRODUCT_NAME}_module_entry,g" \
				  -e "/ZEND_GET_MODULE/ s,pfSense,${PRODUCT_NAME}," \
				  -e "/PHP_PFSENSE_WORLD_EXTNAME/ s,pfSense,${PRODUCT_NAME}," \
				${_pdir}/${_pname}/files/pfSense.c \
				${_pdir}/${_pname}/files/php_pfSense.h
		fi

		if [ -d ${_pdir}/${_pname}/files ]; then
			for fd in $(find ${_pdir}/${_pname}/files -type d -name '*pfSense*'); do
				local _fddir=$(dirname ${fd})
				local _fdname=$(echo $(basename ${fd}) | sed "s,pfSense,${PRODUCT_NAME},")

				mv ${fd} ${_fddir}/${_fdname}
			done
		fi
	done
	echo "Done!" | tee -a ${LOGFILE}
}

poudriere_create_ports_tree() {
	LOGFILE=${BUILDER_LOGS}/poudriere.log

	if ! poudriere ports -l | grep -q -E "^${POUDRIERE_PORTS_NAME}[[:blank:]]"; then
		local _branch=""
		if [ -z "${POUDRIERE_PORTS_GIT_URL}" ]; then
			echo ">>> ERROR: POUDRIERE_PORTS_GIT_URL is not defined"
			print_error_pfS
		fi
		if [ -n "${POUDRIERE_PORTS_GIT_BRANCH}" ]; then
			_branch="-B ${POUDRIERE_PORTS_GIT_BRANCH}"
		fi
		echo -n ">>> Creating poudriere ports tree, it may take some time... " | tee -a ${LOGFILE}
		if ! script -aq ${LOGFILE} poudriere ports -c -p "${POUDRIERE_PORTS_NAME}" -m git -U ${POUDRIERE_PORTS_GIT_URL} ${_branch} >/dev/null 2>&1; then
			echo "" | tee -a ${LOGFILE}
			echo ">>> ERROR: Error creating poudriere ports tree, aborting..." | tee -a ${LOGFILE}
			print_error_pfS
		fi
		echo "Done!" | tee -a ${LOGFILE}
		poudriere_rename_ports
	fi
}

poudriere_init() {
	local _error=0
	local _archs=$(poudriere_possible_archs)

	LOGFILE=${BUILDER_LOGS}/poudriere.log

	# Sanity checks
	if [ -z "${ZFS_TANK}" ]; then
		echo ">>> ERROR: \$ZFS_TANK is empty" | tee -a ${LOGFILE}
		error=1
	fi

	if [ -z "${ZFS_ROOT}" ]; then
		echo ">>> ERROR: \$ZFS_ROOT is empty" | tee -a ${LOGFILE}
		error=1
	fi

	if [ -z "${POUDRIERE_PORTS_NAME}" ]; then
		echo ">>> ERROR: \$POUDRIERE_PORTS_NAME is empty" | tee -a ${LOGFILE}
		error=1
	fi

	if [ ${_error} -eq 1 ]; then
		print_error_pfS
	fi

	# Check if zpool exists
	if ! zpool list ${ZFS_TANK} >/dev/null 2>&1; then
		echo ">>> ERROR: ZFS tank ${ZFS_TANK} not found, please create it and try again..." | tee -a ${LOGFILE}
		print_error_pfS
	fi

	# Check if zfs rootfs exists
	if ! zfs list ${ZFS_TANK}${ZFS_ROOT} >/dev/null 2>&1; then
		echo -n ">>> Creating ZFS filesystem ${ZFS_TANK}${ZFS_ROOT}... "
		if zfs create -o atime=off -o mountpoint=/usr/local${ZFS_ROOT} \
		    ${ZFS_TANK}${ZFS_ROOT} >/dev/null 2>&1; then
			echo "Done!"
		else
			echo "Failed!"
			print_error_pfS
		fi
	fi

	# Make sure poudriere is installed
	if ! pkg info --quiet poudriere-devel; then
		echo ">>> Installing poudriere-devel..." | tee -a ${LOGFILE}
		if ! pkg install poudriere-devel >/dev/null 2>&1; then
			echo ">>> ERROR: poudriere-devel was not installed, aborting..." | tee -a ${LOGFILE}
			print_error_pfS
		fi
	fi

	# Create poudriere.conf
	if [ -z "${POUDRIERE_PORTS_GIT_URL}" ]; then
		echo ">>> ERROR: POUDRIERE_PORTS_GIT_URL is not defined"
		print_error_pfS
	fi
	echo ">>> Creating poudriere.conf" | tee -a ${LOGFILE}
	cat <<EOF >/usr/local/etc/poudriere.conf
ZPOOL=${ZFS_TANK}
ZROOTFS=${ZFS_ROOT}
RESOLV_CONF=/etc/resolv.conf
BASEFS=/usr/local/poudriere
USE_PORTLINT=no
USE_TMPFS=yes
NOLINUX=yes
DISTFILES_CACHE=/usr/ports/distfiles
CHECK_CHANGED_OPTIONS=yes
CHECK_CHANGED_DEPS=yes
ATOMIC_PACKAGE_REPOSITORY=yes
COMMIT_PACKAGES_ON_FAILURE=no
KEEP_OLD_PACKAGES=yes
KEEP_OLD_PACKAGES_COUNT=5
EOF

	# Create specific items conf
	[ ! -d /usr/local/etc/poudriere.d ] \
		&& mkdir -p /usr/local/etc/poudriere.d

	# Create DISTFILES_CACHE if it doesn't exist
	if [ ! -d /usr/ports/distfiles ]; then
		mkdir -p /usr/ports/distfiles
	fi

	# Remove old jails
	for jail_arch in ${_archs}; do
		jail_name=$(poudriere_jail_name ${jail_arch})

		if poudriere jail -i -j "${jail_name}" >/dev/null 2>&1; then
			echo ">>> Poudriere jail ${jail_name} already exists, deleting it..." | tee -a ${LOGFILE}
			poudriere jail -d -j "${jail_name}" >/dev/null 2>&1
		fi
	done

	# Remove old ports tree
	if poudriere ports -l | grep -q -E "^${POUDRIERE_PORTS_NAME}[[:blank:]]"; then
		echo ">>> Poudriere ports tree ${POUDRIERE_PORTS_NAME} already exists, deleting it..." | tee -a ${LOGFILE}
		poudriere ports -d -p "${POUDRIERE_PORTS_NAME}"
	fi

	local native_xtools=""
	# Now we are ready to create jails
	for jail_arch in ${_archs}; do
		jail_name=$(poudriere_jail_name ${jail_arch})

		if [ "${jail_arch}" = "arm.armv6" ]; then
			native_xtools="-x"
		else
			native_xtools=""
		fi

		echo -n ">>> Creating jail ${jail_name}, it may take some time... " | tee -a ${LOGFILE}
		# XXX: Change -m to git when it's available in poudriere
		if ! script -aq ${LOGFILE} poudriere jail -c -j "${jail_name}" -v ${FREEBSD_BRANCH} \
				-a ${jail_arch} -m git -U ${FREEBSD_REPO_BASE_POUDRIERE} ${native_xtools} >/dev/null 2>&1; then
			echo "" | tee -a ${LOGFILE}
			echo ">>> ERROR: Error creating jail ${jail_name}, aborting..." | tee -a ${LOGFILE}
			print_error_pfS
		fi
		echo "Done!" | tee -a ${LOGFILE}
	done

	poudriere_create_ports_tree

	echo ">>> Poudriere is now configured!" | tee -a ${LOGFILE}
}

poudriere_update_jails() {
	local _archs=$(poudriere_possible_archs)

	LOGFILE=${BUILDER_LOGS}/poudriere.log

	local native_xtools=""
	for jail_arch in ${_archs}; do
		jail_name=$(poudriere_jail_name ${jail_arch})

		local _create_or_update="-u"
		local _create_or_update_text="Updating"
		if ! poudriere jail -i -j "${jail_name}" >/dev/null 2>&1; then
			echo ">>> Poudriere jail ${jail_name} not found, creating..." | tee -a ${LOGFILE}
			_create_or_update="-c -v ${FREEBSD_BRANCH} -a ${jail_arch} -m git -U ${FREEBSD_REPO_BASE_POUDRIERE}"
			_create_or_update_text="Creating"
		fi

		if [ "${jail_arch}" = "arm.armv6" ]; then
			native_xtools="-x"
		else
			native_xtools=""
		fi

		echo -n ">>> ${_create_or_update_text} jail ${jail_name}, it may take some time... " | tee -a ${LOGFILE}
		if ! script -aq ${LOGFILE} poudriere jail ${_create_or_update} -j "${jail_name}" ${native_xtools} >/dev/null 2>&1; then
			echo "" | tee -a ${LOGFILE}
			echo ">>> ERROR: Error ${_create_or_update_text} jail ${jail_name}, aborting..." | tee -a ${LOGFILE}
			print_error_pfS
		fi
		echo "Done!" | tee -a ${LOGFILE}
	done
}

poudriere_update_ports() {
	LOGFILE=${BUILDER_LOGS}/poudriere.log

	# Create ports tree if necessary
	if ! poudriere ports -l | grep -q -E "^${POUDRIERE_PORTS_NAME}[[:blank:]]"; then
		poudriere_create_ports_tree
	else
		echo -n ">>> Resetting local changes on ports tree ${POUDRIERE_PORTS_NAME}... " | tee -a ${LOGFILE}
		script -aq ${LOGFILE} git -C "/usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}" reset --hard >/dev/null 2>&1
		script -aq ${LOGFILE} git -C "/usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}" clean -fd >/dev/null 2>&1
		echo "Done!" | tee -a ${LOGFILE}
		echo -n ">>> Updating ports tree ${POUDRIERE_PORTS_NAME}... " | tee -a ${LOGFILE}
		script -aq ${LOGFILE} poudriere ports -u -p "${POUDRIERE_PORTS_NAME}" >/dev/null 2>&1
		echo "Done!" | tee -a ${LOGFILE}
		poudriere_rename_ports
	fi
}

poudriere_bulk() {
	local _archs=$(poudriere_possible_archs)

	LOGFILE=${BUILDER_LOGS}/poudriere.log

	if [ -z "${DO_NOT_UPLOAD}" -a -z "${PKG_RSYNC_HOSTNAME}" ]; then
		echo ">>> ERROR: PKG_RSYNC_HOSTNAME is not set"
		print_error_pfS
	fi

	rm -f ${LOGFILE}

	poudriere_create_ports_tree

	[ -d /usr/local/etc/poudriere.d ] || \
		mkdir -p /usr/local/etc/poudriere.d

	if [ -f "${BUILDER_TOOLS}/conf/pfPorts/make.conf" ]; then
		cp -f "${BUILDER_TOOLS}/conf/pfPorts/make.conf" /usr/local/etc/poudriere.d/${POUDRIERE_PORTS_NAME}-make.conf
	fi

	# Change version of pfSense meta ports for snapshots
	if [ -z "${_IS_RELEASE}" ]; then
		local _meta_pkg_version="$(echo "${PRODUCT_VERSION}" | sed 's,DEVELOPMENT,ALPHA,')-${DATESTRING}"
		sed -i '' \
			-e "/^DISTVERSION/ s,^.*,DISTVERSION=	${_meta_pkg_version}," \
			-e "/^PORTREVISION=/d" \
			/usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}/security/${PRODUCT_NAME}/Makefile
	fi

	for jail_arch in ${_archs}; do
		jail_name=$(poudriere_jail_name ${jail_arch})

		if ! poudriere jail -i -j "${jail_name}" >/dev/null 2>&1; then
			echo ">>> Poudriere jail ${jail_name} not found, skipping..." | tee -a ${LOGFILE}
			continue
		fi

		if [ -f "${POUDRIERE_BULK}.${jail_arch}" ]; then
			_ref_bulk="${POUDRIERE_BULK}.${jail_arch}"
		else
			_ref_bulk="${POUDRIERE_BULK}"
		fi

		_bulk=${SCRATCHDIR}/poudriere_bulk.${POUDRIERE_BRANCH}
		sed -e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" ${_ref_bulk} > ${_bulk}

		if ! poudriere bulk -f ${_bulk} -j ${jail_name} -p ${POUDRIERE_PORTS_NAME}; then
			echo ">>> ERROR: Something went wrong..."
			print_error_pfS
		fi

		echo ">>> Cleaning up old packages from repo..."
		if ! poudriere pkgclean -f ${_bulk} -j ${jail_name} -p ${POUDRIERE_PORTS_NAME} -y; then
			echo ">>> ERROR: Something went wrong..."
			print_error_pfS
		fi

		pkg_repo_rsync "/usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME}"
	done
}

# This routine is called to write out to stdout
# a string. The string is appended to $SNAPSHOTSLOGFILE
# and we scp the log file to the builder host if
# needed for the real time logging functions.
snapshots_update_status() {
	if [ -z "$1" ]; then
		return
	fi
	if [ -z "${SNAPSHOTS}" -a -z "${POUDRIERE_SNAPSHOTS}" ]; then
		return
	fi
	echo "$*"
	echo "`date` -|- $*" >> $SNAPSHOTSLOGFILE
	if [ -z "${DO_NOT_UPLOAD}" -a -n "${SNAPSHOTS_RSYNCIP}" ]; then
		LU=$(cat $SNAPSHOTSLASTUPDATE 2>/dev/null)
		CT=$(date "+%H%M%S")
		# Only update every minute
		if [ "$LU" != "$CT" ]; then
			ssh ${SNAPSHOTS_RSYNCUSER}@${SNAPSHOTS_RSYNCIP} \
				"mkdir -p ${SNAPSHOTS_RSYNCLOGS}"
			scp -q $SNAPSHOTSLOGFILE \
				${SNAPSHOTS_RSYNCUSER}@${SNAPSHOTS_RSYNCIP}:${SNAPSHOTS_RSYNCLOGS}/build.log
			date "+%H%M%S" > $SNAPSHOTSLASTUPDATE
		fi
	fi
}

# Copy the current log file to $filename.old on
# the snapshot www server (real time logs)
snapshots_rotate_logfile() {
	if [ -z "${DO_NOT_UPLOAD}" -a -n "${SNAPSHOTS_RSYNCIP}" ]; then
		scp -q $SNAPSHOTSLOGFILE \
			${SNAPSHOTS_RSYNCUSER}@${SNAPSHOTS_RSYNCIP}:${SNAPSHOTS_RSYNCLOGS}/build.log.old
	fi

	# Cleanup log file
	rm -f $SNAPSHOTSLOGFILE;    touch $SNAPSHOTSLOGFILE
	rm -f $SNAPSHOTSLASTUPDATE; touch $SNAPSHOTSLASTUPDATE

}

create_sha256() {
	local _file="${1}"

	if [ ! -f "${_file}" ]; then
		return 1
	fi

	( \
		cd $(dirname ${_file}) && \
		sha256 $(basename ${_file}) > $(basename ${_file}).sha256 \
	)
}

snapshots_create_latest_symlink() {
	local _image="${1}"

	if [ -z "${_image}" ]; then
		return
	fi

	if [ -z "${TIMESTAMP_SUFFIX}" ]; then
		return
	fi

	if [ ! -f "${_image}" ]; then
		return
	fi

	local _symlink=$(echo ${_image} | sed "s,${TIMESTAMP_SUFFIX},-latest,")
	ln -sf $(basename ${_image}) ${_symlink}
	ln -sf $(basename ${_image}).sha256 ${_symlink}.sha256
}

snapshots_create_sha256() {
	local _img=""

	for _img in ${ISOPATH} ${MEMSTICKPATH} ${MEMSTICKSERIALPATH} ${MEMSTICKADIPATH} ${OVAPATH} ${VARIANTIMAGES}; do
		if [ -f "${_img}.gz" ]; then
			_img="${_img}.gz"
		fi
		if [ ! -f "${_img}" ]; then
			continue
		fi
		create_sha256 ${_img}
		snapshots_create_latest_symlink ${_img}
	done

	for NANOTYPE in nanobsd nanobsd-vga; do
		for FILESIZE in ${FLASH_SIZE}; do
			FILENAMEFULL="$(nanobsd_image_filename ${FILESIZE} ${NANOTYPE}).gz"

			if [ -f $IMAGES_FINAL_DIR/nanobsd/$FILENAMEFULL ]; then
				create_sha256 $IMAGES_FINAL_DIR/nanobsd/$FILENAMEFULL
			fi
		done
	done
}

snapshots_scp_files() {
	if [ -z "${RSYNC_COPY_ARGUMENTS}" ]; then
		RSYNC_COPY_ARGUMENTS="-ave ssh --timeout=60 --bwlimit=${RSYNCKBYTELIMIT}" #--bwlimit=50
	fi

	snapshots_update_status ">>> Copying core pkg repo to ${PKG_RSYNC_HOSTNAME}"
	pkg_repo_rsync "${CORE_PKG_PATH}"
	snapshots_update_status ">>> Finished copying core pkg repo"

	snapshots_update_status ">>> Copying files to ${RSYNCIP}"

	# Ensure directory(s) are available
	ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCPATH}/installer"
	ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCPATH}/nanobsd"
	if [ -d $IMAGES_FINAL_DIR/virtualization ]; then
		ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCPATH}/virtualization"
	fi
	# ensure permissions are correct for r+w
	ssh ${RSYNCUSER}@${RSYNCIP} "chmod -R ug+rw ${RSYNCPATH}/."
	rsync $RSYNC_COPY_ARGUMENTS $IMAGES_FINAL_DIR/installer/* \
		${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/installer/
	rsync $RSYNC_COPY_ARGUMENTS $IMAGES_FINAL_DIR/nanobsd/* \
		${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/nanobsd/
	if [ -d $IMAGES_FINAL_DIR/virtualization ]; then
		rsync $RSYNC_COPY_ARGUMENTS $IMAGES_FINAL_DIR/virtualization/* \
			${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/virtualization/
	fi

	snapshots_update_status ">>> Finished copying files."
}
