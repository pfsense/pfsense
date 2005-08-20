#!/bin/sh

if [ ! -x `which fetch` ];then
    echo "no fetch found, I think databeestje lost that part"
    exit 0
fi

baseurl='http://pfsense.com/cgi-bin/cvsweb.cgi/pfSense'
revision='?rev=1;content-type=text%2Fplain'

if [ -z $1 ];then
    echo "No file to update given"
    exit 0
fi

if [ -f $1 ];then
    #echo running `which fetch` -o "$1" "$baseurl$1$revision"
    `which fetch` -o "$1" "$baseurl$1$revision"
else
    echo "File $1 doesn't exist"
fi