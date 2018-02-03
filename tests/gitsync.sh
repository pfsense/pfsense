HOST=$1
PORT=$2

while read i; do 
    if [[ "$i" =~ "8) Shell" ]] ; then
	echo $i
	echo "finished gitsync"
        break
    fi
    echo $i; 

done < <(cat `dirname $0`/gitsync-command - | netcat $HOST $PORT)
