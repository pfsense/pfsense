# Detect interactive logins and display the shell
if [ -n "${SSH_TTY}" -o "${TERM}" = "xterm" ]; then
	/etc/rc.initial
	exit
fi
