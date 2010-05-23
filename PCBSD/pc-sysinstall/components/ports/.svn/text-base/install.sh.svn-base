#!/bin/sh
# Available Variables
# COMPTMPDIR = Set to the tmpdir that contains CFILE
# CFILE =  Set to the file provided by this component
# This script installs the freebsd source

rm -rf /usr/ports
mkdir /usr/ports
cd /usr/ports
echo "Extracting ports tree..."
tar xvjf ${COMPTMPDIR}/${CFILE} >/dev/null 2>/dev/null

if [ -d "/usr/jails/portjail/usr" ] ; then
	echo "Extracting ports tree into portsjail..."
	mkdir -p /usr/jails/portjail/usr/ports
	cd /usr/jails/portjail/usr/ports
	tar xvjf ${COMPTMPDIR}/${CFILE} >/dev/null 2>/dev/null
fi
