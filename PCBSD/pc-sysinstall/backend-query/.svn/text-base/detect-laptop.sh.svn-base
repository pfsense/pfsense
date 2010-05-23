#!/bin/sh

dmesgLine=`dmesg | grep "acpi_acad0"`
if test "${dmesgLine}" = ""; then
  echo "laptop: NO"
else
  echo "laptop: YES"
fi 
