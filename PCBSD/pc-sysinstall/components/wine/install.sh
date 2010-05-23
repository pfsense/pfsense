#!/bin/sh
# This script installs the PBI

DISPLAY=""; export DISPLAY

echo "Installing Wine PBI"
chmod 755 ${COMPTMPDIR}/${CFILE}
${COMPTMPDIR}/${CFILE} -text -accept
