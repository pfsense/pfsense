#!/bin/sh
#
# builder_common.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
	ln -sf .latest/meta.conf ${CORE_PKG_PATH}/meta.conf
	ln -sf .latest/meta.txz ${CORE_PKG_PATH}/meta.txz
	ln -sf .latest/packagesite.txz ${CORE_PKG_PATH}/packagesite.txz
}

# Create core pkg (base, kernel)
core_pkg_create() {
	local _template="${1}"
	local _flavor="${2}"
	local _version="${3}"
	local _root="${4}"
	local _findroot="${5}"
	local _filter="${6}"

	local _template_path=${BUILDER_TOOLS}/templates/core_pkg/${_template}

	# Use default pkg repo to obtain ABI and ALTABI
	local _abi=$(sed -e "s/%%ARCH%%/${TARGET_ARCH}/g" \
	    ${PKG_REPO_DEFAULT%%.conf}.abi)
	local _altabi_arch=$(get_altabi_arch ${TARGET_ARCH})
	local _altabi=$(sed -e "s/%%ARCH%%/${_altabi_arch}/g" \
	    ${PKG_REPO_DEFAULT%%.conf}.altabi)

	${BUILDER_SCRIPTS}/create_core_pkg.sh \
		-t "${_template_path}" \
		-f "${_flavor}" \
		-v "${_version}" \
		-r "${_root}" \
		-s "${_findroot}" \
		-F "${_filter}" \
		-d "${CORE_PKG_REAL_PATH}/All" \
		-a "${_abi}" \
		-A "${_altabi}" \
		|| print_error_pfS
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
	[ -n "${LOGFILE}" -a -f "${LOGFILE}" ] && \
		echo "Log saved on ${LOGFILE}" && \
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
		unset KERNEL_NAME
		export KERNCONF=$BUILD_KERNEL
		export KERNEL_DESTDIR="$KERNEL_BUILD_PATH/$BUILD_KERNEL"
		export KERNEL_NAME=${BUILD_KERNEL}

		LOGFILE="${BUILDER_LOGS}/kernel.${KERNCONF}.${TARGET}.log"
		echo ">>> Building $BUILD_KERNEL kernel."  | tee -a ${LOGFILE}

		if [ -n "${NO_BUILDKERNEL}" -a -f "${CORE_PKG_ALL_PATH}/$(get_pkg_name kernel-${KERNEL_NAME}).txz" ]; then
			echo ">>> NO_BUILDKERNEL set, skipping build" | tee -a ${LOGFILE}
			continue
		fi

		buildkernel

		echo ">>> Staging $BUILD_KERNEL kernel..." | tee -a ${LOGFILE}
		installkernel

		ensure_kernel_exists $KERNEL_DESTDIR

		echo ">>> Creating pkg of $KERNEL_NAME-debug kernel to staging area..."  | tee -a ${LOGFILE}
		core_pkg_create kernel-debug ${KERNEL_NAME} ${CORE_PKG_VERSION} ${KERNEL_DESTDIR} \
		    "./usr/lib/debug/boot" \*.debug
		rm -rf ${KERNEL_DESTDIR}/usr

		echo ">>> Creating pkg of $KERNEL_NAME kernel to staging area..."  | tee -a ${LOGFILE}
		core_pkg_create kernel ${KERNEL_NAME} ${CORE_PKG_VERSION} ${KERNEL_DESTDIR} "./boot/kernel ./boot/modules"

		rm -rf $KERNEL_DESTDIR 2>&1 1>/dev/null
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

	# Set kernel pkg as vital to avoid user end up removing it for any reason
	pkg_chroot ${FINAL_CHROOT_DIR} set -v 1 -y $(get_pkg_name kernel-${KERNEL_NAME})

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

	echo ">>> $(LC_ALL=C date) - Starting build world for ${TARGET} architecture..." | tee -a ${LOGFILE}
	script -aq $LOGFILE ${BUILDER_SCRIPTS}/build_freebsd.sh -K -s ${FREEBSD_SRC_DIR} \
		|| print_error_pfS
	echo ">>> $(LC_ALL=C date) - Finished build world for ${TARGET} architecture..." | tee -a ${LOGFILE}

	LOGFILE=${BUILDER_LOGS}/installworld.${TARGET}
	echo ">>> LOGFILE set to $LOGFILE." | tee -a ${LOGFILE}

	[ -d "${INSTALLER_CHROOT_DIR}" ] \
		|| mkdir -p ${INSTALLER_CHROOT_DIR}

	echo ">>> Installing world with bsdinstall for ${TARGET} architecture..." | tee -a ${LOGFILE}
	script -aq $LOGFILE ${BUILDER_SCRIPTS}/install_freebsd.sh -i -K \
		-s ${FREEBSD_SRC_DIR} \
		-d ${INSTALLER_CHROOT_DIR} \
		|| print_error_pfS

	# Copy additional installer scripts
	install -o root -g wheel -m 0755 ${BUILDER_TOOLS}/installer/*.sh \
		${INSTALLER_CHROOT_DIR}/root

	# XXX set root password since we don't have nullok enabled
	pw -R ${INSTALLER_CHROOT_DIR} usermod root -w yes

	echo ">>> Installing world without bsdinstall for ${TARGET} architecture..." | tee -a ${LOGFILE}
	script -aq $LOGFILE ${BUILDER_SCRIPTS}/install_freebsd.sh -K \
		-s ${FREEBSD_SRC_DIR} \
		-d ${STAGE_CHROOT_DIR} \
		|| print_error_pfS

	# Use the builder cross compiler from obj to produce the final binary.
	BUILD_CC="${MAKEOBJDIRPREFIX}${FREEBSD_SRC_DIR}/${TARGET}.${TARGET_ARCH}/tmp/usr/bin/cc"

	[ -f "${BUILD_CC}" ] || print_error_pfS

	# XXX It must go to the scripts
	[ -d "${STAGE_CHROOT_DIR}/usr/local/bin" ] \
		|| mkdir -p ${STAGE_CHROOT_DIR}/usr/local/bin
	makeargs="CC=${BUILD_CC} DESTDIR=${STAGE_CHROOT_DIR}"
	echo ">>> Building and installing crypto tools and athstats for ${TARGET} architecture... (Starting - $(LC_ALL=C date))" | tee -a ${LOGFILE}
	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR}/tools/tools/crypto ${makeargs} clean all install || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	# XXX FIX IT
#	(script -aq $LOGFILE make -C ${FREEBSD_SRC_DIR}/tools/tools/ath/athstats ${makeargs} clean all install || print_error_pfS;) | egrep '^>>>' | tee -a ${LOGFILE}
	echo ">>> Building and installing crypto tools and athstats for ${TARGET} architecture... (Finished - $(LC_ALL=C date))" | tee -a ${LOGFILE}

	if [ "${PRODUCT_NAME}" = "pfSense" -a -n "${GNID_REPO_BASE}" ]; then
		echo ">>> Building gnid... " | tee -a ${LOGFILE}
		(\
			cd ${GNID_SRC_DIR} && \
			make \
				CC=${BUILD_CC} \
				INCLUDE_DIR=${GNID_INCLUDE_DIR} \
				LIBCRYPTO_DIR=${GNID_LIBCRYPTO_DIR} \
			clean gnid \
		) || print_error_pfS
		install -o root -g wheel -m 0700 ${GNID_SRC_DIR}/gnid \
			${STAGE_CHROOT_DIR}/usr/sbin \
			|| print_error_pfS
		install -o root -g wheel -m 0700 ${GNID_SRC_DIR}/gnid \
			${INSTALLER_CHROOT_DIR}/usr/sbin \
			|| print_error_pfS
	fi

	unset makeargs
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

	local _mntdir=${OVA_TMP}/mnt

	if [ -d "${_mntdir}" ]; then
		local _dev
		# XXX Root cause still didn't found but it doesn't umount
		#     properly on looped builds and then require this extra
		#     check
		while true; do
			_dev=$(mount -p ${_mntdir} 2>/dev/null | awk '{print $1}')
			[ $? -ne 0 -o -z "${_dev}" ] \
				&& break
			umount -f ${_mntdir}
			mdconfig -d -u ${_dev#/dev/}
		done
		chflags -R noschg ${OVA_TMP}
		rm -rf ${OVA_TMP}
	fi

	mkdir -p $(dirname ${OVAPATH})

	mkdir -p ${_mntdir}

	if [ -z "${OVA_SWAP_PART_SIZE_IN_GB}" -o "${OVA_SWAP_PART_SIZE_IN_GB}" = "0" ]; then
		# first partition size (freebsd-ufs)
		local OVA_FIRST_PART_SIZE_IN_GB=${VMDK_DISK_CAPACITY_IN_GB}
		# Calculate real first partition size, removing 256 blocks (131072 bytes) beginning/loader
		local OVA_FIRST_PART_SIZE=$((${OVA_FIRST_PART_SIZE_IN_GB}*1024*1024*1024-131072))
		# Unset swap partition size variable
		unset OVA_SWAP_PART_SIZE
		# Parameter used by mkimg
		unset OVA_SWAP_PART_PARAM
	else
		# first partition size (freebsd-ufs)
		local OVA_FIRST_PART_SIZE_IN_GB=$((VMDK_DISK_CAPACITY_IN_GB-OVA_SWAP_PART_SIZE_IN_GB))
		# Use first partition size in g
		local OVA_FIRST_PART_SIZE="${OVA_FIRST_PART_SIZE_IN_GB}g"
		# Calculate real swap size, removing 256 blocks (131072 bytes) beginning/loader
		local OVA_SWAP_PART_SIZE=$((${OVA_SWAP_PART_SIZE_IN_GB}*1024*1024*1024-131072))
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
	trap "sync; sleep 3; umount ${_mntdir} || umount -f ${_mntdir}; mdconfig -d -u ${_md}; return" 1 2 15 EXIT

	echo "Done!" | tee -a ${LOGFILE}

	clone_directory_contents ${FINAL_CHROOT_DIR} ${_mntdir}

	sync
	sleep 3
	umount ${_mntdir} || umount -f ${_mntdir} >>${LOGFILE} 2>&1
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

	rm -f ${OVA_TMP}/${OVFRAW}

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

	local _exclude_files="${SCRATCHDIR}/base_exclude_files"
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

	core_pkg_create rc "" ${CORE_PKG_VERSION} ${STAGE_CHROOT_DIR}
	core_pkg_create base "" ${CORE_PKG_VERSION} ${STAGE_CHROOT_DIR}
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

	# Make sure correct repo is available on tmp dir
	mkdir -p ${STAGE_CHROOT_DIR}/tmp/pkg/pkg-repos
	setup_pkg_repo \
		${PKG_REPO_BUILD} \
		${STAGE_CHROOT_DIR}/tmp/pkg/pkg-repos/repo.conf \
		${TARGET} \
		${TARGET_ARCH} \
		staging \
		${STAGE_CHROOT_DIR}/tmp/pkg/pkg.conf

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
	elif [ "${_image_type}" = "memstickserial" -o \
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
	pkg_chroot_add ${FINAL_CHROOT_DIR} base

	# Set base/rc pkgs as vital to avoid user end up removing it for any reason
	pkg_chroot ${FINAL_CHROOT_DIR} set -v 1 -y $(get_pkg_name rc)
	pkg_chroot ${FINAL_CHROOT_DIR} set -v 1 -y $(get_pkg_name base)

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
		if [ -n "${_IS_RELEASE}" -o -n "${_IS_RC}" ]; then
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
			${BUILDER_TOOLS}/templates/custom_logos/${_image_variant}/*.svg \
			${FINAL_CHROOT_DIR}/usr/local/share/${PRODUCT_NAME}/custom_logos
		cp -f \
			${BUILDER_TOOLS}/templates/custom_logos/${_image_variant}/*.css \
			${FINAL_CHROOT_DIR}/usr/local/share/${PRODUCT_NAME}/custom_logos
	fi

	# Remove temporary repo conf
	rm -rf ${FINAL_CHROOT_DIR}/tmp/pkg
}

create_distribution_tarball() {
	mkdir -p ${INSTALLER_CHROOT_DIR}/usr/freebsd-dist

	echo -n ">>> Creating distribution tarball... " | tee -a ${LOGFILE}
	tar -C ${FINAL_CHROOT_DIR} --exclude ./pkgs \
		-cJf ${INSTALLER_CHROOT_DIR}/usr/freebsd-dist/base.txz .
	echo "Done!" | tee -a ${LOGFILE}

	echo -n ">>> Creating manifest... " | tee -a ${LOGFILE}
	(cd ${INSTALLER_CHROOT_DIR}/usr/freebsd-dist && \
		sh ${FREEBSD_SRC_DIR}/release/scripts/make-manifest.sh base.txz) \
		> ${INSTALLER_CHROOT_DIR}/usr/freebsd-dist/MANIFEST
	echo "Done!" | tee -a ${LOGFILE}
}

create_iso_image() {
	local _variant="$1"

	LOGFILE=${BUILDER_LOGS}/isoimage.${TARGET}

	if [ -z "${ISOPATH}" ]; then
		echo ">>> ISOPATH is empty skipping generation of ISO image!" | tee -a ${LOGFILE}
		return
	fi

	echo ">>> Building bootable ISO image for ${TARGET}" | tee -a ${LOGFILE}

	mkdir -p $(dirname ${ISOPATH})

	local _image_path=${ISOPATH}
	if [ -n "${_variant}" ]; then
		_image_path=$(echo "$_image_path" | \
			sed "s/${PRODUCT_NAME_SUFFIX}-/&${_variant}-/")
		VARIANTIMAGES="${VARIANTIMAGES}${VARIANTIMAGES:+ }${_image_path}"
	fi

	customize_stagearea_for_image "iso" "" $_variant
	install_default_kernel ${DEFAULT_KERNEL}

	BOOTCONF=${INSTALLER_CHROOT_DIR}/boot.config
	LOADERCONF=${INSTALLER_CHROOT_DIR}/boot/loader.conf

	rm -f ${LOADERCONF} ${BOOTCONF} >/dev/null 2>&1
	echo 'autoboot_delay="3"' > ${LOADERCONF}
	echo 'kern.cam.boot_delay=10000' >> ${LOADERCONF}
	cat ${LOADERCONF} > ${FINAL_CHROOT_DIR}/boot/loader.conf

	create_distribution_tarball

	FSLABEL=$(echo ${PRODUCT_NAME} | tr '[:lower:]' '[:upper:]')

	sh ${FREEBSD_SRC_DIR}/release/${TARGET}/mkisoimages.sh -b \
		${FSLABEL} \
		${_image_path} \
		${INSTALLER_CHROOT_DIR}

	if [ ! -f "${_image_path}" ]; then
		echo "ERROR! ISO image was not built"
		print_error_pfS
	fi

	gzip -qf $_image_path &
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

	BOOTCONF=${INSTALLER_CHROOT_DIR}/boot.config
	LOADERCONF=${INSTALLER_CHROOT_DIR}/boot/loader.conf

	rm -f ${LOADERCONF} ${BOOTCONF} >/dev/null 2>&1

	echo 'autoboot_delay="3"' > ${LOADERCONF}
	echo 'kern.cam.boot_delay=10000' >> ${LOADERCONF}
	echo 'boot_serial="NO"' >> ${LOADERCONF}
	cat ${LOADERCONF} > ${FINAL_CHROOT_DIR}/boot/loader.conf

	create_distribution_tarball

	FSLABEL=$(echo ${PRODUCT_NAME} | tr '[:lower:]' '[:upper:]')

	sh ${FREEBSD_SRC_DIR}/release/${TARGET}/mkisoimages.sh -b \
		${FSLABEL} \
		${_image_path} \
		${INSTALLER_CHROOT_DIR}

	if [ ! -f "${_image_path}" ]; then
		echo "ERROR! memstick image was not built"
		print_error_pfS
	fi

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

	BOOTCONF=${INSTALLER_CHROOT_DIR}/boot.config
	LOADERCONF=${INSTALLER_CHROOT_DIR}/boot/loader.conf

	echo ">>> Activating serial console..." 2>&1 | tee -a ${LOGFILE}
	echo "-S115200 -D" > ${BOOTCONF}

	# Activate serial console+video console in loader.conf
	echo 'autoboot_delay="3"' > ${LOADERCONF}
	echo 'kern.cam.boot_delay=10000' >> ${LOADERCONF}
	echo 'boot_multicons="YES"' >> ${LOADERCONF}
	echo 'boot_serial="YES"' >> ${LOADERCONF}
	echo 'console="comconsole,vidconsole"' >> ${LOADERCONF}
	echo 'comconsole_speed="115200"' >> ${LOADERCONF}

	cat ${BOOTCONF} >> ${FINAL_CHROOT_DIR}/boot.config
	cat ${LOADERCONF} >> ${FINAL_CHROOT_DIR}/boot/loader.conf

	create_distribution_tarball

	sh ${FREEBSD_SRC_DIR}/release/${TARGET}/make-memstick.sh \
		${INSTALLER_CHROOT_DIR} \
		${MEMSTICKSERIALPATH}

	if [ ! -f "${MEMSTICKSERIALPATH}" ]; then
		echo "ERROR! memstick serial image was not built"
		print_error_pfS
	fi

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

	BOOTCONF=${INSTALLER_CHROOT_DIR}/boot.config
	LOADERCONF=${INSTALLER_CHROOT_DIR}/boot/loader.conf

	echo ">>> Activating serial console..." 2>&1 | tee -a ${LOGFILE}
	echo "-S115200 -h" > ${BOOTCONF}

	# Activate serial console+video console in loader.conf
	echo 'autoboot_delay="3"' > ${LOADERCONF}
	echo 'kern.cam.boot_delay=10000' >> ${LOADERCONF}
	echo 'boot_serial="YES"' >> ${LOADERCONF}
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

	if [ ! -f "${MEMSTICKADIPATH}" ]; then
		echo "ERROR! memstick ADI image was not built"
		print_error_pfS
	fi

	gzip -qf $MEMSTICKADIPATH &
	_bg_pids="${_bg_pids}${_bg_pids:+ }$!"

	echo ">>> MEMSTICKADI created: $(LC_ALL=C date)" | tee -a ${LOGFILE}
}

get_altabi_arch() {
	local _target_arch="$1"

	if [ "${_target_arch}" = "amd64" ]; then
		echo "x86:64"
	elif [ "${_target_arch}" = "i386" ]; then
		echo "x86:32"
	elif [ "${_target_arch}" = "armv7" ]; then
		echo "32:el:eabi:softfp"
	else
		echo ">>> ERROR: Invalid arch"
		print_error_pfS
	fi
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
	local _pkg_conf="${6}"

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
		-e "s/%%REPO_BRANCH_PREFIX%%/${REPO_BRANCH_PREFIX}/g" \
		${_template} \
		> ${_target}

	local ALTABI_ARCH=$(get_altabi_arch ${_target_arch})

	ABI=$(cat ${_template%%.conf}.abi 2>/dev/null \
	    | sed -e "s/%%ARCH%%/${_target_arch}/g")
	ALTABI=$(cat ${_template%%.conf}.altabi 2>/dev/null \
	    | sed -e "s/%%ARCH%%/${ALTABI_ARCH}/g")

	if [ -n "${_pkg_conf}" -a -n "${ABI}" -a -n "${ALTABI}" ]; then
		mkdir -p $(dirname ${_pkg_conf})
		echo "ABI=${ABI}" > ${_pkg_conf}
		echo "ALTABI=${ALTABI}" >> ${_pkg_conf}
	fi
}

depend_check() {
	for _pkg in ${BUILDER_PKG_DEPENDENCIES}; do
		if ! pkg info -e ${_pkg}; then
			echo "Missing dependency (${_pkg})."
			print_error_pfS
		fi
	done
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
			${PKG_REPO_BUILD} \
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

	if [ -n "${NO_BUILDWORLD}" -a -n "${NO_BUILDKERNEL}" ]; then
		echo ">>> NO_BUILDWORLD and NO_BUILDKERNEL set, skipping update of freebsd sources" | tee -a ${LOGFILE}
		return
	fi

	echo ">>> Obtaining FreeBSD sources (${FREEBSD_BRANCH})..."
	${BUILDER_SCRIPTS}/git_checkout.sh \
		-r ${FREEBSD_REPO_BASE} \
		-d ${FREEBSD_SRC_DIR} \
		-b ${FREEBSD_BRANCH}

	if [ $? -ne 0 -o ! -d "${FREEBSD_SRC_DIR}/.git" ]; then
		echo ">>> ERROR: It was not possible to clone FreeBSD src repo"
		print_error_pfS
	fi

	if [ -n "${GIT_FREEBSD_COSHA1}" ]; then
		echo -n ">>> Checking out desired commit (${GIT_FREEBSD_COSHA1})... "
		( git -C  ${FREEBSD_SRC_DIR} checkout ${GIT_FREEBSD_COSHA1} ) 2>&1 | \
			grep -C3 -i -E 'error|fatal'
		echo "Done!"
	fi

	if [ "${PRODUCT_NAME}" = "pfSense" -a -n "${GNID_REPO_BASE}" ]; then
		echo ">>> Obtaining gnid sources..."
		${BUILDER_SCRIPTS}/git_checkout.sh \
			-r ${GNID_REPO_BASE} \
			-d ${GNID_SRC_DIR} \
			-b ${GNID_BRANCH}
	fi
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
	local _params=""
	if [ -f "${_root}/tmp/pkg/pkg-repos/repo.conf" ]; then
		_params="--repo-conf-dir /tmp/pkg/pkg-repos "
	fi
	if [ -f "${_root}/tmp/pkg/pkg.conf" ]; then
		_params="${_params} --config /tmp/pkg/pkg.conf "
	fi
	script -aq ${BUILDER_LOGS}/install_pkg_install_ports.txt \
		chroot ${_root} pkg ${_params}$@ >/dev/null 2>&1
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
		${PKG_REPO_BUILD} \
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
	# pkg and MAIN_PKG are vital
	pkg_chroot ${STAGE_CHROOT_DIR} set -y -v 1 pkg ${MAIN_PKG}
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
	local _kernconf=${1:-${KERNCONF}}

	if [ -n "${NO_BUILDKERNEL}" ]; then
		echo ">>> NO_BUILDKERNEL set, skipping build" | tee -a ${LOGFILE}
		return
	fi

	if [ -z "${_kernconf}" ]; then
		echo ">>> ERROR: No kernel configuration defined probably this is not what you want! STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi

	local _old_kernconf=${KERNCONF}
	export KERNCONF=${_kernconf}

	echo ">>> $(LC_ALL=C date) - Starting build kernel for ${TARGET} architecture..." | tee -a ${LOGFILE}
	script -aq $LOGFILE ${BUILDER_SCRIPTS}/build_freebsd.sh -W -s ${FREEBSD_SRC_DIR} \
		|| print_error_pfS
	echo ">>> $(LC_ALL=C date) - Finished build kernel for ${TARGET} architecture..." | tee -a ${LOGFILE}

	export KERNCONF=${_old_kernconf}
}

# Imported from FreeSBIE
installkernel() {
	local _destdir=${1:-${KERNEL_DESTDIR}}
	local _kernconf=${2:-${KERNCONF}}

	if [ -z "${_kernconf}" ]; then
		echo ">>> ERROR: No kernel configuration defined probably this is not what you want! STOPPING!" | tee -a ${LOGFILE}
		print_error_pfS
	fi

	local _old_kernconf=${KERNCONF}
	export KERNCONF=${_kernconf}

	mkdir -p ${STAGE_CHROOT_DIR}/boot
	echo ">>> Installing kernel (${_kernconf}) for ${TARGET} architecture..." | tee -a ${LOGFILE}
	script -aq $LOGFILE ${BUILDER_SCRIPTS}/install_freebsd.sh -W -D -z \
		-s ${FREEBSD_SRC_DIR} \
		-d ${_destdir} \
		|| print_error_pfS

	export KERNCONF=${_old_kernconf}
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
	local _aws_sync_cmd="aws s3 sync --quiet --exclude '.real*/*' --exclude '.latest/*'"

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

	if [ -n "${PKG_REPO_SIGNING_COMMAND}" -a -z "${DO_NOT_SIGN_PKG_REPO}" ]; then
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

	if [ -z "${UPLOAD}" ]; then
		return
	fi

	for _pkg_rsync_hostname in ${PKG_RSYNC_HOSTNAME}; do
		# Make sure destination directory exist
		ssh -o StrictHostKeyChecking=no -p ${PKG_RSYNC_SSH_PORT} \
			${PKG_RSYNC_USERNAME}@${_pkg_rsync_hostname} \
			"mkdir -p ${PKG_RSYNC_DESTDIR}"

		echo -n ">>> Sending updated repository to ${_pkg_rsync_hostname}... " | tee -a ${_logfile}
		if script -aq ${_logfile} rsync -Have "ssh -o StrictHostKeyChecking=no -p ${PKG_RSYNC_SSH_PORT}" \
			--timeout=60 --delete-delay ${_repo_path} \
			${PKG_RSYNC_USERNAME}@${_pkg_rsync_hostname}:${PKG_RSYNC_DESTDIR} >/dev/null 2>&1
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
			for _pkg_final_rsync_hostname in ${PKG_FINAL_RSYNC_HOSTNAME}; do
				# Send .real* directories first to prevent having a broken repo while transfer happens
				local _cmd="rsync -Have \"ssh -o StrictHostKeyChecking=no -p ${PKG_FINAL_RSYNC_SSH_PORT}\" \
					--timeout=60 ${PKG_RSYNC_DESTDIR}/./${_repo_base%%-core}* \
					--include=\"/*\" --include=\"*/.real*\" --include=\"*/.real*/***\" \
					--exclude=\"*\" \
					${PKG_FINAL_RSYNC_USERNAME}@${_pkg_final_rsync_hostname}:${PKG_FINAL_RSYNC_DESTDIR}"

				echo -n ">>> Sending updated packages to ${_pkg_final_rsync_hostname}... " | tee -a ${_logfile}
				if script -aq ${_logfile} ssh -o StrictHostKeyChecking=no -p ${PKG_RSYNC_SSH_PORT} \
					${PKG_RSYNC_USERNAME}@${_pkg_rsync_hostname} ${_cmd} >/dev/null 2>&1; then
					echo "Done!" | tee -a ${_logfile}
				else
					echo "Failed!" | tee -a ${_logfile}
					echo ">>> ERROR: An error occurred sending repo to final hostname"
					print_error_pfS
				fi

				_cmd="rsync -Have \"ssh -o StrictHostKeyChecking=no -p ${PKG_FINAL_RSYNC_SSH_PORT}\" \
					--timeout=60 --delete-delay ${PKG_RSYNC_DESTDIR}/./${_repo_base%%-core}* \
					${PKG_FINAL_RSYNC_USERNAME}@${_pkg_final_rsync_hostname}:${PKG_FINAL_RSYNC_DESTDIR}"

				echo -n ">>> Sending updated repositories metadata to ${_pkg_final_rsync_hostname}... " | tee -a ${_logfile}
				if script -aq ${_logfile} ssh -o StrictHostKeyChecking=no -p ${PKG_RSYNC_SSH_PORT} \
					${PKG_RSYNC_USERNAME}@${_pkg_rsync_hostname} ${_cmd} >/dev/null 2>&1; then
					echo "Done!" | tee -a ${_logfile}
				else
					echo "Failed!" | tee -a ${_logfile}
					echo ">>> ERROR: An error occurred sending repo to final hostname"
					print_error_pfS
				fi

				if [ -z "${PKG_FINAL_S3_PATH}" ]; then
					continue
				fi

				local _repos=$(ssh -o StrictHostKeyChecking=no -p ${PKG_FINAL_RSYNC_SSH_PORT} \
				    ${PKG_FINAL_RSYNC_USERNAME}@${_pkg_final_rsync_hostname} \
				    "ls -1d ${PKG_FINAL_RSYNC_DESTDIR}/${_repo_base%%-core}*")
				for _repo in ${_repos}; do
					echo -n ">>> Sending updated packages to AWS ${PKG_FINAL_S3_PATH}... " | tee -a ${_logfile}
					if script -aq ${_logfile} ssh -o StrictHostKeyChecking=no -p ${PKG_FINAL_RSYNC_SSH_PORT} \
					    ${PKG_FINAL_RSYNC_USERNAME}@${_pkg_final_rsync_hostname} \
					    "${_aws_sync_cmd} ${_repo} ${PKG_FINAL_S3_PATH}/$(basename ${_repo})"; then
						echo "Done!" | tee -a ${_logfile}
					else
						echo "Failed!" | tee -a ${_logfile}
						echo ">>> ERROR: An error occurred sending files to AWS S3"
						print_error_pfS
					fi
					echo -n ">>> Cleaning up packages at AWS ${PKG_FINAL_S3_PATH}... " | tee -a ${_logfile}
					if script -aq ${_logfile} ssh -o StrictHostKeyChecking=no -p ${PKG_FINAL_RSYNC_SSH_PORT} \
					    ${PKG_FINAL_RSYNC_USERNAME}@${_pkg_final_rsync_hostname} \
					    "${_aws_sync_cmd} --delete ${_repo} ${PKG_FINAL_S3_PATH}/$(basename ${_repo})"; then
						echo "Done!" | tee -a ${_logfile}
					else
						echo "Failed!" | tee -a ${_logfile}
						echo ">>> ERROR: An error occurred sending files to AWS S3"
						print_error_pfS
					fi
				done
			done
		fi
	done
}

