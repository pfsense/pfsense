#!/bin/sh
# Script which checks if we are running from install media, or real system
#############################################################################

mount | grep "/dev/iso9660/pfSense" >/dev/null 2>/dev/null
if [ "$?" = "0" ]
then
  echo "INSTALL-MEDIA"
  exit 0
else
  echo "REAL-DISK"
  exit 1
fi

