#!/bin/sh
#
# update_pot.sh
#
# Copyright (c) 2016 Electric Sheep Fencing, LLC. All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in
#    the documentation and/or other materials provided with the
#    distribution.
#
# 3. All advertising materials mentioning features or use of this software
#    must display the following acknowledgment:
#    "This product includes software developed by the pfSense Project
#    for use in the pfSense® software distribution. (http://www.pfsense.org/).
#
# 4. The names "pfSense" and "pfSense Project" must not be used to
#    endorse or promote products derived from this software without
#    prior written permission. For written permission, please contact
#    coreteam@pfsense.org.
#
# 5. Products derived from this software may not be called "pfSense"
#    nor may "pfSense" appear in their names without prior written
#    permission of the Electric Sheep Fencing, LLC.
#
# 6. Redistributions of any form whatsoever must retain the following
#    acknowledgment:
#
# "This product includes software developed by the pfSense Project
# for use in the pfSense software distribution (http://www.pfsense.org/).
#
# THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
# EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
# PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
# ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
# SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
# NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
# STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
# OF THE POSSIBILITY OF SUCH DAMAGE.

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
