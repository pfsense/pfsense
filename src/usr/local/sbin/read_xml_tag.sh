#!/bin/sh
#
# read_xml_tag.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2015-2016 Electric Sheep Fencing
# Copyright (c) 2015-2019 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

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
