#!/bin/sh
#
# clean.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2015-2016 Electric Sheep Fencing, LLC
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

sed -i '' 's/> </></g' $1 ;
sed -i '' 's/    /	/g' $1 ;
sed -i '' 's/\s+$//g' $1 ;
sed -i '' 's/ width="17" height="17" border="0"//g' $1 ;
sed -i '' 's/<td [^>]+listhdrr[^>]+>/<th>/g' $1 ;
sed -i '' 's/<body[^>]*>//g' $1 ;
sed -i '' 's/<\(table\|td\|span\|div\)[^>]\+>/<\1>/g' $1 ;
sed -i '' 's/<?php include("fbegin.inc"); ?>//g' $1 ;
sed -i '' 's/<?php include("fend.inc"); ?>/<?php include("foot.inc"); ?>/g' $1 ;
sed -i '' 's/<?php echo /<?=/g' $1 ;
sed -i '' 's/;\s*?>/?>/g' $1 ;
sed -i '' 's/<?\s*=\s*/<?=/g' $1 ;
sed -i '' 's/ <> / != /g' $1 ;
