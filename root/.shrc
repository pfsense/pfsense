# Detect interactive logins and display the shell
if [ -n "${SSH_TTY}" -o "${TERM}" = "cons25" ]; then
	/etc/rc.initial
	exit
fi
