#!/bin/sh

export PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin

does_if_exist() {
	local _if="$1"

	[ -z "${_if}" ] \
		&& return 1

	ifconfig ${_if} >/dev/null 2>&1
	return $?
}

get_if_mac() {
	local _if="$1"
	local _if_mac="000000000000"

	if does_if_exist ${_if}; then
		_if_mac=$(ifconfig ${_if} \
		    | awk '/ether/ {gsub(/:/, "", $2); print $2}')
	fi

	echo "${_if_mac}"
}

upgrade_netgate_coreboot() {
	local _roms_dir=/usr/local/share/pfSense-pkg-Netgate_Coreboot_Upgrade/roms

	# No roms found in this installation media, aborting
	[ -d /mnt/${_roms_dir} ] \
	    || return 0

	local _adi_flash_util=/usr/local/sbin/adi_flash_util
	local _adi_smbios_util=/usr/local/sbin/adi_smbios_util

	local _product=$(kenv -q smbios.system.product 2>/dev/null)
	local _planar_product=$(kenv -q smbios.planar.product 2>/dev/null)
	local _coreboot_model=""

	case "${_product}" in
		RCC-VE)
			_coreboot_model="RCCVE"
			;;
		DFFv2)
			_coreboot_model="DFF2"
			;;
		RCC)
			_coreboot_model="RCC"
			;;
		*)
			if [ "${_planar_product%-*}" == "80300-0134" ]; then
				_coreboot_model="PLCC"
			else
				# Unsupported model
				return 0
			fi
			;;
	esac

	if [ "${_coreboot_model}" = "PLCC" ]; then
		local _adi_util=${_adi_smbios_util}
		local _adi_util_param="-w"
	else
		local _adi_util=${_adi_flash_util}
		unset _adi_util_param
	fi

	# Upgrade utility is not available
	[ -f /mnt/${_adi_util} ] \
	    || return 0

	# Check current model and version
	local _cur_model=$(kenv -q smbios.bios.version 2>/dev/null | \
	    sed 's/^ADI_//; s/-.*//')
	local _cur_version=$(kenv -q smbios.bios.version 2>/dev/null | \
	    sed "s/^ADI_${_cur_model}-//; s/-.*//")

	# Models don't match, leave it alone
	[ "${_coreboot_model}" != "${_cur_model}" ] \
		&& return 0

	# Get remote available version
	local _remote_version="0"
	local _url=http://factory-install.netgate.com/coreboot/${_coreboot_model}_version.txt
	if fetch -o /tmp/remote_version ${_url} >/dev/null 2>&1; then
		_remote_version=$(head -n 1 /tmp/remote_version)
	fi

	# Look for available rom for this model
	local _avail_rom=$(cd /mnt/${_roms_dir} && \
	    ls -1 ADI_${_coreboot_model}-*.rom ADI_${_coreboot_model}-*.bin \
	    2>/dev/null | tail -n 1)

	local _get_remote=1
	if [ -f "/mnt/${_roms_dir}/${_avail_rom}" ]; then
		# Get available version
		_avail_version=$(echo "${_avail_rom}" | \
		    sed "s/^ADI_${_coreboot_model}-//; s/-.*//")

		# If local available version is the same, use it
		_ver_cmp=$(/mnt/usr/local/sbin/pkg-static version -t \
		    "${_remote_version}" "${_avail_version}")

		if [ "${_ver_cmp}" != ">" ]; then
			local _version="${_avail_version}"
			local _rom="${_roms_dir}/${_avail_rom}"
			unset _get_remote
		fi
	fi

	if [ -n "${_get_remote}" ]; then
		local _romname=$(tail -n 1 /tmp/remote_version)
		local _rom="/tmp/coreboot_rom"
		if ! fetch -o /mnt${_rom} \
		    http://factory-install.netgate.com/coreboot/${_romname}; then
			return 0
		fi
		local _version="${_remote_version}"
	fi

	# Installed version is the latest, nothing to be done here
	_ver_cmp=$(/mnt/usr/local/sbin/pkg-static version -t \
	    "${_cur_version}" "${_version}")
	[ "${_ver_cmp}" != "<" ] \
		&& return 0

	# Upgrade coreboot
	echo "===> Upgrading Netgate Coreboot"
	mkdir -p /mnt/dev
	mount -t devfs devfs /mnt/dev
	chroot /mnt ${_adi_util} ${_adi_util_param} -u ${_rom}
	local _rc=$?
	umount -f /mnt/dev
	return ${_rc}
}

