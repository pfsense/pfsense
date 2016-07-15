#!/bin/sh
#
# update_pot.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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

# Verify if user defined a custom path for xgettext
if [ -n "$XGETTEXT" ]; then
	if [ ! -x "$XGETTEXT" ]; then
		echo "XGETTEXT env var points to invalid executable"
		exit 1
	fi
elif ! which -s xgettext; then
	echo "xgettext not found, try to set env var XGETTEXT with full path"
	exit 1
else
	XGETTEXT=$(which xgettext)
fi

ROOT=$(realpath "$(dirname $0)/../..")

FILES=$(mktemp -t src-files-)

if [ -z "$FILES" -o ! -f "$FILES" ]; then
	echo "Error creating temporary file"
	exit 1
fi

trap "rm -f $FILES" 1 2 15 EXIT

( \
       cd $ROOT && \
       find src -type f -name '*.inc' -or -name '*.class' -or -name '*.php' \
) > $FILES

POT=$ROOT/src/usr/local/share/locale/en/LC_MESSAGES/pfSense.pot

( \
	cd $ROOT && \
	$XGETTEXT \
		-f $FILES \
		-o $POT \
		-L php \
		-ksetHelp \
		-kForm_Section \
		-kForm_Group \
		-kForm_Input:2 \
		-kForm_Checkbox:2 \
		-kForm_StaticText \
		-kModal \
		-kForm_Button:2 \
		-kForm_Textarea:2 \
		-kForm_MultiCheckboxGroup \
		-kForm_MultiCheckbox:2 \
		-kForm_IpAddress:2 \
		-kForm_Select:2 \
)

( \
	cd $ROOT && \
	$XGETTEXT \
		-f $FILES \
		-o $POT \
		-L php \
		-j \
		-kForm_Checkbox:3 \
		-kForm_StaticText:2 \
		-kForm_MultiCheckbox:3 \
)
