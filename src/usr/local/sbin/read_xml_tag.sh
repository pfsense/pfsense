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

# Get xml_rootobj
globals_inc="/etc/inc/globals.inc"
if [ -f /etc/inc/globals_override.inc ]; then
	globals_inc="/etc/inc/globals_override.inc ${globals_inc}"
fi
xml_rootobj=$(cat ${globals_inc} | \
	grep xml_rootobj | \
	head -n 1 | \
	sed 's/^.*=>* *//; s/["\;,]*//g')

# defaults to pfsense
xml_rootobj=${product:-"pfsense"}

/usr/local/bin/xmllint --xpath "${type}(//${xml_rootobj}/${path})" ${config}
exit $?