# Check if M.2 device is present
has_ada_dev() {
	for _disk in $(sysctl -qn kern.disks); do
		echo "$_disk" | /usr/bin/egrep -q '^ada[0-9]' \
		    || continue
		return 0
	done

	return 1
}

# Detect 2 identical disks (RAID)
has_raid() {
	# Ignore mmcsd0bootN from XG-7100
	local _lines=$(diskinfo $(sysctl -qn kern.disks) 2>/dev/null \
	    | egrep -v 'mmcsd[0-9]boot[0-9]' | sed 's/^[^[:blank:]]*//' | sort \
	    | uniq -d | wc -l)

	[ $_lines -eq 0 ] \
	    && return 1 \
	    || return 0
}

# Detect mSata (128Gb)
has_msata() {
	local _ada_disk=""

	for _disk in $(sysctl -qn kern.disks); do
		echo "${_disk}" | egrep -q '^ada[0-9]' \
		    || continue

		_ada_disk=${_disk}
		break
	done

	[ -z "${_ada_disk}" ] \
	    && return 1

	local _disk_size=$(diskinfo ${_ada_disk} | awk '{print $3}')

	[ -z "${_disk_size}" ] \
	    && return 1

	local _size_in_gb=$(expr ${_disk_size} / 1024 / 1024 / 1024)

	[ $_size_in_gb -lt 130 ] \
	    && return 0 \
	    || return 1
}

get_cur_model() {
	local _cur_model=""

	local _product=$(kenv -q smbios.system.product 2>/dev/null)
	local _planar_product=$(kenv -q smbios.planar.product 2>/dev/null)
	local _hw_model=$(sysctl -qb hw.model)
	local _hw_ncpu=$(sysctl -qn hw.ncpu)
	local _boardpn=""
	local _model=""

	# Read board model from FDT.
	if [ "${machine_arch}" == "aarch64" ]; then
		_model=$(/usr/sbin/ofwdump -P model -R /)
	fi

	case "${_product}" in
		RCC-VE)
			if ! ifconfig igb4 >/dev/null 2>&1; then
				_cur_model="SG-2440"
			elif echo $_hw_model | grep -q C2558; then
				_cur_model="SG-4860"
			elif echo $_hw_model | grep -q C2758; then
				_cur_model="SG-8860"
			else
				_cur_model="RCC-VE"
			fi
			;;
		RCC)
			_cur_model="XG-2758"
			;;
		DFFv2)
			_cur_model="SG-2220"
			;;
		FW7541)
			_cur_model="FW7541"
			;;
		SG-5100)
			_cur_model="SG-5100"
			;;
		APU)
			_cur_model="APU"
			;;
		SYS-5018A-FTN4|A1SAi)
			_cur_model="C2758"
			;;
		SYS-5018D-FN4T)
			if sysctl -nq hw.model | grep -q "D-1541"; then
				_cur_model="XG-1541"
			else
				_cur_model="XG-1540"
			fi
			;;
		"Minnowboard Turbot D0 PLATFORM")
			case "${_hw_ncpu}" in
				4)
					_cur_model="SG-2340"
					;;
				2)
					_cur_model="SG-2320"
					;;
			esac
			;;
		mvebu_armada-37xx)
			if [ -n "${_model}" -a "${_model}" == "Netgate SG-1100" ]; then
				_cur_model="SG-1100"
			fi
			;;
	esac

	if [ "${_planar_product}" = 'X10SDV-8C-TLN4F+' ]; then
		_cur_model="XG-1537"
	elif [ "${_planar_product%-*}" == "80300-0134" ]; then
		_cur_model="XG-7100"
	elif [ "${_planar_product%-*}" == "80300-0138" ]; then
		_cur_model="DNVNANO"
	fi

	if [ "${machine_arch}" == "armv6" -a -f /usr/local/sbin/u-boot-env ]; then
		_boardpn=$(/usr/local/sbin/u-boot-env boardpn)
		if [ "${_boardpn%-*}" == "80500-0148" ]; then
			_cur_model="SG-3100"
		fi
	fi

	_ti_soc_model=$(sysctl -qn hw.ti_soc_model)
	if [ -n "${_ti_soc_model}" ]; then
		/sbin/ifconfig cpsw0 >/dev/null 2>&1
		if [ $? -eq 0 ]; then
			_cur_model="SG-1000"
		fi
	fi

	echo "$_cur_model"
}

