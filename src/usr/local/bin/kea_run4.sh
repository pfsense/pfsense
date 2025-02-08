#!/bin/sh
# run kea ipv4 user scripts in directory. requires kea run_script hook library enabled.
USER_SCRIPTS="/conf/kea4_scripts.d"
KEA_EVENT="$1"
if [ -d $USER_SCRIPTS ]; then
    for kea_rc in $USER_SCRIPTS/*; do
        if [ -f "$kea_rc" -a -x "$kea_rc" ]; then
            $kea_rc $KEA_EVENT
        fi
    done
    unset kea_rc
fi

