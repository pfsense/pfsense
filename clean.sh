#!/bin/sh

sed -i -e 's/> </></g' \
 -e 's/    /	/g' \
 -e 's/\s+$//g' \
 -e 's/ width="17" height="17" border="0"//g' \
 -e 's/<td [^>]+listhdrr[^>]+>/<th>/g' \
 -e 's/<body[^>]*>//g' \
 -e 's/<\(table\|td\|span\|div\)[^>]\+>/<\1>/g' \
 -e 's/<?php include("fbegin.inc"); ?>//g' \
 -e 's/<?php include("fend.inc"); ?>/<?php include("foot.inc"); ?>/g' \
 -e 's/<?php echo /<?=/g' \
 -e 's/;?\s*?>/?>/g' \
 -e 's/<?\s*=\s*/<?=/g' \
  $1