poudriere_possible_archs() {
	local _arch=$(uname -m)
	local _archs=""

	# If host is amd64, we'll create both repos, and if possible armv7
	if [ "${_arch}" = "amd64" ]; then
		_archs="amd64.amd64"

		if [ -f /usr/local/bin/qemu-arm-static ]; then
			# Make sure binmiscctl is ok
			/usr/local/etc/rc.d/qemu_user_static forcestart >/dev/null 2>&1

			if binmiscctl lookup armv7 >/dev/null 2>&1; then
				_archs="${_archs} arm.armv7"
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
		local _pdescr=""

		if [ -e ${_pdir}/${_pname} ]; then
			rm -rf ${_pdir}/${_pname}
		fi

		cp -r ${d} ${_pdir}/${_pname}

		if [ -f ${_pdir}/${_pname}/pkg-plist ]; then
			_plist=${_pdir}/${_pname}/pkg-plist
		fi

		if [ -f ${_pdir}/${_pname}/pkg-descr ]; then
			_pdescr=${_pdir}/${_pname}/pkg-descr
		fi

		sed -i '' -e "s,pfSense,${PRODUCT_NAME},g" \
			  -e "s,https://www.pfsense.org,${PRODUCT_URL},g" \
			  -e "/^MAINTAINER=/ s,^.*$,MAINTAINER=	${PRODUCT_EMAIL}," \
			${_pdir}/${_pname}/Makefile ${_pdescr} ${_plist}

		# PHP module is special
		if echo "${_pname}" | grep -q "^php[0-9]*-${PRODUCT_NAME}-module"; then
			local _product_capital=$(echo ${PRODUCT_NAME} | tr '[a-z]' '[A-Z]')
			sed -i '' -e "s,PHP_PFSENSE,PHP_${_product_capital},g" \
				  -e "s,PFSENSE_SHARED_LIBADD,${_product_capital}_SHARED_LIBADD,g" \
				  -e "s,pfSense,${PRODUCT_NAME},g" \
				  -e "s,pfSense.c,${PRODUCT_NAME}\.c,g" \
				${_pdir}/${_pname}/files/config.m4

			sed -i '' -e "s,COMPILE_DL_PFSENSE,COMPILE_DL_${_product_capital}," \
				  -e "s,pfSense_module_entry,${PRODUCT_NAME}_module_entry,g" \
				  -e "s,php_pfSense.h,php_${PRODUCT_NAME}\.h,g" \
				  -e "/ZEND_GET_MODULE/ s,pfSense,${PRODUCT_NAME}," \
				  -e "/PHP_PFSENSE_WORLD_EXTNAME/ s,pfSense,${PRODUCT_NAME}," \
				${_pdir}/${_pname}/files/pfSense.c \
				${_pdir}/${_pname}/files/dummynet.c \
				${_pdir}/${_pname}/files/php_pfSense.h
		fi

		if [ -d ${_pdir}/${_pname}/files ]; then
			for fd in $(find ${_pdir}/${_pname}/files -name '*pfSense*'); do
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
			_branch="${POUDRIERE_PORTS_GIT_BRANCH}"
		fi
		echo -n ">>> Creating poudriere ports tree, it may take some time... " | tee -a ${LOGFILE}
		if [ "${AWS}" = 1 ]; then
			set -e
			script -aq ${LOGFILE} poudriere ports -c -p "${POUDRIERE_PORTS_NAME}" -m none
			script -aq ${LOGFILE} zfs create ${ZFS_TANK}/poudriere/ports/${POUDRIERE_PORTS_NAME}

			# If S3 doesn't contain stashed ports tree, create one
			if ! aws_exec s3 ls s3://pfsense-engineering-build-pkg/${FLAVOR}-ports.tz >/dev/null 2>&1; then
				mkdir ${SCRATCHDIR}/${FLAVOR}-ports
				${BUILDER_SCRIPTS}/git_checkout.sh \
				    -r ${POUDRIERE_PORTS_GIT_URL} \
				    -d ${SCRATCHDIR}/${FLAVOR}-ports \
				    -b ${POUDRIERE_PORTS_GIT_BRANCH}

				tar --zstd -C ${SCRATCHDIR} -cf ${FLAVOR}-ports.tz ${FLAVOR}-ports
				aws_exec s3 cp ${FLAVOR}-ports.tz s3://pfsense-engineering-build-pkg/${FLAVOR}-ports.tz --no-progress
			else
				# Download local copy of the ports tree stashed in S3
				echo ">>>  Downloading cached copy of the ports tree from S3.." | tee -a ${LOGFILE}
				aws_exec s3 cp s3://pfsense-engineering-build-pkg/${FLAVOR}-ports.tz . --no-progress
			fi

			script -aq ${LOGFILE} tar --strip-components 1 -xf ${FLAVOR}-ports.tz -C /usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}
			# Update the ports tree
			(
				cd /usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}
				echo ">>>  Updating cached copy of the ports tree from git.." | tee -a ${LOGFILE}
				script -aq ${LOGFILE} git pull
				script -aq ${LOGFILE} git checkout ${_branch}
			)
			set +e
		else
			if ! script -aq ${LOGFILE} poudriere ports -c -p "${POUDRIERE_PORTS_NAME}" -m git -U ${POUDRIERE_PORTS_GIT_URL} -B ${_branch} >/dev/null 2>&1; then
				echo "" | tee -a ${LOGFILE}
				echo ">>> ERROR: Error creating poudriere ports tree, aborting..." | tee -a ${LOGFILE}
				print_error_pfS
			fi
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
	if [ ! -f /usr/local/bin/poudriere ]; then
		echo ">>> Installing poudriere..." | tee -a ${LOGFILE}
		if ! pkg install poudriere >/dev/null 2>&1; then
			echo ">>> ERROR: poudriere was not installed, aborting..." | tee -a ${LOGFILE}
			print_error_pfS
		fi
	fi

	# Create poudriere.conf
	if [ -z "${POUDRIERE_PORTS_GIT_URL}" ]; then
		echo ">>> ERROR: POUDRIERE_PORTS_GIT_URL is not defined"
		print_error_pfS
	fi

	# PARALLEL_JOBS us ncpu / 4 for best performance
	local _parallel_jobs=$(sysctl -qn hw.ncpu)
	_parallel_jobs=$((_parallel_jobs / 4))

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
ALLOW_MAKE_JOBS=yes
PARALLEL_JOBS=${_parallel_jobs}
EOF

	if pkg info -e ccache; then
	cat <<EOF >>/usr/local/etc/poudriere.conf
