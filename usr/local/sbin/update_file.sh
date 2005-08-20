#!/bin/sh

if [ ! -x `which fetch` ];then
    echo "no fetch found, I think databeestje lost that part"
    exit 0
fi

baseurl='http://pfsense.com/cgi-bin/cvsweb.cgi/pfSense'
urlrev='?rev='
urlcon=';content-type=text%2Fplain'
rev='1'

if [ -z $1 ];then
    echo "No file to update given"
    echo "Usage: $0 /path/to/file [revision]"
    exit 0
fi

if [ ! -f $1 ];then
    echo "File $1 doesn't exist"
    exit 0
fi

if [ ! -z $2 ];then
    rev=$2
    echo "trying to fetch $rev"
else
    echo "trying to fetch latest"
fi

`which fetch` -o "$1" "$baseurl$1$urlrev$rev$urlcon"