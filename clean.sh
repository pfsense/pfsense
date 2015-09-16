#!/bin/sh

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
