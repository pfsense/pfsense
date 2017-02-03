#!/bin/sh

ldir=src/usr/local/share/locale
: ${MSGFMT=$(which msgfmt)}

if [ -z "${MSGFMT}" -o ! -e "${MSGFMT}" ]; then
	echo "ERROR: msgfmt not found"
	exit 1
fi

if [ ! -d $ldir ]; then
	echo "ERROR: Locale dir (${ldir}) not found"
	exit 1
fi

if ! ./tools/scripts/update_pot.sh; then
	echo "ERROR: Unable to update pot"
	exit 1
fi

if git status -s | grep -q "${ldir}/pot/pfSense.pot"; then
	git add ${ldir}/pot/pfSense.pot
	git commit -m "Regenerate pot"
	if ! zanata-cli -B push; then
		echo "ERROR: Unable to push pot to Zanata"
		exit 1
	fi
fi

#zanata-cli -B pull --min-doc-percent 75
if ! zanata-cli -B pull; then
	echo "ERROR: Unable to pull po files from Zanata"
	exit 1
fi

unset commit
for po in $(git status -s ${ldir}/*/*/pfSense.po | awk '{print $2}'); do
	if ! $MSGFMT -o ${po%%.po}.mo ${po}; then
		echo "ERROR: Error compiling ${po}"
		exit 1
	fi
	git add $(dirname ${po})
	commit=1
done

if [ -n "${commit}" ]; then
	git commit -m "Update translation files"
fi
