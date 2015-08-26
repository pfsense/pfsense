#!/bin/sh
#
# build_snapshots.sh
#
# Copyright (c) 2007-2015 Electric Sheep Fencing, LLC
# All rights reserved
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
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

export BUILDER_TOOLS=$(realpath $(dirname ${0}))
export BUILDER_ROOT=$(realpath "${BUILDER_TOOLS}/..")

NO_UPLOAD=""
LOOPED_SNAPSHOTS=""

# Handle command line arguments
while test "$1" != "" ; do
	case $1 in
	--noupload|-u)
		NO_UPLOAD="-u"
		;;
	--looped)
		LOOPED_SNAPSHOTS="true"
	esac
	shift
done

# Source ${PRODUCT_NAME} / FreeSBIE variables
# *** DO NOT SOURCE BUILDER_COMMON.SH!
# *** IT WILL BREAK EVERYTHING FOR 
# *** SOME UNKNOWN LAYERING REASON.
# *** 04/07/2008, 11/04/2009                      
echo ">>> Execing build.conf"
. ${BUILDER_TOOLS}/builder_defaults.sh

if [ -z "${RSYNCIP}" -a -z "${NO_UPLOAD}" ]; then
	echo ">>> ERROR: RSYNCIP is not defined"
	exit 1
fi

if [ -z "${RSYNCUSER}" -a -z "${NO_UPLOAD}" ]; then
	echo ">>> ERROR: RSYNCUSER is not defined"
	exit 1
fi

if [ -z "${RSYNCPATH}" -a -z "${NO_UPLOAD}" ]; then
	echo ">>> ERROR: RSYNCPATH is not defined"
	exit 1
fi

if [ -z "${RSYNCLOGS}" -a -z "${NO_UPLOAD}" ]; then
	echo ">>> ERROR: RSYNCLOGS is not defined"
	exit 1
fi

# Keeps track of how many time builder has looped
BUILDCOUNTER=0

# Local variables that are used by builder scripts
STAGINGAREA=${SCRATCHDIR}/staging
RSYNCKBYTELIMIT="248000"

export SNAPSHOTSLOGFILE=${SNAPSHOTSLOGFILE:-"$SCRATCHDIR/snapshots-build.log"}
export SNAPSHOTSLASTUPDATE=${SNAPSHOTSLASTUPDATE:-"$SCRATCHDIR/snapshots-lastupdate.log"}

# Ensure directories exist
mkdir -p $STAGINGAREA

echo "" > $SNAPSHOTSLOGFILE
echo "" > $SNAPSHOTSLASTUPDATE

git_last_commit() {
	if [ -d "${1}/.git" ]; then
		git -C "${1}" pull -q
		git -C "${1}" log -1 --format='%H'
	fi
}

# This routine is called in between runs. We
# will sleep for a bit and check for new commits
# in between sleeping for short durations.
sleep_between_runs() {
	COUNTER=0
	while [ $COUNTER -lt $maxsleepvalue ]; do
		sleep 60
		CURRENT_COMMIT=$(git_last_commit "${BUILDER_ROOT}")
		if [ "${LAST_COMMIT}" != "${CURRENT_COMMIT}" ]; then
			update_status ">>> New commit: $CURRENT_AUTHOR - $CURRENT_COMMIT .. No longer sleepy."
			COUNTER=$(($maxsleepvalue + 60))
			export LAST_COMMIT="${CURRENT_COMMIT}"
		fi
		COUNTER=$(($COUNTER + 60))
	done
	if [ $COUNTER -ge $maxsleepvalue ]; then
		update_status ">>> Sleep timer expired. Restarting build."
		maxsleepvalue=0
		COUNTER=0
	fi
}

# This routine is called to write out to stdout
# a string. The string is appended to $SNAPSHOTSLOGFILE
# and we scp the log file to the builder host if
# needed for the real time logging functions.
update_status() {
	if [ "$1" = "" ]; then
		return
	fi
	echo $1
	echo "`date` -|- $1" >> $SNAPSHOTSLOGFILE
	if [ -z "${NO_UPLOAD}" ]; then
		LU=`cat $SNAPSHOTSLASTUPDATE`
		CT=`date "+%H%M%S"`
		# Only update every minute
		if [ "$LU" != "$CT" ]; then 
			ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCLOGS}"
			scp -q $SNAPSHOTSLOGFILE ${RSYNCUSER}@${RSYNCIP}:${RSYNC_LOGS}/build.log
			date "+%H%M%S" > $SNAPSHOTSLASTUPDATE
		fi
	fi
}

# Copy the current log file to $filename.old on
# the snapshot www server (real time logs)
rotate_logfile() {
	if [ -n "$MASTER_BUILDER_SSH_LOG_DEST" -a -z "${NO_UPLOAD}" ]; then
		scp -q $SNAPSHOTSLOGFILE ${RSYNCUSER}@${RSYNCIP}:${RSYNC_LOGS}/build.log.old
	fi

	# Cleanup log file
	echo "" > $SNAPSHOTSLOGFILE
}

