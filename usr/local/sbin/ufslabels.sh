#!/bin/sh
############################################
#
# Change fstab to use ufsid and geom labels to avoid relying on device numbers directly.
#
############################################

# : cat /etc/fstab
# # Device                Mountpoint      FStype  Options         Dump    Pass#
# /dev/ad0s1a             /               ufs     rw              1       1
# /dev/ad0s1b             none            swap    sw              0       0

string_length() {
	unset LEN
	LEN=`echo -n ${1} | /usr/bin/wc -m | awk '{print $1}'`
}

get_ufsid() {
	# $1 = device
	# : /sbin/dumpfs /dev/ad0s1a | /usr/bin/head -n 2 | /usr/bin/tail -n 1 | /usr/bin/cut -f2 -d'[' | /usr/bin/cut -f1 -d ']' | /usr/bin/sed -e 's/[[:blank:]]//g'
	# 51928c99a471c440

	unset UFSID

	ID_PARTS=`/sbin/dumpfs /dev/${1} | /usr/bin/head -n 2 | /usr/bin/tail -n 1 | /usr/bin/cut -f2 -d'[' | /usr/bin/cut -f1 -d ']'`
	# " 51110eb0 f288b35d " (note it has more spaces than we need/want)
	ID_PART1=`echo ${ID_PARTS} | awk '{print $1;}'`
	# "51110eb0"
	ID_PART2=`echo ${ID_PARTS} | awk '{print $2;}'`
	# "f288b35d"

	if [ "${ID_PART1}" = "" ] || [ "${ID_PART2}" = "" ]; then
		echo "Invalid ufsid on ${1} (${ID_PARTS}), cannot continue"
		exit.
	fi

	# Safety check to avoid http://www.freebsd.org/cgi/query-pr.cgi?pr=156908
	string_length ${ID_PART1}
	if [ ${LEN} -ne 8 ]; then
		ID_PART1=`printf "%08s" "${ID_PART1}"`
	fi
	string_length ${ID_PART2}
	if [ ${LEN} -ne 8 ]; then
		ID_PART2=`printf "%08s" "${ID_PART2}"`
	fi
	UFSID=${ID_PART1}${ID_PART2}
}

find_fs_device(){
	unset DEV
	DEV=`/usr/bin/grep -e "[[:blank:]]${1}[[:blank:]]" ${FSTAB} | awk '{print $1;}'`
	DEV=${DEV##/dev/}
}

FSTAB=/etc/fstab
cp ${FSTAB} ${FSTAB}.tmp

ALL_FILESYSTEMS=`/bin/cat /etc/fstab | /usr/bin/grep ufs | /usr/bin/awk '{print $2;}'`

for FS in ${ALL_FILESYSTEMS}
do
	find_fs_device ${FS}
	if [ "${DEV}" != "" ]; then
		get_ufsid ${DEV}
		string_length ${UFSID}
		if [ ${LEN} -ne 16 ]; then
			echo "Invalid UFS ID for FS ${FS} ($UFSID), skipping"
		else
			/usr/bin/sed -i'' -e "s/${DEV}/ufsid\/${UFSID}/g" ${FSTAB}.tmp
		fi
	else
		echo "Unable to find device for ${FS}"
		return -1
	fi
	echo "FS: ${FS} on device ${DEV} with ufsid ${UFSID}"
done

find_fs_device swap
SWAPDEV=${DEV}
echo "FS: Swap on device ${SWAPDEV}"

if [ "${SWAPDEV}" != "" ]; then
	/usr/bin/sed -i'' -e "s/${SWAPDEV}/label\/swap/g" ${FSTAB}.tmp
fi

echo "===================="
echo "Current fstab:"
cat ${FSTAB}
echo "===================="
echo "New fstab:"
cat ${FSTAB}.tmp

if [ ${1} = "commit" ]; then
	COMMIT=y
else
	echo "Commit changes? (y/n):"
	read COMMIT
fi

# Commit changes
if [ "${COMMIT}" = "y" ] || [ "${COMMIT}" = "Y" ]; then
	echo "Disabling swap to apply label"
	/sbin/swapoff /dev/${SWAPDEV}
	echo "Applying label to swap parition"
	/sbin/glabel label swap /dev/${SWAPDEV}

	echo "Activating new fstab"
	/bin/mv -f ${FSTAB} ${FSTAB}.old
	/bin/mv -f ${FSTAB}.tmp ${FSTAB}

	echo "Re-enabling swap"
	/sbin/swapon -a 2>/dev/null >/dev/null
fi
