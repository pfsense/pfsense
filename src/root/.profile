# Detect interactive logins and display the shell
if [ -n "${SSH_TTY}" -o "${TERM}" = "cons25" ]; then
	[ -f /etc/motd-passwd ] \
		&& cat /etc/motd-passwd
	/etc/rc.initial
	exit
fi
