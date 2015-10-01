#!/bin/sh

if [ -z "$1" -o -z "$2" ]; then
	echo "ERROR: Missing parameters" >&2
	exit 1
fi

type="${1}"
path="${2}"
config="${3}"
config=${config:-"/cf/conf/config.xml"}

if [ ! -f "$config" ]; then
	echo "ERROR: Config file not found" >&2
	exit 1
fi

# Get xml_rootobj, if not defined defaults to pfsense
# Use php -n here because we are not ready to load extensions yet
xml_rootobj=$(/usr/local/bin/php -n /usr/local/sbin/read_global_var xml_rootobj pfsense 2>/dev/null)

/usr/local/bin/xmllint --xpath "${type}(//${xml_rootobj}/${path})" ${config} 2>/dev/null
exit $?