CCACHE_DIR=/var/cache/ccache
EOF
	fi

	# Create specific items conf
	[ ! -d /usr/local/etc/poudriere.d ] \
		&& mkdir -p /usr/local/etc/poudriere.d

	# Create DISTFILES_CACHE if it doesn't exist
	if [ ! -d /usr/ports/distfiles ]; then
		mkdir -p /usr/ports/distfiles
	fi

	if [ "${AWS}" = 1 ]; then
		# Download a copy of the distfiles from S3
		echo ">>> Downloading distfile cache from S3.." | tee -a ${LOGFILE}
		aws_exec s3 cp s3://pfsense-engineering-build-pkg/distfiles.tar . --no-progress
		script -aq ${LOGFILE} tar -xf distfiles.tar -C /usr/ports/distfiles
		# Save a list of distfiles
		find /usr/ports/distfiles > pre-build-distfile-list

	fi

	# Remove old jails
	for jail_arch in ${_archs}; do
		jail_name=$(poudriere_jail_name ${jail_arch})

		if poudriere jail -i -j "${jail_name}" >/dev/null 2>&1; then
			echo ">>> Poudriere jail ${jail_name} already exists, deleting it..." | tee -a ${LOGFILE}
			poudriere jail -d -j "${jail_name}"
		fi
	done

	# Remove old ports tree
	if poudriere ports -l | grep -q -E "^${POUDRIERE_PORTS_NAME}[[:blank:]]"; then
		echo ">>> Poudriere ports tree ${POUDRIERE_PORTS_NAME} already exists, deleting it..." | tee -a ${LOGFILE}
		poudriere ports -d -p "${POUDRIERE_PORTS_NAME}"
		if [ "${AWS}" = 1 ]; then
			for d in `zfs list -o name`; do
				if [ "${d}" = "${ZFS_TANK}/poudriere/ports/${POUDRIERE_PORTS_NAME}" ]; then
					script -aq ${LOGFILE} zfs destroy ${ZFS_TANK}/poudriere/ports/${POUDRIERE_PORTS_NAME}
				fi
			done
		fi
	fi

	local native_xtools=""
	# Now we are ready to create jails
	for jail_arch in ${_archs}; do
		jail_name=$(poudriere_jail_name ${jail_arch})

		if [ "${jail_arch}" = "arm.armv7" ]; then
			native_xtools="-x"
		else
			native_xtools=""
		fi

		echo ">>> Creating jail ${jail_name}, it may take some time... " | tee -a ${LOGFILE}
		if [ "${AWS}" = "1" ]; then
			mkdir objs
			echo ">>> Downloading prebuilt release objs from s3://pfsense-engineering-build-freebsd-obj-tarballs/${FLAVOR}/ ..." | tee -a ${LOGFILE}
			# Download prebuilt release tarballs from previous job
			aws_exec s3 cp s3://pfsense-engineering-build-freebsd-obj-tarballs/${FLAVOR}/LATEST-${jail_arch} objs --no-progress
			SRC_COMMIT=`cat objs/LATEST-${jail_arch}`
			aws_exec s3 cp s3://pfsense-engineering-build-freebsd-obj-tarballs/${FLAVOR}/MANIFEST-${jail_arch}-${SRC_COMMIT} objs --no-progress
			ln -s MANIFEST-${jail_arch}-${SRC_COMMIT} objs/MANIFEST
			for i in base doc kernel src tests; do
				if [ ! -f objs/${i}-${jail_arch}-${SRC_COMMIT}.txz ]; then
					aws_exec s3 cp s3://pfsense-engineering-build-freebsd-obj-tarballs/${FLAVOR}/${i}-${jail_arch}-${SRC_COMMIT}.txz objs --no-progress
					ln -s ${i}-${jail_arch}-${SRC_COMMIT}.txz objs/${i}.txz
				fi
			done

			if ! script -aq ${LOGFILE} poudriere jail -c -j "${jail_name}" -v ${FREEBSD_BRANCH} \
					-a ${jail_arch} -m url=file://${PWD}/objs >/dev/null 2>&1; then
				echo "" | tee -a ${LOGFILE}
				echo ">>> ERROR: Error creating jail ${jail_name}, aborting..." | tee -a ${LOGFILE}
				print_error_pfS
			fi

			# Download a cached pkg repo from S3
			OLDIFS=${IFS}
			IFS=$'\n'
			echo ">>> Downloading cached pkgs for ${jail_arch} from S3.." | tee -a ${LOGFILE}
			for i in $(aws_exec s3 ls s3://pfsense-engineering-build-pkg/); do
				echo ${i} | awk '{print $4}' | grep ${FLAVOR}-pkgs-${jail_arch}.tar > /dev/null
				if [ $? -eq 0 ]; then
					aws_exec s3 cp s3://pfsense-engineering-build-pkg/${FLAVOR}-pkgs-${jail_arch}.tar . --no-progress
					[ ! -d /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME} ] && mkdir -p /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME}
					echo "Extracting ${FLAVOR}-pkgs-${jail_arch}.tar to /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME}" | tee -a ${LOGFILE}
					[ ! -d /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME} ] && mkdir /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME}
					script -aq ${LOGFILE} tar -xf ${FLAVOR}-pkgs-${jail_arch}.tar -C /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME}
					# Save a list of pkgs
					cd /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME}/.latest
					find . > ${WORKSPACE}/pre-build-pkg-list-${jail_arch}
					cd ${WORKSPACE}
				else
					touch pre-build-pkg-list-${jail_arch}
				fi
			done
			IFS=${OLDIFS}
		else
			if ! script -aq ${LOGFILE} poudriere jail -c -j "${jail_name}" -v ${FREEBSD_BRANCH} \
					-a ${jail_arch} -m git -U ${FREEBSD_REPO_BASE_POUDRIERE} ${native_xtools} >/dev/null 2>&1; then
				echo "" | tee -a ${LOGFILE}
				echo ">>> ERROR: Error creating jail ${jail_name}, aborting..." | tee -a ${LOGFILE}
				print_error_pfS
			fi
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

		if [ "${jail_arch}" = "arm.armv7" ]; then
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

