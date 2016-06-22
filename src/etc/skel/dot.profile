# Detect interactive logins and display the shell
unset _interactive
if [ -n "${SSH_TTY}" ]; then
	_interactive=1
else
	case "${TERM}" in
	cons25|xterm|vt100|vt102|vt220)
		_interactive=1
		;;
	esac
fi

if [ -n "${_interactive}" ]; then
	echo "INTERACTIVE"
	/etc/rc.initial
	exit
fi
	echo "NON"