get_prodtrack_model() {
	local _cur_model="$1"
	local _prodtrack_model=""

	[ -z "${_cur_model}" ] \
	    && return 0

	# First deal with models easier to identify
	case "${_cur_model}" in
		SG-1000)
			_prodtrack_model="SG-1000-EMMC-512M"
			;;
		SG-2220)
			has_ada_dev \
			    && _prodtrack_model="SG-2220-M2-2GB" \
			    || _prodtrack_model="SG-2220-EMMC-2GB"
			;;
		SG-2320|SG-2340)
			_prodtrack_model="${_cur_model}-M2-2GB"
			;;
		SG-2440)
			has_ada_dev \
			    && _prodtrack_model="SG-2440-MSATA-4GB" \
			    || _prodtrack_model="SG-2440-EMMC-4GB"
			;;
		SG-3100)
			has_ada_dev \
			    && _prodtrack_model="SG-3100-M2-2GB" \
			    || _prodtrack_model="SG-3100-EMMC-2GB"
			;;
		SG-4860*|SG-8860*)
			has_ada_dev \
			    && _prodtrack_model="${_cur_model}-MSATA-8GB" \
			    || _prodtrack_model="${_cur_model}-EMMC-8GB"
			;;
		SG-5100)
			has_ada_dev \
			    && _prodtrack_model="SG-5100-M2-4GB" \
			    || _prodtrack_model="SG-5100-EMMC-4GB"
			;;
	esac

	if [ -n "${_prodtrack_model}" ]; then
		echo "${_prodtrack_model}"
		return 0
	fi

	local _pmem=$(expr $(/sbin/sysctl -qn hw.physmem) / 1024 / 1024 / 1024)
	local _mem=""

	if [ $_pmem -gt 25 ]; then
		_mem="32GB"
	elif [ $_pmem -gt 17 ]; then
		_mem="24GB"
	elif [ $_pmem -gt 13 ]; then
		_mem="16GB"
	elif [ $_pmem -gt 5 ]; then
		_mem="8GB"
	fi

	local _disk=""
	if ! has_ada_dev; then
		_disk="EMMC"
	elif has_raid; then
		_disk="RAID"
	elif has_msata; then
		echo "${_cur_model}" | grep -q '^XG-7100' \
		    && _disk="M2" \
		    || _disk="MSATA"
	elif echo "${_cur_model}" | grep -q '^XG-15'; then
		_disk="M2"
	else
		echo "${_cur_model}" | grep -q '^XG-7100' \
		    && _disk="M2" \
		    || _disk="SATA"
	fi

	echo "${_cur_model}-${_disk}-${_mem}"
	return 0
}

machine_arch=$(uname -p)

unset selected_model
cur_model=$(get_cur_model)
if [ -z "${cur_model}" ]; then
	echo "Unsupported platform"
	exit 1
fi

