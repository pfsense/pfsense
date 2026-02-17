#!/bin/sh
#
# Set the IFS parameters for an interface configured for
# point-to-point use at a specific distance.  Based on a
# program by Gunter Burchardt.
#
DEV=ath0

usage()
{
	echo "Usage: $0 [-i athX] [-d meters]"
	exit 2
}

while getopts d:i: opt; do
	case "${opt}" in
		i)
			DEV=$OPTARG
			;;
		d)
			d=$OPTARG
			;;
		*)
			usage
			;;
	esac
done

[ -z "${d}" ] && usage

slottime=`expr 9 + \( $d / 300 \)`
if expr \( $d % 300 \) != 0 >/dev/null 2>&1; then
	slottime=`expr $slottime + 1`
fi
timeout=`expr $slottime \* 2 + 3`

printf "Setup IFS parameters on interface ${DEV} for %i meter p-2-p link\n" "$d"
ATHN="${DEV#ath}"
sysctl "dev.ath.$ATHN.slottime=$slottime"
sysctl "dev.ath.$ATHN.acktimeout=$timeout"
sysctl "dev.ath.$ATHN.ctstimeout=$timeout"