save_logs_to_s3() {
	# Save a copy of the past few logs into S3
	DATE=`date +%Y%m%d-%H%M%S`
	script -aq ${LOGFILE} tar --zstd -cf pkg-logs-${jail_arch}-${DATE}.tar -C /usr/local/poudriere/data/logs/bulk/${jail_name}-${POUDRIERE_PORTS_NAME}/latest/ .
	aws_exec s3 cp pkg-logs-${jail_arch}-${DATE}.tar s3://pfsense-engineering-build-pkg/logs/ --no-progress
	OLDIFS=${IFS}
	IFS=$'\n'
	local _logtemp=$( mktemp /tmp/loglist.XXXXX )
	for i in $(aws_exec s3 ls s3://pfsense-engineering-build-pkg/logs/); do
		echo ${i} | awk '{print $4}' | grep pkg-logs-${jail_arch} >> ${_logtemp}
	done
	local _maxlogs=5
	local _curlogs=0
	_curlogs=$( wc -l ${_logtemp} | awk '{print $1}' )
	if [ ${_curlogs} -gt ${_maxlogs} ]; then
		local _extralogs=$(( ${_curlogs} - ${_maxlogs} ))
		for _last in $( head -${_extralogs} ${_logtemp} ); do
			aws_exec s3 rm s3://pfsense-engineering-build-pkg/logs/${_last}
		done
	fi
	IFS=${OLDIFS}
}

save_pkgs_to_s3() {
	echo ">>> Save a copy of the package repo into S3..." | tee -a ${LOGFILE}
	cd /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME}/.latest
	find . > ${WORKSPACE}/post-build-pkg-list-${jail_arch}
	cd ${WORKSPACE}
	diff pre-build-pkg-list-${jail_arch} post-build-pkg-list-${jail_arch} > /dev/null
	if [ $? = 1 ]; then
		[ -f ${FLAVOR}-pkgs-${jail_arch}.tar ] && rm ${FLAVOR}-pkgs-${jail_arch}.tar
		script -aq ${LOGFILE} tar -cf ${FLAVOR}-pkgs-${jail_arch}.tar -C /usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME} .
		aws_exec s3 cp ${FLAVOR}-pkgs-${jail_arch}.tar s3://pfsense-engineering-build-pkg/ --no-progress

		save_logs_to_s3
	fi
}