# Try to read serial
unset is_arm
unset is_adi
unset vga_only
unset serial_only
if [ "${cur_model}" == "SG-1000" ]; then
	is_arm=1
	dd if=/dev/icee0 of=/tmp/serial.bin bs=1 count=12 skip=16
	serial=$(hexdump -C /tmp/serial.bin | \
	    sed '1!d; s,\.*\|$,,; s,^.*\|,,')

elif [ "${cur_model}" == "SG-1100" ]; then
	is_arm=1
	serial=		# XXX

elif [ "${cur_model}" == "SG-3100" ]; then
	is_arm=1
	serial=$(/usr/local/sbin/u-boot-env boardsn 2>/dev/null)

elif [ "${machine_arch}" == "amd64" ]; then
	for key in system planar; do
		serial=$(kenv -q smbios.system.serial | \
		    grep -E '^[a-zA-Z0-9]{10,16}$')

		if [ -n "$serial" -a "$serial" != "0123456789" ]; then
			break
		fi
		serial=""
	done

	product=$(kenv -q smbios.system.product)
	case "${product}" in
		RCC-VE|DFFv2|RCC)
			is_adi=1
			;;
		"Minnowboard Turbot D0 PLATFORM")
			serial=$(ifconfig igb0 | sed -n \
			    '/hwaddr / { s,^.*hwaddr *,,; s,:,,g; p; }')
			vga_only=1
			;;
	esac
	if [ "${cur_model}" == "XG-7100" ]; then
		serial_only=1
	fi
else
	echo "Unsupported platform"
	exit 1
fi

# XXX is it really necessary?
if [ "${serial}" = "123456789" ]; then
	serial=""
fi

if [ -n "${is_adi}" ]; then
	case "${cur_model}" in
		SG-2220|SG-2440|XG-2758)
			selected_model="${cur_model}"
			;;
		SG-4860|SG-8860)
			models="\
			    \"${cur_model}\" \"${cur_model}\" \
			    \"${cur_model}-1U\" \"${cur_model}-1U\" \
			"
			;;
		*)
			selected_model="Default"
	esac
elif [ "${machine_arch}" != "armv6" -a "${machine_arch}" != "aarch64" ]; then
	case "${cur_model}" in
		C2758|APU|SG-2320|SG-2340|XG-1537|SG-5100|XG-154*)
			selected_model="${cur_model}"
			;;
		XG-7100)
			models="\
			    \"${cur_model}-DT\" \"${cur_model}-DT\" \
			    \"${cur_model}-1U\" \"${cur_model}-1U\" \
			"
			;;
		*)
			selected_model="Default"
	esac
else
	selected_model=${cur_model}
fi

if [ -z "${selected_model}" ]; then
	exec 3>&1
	selected_model=$(echo $models | xargs dialog \
	    --backtitle "pfSense installer" \
	    --title "Hardware model" \
	    --menu "Select corresponding hardware model" \
	    0 0 0 2>&1 1>&3) || exit 1
	exec 3>&-
fi

prodtrack_model=$(get_prodtrack_model "${selected_model}")