dobuilds() {
	# Build images
	(cd ${BUILDER_ROOT} && ./build.sh --flash-size '1g 2g 4g' "iso memstick memstickserial memstickadi fullupdate nanobsd nanobsd-vga")
	# Copy files
	copy_to_staging_iso_updates
	copy_to_staging_nanobsd '1g 2g 4g'
}

copy_to_staging_nanobsd() {
	for NANOTYPE in nanobsd nanobsd-vga; do
		for FILESIZE in ${1}; do
			FILENAMEFULL="${PRODUCT_NAME}-${PRODUCT_VERSION}-${FILESIZE}-${TARGET}-${NANOTYPE}-${DATESTRING}.img.gz"
			FILENAMEUPGRADE="${PRODUCT_NAME}-${PRODUCT_VERSION}-${FILESIZE}-${TARGET}-${NANOTYPE}-upgrade-${DATESTRING}.img.gz"
			mkdir -p $STAGINGAREA/nanobsd
			mkdir -p $STAGINGAREA/nanobsdupdates

			cp $IMAGES_FINAL_DIR/$FILENAMEFULL $STAGINGAREA/nanobsd/ 2>/dev/null
			cp $IMAGES_FINAL_DIR/$FILENAMEUPGRADE $STAGINGAREA/nanobsdupdates 2>/dev/null

			if [ -f $STAGINGAREA/nanobsd/$FILENAMEFULL ]; then
				md5 $STAGINGAREA/nanobsd/$FILENAMEFULL > $STAGINGAREA/nanobsd/$FILENAMEFULL.md5 2>/dev/null
				sha256 $STAGINGAREA/nanobsd/$FILENAMEFULL > $STAGINGAREA/nanobsd/$FILENAMEFULL.sha256 2>/dev/null
			fi
			if [ -f $STAGINGAREA/nanobsdupdates/$FILENAMEUPGRADE ]; then
				md5 $STAGINGAREA/nanobsdupdates/$FILENAMEUPGRADE > $STAGINGAREA/nanobsdupdates/$FILENAMEUPGRADE.md5 2>/dev/null
				sha256 $STAGINGAREA/nanobsdupdates/$FILENAMEUPGRADE > $STAGINGAREA/nanobsdupdates/$FILENAMEUPGRADE.sha256 2>/dev/null
			fi

			# Copy NanoBSD auto update:
			if [ -f $STAGINGAREA/nanobsdupdates/$FILENAMEUPGRADE ]; then
				cp $STAGINGAREA/nanobsdupdates/$FILENAMEUPGRADE $STAGINGAREA/latest-${NANOTYPE}-$FILESIZE.img.gz 2>/dev/null
				sha256 $STAGINGAREA/latest-${NANOTYPE}-$FILESIZE.img.gz > $STAGINGAREA/latest-${NANOTYPE}-$FILESIZE.img.gz.sha256 2>/dev/null
				# NOTE: Updates need a file with output similar to date output
				# Use the file generated at start of dobuilds() to be consistent on times
				cp $BUILTDATESTRINGFILE $STAGINGAREA/version-${NANOTYPE}-$FILESIZE
			fi
		done
	done
}

copy_to_staging_iso_updates() {
	# Copy ISOs
	md5 ${ISOPATH}.gz > ${ISOPATH}.md5
	sha256 ${ISOPATH}.gz > ${ISOPATH}.sha256
	cp ${ISOPATH}* $STAGINGAREA/ 2>/dev/null

	# Copy memstick items
	md5 ${MEMSTICKPATH}.gz > ${MEMSTICKPATH}.md5
	sha256 ${MEMSTICKPATH}.gz > ${MEMSTICKPATH}.sha256
	cp ${MEMSTICKPATH}* $STAGINGAREA/ 2>/dev/null

	md5 ${MEMSTICKSERIALPATH}.gz > ${MEMSTICKSERIALPATH}.md5
	sha256 ${MEMSTICKSERIALPATH}.gz > ${MEMSTICKSERIALPATH}.sha256
	cp ${MEMSTICKSERIALPATH}* $STAGINGAREA/ 2>/dev/null

	md5 ${MEMSTICKADIPATH}.gz > ${MEMSTICKADIPATH}.md5
	sha256 ${MEMSTICKADIPATH}.gz > ${MEMSTICKADIPATH}.sha256
	cp ${MEMSTICKADIPATH}* $STAGINGAREA/ 2>/dev/null

	md5 ${UPDATES_TARBALL_FILENAME} > ${UPDATES_TARBALL_FILENAME}.md5
	sha256 ${UPDATES_TARBALL_FILENAME} > ${UPDATES_TARBALL_FILENAME}.sha256
	cp ${UPDATES_TARBALL_FILENAME}* $STAGINGAREA/ 2>/dev/null
	# NOTE: Updates need a file with output similar to date output
	# Use the file generated at start of dobuilds() to be consistent on times
	cp $BUILTDATESTRINGFILE $STAGINGAREA/version 2>/dev/null
}