aws_exec() {
	script -aq ${LOGFILE} \
	    env AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID} \
	    AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY} \
	    AWS_DEFAULT_REGION=us-east-2 \
	    aws $@
	return $?
}

poudriere_bulk() {
	local _archs=$(poudriere_possible_archs)
	local _makeconf

	# Create DISTFILES_CACHE if it doesn't exist
	if [ ! -d /usr/ports/distfiles ]; then
		mkdir -p /usr/ports/distfiles
	fi

	LOGFILE=${BUILDER_LOGS}/poudriere.log

	if [ -n "${UPLOAD}" -a -z "${PKG_RSYNC_HOSTNAME}" ]; then
		echo ">>> ERROR: PKG_RSYNC_HOSTNAME is not set"
		print_error_pfS
	fi

	rm -f ${LOGFILE}

	poudriere_create_ports_tree

	[ -d /usr/local/etc/poudriere.d ] || \
		mkdir -p /usr/local/etc/poudriere.d

	_makeconf=/usr/local/etc/poudriere.d/${POUDRIERE_PORTS_NAME}-make.conf
	if [ -f "${BUILDER_TOOLS}/conf/pfPorts/make.conf" ]; then
		sed -e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" \
		    "${BUILDER_TOOLS}/conf/pfPorts/make.conf" > ${_makeconf}
	fi

	cat <<EOF >>/usr/local/etc/poudriere.d/${POUDRIERE_PORTS_NAME}-make.conf

PKG_REPO_BRANCH_DEVEL=${PKG_REPO_BRANCH_DEVEL}
PKG_REPO_BRANCH_RELEASE=${PKG_REPO_BRANCH_RELEASE}
PKG_REPO_SERVER_DEVEL=${PKG_REPO_SERVER_DEVEL}
PKG_REPO_SERVER_RELEASE=${PKG_REPO_SERVER_RELEASE}
POUDRIERE_PORTS_NAME=${POUDRIERE_PORTS_NAME}
PFSENSE_DEFAULT_REPO=${PFSENSE_DEFAULT_REPO}
PRODUCT_NAME=${PRODUCT_NAME}
REPO_BRANCH_PREFIX=${REPO_BRANCH_PREFIX}
EOF

	local _value=""
	for jail_arch in ${_archs}; do
		eval "_value=\${PKG_REPO_BRANCH_DEVEL_${jail_arch##*.}}"
		if [ -n "${_value}" ]; then
			echo "PKG_REPO_BRANCH_DEVEL_${jail_arch##*.}=${_value}" \
				>> ${_makeconf}
		fi
		eval "_value=\${PKG_REPO_BRANCH_RELEASE_${jail_arch##*.}}"
		if [ -n "${_value}" ]; then
			echo "PKG_REPO_BRANCH_RELEASE_${jail_arch##*.}=${_value}" \
				>> ${_makeconf}
		fi
		eval "_value=\${PKG_REPO_SERVER_DEVEL_${jail_arch##*.}}"
		if [ -n "${_value}" ]; then
			echo "PKG_REPO_SERVER_DEVEL_${jail_arch##*.}=${_value}" \
				>> ${_makeconf}
		fi
		eval "_value=\${PKG_REPO_SERVER_RELEASE_${jail_arch##*.}}"
		if [ -n "${_value}" ]; then
			echo "PKG_REPO_SERVER_RELEASE_${jail_arch##*.}=${_value}" \
				>> ${_makeconf}
		fi
	done

	# Change version of pfSense meta ports for snapshots
	if [ -z "${_IS_RELEASE}" ]; then
		local _meta_pkg_version="$(echo "${PRODUCT_VERSION}" | sed 's,DEVELOPMENT,ALPHA,')-${DATESTRING}"
		sed -i '' \
			-e "/^DISTVERSION/ s,^.*,DISTVERSION=	${_meta_pkg_version}," \
			-e "/^PORTREVISION=/d" \
			/usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}/security/${PRODUCT_NAME}/Makefile \
			/usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}/sysutils/${PRODUCT_NAME}-repo/Makefile
	fi

	# Copy over pkg repo templates to pfSense-repo
	mkdir -p /usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}/sysutils/${PRODUCT_NAME}-repo/files
	cp -f ${PKG_REPO_BASE}/* \
		/usr/local/poudriere/ports/${POUDRIERE_PORTS_NAME}/sysutils/${PRODUCT_NAME}-repo/files

	for jail_arch in ${_archs}; do
		jail_name=$(poudriere_jail_name ${jail_arch})

		if ! poudriere jail -i -j "${jail_name}" >/dev/null 2>&1; then
			echo ">>> Poudriere jail ${jail_name} not found, skipping..." | tee -a ${LOGFILE}
			continue
		fi

		_ref_bulk=${SCRATCHDIR}/poudriere_bulk.${POUDRIERE_BRANCH}.ref.${jail_arch}
		rm -rf ${_ref_bulk} ${_ref_bulk}.tmp
		touch ${_ref_bulk}.tmp
		if [ -f "${POUDRIERE_BULK}.${jail_arch#*.}" ]; then
			cat "${POUDRIERE_BULK}.${jail_arch#*.}" >> ${_ref_bulk}.tmp
		fi
		if [ -f "${POUDRIERE_BULK}" ]; then
			cat "${POUDRIERE_BULK}" >> ${_ref_bulk}.tmp
		fi
		cat ${_ref_bulk}.tmp | sort -u > ${_ref_bulk}

		_bulk=${SCRATCHDIR}/poudriere_bulk.${POUDRIERE_BRANCH}.${jail_arch}
		sed -e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" ${_ref_bulk} > ${_bulk}

		local _exclude_bulk="${POUDRIERE_BULK}.exclude.${jail_arch}"
		if [ -f "${_exclude_bulk}" ]; then
			mv ${_bulk} ${_bulk}.tmp
			sed -e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" ${_exclude_bulk} > ${_bulk}.exclude
			cat ${_bulk}.tmp ${_bulk}.exclude | sort | uniq -u > ${_bulk}
			rm -f ${_bulk}.tmp ${_bulk}.exclude
		fi

		echo ">>> Poudriere bulk started at `date "+%Y/%m/%d %H:%M:%S"` for ${jail_arch}"
		if ! poudriere bulk -f ${_bulk} -j ${jail_name} -p ${POUDRIERE_PORTS_NAME}; then
			echo ">>> ERROR: Something went wrong..."
			if [ "${AWS}" = 1 ]; then
				save_pkgs_to_s3
			fi
			print_error_pfS
		fi
		echo ">>> Poudriere bulk complated at `date "+%Y/%m/%d %H:%M:%S"` for ${jail_arch}"

		echo ">>> Cleaning up old packages from repo..."
		if ! poudriere pkgclean -f ${_bulk} -j ${jail_name} -p ${POUDRIERE_PORTS_NAME} -y; then
			echo ">>> ERROR: Something went wrong..."
			print_error_pfS
		fi

		if [ "${AWS}" = 1 ]; then
			save_pkgs_to_s3
		fi

		pkg_repo_rsync "/usr/local/poudriere/data/packages/${jail_name}-${POUDRIERE_PORTS_NAME}"
	done

	if [ "${AWS}" = 1 ]; then
		echo ">>> Save a copy of the distfiles into S3..." | tee -a ${LOGFILE}
		# Save a copy of the distfiles from S3
		find /usr/ports/distfiles > post-build-distfile-list
		diff pre-build-distfile-list post-build-distfile-list > /dev/null
		if [ $? -eq 1 ]; then
			rm distfiles.tar
			script -aq ${LOGFILE} tar -cf distfiles.tar -C /usr/ports/distfiles .
			aws_exec s3 cp distfiles.tar s3://pfsense-engineering-build-pkg/ --no-progress
		fi
	fi
}

# This routine is called to write out to stdout
# a string. The string is appended to $SNAPSHOTSLOGFILE
snapshots_update_status() {
	if [ -z "$1" ]; then
		return
	fi
	if [ -z "${SNAPSHOTS}" -a -z "${POUDRIERE_SNAPSHOTS}" ]; then
		return
	fi
	echo "$*"
	echo "`date` -|- $*" >> $SNAPSHOTSLOGFILE
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
}

snapshots_scp_files() {
	if [ -z "${RSYNC_COPY_ARGUMENTS}" ]; then
		RSYNC_COPY_ARGUMENTS="-Have \"ssh -o StrictHostKeyChecking=no\" --timeout=60"
	fi

	snapshots_update_status ">>> Copying core pkg repo to ${PKG_RSYNC_HOSTNAME}"
	pkg_repo_rsync "${CORE_PKG_PATH}"
	snapshots_update_status ">>> Finished copying core pkg repo"

	for _rsyncip in ${RSYNCIP}; do
		snapshots_update_status ">>> Copying files to ${_rsyncip}"

		# Ensure directory(s) are available
		ssh -o StrictHostKeyChecking=no ${RSYNCUSER}@${_rsyncip} "mkdir -p ${RSYNCPATH}/installer"
		if [ -d $IMAGES_FINAL_DIR/virtualization ]; then
			ssh -o StrictHostKeyChecking=no ${RSYNCUSER}@${_rsyncip} "mkdir -p ${RSYNCPATH}/virtualization"
		fi
		# ensure permissions are correct for r+w
		ssh -o StrictHostKeyChecking=no ${RSYNCUSER}@${_rsyncip} "chmod -R ug+rw ${RSYNCPATH}/."
		rsync $RSYNC_COPY_ARGUMENTS $IMAGES_FINAL_DIR/installer/* \
			${RSYNCUSER}@${_rsyncip}:${RSYNCPATH}/installer/
		if [ -d $IMAGES_FINAL_DIR/virtualization ]; then
			rsync $RSYNC_COPY_ARGUMENTS $IMAGES_FINAL_DIR/virtualization/* \
				${RSYNCUSER}@${_rsyncip}:${RSYNCPATH}/virtualization/
		fi

		snapshots_update_status ">>> Finished copying files."
	done
}
