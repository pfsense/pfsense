#!/bin/sh
#
# runsntp.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2013 BSD Perimeter
# Copyright (c) 2013-2016 Electric Sheep Fencing
# Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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

# write our PID to file
echo $$ > $1

# execute msntp in endless loop; restart if it
# exits (wait 1 second to avoid restarting too fast in case
# the network is not yet setup)
while true; do
	/usr/local/bin/msntp -v -r -P no -l $2 -x $3 $4 2>&1 | logger -p daemon.info -i -t msntp
	sleep 60
done
