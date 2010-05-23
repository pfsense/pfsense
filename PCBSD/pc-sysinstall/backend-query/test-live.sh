#!/bin/sh
# Script which checks if we are running from install media, or real system
#############################################################################

# Test for PC-BSD Style mount
dmesg | grep "md0: Preloaded image" >/dev/null 2>/dev/null
if [ "$?" = "0" ]
then
  echo "INSTALL-MEDIA"
  exit 0
fi

# Test for pfSense LIVECD
mount | grep "/dev/iso9660/pfSense" >/dev/null 2>/dev/null
if [ "$?" = "0" ]
then
  echo "pfSense-LiveCD/Installer"
  exit 0
fi

echo "REAL-DISK"
exit 1


