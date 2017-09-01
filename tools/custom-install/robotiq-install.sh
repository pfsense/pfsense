#!/bin/sh

CONF=http://factory-logger.pfmechanics.com/robotiq-config.xml
ORDER=67180

# Fetch custom config
if ! fetch -o /tmp/custom-config.xml ${CONF}; then
	echo "Error downloading custom config ${CONF}"
	exit 1
fi

# Install custom config
if ! install -o root -g wheel -m 0644 /tmp/custom-config.xml \
    /mnt/cf/conf/config.xml; then
	echo "Error installing config.xml in /mnt/cf/conf/config.xml"
	exit 1
fi

# Install custom config as default
if ! install -o root -g wheel -m 0644 /tmp/custom-config.xml \
    /mnt/conf.default/config.xml; then
	echo "Error installing config.xml in /conf.default/config.xml"
	exit 1
fi

# Skip initial wizard
rm -f /mnt/cf/conf/trigger_initial_wizard

# Define Order #
echo $ORDER > /tmp/custom_order

exit 0
