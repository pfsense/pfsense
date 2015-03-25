#!/bin/sh

sed -i -e 's/> </></g' $1 ;
sed -i -e 's/    /	/g' $1 ;
sed -i -e 's/\s+$//g' $1 ;
sed -i -e 's/ width="17" height="17" border="0"//g' $1 ;
sed -i -e 's/<td [^>]+listhdrr[^>]+>/<th>/g' $1 ;
sed -i -e 's/<body[^>]*>//g' $1 ;
sed -i -e 's/<\(table\|td\|span\|div\)[^>]\+>/<\1>/g' $1 ;
sed -i -e 's/<?php include("fbegin.inc"); ?>//g' $1 ;
sed -i -e 's/<?php include("fend.inc"); ?>/<?php include("foot.inc"); ?>/g' $1 ;
sed -i -e 's/<?php echo /<?=/g' $1 ;
sed -i -e 's/;\s*?>/?>/g' $1 ;
sed -i -e 's/<?\s*=\s*/<?=/g' $1 ;
sed -i -e 's/ <> / != /g' $1 ;