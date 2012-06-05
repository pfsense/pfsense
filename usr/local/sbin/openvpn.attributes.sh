#!/bin/sh

echo $script_type > /tmp/script
if [ "$script_type" = "client-connect" ]; then
	if [ -f /tmp/$common_name ]; then
		/bin/cat /tmp/$common_name > $1
		/bin/rm /tmp/$common_name
	fi
elif [ "$script_type" = "client-disconnect" ]; then
	command="/sbin/pfctl -a 'openvpn/$common_name' -F rules"
        eval $command
	/sbin/pfctl -k $ifconfig_pool_remote_ip
	/sbin/pfctl -K $ifconfig_pool_remote_ip
fi

exit 0
