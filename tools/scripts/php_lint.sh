#!/bin/sh

#
# This is a little script that will use PHP's lint functionality to
# check if syntax of PHP files is correct, ensuring less likelihood
# of bad code making it's way into the project.
#
# For best results, execute this in an environment with PHP version
# matching the one being used on the pfSense branch to be checked.
#

PHP_EXECUTABLE=`which php`

if [ -x "${PHP_EXECUTABLE}" ]; then
	"${PHP_EXECUTABLE}" -v
	find . -type f -name "*.inc" -exec "${PHP_EXECUTABLE}" -n -l "{}" \; | grep -v "No syntax errors detected" 2>&1
	find . -type f -name "*.php" -exec "${PHP_EXECUTABLE}" -n -l "{}" \; | grep -v "No syntax errors detected" 2>&1
else
	echo "PHP executable not found!"
fi