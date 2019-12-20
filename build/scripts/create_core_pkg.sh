#!/bin/sh
#
# create_core_pkg.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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

export PATH=/usr/local/bin:/usr/local/sbin:/usr/bin:/usr/sbin:/bin:/sbin

scripts_path=$(dirname $(realpath $0))

if [ ! -f "${scripts_path}/common.subr" ]; then
	echo >&2 "ERROR: common.subr is missing"
	exit 1
fi

. ${scripts_path}/common.subr

usage() {
	cat >&2 <<END
Usage: $(basename $0) -t template -d destdir [-h]

Options:
	-t template  -- Path to package template directory
	-f flavor    -- package flavor
	-v version   -- package version
	-r root      -- root directory containing package files
	-s search    -- search path
	-F filter    -- filter pattern to exclude files from plist
	-d destdir   -- Destination directory to create package
	-a ABI       -- Package ABI
	-A ALTABI    -- Package ALTABI (aka arch)
	-h           -- Show this help and exit

Environment:
	TMPDIR       -- Temporary directory (default: /tmp)
	PRODUCT_NAME -- Product name (default: pfSense)
	PRODUCT_URL  -- Product URL (default: https://www.pfsense.org)
END
	exit 1
}

while getopts s:t:f:v:r:F:d:ha:A: opt; do
	case "$opt" in
		t)
			template=$OPTARG
			;;
		f)
			flavor=$OPTARG
			;;
		v)
			version=$OPTARG
			;;
		r)
			root=$OPTARG
			;;
		s)
			findroot=$OPTARG
			;;
		F)
			filter=$OPTARG
			;;
		d)
			destdir=$OPTARG
			;;
		a)
			ABI=$OPTARG
			;;
		A)
			ALTABI=$OPTARG
			;;
		*)
			usage
			;;
	esac
done

[ -z "$template" ] \
	&& err "template directory is not defined"

[ -e $template -a ! -d $template ] \
	&& err "template path is not a directory"

[ -z "$destdir" ] \
	&& err "destination directory is not defined"

[ -e $destdir -a ! -d $destdir ] \
	&& err "destination path already exists and is not a directory"

: ${TMPDIR=/tmp}
: ${PRODUCT_NAME=pfSense}
: ${PRODUCT_URL=http://www.pfsense.org/}

[ -d $destdir ] \
	|| mkdir -p ${destdir}

template_path=$(realpath ${template})
template_name=$(basename ${template})
template_metadir=${template_path}/metadir
template_licensedir=${template_path}/_license

[ -d ${template_metadir} ] \
	|| err "template directory not found for package ${template_name}"

scratchdir=$(mktemp -d -q ${TMPDIR}/${template_name}.XXXXXXX)

[ -n "${scratchdir}" -a -d ${scratchdir} ] \
	|| err "error creating temporary directory"

trap "force_rm ${scratchdir}" 1 2 15 EXIT

metadir=${scratchdir}/${template_name}_metadir

run "Copying metadata for package ${template_name}" \
	"cp -r ${template_metadir} ${metadir}"

manifest=${metadir}/+MANIFEST
plist=${scratchdir}/${template_name}_plist
exclude_plist=${scratchdir}/${template_name}_exclude_plist

if [ -f "${template_path}/pkg-plist" ]; then
	cp ${template_path}/pkg-plist ${plist}
else
	if [ -n "${filter}" ]; then
		filter="-name ${filter}"
	fi
	if [ -z "${findroot}" ]; then
		findroot="."
	fi
	for froot in ${findroot}; do
		(cd ${root} \
			&& find ${froot} ${filter} -type f -or -type l \
				| sed 's,^.,,' \
				| sort -u \
		) >> ${plist}
	done
fi

if [ -f "${template_path}/exclude_plist" ]; then
	cp ${template_path}/exclude_plist ${exclude_plist}
else
	touch ${exclude_plist}
fi

sed \
	-i '' \
	-e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" \
	-e "s,%%PRODUCT_URL%%,${PRODUCT_URL},g" \
	-e "s,%%FLAVOR%%,${flavor:+-}${flavor},g" \
	-e "s,%%FLAVOR_DESC%%,${flavor:+ (${flavor})},g" \
	-e "s,%%VERSION%%,${version},g" \
	${metadir}/* \
	${plist} \
	${exclude_plist}

if [ -f "${exclude_plist}" ]; then
	sort -u ${exclude_plist} > ${plist}.exclude
	mv ${plist} ${plist}.tmp
	comm -23 ${plist}.tmp ${plist}.exclude > ${plist}
	rm -f ${plist}.tmp ${plist}.exclude
fi

# Add license information
if [ -d "${template_licensedir}" ]; then
	portname=$(sed '/^name: /!d; s,^[^"]*",,; s,",,' ${manifest})
	licenses_dir="/usr/local/share/licenses/${portname}-${version}"

	mkdir -p ${root}${licenses_dir}
	for f in ${template_licensedir}/*; do
		cp ${f} ${licenses_dir}
		echo "${licenses_dir}/$(basename ${f})" >> ${plist}
	done
fi

# Force desired ABI and arch
[ -n "${ABI}" ] \
    && echo "abi: ${ABI}" >> ${manifest}
[ -n "${ALTABI}" ] \
    && echo "arch: ${ALTABI}" >> ${manifest}

run "Creating core package ${template_name}" \
	"pkg create -o ${destdir} -p ${plist} -r ${root} -m ${metadir}"

force_rm ${scratchdir}
trap "-" 1 2 15 EXIT