if [ "${machine_arch}" == "amd64" ]; then
	_loaderconf=/tmp/loader.conf.pfSense
	echo 'autoboot_delay="3"' > ${_loaderconf}
	echo 'kern.ipc.nmbclusters="1000000"' >> ${_loaderconf}
	echo 'kern.ipc.nmbjumbop="524288"' >> ${_loaderconf}
	echo 'kern.ipc.nmbjumbo9="524288"' >> ${_loaderconf}
	echo 'hw.usb.no_pf="1"' >> ${_loaderconf}

	if [ -n "${vga_only}" ]; then
		echo 'console="vidconsole"' >> ${_loaderconf}
	else
		echo 'boot_serial="YES"' >> ${_loaderconf}
		if [ -n "${serial_only}" ]; then
			echo "-S115200 -h" > /tmp/boot.config
			echo 'console="comconsole"' >> ${_loaderconf}
		elif [ -n "${is_adi}" ]; then
			echo "-S115200 -h" > /tmp/boot.config
			echo 'console="comconsole"' >> ${_loaderconf}
			echo 'comconsole_port="0x2F8"' >> ${_loaderconf}
			echo 'hint.uart.0.flags="0x00"' >> ${_loaderconf}
			echo 'hint.uart.1.flags="0x10"' >> ${_loaderconf}
		else
			echo "-S115200 -D" > /tmp/boot.config
			echo 'boot_multicons="YES"' >> ${_loaderconf}
			echo 'console="comconsole,vidconsole"' >> ${_loaderconf}
		fi
		echo 'comconsole_speed="115200"' >> ${_loaderconf}
	fi

	if [ "${cur_model}" == "XG-7100" ]; then
		echo 'hint.mdio.0.at="ix2"' >> ${_loaderconf}
		echo 'hint.e6000sw.0.addr=0' >> ${_loaderconf}
		echo 'hint.e6000sw.0.is8190=1' >> ${_loaderconf}
		echo 'hint.e6000sw.0.port0disabled=1' >> ${_loaderconf}
		echo 'hint.e6000sw.0.port9cpu=1' >> ${_loaderconf}
		echo 'hint.e6000sw.0.port10cpu=1' >> ${_loaderconf}
		echo 'hint.e6000sw.0.port9speed=2500' >> ${_loaderconf}
		echo 'hint.e6000sw.0.port10speed=2500' >> ${_loaderconf}
	fi
fi

if [ ! -f /tmp/buildroom ]; then
	exit 0
fi

# Make sure coreboot is in the last version
if ! upgrade_netgate_coreboot; then
	echo "Error while upgrading Netgate Coreboot"
	exit 1
fi

# Get WAN mac address
unset wan_if
case "${selected_model}" in
	APU)
		wan_if="re1"
		;;
	XG-154*)
		if does_if_exist igb2; then
			wan_if="igb4"
		elif does_if_exist cxl0; then
			wan_if="cxl0"
		else
			wan_if="ix0"
		fi
		;;
	XG-2758)
		if does_if_exist igb7; then
			wan_if="igb4"
		else
			wan_if="igb0"
		fi
		;;
	SG-4860*|SG-8860*)
		wan_if="igb1"
		;;
	SG-1000)
		wan_if="cpsw0"
		;;
	SG-1100)
		wan_if="mvneta0"
		;;
	SG-3100)
		wan_if="mvneta2"
		;;
	XG-7100*)
		wan_if="ix2"
		;;
	*)
		wan_if="igb0"
		;;
esac

wan_mac=$(get_if_mac ${wan_if})

if [ "${selected_model}" != "SG-1000" ]; then
	# Get WLAN mac address
	wlan_devices=$(sysctl -qn net.wlan.devices)
	if [ -n "${wlan_devices}" ]; then
		if ! does_if_exist wlan0; then
			wlan_dev=$(echo "${wlan_devices}" | awk '{ print $1 }')
			ifconfig create wlan0 wlandev ${wlan_dev} >/dev/null 2>&1
		fi
	fi
fi

wlan_mac=$(get_if_mac wlan0)

