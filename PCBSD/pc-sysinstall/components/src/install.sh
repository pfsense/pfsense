#!/bin/sh
# Available Variables
# COMPTMPDIR = Set to the tmpdir that contains CFILE
# CFILE =  Set to the file(s) provided by this component
#          Comma delimited, if more than one file
# This script installs the freebsd source

rm -rf /usr/src
mkdir /usr/src
cd /usr/src
echo "Extracting FREEBSD source tree..."
tar xvjf ${COMPTMPDIR}/${CFILE} >/dev/null 2>/dev/null
