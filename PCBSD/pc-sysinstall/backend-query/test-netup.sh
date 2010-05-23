#!/bin/sh
# Script which tests "fetch" when using a network connection, and saves
# if we are using direct connect, or need FTP passive mode
#############################################################################

rm ${TMPDIR}/.testftp >/dev/null 2>/dev/null

ping -c 2 www.pcbsd.org >/dev/null 2>/dev/null
if [ "$?" = "0" ]
then
   echo "ftp: Up"
   exit 0
fi

ping -c 2 www.freebsd.org >/dev/null 2>/dev/null
if [ "$?" = "0" ]
then
   echo "ftp: Up"
   exit 0
fi
   
echo "ftp: Down"
exit 1
