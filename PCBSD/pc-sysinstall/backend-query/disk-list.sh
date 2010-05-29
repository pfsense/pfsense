#!/bin/sh

# Create our device listing
SYSDISK=$(sysctl -n kern.disks)

# Now loop through these devices, and list the disk drives
for i in ${SYSDISK}
do

  # Get the current device
  DEV="${i}"

  # Make sure we don't find any cd devices
  case "${DEV}" in
     acd[0-9]*|cd[0-9]*|scd[0-9]*) continue ;;
  esac

  # Check the dmesg output for some more info about this device
  NEWLINE=$(dmesg | sed -n "s/^$DEV: .*<\(.*\)>.*$/ <\1>/p" | head -n 1)
  if [ -z "$NEWLINE" ]; then
    NEWLINE=" <Unknown Device>"
  fi

  # Save the disk list
  if [ ! -z "$DLIST" ]
  then
    DLIST="\n${DLIST}"
  fi

  DLIST="${DEV}:${NEWLINE}${DLIST}"

done

# Echo out the found line
echo -e "$DLIST" | sort