if [ "${selected_model}" = "SG-1000" ]; then
	image_default_url="http://factory-install.netgate.com/pfSense-netgate-sg-1000-latest.img.gz"
	image_url=$(kenv -q ufw.install.image.url)
	image_url=${image_url:-${image_default_url}}

	fetch -o - "${image_url}" | gunzip | dd of=/dev/mmcsd0 bs=1m
	if [ $? -ne 0 ]; then
		echo "Error: Anything went wrong when tried to dd image to eMMC"
		exit 1
	fi

	# Get / ufsid
	UFSID=$(glabel status -s mmcsd0s2a 2>/dev/null \
	    | head -n 1 | cut -d' ' -f1)

	if [ -z "${UFSID}" ]; then
		echo "Error obtaining UFSID"
		exit 1
	fi

	if ! mount /dev/${UFSID} /mnt; then
		echo "Error mounting pfSense partition"
		exit 1
	fi
	trap "umount /mnt; return" 1 2 15 EXIT

	# Found available label for EMMCBOOT
	idx=0
	while [ -e "/dev/label/EMMCBOOT${idx}" ]; do
		idx=$((idx+1))
	done
	EMMCBOOT_LABEL="EMMCBOOT${idx}"

	if ! glabel label ${EMMCBOOT_LABEL} /dev/mmcsd0s1; then
		echo "Error setting EMMCBOOT label"
		exit 1
	fi

	sed -i '' \
	    -e "/[[:blank:]]\/boot\/msdos[[:blank:]]/ s,^/dev/[^[:blank:]]*,/dev/label/${EMMCBOOT_LABEL}," \
	    -e "/[[:blank:]]\/[[:blank:]]/ s,^/dev/[^[:blank:]]*,/dev/${UFSID}," \
	    /mnt/etc/fstab

	# Enable the factory post installation automatic halt
	touch /mnt/root/factory_boot
elif [ "${selected_model}" = "SG-1100" ]; then

echo "SG-1100 install.."
sleep 2

