#!/bin/sh

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

hostname="factory-logger.pfmechanics.com"

if ! pgrep -q dhclient; then
	# First, find a connected interface
	if=$(ifconfig \
		| sed -E '/^([a-z]|[[:blank:]]*status: )/!d; /^lo0:/d' \
		| sed -e 'N; s/\n/ /' \
		| egrep 'status: *active' \
		| sed 's,:.*,,' \
		| head -n 1)

	# If we couldn't, just abort
	if [ -z "${if}" ]; then
		exit 0
	fi

	# Then, try to obtain an IP address to it running dhclient
	# if it fails, abort
	if ! /sbin/dhclient ${if}; then
		exit 0
	fi
fi

# Check if we are in buildroom, if not, abort
unset buildroom
if /usr/bin/grep -q 'option classless-routes 32,1,2,3,4,127,0,0,1' \
    /var/db/dhclient.leases*; then
	buildroom=1
fi

# Try to read serial
machine_arch=$(uname -p)
unset is_adi
case "${machine_arch}" in
	amd64)
		serial=$(kenv smbios.system.serial)
		product=$(kenv -q smbios.system.product)
		case "${product}" in
			RCC-VE|DFFv2|RCC)
				is_adi=1
				;;
		esac
		;;
	armv6)
		# XXX add code to read serial from ufw
		;;
	*)
		echo "Unsuported platform"
		exit 1
esac

# XXX is it really necessary?
if [ "${serial}" = "123456789" ]; then
	serial=""
fi

# Bad serial
if ! echo "${serial}" | egrep -q '^[0-9]*$'; then
	serial=""
fi

unset selected_model
if [ -n "${is_adi}" ]; then
	models="\
	\"SG-2220\" \"SG-2220\" \
	\"SG-2440\" \"SG-2440\" \
	\"SG-4860\" \"SG-4860\" \
	\"SG-4860-1U\" \"SG-4860-1U\" \
	\"SG-8860\" \"SG-8860\" \
	\"SG-8860-1U\" \"SG-8860-1U\" \
	\"XG-2758\" \"XG-2758\" \
	\"Default\" \"Other / not listed\" \
	"
elif [ "${machine_arch}" != "armv6" ]; then
	models="\
	\"C2758\" \"C2758\" \
	\"APU\" \"APU\" \
	\"XG-1540\" \"XG-1540\" \
	\"XG-1541\" \"XG-1541\" \
	\"Default\" \"Other / not listed\" \
	"
else
	selected_model="SG-1000"
fi

if [ -z "${selected_model}" ]; then
	exec 3>&1
	selected_model=$(echo $models | xargs dialog \
		--backtitle "pfSense installer" \
		--title "Hardware model" \
		--menu "Select corresponding hardware model" \
		0 0 0 2>&1 1>&3) || exit 1
	exec 3>&1
fi

echo 'boot_serial="YES"' > /tmp/loader.conf.pfSense
if [ -n "${is_adi}" ]; then
	echo "-S115200 -h" > /tmp/boot.config
	echo 'console="comconsole"' >> /tmp/loader.conf.pfSense
	echo 'comconsole_port="0x2F8"' >> /tmp/loader.conf.pfSense
	echo 'hint.uart.0.flags="0x00"' >> /tmp/loader.conf.pfSense
	echo 'hint.uart.1.flags="0x10"' >> /tmp/loader.conf.pfSense
else
	echo "-S115200 -D" > /tmp/boot.config
	echo 'boot_multicons="YES"' >> /tmp/loader.conf.pfSense
	echo 'console="comconsole,vidconsole"' >> /tmp/loader.conf.pfSense
fi
echo 'comconsole_speed="115200"' >> /tmp/loader.conf.pfSense
echo 'kern.ipc.nmbclusters="1000000"' >> /tmp/loader.conf.pfSense
echo 'kern.ipc.nmbjumbop="524288"' >> /tmp/loader.conf.pfSense
echo 'kern.ipc.nmbjumbo9="524288"' >> /tmp/loader.conf.pfSense

if [ -z "${buildroom}" ]; then
	exit 0
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
	*)
		wan_if="igb0"
		;;
esac

wan_mac=$(get_if_mac ${wan_if})

# Get WLAN mac address
wlan_devices=$(sysctl -n net.wlan.devices)
if [ -n "${wlan_devices}" ]; then
	if ! does_if_exist wlan0; then
		wlan_dev=$(echo "${wlan_devices}" | awk '{ print $1 }')
		ifconfig create wlan0 wlandev ${wlan_dev} >/dev/null 2>&1
	fi
fi

wlan_mac=$(get_if_mac wlan0)

exec 3>&1
col=30
factory_raw_data=$(dialog --nocancel \
	--backtitle "pfSense installer" \
	--title "Register Serial Number" \
	--form "Enter system information" 0 0 0 \
	"Model" 1 0 "${selected_model}" 1 $col 0 0 \
	"Serial" 2 0 "${serial}" 2 $col 16 16 \
	"Order Number" 3 0 "" 3 $col 16 0 \
	"Print sticker (0/1)" 4 0 "1" 4 $col 1 0 \
	"Builder Initials" 5 0 "" 5 $col 16 0 \
	2>&1 1>&3)
exec 3>&1

factory_data=$(echo "$factory_raw_data" \
	| sed 's,#,,g' \
	| paste -d'#' -s -)

set_vars=$(echo "$factory_data" | \
	awk '
	BEGIN { FS="#" }
	{
		print "serial=\""$1"\"";
		print "order=\""$2"\""
		print "sticker=\""$3"\"";
		print "builder=\""$4"\"";
	}')

eval "${set_vars}"

if [ "${sticker}" != "0" ]; then
	sticker=1
fi

if [ -z "${serial}" -o -z "${order}" -o -z "${builder}" ]; then
	exit 1
fi

release_ver="UNKNOWN"
if [ -f /mnt/etc/version ]; then
	release_ver=$(cat /mnt/etc/version)
fi

postreq="model=${selected_model}&serial=${serial}&order=${order}"
postreq="${postreq}&release=${release_ver}&wan_mac=${wan_mac}"
postreq="${postreq}&wlan_mac=${wlan_mac}&print=${sticker}&builder=${builder}"
postreq="${postreq}&submit=Submit"

postreq_len=$(echo "${postreq}" | wc -c)

cat <<EOF > /tmp/postdata
POST http://${hostname}/log.php HTTP/1.0
Content-Type: application/x-www-form-urlencoded
Content-Length: ${postreq_len}

${postreq}

EOF

/usr/bin/nc < /tmp/postdata -w 5 ${hostname} 80 >/tmp/postresult
exit $?