scp_files() {
	if [ -z "${RSYNC_COPY_ARGUMENTS:-}" ]; then
		RSYNC_COPY_ARGUMENTS="-ave ssh --timeout=60 --bwlimit=${RSYNCKBYTELIMIT}" #--bwlimit=50
	fi
	update_status ">>> Copying files to ${RSYNCIP}"

	rm -f $SCRATCHDIR/ssh-snapshots*

	# Ensure directory(s) are available
	ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCPATH}/livecd_installer"
	ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCPATH}/updates"
	ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCPATH}/nanobsd"
	if [ -d $STAGINGAREA/virtualization ]; then
		ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCPATH}/virtualization"
	fi
	ssh ${RSYNCUSER}@${RSYNCIP} "mkdir -p ${RSYNCPATH}/.updaters"
	# ensure permissions are correct for r+w
	ssh ${RSYNCUSER}@${RSYNCIP} "chmod -R ug+rw /usr/local/www/snapshots/FreeBSD_${FREEBSD_PARENT_BRANCH}/${TARGET}/."
	ssh ${RSYNCUSER}@${RSYNCIP} "chmod -R ug+rw ${RSYNCPATH}/."
	ssh ${RSYNCUSER}@${RSYNCIP} "chmod -R ug+rw ${RSYNCPATH}/*/."
	rsync $RSYNC_COPY_ARGUMENTS $STAGINGAREA/${PRODUCT_NAME}-*iso* \
		${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/livecd_installer/
	rsync $RSYNC_COPY_ARGUMENTS $STAGINGAREA/${PRODUCT_NAME}-memstick* \
		${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/livecd_installer/
	rsync $RSYNC_COPY_ARGUMENTS $STAGINGAREA/${PRODUCT_NAME}-*Update* \
		${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/updates/
	rsync $RSYNC_COPY_ARGUMENTS $STAGINGAREA/nanobsd/* \
		${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/nanobsd/
	rsync $RSYNC_COPY_ARGUMENTS $STAGINGAREA/nanobsdupdates/* \
		${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/updates/
	if [ -d $STAGINGAREA/virtualization ]; then
		rsync $RSYNC_COPY_ARGUMENTS $STAGINGAREA/virtualization/* \
			${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/virtualization/
	fi

	# Rather than copy these twice, use ln to link to the latest one.

	ssh ${RSYNCUSER}@${RSYNCIP} "rm -f ${RSYNCPATH}/.updaters/latest.tgz"
	ssh ${RSYNCUSER}@${RSYNCIP} "rm -f ${RSYNCPATH}/.updaters/latest.tgz.sha256"

	LATESTFILENAME="`ls $UPDATESDIR/*.tgz | grep Full | grep -v md5 | grep -v sha256 | tail -n1`"
	LATESTFILENAME=`basename ${LATESTFILENAME}`
	ssh ${RSYNCUSER}@${RSYNCIP} "ln -s ${RSYNCPATH}/updates/${LATESTFILENAME} \
		${RSYNCPATH}/.updaters/latest.tgz"
	ssh ${RSYNCUSER}@${RSYNCIP} "ln -s ${RSYNCPATH}/updates/${LATESTFILENAME}.sha256 \
		${RSYNCPATH}/.updaters/latest.tgz.sha256"

	for i in 1g 2g 4g
	do
		ssh ${RSYNCUSER}@${RSYNCIP} "rm -f ${RSYNCPATH}/.updaters/latest-nanobsd-${i}.img.gz"
		ssh ${RSYNCUSER}@${RSYNCIP} "rm -f ${RSYNCPATH}/.updaters/latest-nanobsd-${i}.img.gz.sha256"
		ssh ${RSYNCUSER}@${RSYNCIP} "rm -f ${RSYNCPATH}/.updaters/latest-nanobsd-vga-${i}.img.gz"
		ssh ${RSYNCUSER}@${RSYNCIP} "rm -f ${RSYNCPATH}/.updaters/latest-nanobsd-vga-${i}.img.gz.sha256"

		FILENAMEUPGRADE="${PRODUCT_NAME}-${PRODUCT_VERSION}-${i}-${TARGET}-nanobsd-upgrade-${DATESTRING}.img.gz"
		ssh ${RSYNCUSER}@${RSYNCIP} "ln -s ${RSYNCPATH}/updates/${FILENAMEUPGRADE} \
			${RSYNCPATH}/.updaters/latest-nanobsd-${i}.img.gz"
		ssh ${RSYNCUSER}@${RSYNCIP} "ln -s ${RSYNCPATH}/updates/${FILENAMEUPGRADE}.sha256 \
			${RSYNCPATH}/.updaters/latest-nanobsd-${i}.img.gz.sha256"

		FILENAMEUPGRADE="${PRODUCT_NAME}-${PRODUCT_VERSION}-${i}-${TARGET}-nanobsd-vga-upgrade-${DATESTRING}.img.gz"
		ssh ${RSYNCUSER}@${RSYNCIP} "ln -s ${RSYNCPATH}/updates/${FILENAMEUPGRADE} \
			${RSYNCPATH}/.updaters/latest-nanobsd-vga-${i}.img.gz"
		ssh ${RSYNCUSER}@${RSYNCIP} "ln -s ${RSYNCPATH}/updates/${FILENAMEUPGRADE}.sha256 \
			${RSYNCPATH}/.updaters/latest-nanobsd-vga-${i}.img.gz.sha256"
	done

	rsync $RSYNC_COPY_ARGUMENTS $STAGINGAREA/version* \
		${RSYNCUSER}@${RSYNCIP}:${RSYNCPATH}/.updaters
	update_status ">>> Finished copying files."
}

cleanup_builds() {
	# Remove prior builds
	update_status ">>> Cleaning up after prior builds..."
	rm -rf $STAGINGAREA/*
	rm -rf $IMAGES_FINAL_DIR/*
	(cd ${BUILDER_ROOT} && ./build.sh --clean-builder)
}

build_loop_operations() {
	update_status ">>> Starting build loop operations"
	# --- Items we need to run for a complete build run ---
	# Cleanup prior builds
	cleanup_builds
	# Update pkgs if necessary
	if pkg update -r ${PRODUCT_NAME} >/dev/null 2>&1; then
		update_status ">>> Updating builder packages... "
		pkg upgrade -r ${PRODUCT_NAME} -y -q >/dev/null 2>&1
	fi
	# Do the builds
	dobuilds
	# SCP files to snapshot web hosting area
	if [ -z "${NO_UPLOAD}" ]; then
		scp_files
	fi
	# Alert the world that we have some snapshots ready.
	update_status ">>> Builder run is complete."
}

if [ -z "${LOOPED_SNAPSHOTS}" ]; then
	build_loop_operations
else
	# Main builder loop
	while [ /bin/true ]; do
		BUILDCOUNTER=`expr $BUILDCOUNTER + 1`
		update_status ">>> Starting builder run #${BUILDCOUNTER}..."

		# Launch the snapshots builder script and pipe its
		# contents to the while loop so we can record the 
		# script progress in real time to the public facing
		# snapshot server (${RSYNCIP}).
		( build_loop_operations ) | while read LINE
		do
			update_status "$LINE"
		done

		export minsleepvalue=28800
		export maxsleepvalue=86400

		# Initialize variables that keep track of last commit
		[ -z "${LAST_COMMIT}" ] \
			&& export LAST_COMMIT="$(git -C ${BUILDER_ROOT} log | head -n1 | cut -d' ' -f2)"

		update_status ">>> Sleeping for at least $minsleepvalue, at most $maxsleepvalue in between snapshot builder runs.  Last known commit ${LAST_COMMIT}"
		update_status ">>> Freezing build process at `date`."
		sleep $minsleepvalue
		update_status ">>> Thawing build process and resuming checks for pending commits at `date`."

		# Count some sheep or wait until a new commit turns up 
		# for one days time.  We will wake up if a new commit
		# is detected during sleepy time.
		sleep_between_runs $maxsleepvalue

		# If REBOOT_AFTER_SNAPSHOT_RUN is defined reboot
		# the box after the run. 
		if [ ! -z "${REBOOT_AFTER_SNAPSHOT_RUN:-}" ]; then
			update_status ">>> Rebooting `hostname` due to \$REBOOT_AFTER_SNAPSHOT_RUN"
			shutdown -r now
			kill $$
		fi
		# Rotate log file (.old)
		rotate_logfile

		# Set a common DATESTRING for the build if not set from builder_defaults.sh.
		# Rely on builder_defaults.sh doing the right job the first time included from this script.
		# NOTE: This is needed to have autoupdate detect a new version.
		# Override it here to have continuous builds with proper labels
		rm -f $DATESTRINGFILE
		rm -f $BUILTDATESTRINGFILE
		unset DATESTRING
		unset BUILTDATESTRING
		unset ISOPATH
		unset MEMSTICKPATH
		unset MEMSTICKSERIALPATH
		unset MEMSTICKADIPATH
		unset UPDATES_TARBALL_FILENAME
		# builder_defaults.sh will set variables with correct timestamp
		. ${BUILDER_TOOLS}/builder_defaults.sh
	done
fi