elif [ "${selected_model}" = "SG-3100" ]; then
	[ "$(/usr/local/sbin/u-boot-env boardrev 2>/dev/null)" = "R100" ] \
		&& gpiodev="1" \
		|| gpiodev="0"

	gpioctl -f /dev/gpioc${gpiodev} 2 duty 200 > /dev/null
	sysctl -q dev.gpio.${gpiodev}.led.0.pwm=0 > /dev/null
	sysctl -q dev.gpio.${gpiodev}.led.1.pwm=0 > /dev/null
	sysctl -q dev.gpio.${gpiodev}.led.2.pwm=0 > /dev/null

	BOOTDEV=""
	BOOTFROM=""

	# Find the eMMC device
	DEV=mmcsd0
	if geom disk list ${DEV} 2>/dev/null | grep -q MMCHC; then
		TARGET=${DEV}
		BOOTFROM="-e"
	elif geom disk list ${DEV} 2>/dev/null | grep -q SDHC; then
		TARGET=${DEV}
		BOOTFROM="-e"
	else
		echo "Error: no eMMC device detected.  aborting."
		exit 1
	fi

	# Find the M.2 device
	M2DEV=""
	for DEV in ada0 ada1 ada2 ada3 ada4 ada5 ada6 ada7 ada8 ada9
	do
		if geom disk list ${DEV} 2>/dev/null; then
			M2DEV="${M2DEV}${M2DEV:+ }${DEV}"
		fi
	done

	if echo "${M2DEV}" | grep -q " "; then
		read -p \
		    "Type the name of the destination device (${M2DEV}): " \
		    TARGET
		if [ -z "${TARGET}" ] || ! geom disk list ${TARGET}; then
			echo "Error: Invalid device ${TARGET}"
			exit 1
		fi
		BOOTFROM="-m"
		BOOTDEV=${TARGET#ada*}
	elif [ -n "${M2DEV}" ]; then
		TARGET=${M2DEV}
		BOOTFROM="-m"
		BOOTDEV=${TARGET#ada*}
	fi

	# Update LED status.
	gpioctl -f /dev/gpioc${gpiodev} 2 duty 0 > /dev/null
	gpioctl -f /dev/gpioc${gpiodev} 5 duty 0 > /dev/null
	gpioctl -f /dev/gpioc${gpiodev} 8 duty 0 > /dev/null
	gpioctl -f /dev/gpioc${gpiodev} 1 duty 200 > /dev/null
	gpioctl -f /dev/gpioc${gpiodev} 4 duty 100 > /dev/null
	gpioctl -f /dev/gpioc${gpiodev} 7 duty 35 > /dev/null

	echo "Erasing the disk contents..."
	dd if=/dev/zero of=/dev/${TARGET} bs=4m count=15 2> /dev/null

	image_default_url="http://factory-install.netgate.com/pfSense-netgate-SG-3100-latest.img.gz"
	image_url=${image_url:-${image_default_url}}

	echo "Writing the firmware to disk..."
	echo "(this may take a few minutes to complete)"
	fetch -o - "${image_url}" | gunzip | dd of=/dev/${TARGET} bs=4m
	if [ $? -ne 0 ]; then
		echo "Error: Anything went wrong when tried to dd image to disk"
		exit 1
	fi

	echo "Fixing disk labels..."
	DISKID=$(glabel status -s "${TARGET}" 2>/dev/null \
	    | grep diskid | cut -d' ' -f1)

	if [ -z "${DISKID}" ]; then
		echo
		echo "error obtaining DISKID.  aborting."
		exit 1
	fi
	if ! mount /dev/${DISKID}s2a /mnt; then
		echo
		echo "error mounting pfSense partition.  aborting."
		exit 1
	fi

	# Found available label for PFSENSEBOOT
	idx=0
	while [ -e "/dev/label/PFSENSEBOOT${idx}" ]; do
		idx=$((idx+1))
	done
	BOOT_LABEL="PFSENSEBOOT${idx}"
	if ! glabel label ${BOOT_LABEL} "/dev/${DISKID}s1"; then
		echo
		echo "error setting BOOT label.  aborting."
		exit 1
	fi

	sed -i '' \
	    -e "/[[:blank:]]\/boot\/u-boot[[:blank:]]/ s,^/dev/[^[:blank:]]*,/dev/${DISKID}s1," \
	    -e "/[[:blank:]]\/[[:blank:]]/ s,^/dev/[^[:blank:]]*,/dev/${DISKID}s2a," \
	    /mnt/etc/fstab

	# Set the boot device and update the u-boot environment
	echo "Fixing u-boot environment..."
	/bin/dd if=/dev/flash/spi0 of=/tmp/env bs=64k skip=16 count=1
	/usr/local/sbin/u-boot-env-update ${BOOTFROM} ${BOOTDEV} /tmp/env /tmp/newenv
	/usr/local/sbin/u-boot-env-write -yY /tmp/newenv

	# Update the boot status on SG-3100, pfSense is installed.
	gpioctl -f /dev/gpioc${gpiodev} 1 duty 100 > /dev/null
	gpioctl -f /dev/gpioc${gpiodev} 4 duty 0 > /dev/null
	gpioctl -f /dev/gpioc${gpiodev} 7 duty 0 > /dev/null
	sysctl -q dev.gpio.${gpiodev}.led.1.pwm=1 > /dev/null
	sysctl -q dev.gpio.${gpiodev}.led.2.pwm=1 > /dev/null
	sysctl -q dev.gpio.${gpiodev}.led.0.T1-T3=1040 > /dev/null
	sysctl -q dev.gpio.${gpiodev}.led.0.T2=520 > /dev/null
	sysctl -q dev.gpio.${gpiodev}.pin.1.T4=3640 > /dev/null
else
	support_types=$(fetch -o - http://prodtrack.netgate.com/listspt 2>/dev/null)
	if [ -z "${support_types}" ]; then
		echo "Error: Unable to get the list of support plans"
		exit 1
	fi

	OIFS="${IFS}"
	IFS="
"

	unset support_menu
	for item in ${support_types}; do
		code=$(echo "${item}" | cut -d',' -f1)
		desc=$(echo "${item}" | sed 's/^[^,]*,//')
		support_menu="${support_menu}${support_menu:+ }\"${code}\" \"${desc}\""
	done

	IFS="${OIFS}"

	if [ -n "${support_menu}" ]; then
		exec 3>&1
		support_type=$(echo $support_menu | xargs dialog \
		    --backtitle "pfSense installer" \
		    --title "Support type" \
		    --menu "Select corresponding support type" \
		    0 0 0 2>&1 1>&3) || exit 1
		exec 3>&-
	fi
fi

if [ -f /tmp/custom ]; then
	custom=$(cat /tmp/custom)
	custom_url="http://factory-install.netgate.com/${custom}-install.sh"

	if ! fetch -o /tmp/custom.sh ${custom_url}; then
		echo "Error downloading custom script from ${custom_url}"
		exit 1
	fi

	if ! sh /tmp/custom.sh; then
		echo "Error executing custom script from ${custom_url}"
		exit 1
	fi

	if [ -f /tmp/custom_order ]; then
		order=$(cat /tmp/custom_order)
	fi
fi

default_serial="${serial}"
serial_size=0
sticker=1
# Serial is empty, let operator edit it
if [ -z "${default_serial}" ]; then
	serial_size=16
fi
while [ -z "${is_arm}" ]; do
	exec 3>&1
	col=30
	factory_raw_data=$(dialog --nocancel \
	    --backtitle "pfSense installer" \
	    --title "Register Serial Number" \
	    --form "Enter system information" 0 0 0 \
	    "Model" 1 0 "${prodtrack_model}" 1 $col 0 0 \
	    "Serial" 2 0 "${default_serial}" 2 $col ${serial_size} ${serial_size} \
	    "Order Number" 3 0 "" 3 $col 16 0 \
	    "Print sticker (0/1)" 4 0 "${sticker}" 4 $col 2 1 \
	    2>&1 1>&3)
	exec 3>&-

	factory_data=$(echo "$factory_raw_data" \
	    | sed 's,#,,g' \
	    | paste -d'#' -s -)

	if [ ${serial_size} -eq 0 ]; then
		set_vars=$(echo "$factory_data" | \
		    awk '
		    BEGIN { FS="#" }
		    {
		    	print "order=\""$1"\""
		    	print "sticker=\""$2"\"";
		    }')
	else
		set_vars=$(echo "$factory_data" | \
		    awk '
		    BEGIN { FS="#" }
		    {
		    	print "serial=\""$1"\"";
		    	print "order=\""$2"\""
		    	print "sticker=\""$3"\"";
		    }')
	fi

	eval "${set_vars}"

	if [ "${sticker}" != "0" ]; then
		sticker=1
	fi

	if [ -n "${serial}" -a -n "${order}" ]; then
		break
	fi

	dialog --backtitle "pfSense installer" --title "Error" \
	    --msgbox \
	    "Serial and Order Number are mandatory" \
	    0 0
done

release_ver="UNKNOWN"
if [ -f /mnt/etc/version ]; then
	release_ver=$(cat /mnt/etc/version)
fi

if [ -f /mnt/etc/version.patch ]; then
	release_patch=$(cat /mnt/etc/version.patch)
	if [ -n "${release_patch}" -a "${release_patch}" != "0" ]; then
		release_ver="${release_ver}-p${release_patch}"
	fi
fi

umount /mnt
sync; sync; sync
trap "-" 1 2 15 EXIT

# Calculate the "Unique ID" for support and tracking purposes
UID=$(gnid)

postreq="model=${prodtrack_model}&serial=${serial}&release=${release_ver}"
postreq="${postreq}&wan_mac=${wan_mac}&print=${sticker}"
postreq="${postreq}&uniqueid=${UID}"

if [ -n "${order}" ]; then
	postreq="${postreq}&order=${order}"
fi

if [ "${selected_model}" != "SG-1000" ]; then
	postreq="${postreq}&wlan_mac=${wlan_mac}"
fi

if [ -n "${support_type}" ]; then
	postreq="${postreq}&support=${support_type}"
fi

postreq="${postreq}&submit=Submit"

# ProdTrack
fetch -o /dev/null "http://prodtrack.netgate.com/builder?${postreq}" >/dev/null 2>&1

exit $?
