#!/bin/sh
# Script which checks if we are running from install media, or real system
#############################################################################

dmesg | grep "md0: Preloaded image" >/dev/null 2>/dev/null
if [ "$?" = "0" ]
then
  echo "INSTALL-MEDIA"
  exit 0
else
  echo "REAL-DISK"
  exit 1
fi

