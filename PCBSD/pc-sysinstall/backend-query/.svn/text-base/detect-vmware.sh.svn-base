#!/bin/sh


pciconf -lv | grep -i vmware >/dev/null 2>/dev/null
if [ "$?" = "0" ]
then
  echo "vmware: YES"
  exit 0
else
  echo "vmware: NO"
  exit 1
fi
