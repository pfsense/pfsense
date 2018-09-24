#!/bin/sh
#
# pfSense-led.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2018 Rubicon Communications, LLC (Netgate)
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

#
# SG-1100
#
sg1100_led_booting() {
	echo f1 > /dev/led/ok
}

sg1100_led_ready() {
	echo 1 > /dev/led/ok
}

sg1100_led_update() {
	echo f5 > /dev/led/ok
}

sg1100_led_update_off() {
	echo 1 > /dev/led/ok
}

#
# SG-3100
#
sg3100_gpiounit() {
	local _boardrev=$(/bin/kenv -q uboot.boardrev)

	if [ "${_boardrev}" == "R100" ]; then
		echo "1"
	else
		echo "0"
	fi
}

sg3100_led_booting() {
	local _gpiodev=$(sg3100_gpiounit)

	/usr/sbin/gpioctl -f /dev/gpioc${_gpiodev} 2 duty 200 > /dev/null
	/sbin/sysctl -q dev.gpio.${_gpiodev}.led.0.pwm=0 > /dev/null
	/sbin/sysctl -q dev.gpio.${_gpiodev}.led.1.pwm=0 > /dev/null
	/sbin/sysctl -q dev.gpio.${_gpiodev}.led.2.pwm=0 > /dev/null
}

sg3100_led_ready() {
	local _gpiodev=$(sg3100_gpiounit)

	/usr/sbin/gpioctl -f /dev/gpioc${_gpiodev} 2 duty 100 > /dev/null
	/usr/sbin/gpioctl -f /dev/gpioc${_gpiodev} 5 duty 0 > /dev/null
	/usr/sbin/gpioctl -f /dev/gpioc${_gpiodev} 8 duty 0 > /dev/null
	/sbin/sysctl -q dev.gpio.${_gpiodev}.led.1.pwm=1 > /dev/null
	/sbin/sysctl -q dev.gpio.${_gpiodev}.led.2.pwm=1 > /dev/null
	/sbin/sysctl -q dev.gpio.${_gpiodev}.led.0.T1-T3=1040 > /dev/null
	/sbin/sysctl -q dev.gpio.${_gpiodev}.led.0.T2=520 > /dev/null
	/sbin/sysctl -q dev.gpio.${_gpiodev}.pin.2.T4=3640 > /dev/null
}

sg3100_led_update() {
	local _gpiodev=$(sg3100_gpiounit)

	/usr/sbin/gpioctl -f /dev/gpioc${_gpiodev} 3 duty 150 > /dev/null
	/usr/sbin/gpioctl -f /dev/gpioc${_gpiodev} 4 duty 15 > /dev/null
	/sbin/sysctl dev.gpio.${_gpiodev}.led.1.T1-T3=1040 > /dev/null
	/sbin/sysctl dev.gpio.${_gpiodev}.pin.3.T4=3640 > /dev/null
	/sbin/sysctl dev.gpio.${_gpiodev}.pin.4.T4=3640 > /dev/null
	/sbin/sysctl dev.gpio.${_gpiodev}.led.1.pwm=0 > /dev/null
}

sg3100_led_update_off() {
	local _gpiodev=$(sg3100_gpiounit)

	/usr/sbin/gpioctl -f /dev/gpioc${_gpiodev} 3 duty 0 > /dev/null
	/usr/sbin/gpioctl -f /dev/gpioc${_gpiodev} 4 duty 0  > /dev/null
	/sbin/sysctl dev.gpio.${_gpiodev}.led.1.pwm=1 > /dev/null
}

#
# SG-5100
#
sg5100_led_booting() {
	# Booting (red)
	/usr/local/sbin/SG-5100led 1
}

sg5100_led_ready() {
	# Boot finished (green)
	/usr/local/sbin/SG-5100led 3
}

sg5100_led_update() {
	# updates, green flashing
	/usr/local/sbin/SG-5100led 4
}

sg5100_led_update_off() {
	# No updates, green
	/usr/local/sbin/SG-5100led 3
}

#
# Common code
#
led_booting() {
	case "$SYSTEM" in
	"SG-1100")
		sg1100_led_booting
		;;
	"SG-3100")
		sg3100_led_booting
		;;
	"SG-5100")
		sg5100_led_booting
		;;
	*)
		usage
		;;
	esac
}

led_ready() {
	case "$SYSTEM" in
	"SG-1100")
		sg1100_led_ready
		;;
	"SG-3100")
		sg3100_led_ready
		;;
	"SG-5100")
		sg5100_led_ready
		;;
	*)
		usage
		;;
	esac
}

led_update() {
	case "$SYSTEM" in
	"SG-1100")
		sg1100_led_update
		;;
	"SG-3100")
		sg3100_led_update
		;;
	"SG-5100")
		sg5100_led_update
		;;
	*)
		usage
		;;
	esac
}

led_update_off() {
	case "$SYSTEM" in
	"SG-1100")
		sg1100_led_update_off
		;;
	"SG-3100")
		sg3100_led_update_off
		;;
	"SG-5100")
		sg5100_led_update_off
		;;
	*)
		usage
		;;
	esac
}

usage() {
	echo "usage:"
	echo "pfSense-LED booting"
	echo "pfSense-LED ready"
	echo "pfSense-LED update [1|0]"
	exit 1
}

if [ ${#} -lt 1 ]; then
	usage
fi
if [ "${1}" == update -a ${#} -lt 2 ]; then
	usage
fi
if [ "${1}" == update ]; then
	if [ ${2} -ne 0 -a ${2} -ne 1 ]; then
		usage
	fi
fi

_boardpn=$(/bin/kenv -q uboot.boardpn 2>/dev/null)
_model=$(/bin/kenv -q smbios.system.product 2>/dev/null)
SYSTEM=""
if [ "${_boardpn%-*}" = "80500-0148" ]; then
	SYSTEM="SG-3100"
elif [ "${_model}" = "SG-5100" ]; then
	SYSTEM="SG-5100"
elif [ "${_model}" = "mvebu_armada-37xx" ]; then
	_type=$(/usr/sbin/ofwdump -P model -R / 2>/dev/null)
	if [ "${_type}" = "Netgate SG-1100" ]; then
		SYSTEM="SG-1100"
	fi
fi
if [ -z "${SYSTEM}" ]; then
	exit 1
fi

case ${1} in
booting)
	led_booting
	exit 0
	;;
ready)
	led_ready
	exit 0
	;;
update)
	;;
*)
	usage
esac

if [ ${2} -eq 0 ]; then
	led_update_off
else
	led_update
fi

exit 0
