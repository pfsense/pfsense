# Detect interactive logins and display the shell
if [ `env | grep SSH_TTY | wc -l` -gt 0 ] || [ `env | grep cons25 | wc -l` -gt 0 ]; then
	/etc/rc.initial
	exit
fi
