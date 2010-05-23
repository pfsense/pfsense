#!/bin/sh

FOUND="0"

# Lets parse the xorg.list file, and see what models are supported
while read line
do

  if [ "$FOUND" = "1" -a ! -z "$line" ]
  then
    echo $line | grep '! ' >/dev/null 2>/dev/null
    if [ "$?" = "0" ]
    then
     exit 0
    else 
      model="`echo $line | sed 's|(|[|g'`"
      model="`echo $model | sed 's|)|]|g'`"
      echo "$model"
    fi 
  fi 

  if [ "${FOUND}" = "0" ]
  then
    echo $line | grep '! model' >/dev/null 2>/dev/null
    if [ "$?" = "0" ]
    then
      FOUND="1"
    fi 
  fi

done < /usr/local/share/X11/xkb/rules/xorg.lst

exit 0
