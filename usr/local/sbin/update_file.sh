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
    echo "Usage: `basename $0` /path/to/file [revision]"
    echo "Usage: `basename $0` -all"
    exit 0
fi

if [ "$1" = "-all" ];then
    echo "This will update all .php .js and .inc pages on your pfsense box!"
    FMATCHES=`find /etc/inc/ /usr/local/www /usr/local/captiveportal -name "*.php" -or -name "*.inc" -or -name "*.js"`
elif [ ! -f $1 ];then
    echo "File $1 doesn't exist"
    exit 0
else
    FMATCHES=$1
fi

/etc/rc.conf_mount_rw

for file in $FMATCHES ;do
    if [ ! -z $2 ];then
        rev=$2
        echo "trying to fetch $rev $file"
    else
        echo "trying to fetch latest $file"
    fi
    #echo fetch -o "$file" "$baseurl$file$urlrev$rev$urlcon"
    `which fetch` -q -o "$file" "$baseurl$file$urlrev$rev$urlcon"
done

/etc/rc.conf_mount_ro

if [ $? -eq 0 ]; then
        echo "File updated."
    else
        echo "An error occured during update."
fi